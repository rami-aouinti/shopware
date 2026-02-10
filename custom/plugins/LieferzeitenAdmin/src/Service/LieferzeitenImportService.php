<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use LieferzeitenAdmin\Entity\PaketEntity;
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
        private readonly LockFactory $lockFactory,
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
                $response = $this->httpClient->request('GET', $url, $token !== '' ? ['headers' => ['Authorization' => sprintf('Bearer %s', $token)]] : []);
                $payload = $response->toArray(false);
                $orders = $payload['orders'] ?? $payload;

                if (!is_array($orders)) {
                    continue;
                }

                foreach ($orders as $order) {
                    if (!is_array($order) || $this->isTestOrder($order)) {
                        continue;
                    }

                    $adapter = $this->adapterRegistry->resolve($channel, $order);
                    $normalized = $adapter?->normalize($order) ?? $order;

                    $externalId = (string) ($normalized['externalId'] ?? $normalized['id'] ?? $normalized['orderNumber'] ?? '');
                    if ($externalId === '') {
                        continue;
                    }

                    $san6 = $this->san6Client->fetchByOrderNumber((string) ($normalized['orderNumber'] ?? $externalId));
                    $matched = $this->matchingService->match($normalized, $san6);

                    $resolution = $this->baseDateResolver->resolve($matched);
                    $settings = $this->settingsProvider->getForChannel($channel);
                    $calculatedDeliveryDate = $this->deliveryDateCalculator->calculate($resolution['baseDate'], $settings);

                    $matched['baseDateType'] = $resolution['baseDateType'];
                    $matched['paymentDate'] = $matched['paymentDate'] ?? null;
                    $matched['calculatedDeliveryDate'] = $calculatedDeliveryDate?->format(DATE_ATOM);
                    $matched['sourceSystem'] = $matched['sourceSystem'] ?? $channel;

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
                    $queue = is_array($existingPaket?->getStatusPushQueue()) ? $existingPaket->getStatusPushQueue() : [];

                    if ($mappedStatus === 7 && $this->isManualStatus7Change($matched, $existingPaket, $existingStatus)) {
                        $queue = $this->enqueueStatusPush($queue, 7, 'manual_status_7');
                    }

                    if ($mappedStatus === 8 && $this->isForceSetCompleted($matched, $existingPaket)) {
                        $queue = $this->enqueueStatusPush($queue, 8, 'force_set_status_8');
                    }

                    $matched['status'] = (string) $mappedStatus;
                    $matched['statusPushQueue'] = $queue;

                    $paketId = $this->upsertPaket($externalId, $matched, $context, $existingPaket);
                    $this->upsertPositionAndTrackingHistory($paketId, $matched, $context);
                }
            }
        } finally {
            $lock->release();
        }
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
            'paymentMethod' => $payload['paymentMethod'] ?? null,
            'paymentDate' => $this->parseDate($payload['paymentDate'] ?? null),
            'orderDate' => $this->parseDate($payload['orderDate'] ?? $payload['date'] ?? null),
            'baseDateType' => $payload['baseDateType'] ?? null,
            'calculatedDeliveryDate' => $this->parseDate($payload['calculatedDeliveryDate'] ?? null),
            'syncBadge' => $payload['syncBadge'] ?? null,
            'statusPushQueue' => $payload['statusPushQueue'] ?? [],
        ]], $context);

        return $id;
    }

    /** @param array<string,mixed> $payload */
    private function upsertPositionAndTrackingHistory(string $paketId, array $payload, Context $context): void
    {
        $positionNumber = (string) ($payload['positionNumber'] ?? $payload['orderNumber'] ?? $payload['externalId'] ?? '');
        if ($positionNumber === '') {
            return;
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
    }

    private function findPaketIdByNumber(string $paketNumber, Context $context): ?string
    {
        return $this->findPaketByNumber($paketNumber, $context)?->getId();
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

    /** @param array<string,mixed> $payload
      * @return array<int,string>
      */
    private function extractTrackingNumbers(array $payload): array
    {
        $tracking = [];

        $direct = $payload['trackingNumbers'] ?? null;
        if (is_array($direct)) {
            foreach ($direct as $n) {
                if (is_string($n) && $n !== '') {
                    $tracking[] = $n;
                }
            }
        }

        $parcels = $payload['parcels'] ?? [];
        if (is_array($parcels)) {
            foreach ($parcels as $parcel) {
                if (!is_array($parcel)) {
                    continue;
                }

                $number = (string) ($parcel['trackingNumber'] ?? $parcel['sendenummer'] ?? '');
                if ($number !== '') {
                    $tracking[] = $number;
                }
            }
        }

        return array_values(array_unique($tracking));
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
        if (($payload['isTest'] ?? false) === true) {
            return true;
        }

        foreach (['orderNumber', 'email', 'customerEmail', 'environment'] as $field) {
            $value = (string) ($payload[$field] ?? '');
            if ($value !== '' && preg_match('/\b(test|dummy|sandbox|example)\b/i', $value) === 1) {
                return true;
            }
        }

        return false;
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

        $specialCase = (string) ($order['specialCompletionCase'] ?? $san6Payload['specialCompletionCase'] ?? '');
        if ($specialCase !== '' && in_array(strtolower($specialCase), ['manual_complete', 'digital_only', 'pickup_done', 'special_case'], true)) {
            return true;
        }

        $parcels = $order['parcels'] ?? $san6Payload['parcels'] ?? [];
        if (!is_array($parcels) || $parcels === []) {
            return false;
        }

        $delivered = 0;
        foreach ($parcels as $parcel) {
            if (!is_array($parcel)) {
                continue;
            }

            $state = strtolower((string) ($parcel['status'] ?? $parcel['state'] ?? ''));
            $closed = (bool) ($parcel['closed'] ?? false);

            if ($closed || in_array($state, ['delivered', 'zugestellt', 'completed', '8'], true)) {
                ++$delivered;
            }
        }

        return $delivered > 0 && $delivered === count($parcels);
    }

    private function isManualStatus7Change(array $payload, ?PaketEntity $existingPaket, ?int $existingStatus): bool
    {
        if ((bool) ($payload['manualStatusChange'] ?? false)) {
            return true;
        }

        if ($existingPaket === null || $existingStatus !== 7) {
            return false;
        }

        $lastChangedBy = strtolower((string) ($existingPaket->getLastChangedBy() ?? ''));
        if ($lastChangedBy === '' || $lastChangedBy === 'system' || $lastChangedBy === 'sync') {
            return false;
        }

        return true;
    }

    private function isForceSetCompleted(array $payload, ?PaketEntity $existingPaket): bool
    {
        if ((bool) ($payload['forceSetCompleted'] ?? $payload['forceSetStatus8'] ?? false)) {
            return true;
        }

        if ($existingPaket === null) {
            return false;
        }

        if ($this->normalizeStatusInt($existingPaket->getStatus()) !== 8) {
            return false;
        }

        $lastChangedBy = strtolower((string) ($existingPaket->getLastChangedBy() ?? ''));

        return $lastChangedBy !== '' && $lastChangedBy !== 'system' && $lastChangedBy !== 'sync';
    }

    /**
     * @param array<int, array<string,mixed>> $queue
     *
     * @return array<int, array<string,mixed>>
     */
    private function enqueueStatusPush(array $queue, int $targetStatus, string $reason): array
    {
        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((int) ($item['targetStatus'] ?? 0) === $targetStatus && (string) ($item['state'] ?? '') === 'pending') {
                return $queue;
            }
        }

        $queue[] = [
            'targetStatus' => $targetStatus,
            'reason' => $reason,
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => date(DATE_ATOM),
            'createdAt' => date(DATE_ATOM),
        ];

        return $queue;
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

                $nextAttemptAt = strtotime((string) ($item['nextAttemptAt'] ?? '')) ?: 0;
                if ($nextAttemptAt > $now) {
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
}
