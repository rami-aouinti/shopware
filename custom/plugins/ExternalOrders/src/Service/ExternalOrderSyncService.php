<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalOrderSyncService
{
    public function __construct(
        private readonly EntityRepository $externalOrderRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncNewOrders(Context $context): void
    {
        $apiUrl = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersApiUrl');
        if ($apiUrl === '') {
            $this->logger->warning('External Orders sync skipped: missing API URL.');

            return;
        }

        $apiToken = (string) $this->systemConfigService->get('ExternalOrders.config.externalOrdersApiToken');

        $options = [];
        if ($apiToken !== '') {
            $options['headers'] = [
                'Authorization' => sprintf('Bearer %s', $apiToken),
            ];
        }

        $response = $this->httpClient->request('GET', $apiUrl, $options);
        $payload = $response->toArray(false);

        if (!is_array($payload)) {
            $this->logger->warning('External Orders sync skipped: API response is not an array.');

            return;
        }

        $orders = $payload['orders'] ?? $payload;
        if (!is_array($orders)) {
            $this->logger->warning('External Orders sync skipped: API response does not contain orders.');

            return;
        }

        $externalIds = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $externalId = $this->resolveExternalId($order);
            if ($externalId !== null) {
                $externalIds[] = $externalId;
            }
        }

        if ($externalIds === []) {
            $this->logger->info('External Orders sync finished: no external IDs found.');

            return;
        }

        $existingIds = $this->fetchExistingIds($externalIds, $context);
        $upsertPayload = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $externalId = $this->resolveExternalId($order);
            if ($externalId === null) {
                continue;
            }

            $upsertPayload[] = [
                'id' => $existingIds[$externalId] ?? Uuid::randomHex(),
                'externalId' => $externalId,
                'payload' => $order,
            ];
        }

        if ($upsertPayload === []) {
            $this->logger->info('External Orders sync finished: nothing to upsert.');

            return;
        }

        $this->externalOrderRepository->upsert($upsertPayload, $context);
        $this->logger->info('External Orders sync finished.', [
            'total' => count($upsertPayload),
        ]);
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function fetchExistingIds(array $externalIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('externalId', $externalIds));
        $criteria->setLimit(count($externalIds));

        $result = $this->externalOrderRepository->search($criteria, $context);

        $mapping = [];
        foreach ($result->getEntities() as $entity) {
            $mapping[$entity->getExternalId()] = $entity->getId();
        }

        return $mapping;
    }

    /**
     * @param array<mixed> $order
     */
    private function resolveExternalId(array $order): ?string
    {
        $externalId = $order['externalId'] ?? $order['id'] ?? $order['orderNumber'] ?? null;

        if (!is_string($externalId) || $externalId === '') {
            return null;
        }

        return $externalId;
    }
}
