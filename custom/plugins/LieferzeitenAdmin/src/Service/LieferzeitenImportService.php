<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

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

                    $paketId = $this->upsertPaket($externalId, $matched, $context);
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

    /** @param array<string,mixed> $payload */
    private function upsertPaket(string $externalId, array $payload, Context $context): string
    {
        $paketNumber = (string) ($payload['paketNumber'] ?? $payload['packageNumber'] ?? $payload['orderNumber'] ?? $externalId);
        $existingId = $this->findPaketIdByNumber($paketNumber, $context);
        $id = $existingId ?? Uuid::randomHex();

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
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('paketNumber', $paketNumber));

        $entity = $this->paketRepository->search($criteria, $context)->first();

        return $entity?->getId();
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
}
