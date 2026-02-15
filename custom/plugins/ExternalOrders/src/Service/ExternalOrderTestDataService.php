<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ExternalOrderTestDataService
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly FakeExternalOrderProvider $fakeExternalOrderProvider,
        private readonly ExternalToOrderMapper $externalToOrderMapper,
        private readonly Connection $connection,
    ) {
    }

    public function hasSeededFakeOrders(Context $context): bool
    {
        $hasMetadata = $this->connection->fetchOne(
            'SELECT 1 FROM external_order_data WHERE external_id LIKE :demoPrefix OR external_id LIKE :legacyPrefix LIMIT 1',
            [
                'demoPrefix' => FakeExternalOrderProvider::DEMO_ORDER_PREFIX . '%',
                'legacyPrefix' => 'fake-%',
            ]
        );

        if ($hasMetadata !== false) {
            return true;
        }

        $demoExternalOrderIds = $this->getDemoExternalOrderIds();
        if ($demoExternalOrderIds === []) {
            return false;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customFields.external_order_id', $demoExternalOrderIds));
        $criteria->setLimit(1);

        return $this->orderRepository->search($criteria, $context)->getTotal() > 0;
    }

    public function removeSeededFakeOrders(Context $context): int
    {
        $orderIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(order_id)) FROM external_order_data WHERE external_id LIKE :demoPrefix OR external_id LIKE :legacyPrefix',
            [
                'demoPrefix' => FakeExternalOrderProvider::DEMO_ORDER_PREFIX . '%',
                'legacyPrefix' => 'fake-%',
            ]
        );

        $this->connection->executeStatement(
            'DELETE FROM external_order_data WHERE external_id LIKE :demoPrefix OR external_id LIKE :legacyPrefix',
            [
                'demoPrefix' => FakeExternalOrderProvider::DEMO_ORDER_PREFIX . '%',
                'legacyPrefix' => 'fake-%',
            ]
        );

        $orderIds = array_values(array_filter($orderIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($orderIds === []) {
            $orderIds = $this->fetchLegacyDemoOrderIds($context);
        }

        if ($orderIds === []) {
            return 0;
        }

        $deletePayload = array_map(static fn (string $id): array => ['id' => $id], $orderIds);
        $this->orderRepository->delete($deletePayload, $context);

        return count($deletePayload);
    }

    /**
     * @return array<int, string>
     */
    private function fetchLegacyDemoOrderIds(Context $context): array
    {
        $demoExternalOrderIds = $this->getDemoExternalOrderIds();
        if ($demoExternalOrderIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customFields.external_order_id', $demoExternalOrderIds));
        $criteria->setLimit(5000);

        $result = $this->orderRepository->search($criteria, $context);
        $orderIds = [];

        foreach ($result->getEntities() as $entity) {
            $orderIds[] = $entity->getId();
        }

        return $orderIds;
    }

    public function seedFakeOrdersOnce(Context $context): int
    {
        $payloads = $this->fakeExternalOrderProvider->getSeedPayloads();
        if ($payloads === []) {
            return 0;
        }

        $externalIds = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId !== null) {
                $externalIds[] = $externalId;
            }
        }

        $externalIds = array_values(array_unique($externalIds));
        if ($externalIds === []) {
            return 0;
        }

        $existingIds = $this->fetchExistingIds($externalIds, $context);
        $orderUpsertPayload = [];
        $metadataPayload = [];

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId === null || isset($existingIds[$externalId])) {
                continue;
            }

            $channel = (string) ($payload['channel'] ?? 'unknown');
            $mappedOrderPayload = $this->externalToOrderMapper->mapToOrderPayload($payload, $channel, $externalId);
            $orderUpsertPayload[] = $mappedOrderPayload;

            $metadataPayload[] = [
                'id' => Uuid::fromStringToHex('external-order-data-' . $externalId),
                'order_id' => hex2bin((string) $mappedOrderPayload['id']),
                'external_id' => $externalId,
                'channel' => $channel,
                'raw_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'source_status' => isset($payload['status']) ? (string) $payload['status'] : null,
                'source_created_at' => $this->normalizeDateTime($payload['datePurchased'] ?? $payload['date'] ?? null),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ];
        }

        if ($orderUpsertPayload === []) {
            return 0;
        }

        $this->orderRepository->upsert($orderUpsertPayload, $context);
        $this->upsertExternalOrderMetadata($metadataPayload);

        return count($orderUpsertPayload);
    }

    /**
     * @return array<int, string>
     */
    public function getDemoExternalOrderIds(): array
    {
        $payloads = $this->fakeExternalOrderProvider->getSeedPayloads();
        $externalIds = [];

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $externalId = $this->resolveExternalId($payload);
            if ($externalId === null) {
                continue;
            }

            $externalIds[] = $externalId;
        }

        return array_values(array_unique($externalIds));
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function fetchExistingIds(array $externalIds, Context $context): array
    {
        $mapping = [];
        $chunkSize = 500;

        foreach (array_chunk($externalIds, $chunkSize) as $chunk) {
            $criteria = new Criteria();
            $criteria->setLimit(count($chunk));
            $criteria->addFilter(new EqualsAnyFilter('customFields.external_order_id', $chunk));
            $result = $this->orderRepository->search($criteria, $context);

            foreach ($result->getEntities() as $entity) {
                $customFields = $entity->getCustomFields();
                if (!is_array($customFields)) {
                    continue;
                }

                $existingExternalId = $customFields['external_order_id'] ?? null;
                if (!is_string($existingExternalId) || $existingExternalId === '') {
                    continue;
                }

                $mapping[$existingExternalId] = $entity->getId();
            }
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

    /**
     * @param array<int, array<string, mixed>> $metadataPayload
     */
    private function upsertExternalOrderMetadata(array $metadataPayload): void
    {
        foreach ($metadataPayload as $metadataRow) {
            try {
                $this->connection->insert('external_order_data', $metadataRow);
            } catch (UniqueConstraintViolationException) {
                $this->connection->update('external_order_data', [
                    'order_id' => $metadataRow['order_id'],
                    'channel' => $metadataRow['channel'],
                    'raw_payload' => $metadataRow['raw_payload'],
                    'source_status' => $metadataRow['source_status'],
                    'source_created_at' => $metadataRow['source_created_at'],
                    'updated_at' => $metadataRow['updated_at'],
                ], ['external_id' => $metadataRow['external_id']]);
            }
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v');
        } catch (\Throwable) {
            return null;
        }
    }
}
