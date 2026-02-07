<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\Context;

class StockImportLocationFinder
{
    public const BIN_LOCATION_CODE_UNKNOWN = 'unknown';

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array{
     *     binLocationCode?: string,
     *     warehouseCode?: string,
     *     warehouseName?: string,
     * } $stockImportLocationDescriptor
     */
    public function findStockImportLocation(array $stockImportLocationDescriptor, Context $context): ?StockImportLocation
    {
        $binLocationGiven = isset($stockImportLocationDescriptor['binLocationCode']) && $stockImportLocationDescriptor['binLocationCode'] !== '';
        $warehouseGiven = (isset($stockImportLocationDescriptor['warehouseCode']) && $stockImportLocationDescriptor['warehouseCode'] !== '')
            || (isset($stockImportLocationDescriptor['warehouseName']) && $stockImportLocationDescriptor['warehouseName'] !== '');

        if (!$binLocationGiven && !$warehouseGiven) {
            return StockImportLocation::stockArea(StockArea::everywhere());
        }

        if ($binLocationGiven && $warehouseGiven) {
            if ($stockImportLocationDescriptor['binLocationCode'] === self::BIN_LOCATION_CODE_UNKNOWN) {
                /** @var WarehouseEntity $warehouse */
                $warehouse = $this->entityManager->findOneBy(
                    WarehouseDefinition::class,
                    array_filter(
                        [
                            'code' => $stockImportLocationDescriptor['warehouseCode'] ?? null,
                            'name' => $stockImportLocationDescriptor['warehouseName'] ?? null,
                        ],
                        fn($value) => $value !== null,
                    ),
                    $context,
                );
                if (!$warehouse) {
                    return null;
                }

                return StockImportLocation::stockLocationReference(StockLocationReference::warehouse($warehouse->getId()));
            }

            /** @var BinLocationEntity $binLocation */
            $binLocation = $this->entityManager->findOneBy(
                BinLocationDefinition::class,
                array_filter(
                    [
                        'code' => $stockImportLocationDescriptor['binLocationCode'],
                        'warehouse.code' => $stockImportLocationDescriptor['warehouseCode'] ?? null,
                        'warehouse.name' => $stockImportLocationDescriptor['warehouseName'] ?? null,
                    ],
                    fn($value) => $value !== null,
                ),
                $context,
            );
            if (!$binLocation) {
                return null;
            }

            return StockImportLocation::stockLocationReference(StockLocationReference::binLocation($binLocation->getId()));
        }

        if ($warehouseGiven && !$binLocationGiven) {
            /** @var WarehouseEntity $warehouse */
            $warehouse = $this->entityManager->findOneBy(
                WarehouseDefinition::class,
                array_filter(
                    [
                        'code' => $stockImportLocationDescriptor['warehouseCode'] ?? null,
                        'name' => $stockImportLocationDescriptor['warehouseName'] ?? null,
                    ],
                    fn($value) => $value !== null,
                ),
                $context,
            );
            if (!$warehouse) {
                return null;
            }

            return StockImportLocation::stockArea(StockArea::warehouse($warehouse->getId()));
        }

        throw new InvalidArgumentException(
            'Key "binLocationCode" can only be provided together with either "warehouseCode" or "warehouseName".',
        );
    }
}
