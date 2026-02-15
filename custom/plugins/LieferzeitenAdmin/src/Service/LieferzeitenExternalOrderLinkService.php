<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class LieferzeitenExternalOrderLinkService
{
    private const ORDER_PREFIX = 'DEMO-';

    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, string> $expectedExternalOrderIds
     * @return array{linked:int, missingIds:array<int, string>}
     */
    public function linkDemoExternalOrders(array $expectedExternalOrderIds): array
    {
        $expectedExternalOrderIds = array_values(array_unique(array_filter(
            $expectedExternalOrderIds,
            static fn ($externalOrderId): bool => is_string($externalOrderId) && str_starts_with($externalOrderId, self::ORDER_PREFIX),
        )));

        if ($expectedExternalOrderIds === []) {
            return ['linked' => 0, 'missingIds' => []];
        }

        $persistedExternalOrderIds = $this->fetchPersistedDemoExternalOrderIds();

        if ($persistedExternalOrderIds === []) {
            $this->logger->warning('No persisted demo external orders found while linking Lieferzeiten demo data.', [
                'missingIds' => $expectedExternalOrderIds,
            ]);

            $this->deletePaketeByExternalOrderIds($expectedExternalOrderIds);

            return [
                'linked' => 0,
                'missingIds' => $expectedExternalOrderIds,
            ];
        }

        $linkedIds = array_values(array_intersect($expectedExternalOrderIds, $persistedExternalOrderIds));
        $missingIds = array_values(array_diff($expectedExternalOrderIds, $persistedExternalOrderIds));

        if ($linkedIds !== []) {
            $this->touchLinkedPakete($linkedIds);
        }

        if ($missingIds !== []) {
            $this->logger->warning('Some Lieferzeiten demo external order IDs were not found in persisted external orders.', [
                'missingIds' => $missingIds,
            ]);

            $this->deletePaketeByExternalOrderIds($missingIds);
        }

        return [
            'linked' => count($linkedIds),
            'missingIds' => $missingIds,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fetchPersistedDemoExternalOrderIds(): array
    {
        $ids = [];

        if ($this->tableExists('external_order_data')) {
            $ids = array_merge($ids, $this->connection->fetchFirstColumn(
                'SELECT external_id FROM `external_order_data` WHERE external_id LIKE :prefix',
                ['prefix' => self::ORDER_PREFIX . '%'],
            ));
        }

        if ($this->tableExists('external_order')) {
            $ids = array_merge($ids, $this->connection->fetchFirstColumn(
                'SELECT external_id FROM `external_order` WHERE external_id LIKE :prefix',
                ['prefix' => self::ORDER_PREFIX . '%'],
            ));
        }

        if ($this->tableExists('order')) {
            $ids = array_merge($ids, $this->connection->fetchFirstColumn(
                'SELECT JSON_UNQUOTE(JSON_EXTRACT(custom_fields, "$.external_order_id"))
                 FROM `order`
                 WHERE JSON_UNQUOTE(JSON_EXTRACT(custom_fields, "$.external_order_id")) LIKE :prefix',
                ['prefix' => self::ORDER_PREFIX . '%'],
            ));
        }

        $ids = array_filter($ids, static fn ($id): bool => is_string($id) && $id !== '');

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $externalOrderIds
     */
    private function touchLinkedPakete(array $externalOrderIds): void
    {
        $placeholders = implode(',', array_fill(0, count($externalOrderIds), '?'));

        $this->connection->executeStatement(
            sprintf(
                "UPDATE `lieferzeiten_paket`
                 SET last_changed_by = 'demo.external-order-linker',
                     last_changed_at = NOW(3)
                 WHERE external_order_id IN (%s)",
                $placeholders,
            ),
            $externalOrderIds,
        );
    }

    /**
     * @param array<int, string> $externalOrderIds
     */
    private function deletePaketeByExternalOrderIds(array $externalOrderIds): void
    {
        if ($externalOrderIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($externalOrderIds), '?'));
        $this->connection->executeStatement(
            sprintf('DELETE FROM `lieferzeiten_paket` WHERE external_order_id IN (%s)', $placeholders),
            $externalOrderIds,
        );
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName LIMIT 1',
            ['tableName' => $table],
        );

        $this->tableExistsCache[$table] = $exists !== false;

        return $this->tableExistsCache[$table];
    }
}

