<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class LieferzeitenExternalOrderLinkService
{
    private const ORDER_PREFIX = 'DEMO-';
    private const SEED_MARKER_PREFIX = 'demo.seeder.run:';

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
     * @return array{linked:int, missingIds:array<int, string>, deletedCount:int, deletedMissingPackages:int, destructiveCleanup:bool}
     */
    public function linkDemoExternalOrders(
        array $expectedExternalOrderIds,
        ?string $seedRunId = null,
        ?string $expectedSourceMarker = null,
        bool $allowDestructiveCleanup = false,
    ): array
    {
        $expectedExternalOrderIds = array_values(array_unique(array_filter(
            $expectedExternalOrderIds,
            static fn ($externalOrderId): bool => is_string($externalOrderId) && str_starts_with($externalOrderId, self::ORDER_PREFIX),
        )));

        if ($expectedExternalOrderIds === []) {
            return ['linked' => 0, 'missingIds' => [], 'deletedCount' => 0, 'deletedMissingPackages' => 0, 'destructiveCleanup' => false];
        }

        $destructiveCleanup = $this->canUseDestructiveCleanup($seedRunId, $expectedSourceMarker, $allowDestructiveCleanup);
        $cleanupMarker = $destructiveCleanup ? $expectedSourceMarker : null;

        $persistedExternalOrderIds = $this->fetchPersistedDemoExternalOrderIds();

        if ($persistedExternalOrderIds === []) {
            $this->logger->warning('No persisted demo external orders found while linking Lieferzeiten demo data.', [
                'missingIds' => $expectedExternalOrderIds,
                'destructiveCleanup' => $destructiveCleanup,
                'seedRunId' => $seedRunId,
                'expectedSourceMarker' => $expectedSourceMarker,
            ]);

            $deletedMissingPackages = $this->cleanupMissingPakete($expectedExternalOrderIds, $cleanupMarker);

            return [
                'linked' => 0,
                'missingIds' => $expectedExternalOrderIds,
                'deletedCount' => $deletedMissingPackages,
                'deletedMissingPackages' => $deletedMissingPackages,
                'destructiveCleanup' => $destructiveCleanup,
            ];
        }

        $linkedIds = array_values(array_intersect($expectedExternalOrderIds, $persistedExternalOrderIds));
        $missingIds = array_values(array_diff($expectedExternalOrderIds, $persistedExternalOrderIds));

        if ($linkedIds !== []) {
            $this->touchLinkedPakete($linkedIds);
        }

        $deletedMissingPackages = 0;

        if ($missingIds !== []) {
            $this->logger->warning('Some Lieferzeiten demo external order IDs were not found in persisted external orders.', [
                'missingIds' => $missingIds,
                'destructiveCleanup' => $destructiveCleanup,
                'seedRunId' => $seedRunId,
                'expectedSourceMarker' => $expectedSourceMarker,
            ]);

            $deletedMissingPackages = $this->cleanupMissingPakete($missingIds, $cleanupMarker);
        }

        return [
            'linked' => count($linkedIds),
            'missingIds' => $missingIds,
            'deletedCount' => $deletedMissingPackages,
            'deletedMissingPackages' => $deletedMissingPackages,
            'destructiveCleanup' => $destructiveCleanup,
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
    private function cleanupMissingPakete(array $externalOrderIds, ?string $cleanupMarker): int
    {
        if ($externalOrderIds === []) {
            return 0;
        }

        if ($cleanupMarker === null) {
            $this->logger->warning('Missing Lieferzeiten demo external order IDs detected. Destructive cleanup is disabled by default; no pakete were deleted.', [
                'missingIds' => $externalOrderIds,
            ]);

            return 0;
        }

        $deletedRows = $this->deletePaketeByExternalOrderIdsAndMarker($externalOrderIds, $cleanupMarker);

        if ($deletedRows === 0) {
            $this->logger->warning('No Lieferzeiten pakete deleted for missing external order IDs because no rows matched the current seed marker.', [
                'missingIds' => $externalOrderIds,
                'cleanupMarker' => $cleanupMarker,
            ]);
        }

        return $deletedRows;
    }

    /**
     * @param array<int, string> $externalOrderIds
     */
    private function deletePaketeByExternalOrderIdsAndMarker(array $externalOrderIds, string $cleanupMarker): int
    {
        $placeholders = implode(',', array_fill(0, count($externalOrderIds), '?'));
        $params = [...$externalOrderIds, $cleanupMarker];

        return $this->connection->executeStatement(
            sprintf('DELETE FROM `lieferzeiten_paket` WHERE external_order_id IN (%s) AND last_changed_by = ?', $placeholders),
            $params,
        );
    }

    private function canUseDestructiveCleanup(?string $seedRunId, ?string $expectedSourceMarker, bool $allowDestructiveCleanup): bool
    {
        if ($allowDestructiveCleanup !== true) {
            return false;
        }

        if (!is_string($seedRunId) || $seedRunId === '' || !is_string($expectedSourceMarker) || $expectedSourceMarker === '') {
            return false;
        }

        return $expectedSourceMarker === self::SEED_MARKER_PREFIX . $seedRunId;
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
