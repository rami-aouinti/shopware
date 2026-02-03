<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WarehouseStockInitializer implements EventSubscriberInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'productWritten',
            WarehouseDefinition::ENTITY_WRITTEN_EVENT => 'warehouseWritten',
        ];
    }

    public function productWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $this->ensureProductWarehouseStockForProductsExist(
            $this->getNewlyCreatedEntityIds($entityWrittenEvent),
        );
    }

    public function warehouseWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $this->ensureProductWarehouseStocksExist(
            'warehouse.id',
            $this->getNewlyCreatedEntityIds($entityWrittenEvent),
        );
    }

    public function ensureProductWarehouseStockForProductsExist(array $productIds): void
    {
        $this->ensureProductWarehouseStocksExist('product.id', $productIds);
    }

    private function ensureProductWarehouseStocksExist(string $idFieldName, array $ids): void
    {
        if (count($ids) === 0) {
            return;
        }

        // This query is a potential deadlock candidate and this is why it is wrapped in a retryable transaction
        RetryableTransaction::retryable($this->db, fn() => $this->db->executeStatement(
            'INSERT INTO pickware_erp_warehouse_stock (
                id,
                product_id,
                product_version_id,
                quantity,
                warehouse_id,
                created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                product.id,
                product.version_id,
                0,
                warehouse.id,
                UTC_TIMESTAMP(3)
            FROM product
            CROSS JOIN pickware_erp_warehouse warehouse
            WHERE ' . $idFieldName . ' IN (:ids) AND product.version_id = :liveVersionId
            ON DUPLICATE KEY UPDATE pickware_erp_warehouse_stock.id = pickware_erp_warehouse_stock.id',
            [
                'ids' => array_map('hex2bin', $ids),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ],
        ));
    }

    public function ensureProductWarehouseStocksExistsForAllProducts(): void
    {
        RetryableTransaction::retryable($this->db, function(): void {
            $this->db->executeStatement(
                'INSERT INTO pickware_erp_warehouse_stock (
                id,
                product_id,
                product_version_id,
                quantity,
                warehouse_id,
                created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                product.id,
                product.version_id,
                0,
                warehouse.id,
                UTC_TIMESTAMP(3)
            FROM product
            CROSS JOIN pickware_erp_warehouse warehouse
            WHERE product.version_id = :liveVersionId
            ON DUPLICATE KEY UPDATE pickware_erp_warehouse_stock.id = pickware_erp_warehouse_stock.id',
                ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
            );
        });
    }

    private function getNewlyCreatedEntityIds(EntityWrittenEvent $entityWrittenEvent): array
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return [];
        }

        $ids = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            // $writeResult->getExistence() can be null, but we have no idea why and also not what this means.
            $existence = $writeResult->getExistence();
            if (
                ($existence === null && $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT)
                || ($existence !== null && !$existence->exists())
            ) {
                $ids[] = $writeResult->getPrimaryKey();
            }
        }

        return $ids;
    }
}
