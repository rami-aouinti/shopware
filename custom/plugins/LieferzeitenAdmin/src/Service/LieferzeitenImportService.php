<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use LieferzeitenAdmin\Entity\PaketEntity;
use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Integration\IntegrationContractValidator;
use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
use LieferzeitenAdmin\Sync\Adapter\ChannelOrderAdapterRegistry;
use LieferzeitenAdmin\Sync\San6\San6Client;
use LieferzeitenAdmin\Sync\San6\San6MatchingService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LieferzeitenImportService
{
    /**
     * SAN6 payload contract for internal deliveries without tracking.
     *
     * Supported inputs:
     * - san6.internalDeliveryCompleted: bool|string|int (truthy values mean completed)
     * - san6.deliveryCompletionState: "completed"|"done"|"internal_completed"|"terminated"
     */
    private const SAN6_INTERNAL_DELIVERY_COMPLETED_FLAG_KEYS = [
        'internalDeliveryCompleted',
        'deliveryCompletedWithoutTracking',
        'completedWithoutTracking',
    ];

    private const SAN6_INTERNAL_DELIVERY_COMPLETED_STATES = [
        'completed',
        'done',
        'internal_completed',
        'terminated',
    ];

    public function __construct(
        private readonly EntityRepository $paketRepository,
        private readonly EntityRepository $positionRepository,
        private readonly EntityRepository $sendenummerHistoryRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $config,
        private readonly ChannelOrderAdapterRegistry $adapterRegistry,
        private readonly San6Client $san6Client,
        private readonly San6MatchingService $matchingService,
        private readonly BaseDateResolver $baseDateResolver,
        private readonly ChannelDateSettingsProvider $settingsProvider,
        private readonly BusinessDayDeliveryDateCalculator $deliveryDateCalculator,
        private readonly Status8TrackingMappingProvider $status8TrackingMappingProvider,
        private readonly LockFactory $lockFactory,
        private readonly NotificationEventService $notificationEventService,
        private readonly IntegrationReliabilityService $reliabilityService,
        private readonly IntegrationContractValidator $contractValidator,
        private readonly AuditLogService $auditLogService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(Context $context, string $mode = 'scheduled'): void
    {
        if (!$this->canRunMode($mode)) {
            return;
        }

        $lock = $this->lockFactory->createLock('lieferzeiten_admin.import.lock', 300.0, false);
        if (!$lock->acquire()) {
            $this->logger->warning('Lieferzeiten sync skipped due to active lock.', ['mode' => $mode]);

            return;
        }

        try {
            $this->processPendingStatusPushQueue($context);

            foreach ($this->getChannelConfigs() as $channel => $keys) {
                $url = (string) $this->config->get($keys['url']);
                if ($url === '') {
                    continue;
                }

                $token = (string) $this->config->get($keys['token']);
                $payload = $this->reliabilityService->executeWithRetry('shopware', 'import_channel_' . $channel, function () use ($url, $token): array {
                    $response = $this->httpClient->request('GET', $url, $token !== '' ? ['headers' => ['Authorization' => sprintf('Bearer %s', $token)]] : []);
                    $data = $response->toArray(false);

                    return is_array($data) ? $data : [];
                }, $context, payload: ['url' => $url, 'channel' => $channel]);
                $orders = $payload['orders'] ?? $payload;

                if (!is_array($orders)) {
                    continue;
                }

                foreach ($orders as $order) {
                    if (!is_array($order)) {
                        continue;
                    }

                    $adapter = $this->adapterRegistry->resolve($channel, $order);
                    $normalized = $adapter?->normalize($order) ?? $order;

                    $apiViolations = $this->contractValidator->validateApiPayload($channel, $normalized);
                    if ($apiViolations !== []) {
                        $this->logger->warning('Payload rejected: API contract violation.', [
                            'channel' => $channel,
                            'violations' => $apiViolations,
                            'payload' => $normalized,
                        ]);
                        continue;
                    }

                    $externalId = (string) ($normalized['externalId'] ?? $normalized['id'] ?? $normalized['orderNumber'] ?? '');
                    if ($externalId === '') {
                        continue;
                    }

                    if ($this->isTestOrder($order) || $this->isTestOrder($normalized)) {
                        $this->markExistingOrderAsTest($externalId, $normalized, $context);
                        continue;
                    }

                    $normalized['isTestOrder'] = false;

                    $san6 = $this->san6Client->fetchByOrderNumber((string) ($normalized['orderNumber'] ?? $externalId));
                    if ($san6 !== []) {
                        $san6Violations = $this->contractValidator->validateApiPayload('san6', $san6);
                        if ($san6Violations !== []) {
                            $this->logger->warning('SAN6 payload ignored: API contract violation.', [
                                'externalOrderId' => $externalId,
                                'violations' => $san6Violations,
                                'payload' => $san6,
                            ]);
                            $san6 = [];
                        }
                    }

                    $matched = $this->matchingService->match($normalized, $san6);

                    [$matched, $resolution] = $this->resolveAndApplyBusinessDates($matched, $channel);

                    $matched['sourceSystem'] = $this->contractValidator->resolveValueByPriority(
                        $matched['sourceSystem'] ?? $channel,
                        null,
                        $san6['sourceSystem'] ?? null,
                    ) ?? $channel;

                    if (($resolution['missingPaymentDate'] ?? false) === true) {
                        $this->logger->warning('Missing payment date for prepayment order, using order date fallback.', [
                            'externalOrderId' => $externalId,
                            'channel' => $channel,
                        ]);
                    }

                    if (($matched['hasConflict'] ?? false) === true) {
                        $this->logger->warning('Lieferzeiten import conflict detected.', [
                            'externalOrderId' => $externalId,
                            'conflicts' => $matched['matchingConflicts'] ?? [],
                        ]);
                    }

                    $existingPaket = $this->findPaketByNumber((string) ($matched['paketNumber'] ?? $matched['packageNumber'] ?? $matched['orderNumber'] ?? $externalId), $context);
                    $existingStatus = $this->normalizeStatusInt($existingPaket?->getStatus());

                    $mappedStatus = $this->resolveMappedStatus($matched, $san6, $existingStatus);

                    $matched['status'] = (string) $mappedStatus;
                    $matched['statusPushQueue'] = is_array($existingPaket?->getStatusPushQueue()) ? $existingPaket->getStatusPushQueue() : [];

                    $parcelRows = $this->buildParcelRows($matched, $externalId);
                    if ($parcelRows === []) {
                        $parcelRows = [$matched];
                    }

                    $allTrackingNumbers = [];
                    $useExternalFallback = count($parcelRows) === 1;
                    foreach ($parcelRows as $parcelRow) {
                        $paketContractViolations = $this->contractValidator->validatePersistencePayload('paket', $parcelRow);
                        if ($paketContractViolations !== []) {
                            $this->logger->warning('Payload rejected: persistence contract violation.', [
                                'externalOrderId' => $externalId,
                                'violations' => $paketContractViolations,
                                'payload' => $parcelRow,
                            ]);
                            continue;
                        }

                        $parcelPaketNumber = (string) ($parcelRow['paketNumber'] ?? $parcelRow['packageNumber'] ?? $parcelRow['orderNumber'] ?? $externalId);
                        $parcelExistingPaket = $this->findPaketByNumber($parcelPaketNumber, $context);
                        if ($parcelExistingPaket === null && $useExternalFallback) {
                            $parcelExistingPaket = $this->findPaketByExternalOrderId($externalId, $context);
                        }
                        $paketId = $this->upsertPaket($externalId, $parcelRow, $context, $parcelExistingPaket);
                        $allTrackingNumbers = array_merge($allTrackingNumbers, $this->upsertPositionAndTrackingHistory($paketId, $parcelRow, $context));

                        $this->auditLogService->log('order_synced', 'paket', $paketId, $context, [
                            'externalOrderId' => $externalId,
                            'channel' => $channel,
                            'status' => $mappedStatus,
                            'paketNumber' => $parcelPaketNumber,
                        ], 'shopware');
                    }

                    $trackingNumbers = array_values(array_unique($allTrackingNumbers));
                    $this->emitNotificationEvents($externalId, $channel, $matched, $trackingNumbers, $existingPaket, $existingStatus, $mappedStatus, $context);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{0: array<string,mixed>, 1: array{baseDate: ?\DateTimeImmutable, baseDateType: string, missingPaymentDate: bool}}
     */
    private function resolveAndApplyBusinessDates(array $payload, string $channel): array
    {
        $resolution = $this->baseDateResolver->resolve($payload);

        $payload['baseDateType'] = $resolution['baseDateType'];
        $payload['paymentDate'] = $payload['paymentDate'] ?? null;

        if ($resolution['baseDate'] === null) {
            $payload['calculatedShippingDate'] = null;
            $payload['calculatedDeliveryDate'] = null;

            return [$payload, $resolution];
        }

        $settings = $this->settingsProvider->getForChannel($channel);
        $calculatedShippingDate = $this->deliveryDateCalculator->calculate($resolution['baseDate'], $settings['shipping']);
        $calculatedDeliveryDate = $this->deliveryDateCalculator->calculate($resolution['baseDate'], $settings['delivery']);

        $payload['calculatedShippingDate'] = $calculatedShippingDate?->format(DATE_ATOM);
        $payload['calculatedDeliveryDate'] = $calculatedDeliveryDate?->format(DATE_ATOM);

        $payload['shippingDate'] = $payload['calculatedShippingDate'] ?? $payload['shippingDate'] ?? null;
        $payload['deliveryDate'] = $payload['calculatedDeliveryDate'] ?? $payload['deliveryDate'] ?? null;

        return [$payload, $resolution];
    }

    private function canRunMode(string $mode): bool
    {
        $strategy = (string) $this->config->get('LieferzeitenAdmin.config.syncStrategy');
        $strategy = $strategy !== '' ? $strategy : 'scheduled';

        return $strategy === 'both' || $strategy === $mode;
    }

    /** @return array<string,array{url:string,token:string}> */
    private function getChannelConfigs(): array
    {
        return [
            'shopware' => [
                'url' => 'LieferzeitenAdmin.config.shopwareApiUrl',
                'token' => 'LieferzeitenAdmin.config.shopwareApiToken',
            ],
            'gambio' => [
                'url' => 'LieferzeitenAdmin.config.gambioApiUrl',
                'token' => 'LieferzeitenAdmin.config.gambioApiToken',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function upsertPaket(string $externalId, array $payload, Context $context, ?PaketEntity $existingPaket = null): string
    {
        $paketNumber = (string) ($payload['paketNumber'] ?? $payload['packageNumber'] ?? $payload['orderNumber'] ?? $externalId);
        $id = $existingPaket?->getId() ?? Uuid::randomHex();

        $this->paketRepository->upsert([[
            'id' => $id,
            'paketNumber' => $paketNumber,
            'status' => $payload['status'] ?? null,
            'shippingDate' => $this->parseDate($payload['shippingDate'] ?? null),
            'deliveryDate' => $this->parseDate($payload['deliveryDate'] ?? null),
            'externalOrderId' => $externalId,
            'sourceSystem' => $payload['sourceSystem'] ?? null,
            'customerEmail' => $payload['customerEmail'] ?? null,
            'customerFirstName' => $this->extractCustomerNamePart($payload, 'firstName'),
            'customerLastName' => $this->extractCustomerNamePart($payload, 'lastName'),
            'customerAdditionalName' => $this->extractCustomerNamePart($payload, 'additionalName'),
            'paymentMethod' => $payload['paymentMethod'] ?? null,
            'paymentDate' => $this->parseDate($payload['paymentDate'] ?? null),
            'orderDate' => $this->parseDate($payload['orderDate'] ?? $payload['date'] ?? null),
            'baseDateType' => $payload['baseDateType'] ?? null,
            'shippingAssignmentType' => $payload['shippingAssignmentType'] ?? $payload['versandart'] ?? null,
            'partialShipmentQuantity' => $payload['partialShipmentQuantity'] ?? $payload['partialShipment'] ?? null,
            'businessDateFrom' => $this->parseDate($payload['businessDateFrom'] ?? null),
            'businessDateTo' => $this->parseDate($payload['businessDateTo'] ?? null),
            'calculatedDeliveryDate' => $this->parseDate($payload['calculatedDeliveryDate'] ?? null),
            'syncBadge' => $payload['syncBadge'] ?? null,
            'isTestOrder' => (bool) ($payload['isTestOrder'] ?? false),
            'statusPushQueue' => $payload['statusPushQueue'] ?? [],
        ]], $context);

        return $id;
    }

    /** @param array<string,mixed> $payload */
    private function upsertPositionAndTrackingHistory(string $paketId, array $payload, Context $context): array
    {
        $positionNumber = (string) ($payload['positionNumber'] ?? $payload['orderNumber'] ?? $payload['externalId'] ?? '');
        if ($positionNumber === '') {
            return [];
        }

        $positionId = $this->findPositionIdByNumber($positionNumber, $context) ?? Uuid::randomHex();
        $this->positionRepository->upsert([[
            'id' => $positionId,
            'paketId' => $paketId,
            'positionNumber' => $positionNumber,
            'articleNumber' => $payload['articleNumber'] ?? null,
            'status' => $payload['status'] ?? null,
            'orderedAt' => $this->parseDate($payload['orderDate'] ?? $payload['date'] ?? null),
        ]], $context);

        $trackingNumbers = $this->extractTrackingNumbers($payload);
        if ($trackingNumbers === [] && $this->isInternalShipment($payload)) {
            $trackingNumbers[] = self::INTERNAL_SHIPPING_LABEL;
        }

        foreach ($trackingNumbers as $trackingNumber) {
            if ($this->trackingNumberExists($positionId, $trackingNumber, $context)) {
                continue;
            }

            $this->sendenummerHistoryRepository->create([[
                'id' => Uuid::randomHex(),
                'positionId' => $positionId,
                'sendenummer' => $trackingNumber,
            ]], $context);
        }

        return $trackingNumbers;
    }

    private function findPaketIdByNumber(string $paketNumber, Context $context): ?string
    {
        return $this->findPaketByNumber($paketNumber, $context)?->getId();
    }

    private function findPaketIdByExternalOrderId(string $externalOrderId, Context $context): ?string
    {
        return $this->findPaketByExternalOrderId($externalOrderId, $context)?->getId();
    }

    private function findPaketByExternalOrderId(string $externalOrderId, Context $context): ?PaketEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('externalOrderId', $externalOrderId));

        /** @var PaketEntity|null $entity */
        $entity = $this->paketRepository->search($criteria, $context)->first();

        return $entity;
    }

    private function findPaketByNumber(string $paketNumber, Context $context): ?PaketEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('paketNumber', $paketNumber));

        /** @var PaketEntity|null $entity */
        $entity = $this->paketRepository->search($criteria, $context)->first();

        return $entity;
    }

    private function findPositionIdByNumber(string $positionNumber, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('positionNumber', $positionNumber));

        $entity = $this->positionRepository->search($criteria, $context)->first();

        return $entity?->getId();
    }

    private function trackingNumberExists(string $positionId, string $trackingNumber, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('positionId', $positionId));
        $criteria->addFilter(new EqualsFilter('sendenummer', $trackingNumber));

        return $this->sendenummerHistoryRepository->search($criteria, $context)->first() !== null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int, array<string,mixed>>
     */
    private function buildParcelRows(array $payload, string $externalId): array
    {
        $parcels = $payload['parcelRows'] ?? $payload['parcels'] ?? [];
        if (!is_array($parcels) || $parcels === []) {
            return [];
        }

        $rows = [];
        foreach ($parcels as $index => $parcel) {
            if (!is_array($parcel)) {
                continue;
            }

            $parcelNumber = (string) ($parcel['paketNumber'] ?? $parcel['packageNumber'] ?? $parcel['parcelNumber'] ?? $payload['paketNumber'] ?? $payload['orderNumber'] ?? '');
            $trackingNumber = (string) ($parcel['trackingNumber'] ?? $parcel['sendenummer'] ?? '');

            $rows[] = [
                'externalOrderId' => $externalId,
                'externalId' => $externalId,
                'orderNumber' => $payload['orderNumber'] ?? $externalId,
                'paketNumber' => $parcelNumber !== '' ? $parcelNumber : ($trackingNumber !== '' ? $trackingNumber : ($externalId . '-' . ($index + 1))),
                'status' => $parcel['status'] ?? $parcel['trackingStatus'] ?? $parcel['state'] ?? ($payload['status'] ?? null),
                'shippingDate' => $parcel['shippingDate'] ?? $parcel['shipping_date'] ?? ($payload['shippingDate'] ?? null),
                'deliveryDate' => $parcel['deliveryDate'] ?? $parcel['delivery_date'] ?? ($payload['deliveryDate'] ?? null),
                'sourceSystem' => $payload['sourceSystem'] ?? 'san6',
                'customerEmail' => $payload['customerEmail'] ?? null,
                'customerFirstName' => $this->extractCustomerNamePart($payload, 'firstName'),
                'customerLastName' => $this->extractCustomerNamePart($payload, 'lastName'),
                'customerAdditionalName' => $this->extractCustomerNamePart($payload, 'additionalName'),
                'paymentMethod' => $payload['paymentMethod'] ?? null,
                'paymentDate' => $payload['paymentDate'] ?? null,
                'baseDateType' => $payload['baseDateType'] ?? null,
                'shippingAssignmentType' => $payload['shippingAssignmentType'] ?? null,
                'partialShipmentQuantity' => $payload['partialShipmentQuantity'] ?? null,
                'businessDateFrom' => $payload['businessDateFrom'] ?? null,
                'businessDateTo' => $payload['businessDateTo'] ?? null,
                'calculatedDeliveryDate' => $payload['calculatedDeliveryDate'] ?? null,
                'syncBadge' => $payload['syncBadge'] ?? null,
                'isTestOrder' => $payload['isTestOrder'] ?? false,
                'statusPushQueue' => $payload['statusPushQueue'] ?? [],
                'parcels' => [$parcel],
                'trackingNumbers' => $trackingNumber !== '' ? [$trackingNumber] : [],
            ];
        }

        return $rows;
    }

    /** @param array<string,mixed> $payload
      * @return array<int,string>
      */
    private function extractTrackingNumbers(array $payload): array
    {
        $tracking = [];

        $directCandidates = [
            $payload['trackingNumbers'] ?? null,
            $payload['tracking_numbers'] ?? null,
            $payload['sendenummern'] ?? null,
            $payload['trackingNumber'] ?? null,
            $payload['sendenummer'] ?? null,
        ];

        foreach ($directCandidates as $directCandidate) {
            foreach ($this->normalizeTrackingCandidates($directCandidate) as $candidate) {
                $tracking[] = $candidate;
            }
        }

        $parcels = $payload['parcels'] ?? [];
        if (is_array($parcels)) {
            foreach ($parcels as $parcel) {
                if (!is_array($parcel)) {
                    continue;
                }

                $number = (string) ($parcel['trackingNumber'] ?? $parcel['sendenummer'] ?? '');
                foreach ($this->normalizeTrackingCandidates($number) as $candidate) {
                    $tracking[] = $candidate;
                }
            }
        }

        return array_values(array_unique($tracking));
    }

    /** @return array<int,string> */
    private function normalizeTrackingCandidates(mixed $value): array
    {
        $result = [];

        if (is_array($value)) {
            foreach ($value as $entry) {
                foreach ($this->normalizeTrackingCandidates($entry) as $candidate) {
                    $result[] = $candidate;
                }
            }

            return $result;
        }

        if (!is_string($value)) {
            return [];
        }

        $candidate = trim($value);
        if ($candidate === '' || $this->isInvalidTrackingPlaceholder($candidate)) {
            return [];
        }

        return [$candidate];
    }

    private function isInvalidTrackingPlaceholder(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, [
            '-',
            '--',
            'n/a',
            'na',
            'none',
            'kein tracking',
            'ohne tracking',
            'internal',
            'intern',
            'eigenversand',
        ], true);
    }

    /** @param array<string,mixed> $payload */
    private function isInternalShipment(array $payload): bool
    {
        $candidates = [
            $payload['internalShipment'] ?? null,
            $payload['isInternalShipment'] ?? null,
            $payload['internal_shipping'] ?? null,
            $payload['internalShipping'] ?? null,
            $payload['internal_dispatch'] ?? null,
            $payload['isInternalDispatch'] ?? null,
            $payload['selfShipment'] ?? null,
            $payload['shippingProvider'] ?? null,
            $payload['carrier'] ?? null,
            $payload['trackingProvider'] ?? null,
            $payload['versandart'] ?? null,
            $payload['shippingAssignmentType'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($this->isTruthy($candidate)) {
                return true;
            }

            if (!is_string($candidate)) {
                continue;
            }

            $normalized = mb_strtolower(trim($candidate));
            if (in_array($normalized, [
                'internal',
                'intern',
                'internal_shipping',
                'internal shipment',
                'self',
                'self_shipment',
                'eigenversand',
                'first medical',
                'versand durch first medical',
            ], true)) {
                return true;
            }
        }

        return $this->extractTrackingNumbers($payload) === [];
    }

    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /** @param array<string,mixed> $payload */
    private function isTestOrder(array $payload): bool
    {
        $candidates = [
            $payload['Testbestellung'] ?? null,
            $payload['testbestellung'] ?? null,
            $payload['isTestOrder'] ?? null,
            $payload['testOrder'] ?? null,
            $payload['test_order'] ?? null,
            $payload['isTest'] ?? null,
        ];

        $additional = $payload['additional'] ?? null;
        if (is_array($additional)) {
            $candidates[] = $additional['Testbestellung'] ?? null;
            $candidates[] = $additional['testbestellung'] ?? null;
            $candidates[] = $additional['isTestOrder'] ?? null;
        }

        $detail = $payload['detail'] ?? null;
        if (is_array($detail)) {
            $detailAdditional = $detail['additional'] ?? null;
            if (is_array($detailAdditional)) {
                $candidates[] = $detailAdditional['Testbestellung'] ?? null;
                $candidates[] = $detailAdditional['testbestellung'] ?? null;
                $candidates[] = $detailAdditional['isTestOrder'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->toBool($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'ja', 'y', 'x'], true);
    }

    private function markExistingOrderAsTest(string $externalId, array $payload, Context $context): void
    {
        $paketNumber = (string) ($payload['paketNumber'] ?? $payload['packageNumber'] ?? $payload['orderNumber'] ?? $externalId);
        $paket = $this->findPaketByExternalOrderId($externalId, $context)
            ?? $this->findPaketByNumber($paketNumber, $context);
        $paketId = $paket?->getId();

        if ($paketId === null) {
            return;
        }

        $wasAlreadyTestOrder = $paket?->getIsTestOrder() === true;
        $auditAction = $wasAlreadyTestOrder ? 'order_re_marked_test' : 'order_marked_test';

        $this->paketRepository->upsert([
            [
                'id' => $paketId,
                'isTestOrder' => true,
                'lastChangedBy' => 'sync',
                'lastChangedAt' => date(DATE_ATOM),
            ],
        ], $context);

        $logContext = [
            'externalOrderId' => $externalId,
            'paketNumber' => $paketNumber,
            'paketId' => $paketId,
            'alreadyMarkedAsTest' => $wasAlreadyTestOrder,
        ];

        $this->logger->info('Lieferzeiten order marked as test order during import.', $logContext);
        $this->auditLogService->log($auditAction, 'paket', $paketId, $context, $logContext, 'shopware');
    }

    /** @param array<string,mixed> $order */
    private function resolveMappedStatus(array $order, array $san6Payload, ?int $existingStatus): int
    {
        $sourceStatus = $this->normalizeStatusInt($order['status'] ?? null);
        if ($sourceStatus !== null && $sourceStatus >= 1 && $sourceStatus <= 6) {
            return $sourceStatus;
        }

        if ($this->isCompletedStatus8($order, $san6Payload)) {
            return 8;
        }

        if ($this->isSan6Status7($order, $san6Payload)) {
            return 7;
        }

        return $sourceStatus ?? $existingStatus ?? 1;
    }

    /** @param array<string,mixed> $order */
    private function isSan6Status7(array $order, array $san6Payload): bool
    {
        $san6StatusValue = strtolower((string) ($san6Payload['status'] ?? $san6Payload['state'] ?? $order['san6Status'] ?? ''));
        if (in_array($san6StatusValue, ['7', 'status_7', 'bereit', 'ready', 'freigegeben', 'approved'], true)) {
            return true;
        }

        return (bool) ($san6Payload['status7'] ?? $san6Payload['readyForRelease'] ?? $order['san6Ready'] ?? false);
    }

    /** @param array<string,mixed> $order */
    private function isCompletedStatus8(array $order, array $san6Payload): bool
    {
        if ((bool) ($order['forceSetCompleted'] ?? $order['forceSetStatus8'] ?? false)) {
            return true;
        }

        if ($this->isSan6InternalDeliveryCompleted($order, $san6Payload)) {
            return true;
        }

        $specialCase = (string) ($order['specialCompletionCase'] ?? $san6Payload['specialCompletionCase'] ?? '');
        if ($specialCase !== '' && in_array(strtolower($specialCase), ['manual_complete', 'digital_only', 'pickup_done', 'special_case'], true)) {
            return true;
        }

        $parcels = $order['parcels'] ?? $san6Payload['parcels'] ?? [];
        if (is_array($parcels) && $parcels !== []) {
            $closedParcels = 0;
            foreach ($parcels as $parcel) {
                if (!is_array($parcel)) {
                    continue;
                }

                if ($this->isClosedParcelStatusForStatus8($parcel, $order)) {
                    ++$closedParcels;
                }
            }

            return $closedParcels > 0 && $closedParcels === count($parcels);
        }

        return $this->isSan6InternalDeliveryCompleted($order, $san6Payload);
    }

    /** @param array<string,mixed> $order */
    private function isSan6InternalDeliveryCompleted(array $order, array $san6Payload): bool
    {
        $state = strtolower(trim((string) ($san6Payload[San6MatchingService::INTERNAL_DELIVERY_STATE] ?? $order[San6MatchingService::INTERNAL_DELIVERY_STATE] ?? '')));
        if ($state === San6MatchingService::INTERNAL_DELIVERY_COMPLETED_STATE) {
            return true;
        }

        foreach (self::SAN6_INTERNAL_DELIVERY_COMPLETED_FLAG_KEYS as $key) {
            $value = $san6Payload[$key] ?? $order[$key] ?? null;
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value) || is_int($value)) {
                $normalized = strtolower(trim((string) $value));
                if (in_array($normalized, ['1', 'true', 'yes', 'ja', 'y', 'x'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'no', 'nein', 'n'], true)) {
                    return false;
                }
            }
        }

        $matchedFlag = $san6Payload[San6MatchingService::INTERNAL_DELIVERY_COMPLETED_FLAG] ?? $order[San6MatchingService::INTERNAL_DELIVERY_COMPLETED_FLAG] ?? null;
        if ($matchedFlag !== null && $this->toBool($matchedFlag)) {
            return true;
        }

        $state = strtolower(trim((string) ($san6Payload['deliveryCompletionState'] ?? $order['deliveryCompletionState'] ?? '')));
        if ($state === '') {
            return false;
        }

        return in_array($state, self::SAN6_INTERNAL_DELIVERY_COMPLETED_STATES, true);
    }

    /**
     * @param array<string,mixed> $parcel
     * @param array<string,mixed> $order
     */
    private function isClosedParcelStatusForStatus8(array $parcel, array $order = []): bool
    {
        $mapped = $this->status8TrackingMappingProvider->isClosed($parcel, $order);
        if ($mapped !== null) {
            return $mapped;
        }

        $state = $this->normalizeParcelState($parcel);

        $closed = $parcel['closed'] ?? null;
        if ($closed !== null) {
            return (bool) $closed;
        }

        return in_array($state, ['delivered', 'completed', '8'], true);
    }

    /** @param array<string,mixed> $parcel */
    private function normalizeParcelState(array $parcel): string
    {
        $rawState = (string) ($parcel['trackingStatus'] ?? $parcel['san6Status'] ?? $parcel['status'] ?? $parcel['state'] ?? '');
        $state = mb_strtolower(trim($rawState));

        return str_replace([' ', '-', '/'], '_', $state);
    }


    private function processPendingStatusPushQueue(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(5000);
        $result = $this->paketRepository->search($criteria, $context);

        $updates = [];
        $now = time();

        foreach ($result->getEntities() as $entity) {
            if (!$entity instanceof PaketEntity) {
                continue;
            }

            $queue = $entity->getStatusPushQueue();
            if (!is_array($queue) || $queue === []) {
                continue;
            }

            $changed = false;
            foreach ($queue as $index => $item) {
                if (!is_array($item) || (string) ($item['state'] ?? '') !== 'pending') {
                    continue;
                }

                if (($item['triggerSource'] ?? null) !== 'user_lms') {
                    continue;
                }

                $nextAttemptAt = strtotime((string) ($item['nextAttemptAt'] ?? '')) ?: 0;
                if ($nextAttemptAt > $now) {
                    continue;
                }

                if ((string) ($item['triggerSource'] ?? '') !== 'lms_user') {
                    $queue[$index]['state'] = 'ignored';
                    $queue[$index]['ignoredAt'] = date(DATE_ATOM);
                    $queue[$index]['ignoredReason'] = 'trigger_not_lms_user';
                    $changed = true;
                    continue;
                }

                $targetStatus = (int) ($item['targetStatus'] ?? 0);
                $pushed = $this->pushStatusToChannel($entity, $targetStatus);
                $changed = true;

                if ($pushed) {
                    $queue[$index]['state'] = 'sent';
                    $queue[$index]['sentAt'] = date(DATE_ATOM);
                    continue;
                }

                $attempts = (int) ($queue[$index]['attempts'] ?? 0) + 1;
                $queue[$index]['attempts'] = $attempts;
                $queue[$index]['lastErrorAt'] = date(DATE_ATOM);
                $queue[$index]['nextAttemptAt'] = date(DATE_ATOM, $now + min(86400, (2 ** $attempts) * 60));
            }

            if ($changed) {
                $updates[] = [
                    'id' => $entity->getId(),
                    'statusPushQueue' => $queue,
                ];
            }
        }

        if ($updates !== []) {
            $this->paketRepository->upsert($updates, $context);
        }
    }

    private function pushStatusToChannel(PaketEntity $paket, int $status): bool
    {
        if ($status < 7 || $status > 8) {
            return true;
        }

        $channel = strtolower((string) ($paket->getSourceSystem() ?? ''));
        $pushConfig = $this->getPushConfig($channel);
        if ($pushConfig === null || $pushConfig['url'] === '') {
            $this->logger->warning('Status push skipped due to missing push endpoint configuration.', [
                'paketNumber' => $paket->getPaketNumber(),
                'channel' => $channel,
                'targetStatus' => $status,
            ]);

            return false;
        }

        $requestOptions = [
            'json' => [
                'externalOrderId' => $paket->getExternalOrderId(),
                'paketNumber' => $paket->getPaketNumber(),
                'status' => $status,
            ],
        ];

        if ($pushConfig['token'] !== '') {
            $requestOptions['headers'] = [
                'Authorization' => sprintf('Bearer %s', $pushConfig['token']),
            ];
        }

        try {
            $response = $this->httpClient->request('POST', $pushConfig['url'], $requestOptions);

            return $response->getStatusCode() < 400;
        } catch (\Throwable $exception) {
            $this->logger->error('Status push to source system failed.', [
                'paketNumber' => $paket->getPaketNumber(),
                'channel' => $channel,
                'targetStatus' => $status,
            ]);

            return false;
        }
    }

    /** @return array{url: string, token: string}|null */
    private function getPushConfig(string $channel): ?array
    {
        $map = [
            'shopware' => [
                'url' => 'LieferzeitenAdmin.config.shopwareStatusPushApiUrl',
                'token' => 'LieferzeitenAdmin.config.shopwareStatusPushApiToken',
            ],
            'gambio' => [
                'url' => 'LieferzeitenAdmin.config.gambioStatusPushApiUrl',
                'token' => 'LieferzeitenAdmin.config.gambioStatusPushApiToken',
            ],
        ];

        if (!isset($map[$channel])) {
            return null;
        }

        return [
            'url' => (string) $this->config->get($map[$channel]['url']),
            'token' => (string) $this->config->get($map[$channel]['token']),
        ];
    }


    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $trackingNumbers
     */
    private function emitNotificationEvents(string $externalOrderId, string $sourceSystem, array $payload, array $trackingNumbers, ?PaketEntity $existingPaket, ?int $existingStatus, int $mappedStatus, Context $context): void
    {
        $existingDeliveryDate = $this->normalizeComparableDate($existingPaket?->getDeliveryDate());
        $existingCalculatedDeliveryDate = $this->normalizeComparableDate($existingPaket?->getCalculatedDeliveryDate());
        $incomingDeliveryDate = $this->parseDate($payload['deliveryDate'] ?? null);
        $incomingCalculatedDeliveryDate = $this->parseDate($payload['calculatedDeliveryDate'] ?? null);
        $hasIncomingDeliveryDate = $incomingDeliveryDate !== null || $incomingCalculatedDeliveryDate !== null;
        $hasExistingDeliveryDate = $existingDeliveryDate !== null || $existingCalculatedDeliveryDate !== null;

        foreach (NotificationTriggerCatalog::channels() as $channel) {
            if ($existingPaket === null) {
                $this->notificationEventService->dispatch(
                    sprintf('order-created:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::ORDER_CREATED,
                    $channel,
                    $payload,
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($existingStatus !== null && $existingStatus !== $mappedStatus) {
                $this->notificationEventService->dispatch(
                    sprintf('status-change:%s:%s:%s', $externalOrderId, (string) $mappedStatus, $channel),
                    NotificationTriggerCatalog::ORDER_STATUS_CHANGED,
                    $channel,
                    [
                        'from' => $existingStatus,
                        'to' => $mappedStatus,
                        'externalOrderId' => $externalOrderId,
                    ],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($trackingNumbers !== []) {
                $trackingPayload = [
                    'trackingNumbers' => $trackingNumbers,
                    'externalOrderId' => $externalOrderId,
                ];

                $this->notificationEventService->dispatch(
                    sprintf('tracking:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::TRACKING_UPDATED,
                    $channel,
                    $trackingPayload,
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );

                $this->notificationEventService->dispatch(
                    sprintf('shipping-confirmed:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::SHIPPING_CONFIRMED,
                    $channel,
                    $trackingPayload,
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($hasIncomingDeliveryDate) {
                $this->notificationEventService->dispatch(
                    sprintf('delivery-change:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::DELIVERY_DATE_CHANGED,
                    $channel,
                    $deliveryDatePayload,
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );

                if (!$hasExistingDeliveryDate) {
                    $this->notificationEventService->dispatch(
                        sprintf('delivery-date-assigned:%s:%s', $externalOrderId, $channel),
                        NotificationTriggerCatalog::DELIVERY_DATE_ASSIGNED,
                        $channel,
                        [
                            'deliveryDate' => $payload['deliveryDate'] ?? null,
                            'calculatedDeliveryDate' => $payload['calculatedDeliveryDate'] ?? null,
                            'externalOrderId' => $externalOrderId,
                        ],
                        $context,
                        $externalOrderId,
                        $sourceSystem,
                    );
                } elseif ($existingDeliveryDate !== $incomingDeliveryDate || $existingCalculatedDeliveryDate !== $incomingCalculatedDeliveryDate) {
                    $this->notificationEventService->dispatch(
                        sprintf('delivery-date-updated:%s:%s', $externalOrderId, $channel),
                        NotificationTriggerCatalog::DELIVERY_DATE_UPDATED,
                        $channel,
                        [
                            'previousDeliveryDate' => $existingDeliveryDate,
                            'previousCalculatedDeliveryDate' => $existingCalculatedDeliveryDate,
                            'deliveryDate' => $payload['deliveryDate'] ?? null,
                            'calculatedDeliveryDate' => $payload['calculatedDeliveryDate'] ?? null,
                            'externalOrderId' => $externalOrderId,
                        ],
                        $context,
                        $externalOrderId,
                        $sourceSystem,
                    );
                }
            }

            if (($payload['customsRequired'] ?? false) === true) {
                $this->notificationEventService->dispatch(
                    sprintf('customs:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::CUSTOMS_REQUIRED,
                    $channel,
                    [
                        'externalOrderId' => $externalOrderId,
                        'customs' => $payload['customsData'] ?? null,
                    ],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($mappedStatus === 9 || ($payload['isStorno'] ?? false) === true) {
                $this->notificationEventService->dispatch(
                    sprintf('storno:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::ORDER_CANCELLED_STORNO,
                    $channel,
                    ['externalOrderId' => $externalOrderId],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($this->hasParcelState($payload, 'nicht_zustellbar')) {
                $this->notificationEventService->dispatch(
                    sprintf('delivery-impossible:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::DELIVERY_IMPOSSIBLE,
                    $channel,
                    ['externalOrderId' => $externalOrderId],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }



            if ($this->isPrepaymentOrder($payload) && ($payload['paymentDate'] ?? null) !== null && $previousPaymentDate === null) {
                $this->notificationEventService->dispatch(
                    sprintf('vorkasse-payment-received:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::PAYMENT_RECEIVED_VORKASSE,
                    $channel,
                    [
                        'externalOrderId' => $externalOrderId,
                        'paymentDate' => $payload['paymentDate'],
                        'paymentMethod' => $payload['paymentMethod'] ?? null,
                    ],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }

            if ($existingStatus !== 8 && $mappedStatus === 8) {
                $this->notificationEventService->dispatch(
                    sprintf('order-completed-review-reminder:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::ORDER_COMPLETED_REVIEW_REMINDER,
                    $channel,
                    [
                        'externalOrderId' => $externalOrderId,
                        'completedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                        'status' => $mappedStatus,
                    ],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }
            if ($this->hasParcelState($payload, 'retoure')) {
                $this->notificationEventService->dispatch(
                    sprintf('return-to-sender:%s:%s', $externalOrderId, $channel),
                    NotificationTriggerCatalog::RETURN_TO_SENDER,
                    $channel,
                    ['externalOrderId' => $externalOrderId],
                    $context,
                    $externalOrderId,
                    $sourceSystem,
                );
            }
        }
    }

    /** @param array<string,mixed> $payload */
    private function hasParcelState(array $payload, string $expectedState): bool
    {
        $parcels = $payload['parcels'] ?? [];
        if (!is_array($parcels)) {
            return false;
        }

        foreach ($parcels as $parcel) {
            if (!is_array($parcel)) {
                continue;
            }

            if ($this->normalizeParcelState($parcel) === $expectedState) {
                return true;
            }
        }

        return false;
    }



    /** @param array<string,mixed> $payload */
    private function isPrepaymentOrder(array $payload): bool
    {
        $paymentMethod = mb_strtolower((string) ($payload['paymentMethod'] ?? ''));

        return str_contains($paymentMethod, 'vorkasse') || str_contains($paymentMethod, 'prepayment');
    }

    private function hasDateValueChanged(?\DateTimeInterface $previous, ?\DateTimeInterface $current): bool
    {
        if ($previous === null && $current === null) {
            return false;
        }

        if ($previous === null || $current === null) {
            return true;
        }

        return $previous->format(DATE_ATOM) !== $current->format(DATE_ATOM);
    }

    private function normalizeStatusInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }


    /** @param array<string,mixed> $payload */
    private function extractCustomerNamePart(array $payload, string $part): ?string
    {
        $variants = [
            'firstName' => [
                'customerFirstName',
                'customer_first_name',
                'firstName',
                'firstname',
                'customer.firstName',
                'customer.firstname',
                'orderCustomer.firstName',
                'orderCustomer.first_name',
                'billingAddress.firstName',
                'billingAddress.first_name',
                'customer.shippingFirstName',
                'customer.shipping_first_name',
            ],
            'lastName' => [
                'customerLastName',
                'customer_last_name',
                'lastName',
                'lastname',
                'customer.lastName',
                'customer.lastname',
                'orderCustomer.lastName',
                'orderCustomer.last_name',
                'billingAddress.lastName',
                'billingAddress.last_name',
                'customer.shippingLastName',
                'customer.shipping_last_name',
            ],
            'additionalName' => [
                'customerAdditionalName',
                'customer_additional_name',
                'additionalName',
                'additional_name',
                'customer.additionalName',
                'customer.additional_name',
                'orderCustomer.additionalName',
                'orderCustomer.additional_name',
                'billingAddress.additionalAddressLine1',
                'billingAddress.additional_address_line1',
                'customer.company',
                'customer.additionalName2',
                'customer.additional_name_2',
            ],
        ];

        foreach ($variants[$part] ?? [] as $key) {
            $value = $this->getNestedValue($payload, $key);
            if (!is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return mixed
     */
    private function getNestedValue(array $payload, string $path)
    {
        if (array_key_exists($path, $payload)) {
            return $payload[$path];
        }

        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function normalizeComparableDate(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }
}
