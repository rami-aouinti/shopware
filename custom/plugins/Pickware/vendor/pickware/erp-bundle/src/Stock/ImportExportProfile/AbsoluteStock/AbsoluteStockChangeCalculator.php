<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\AbsoluteStock;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockChangeCalculator;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportLocation;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportLocationType;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AbsoluteStockChangeCalculator implements StockChangeCalculator
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function calculateStockChange(
        array $normalizedRow,
        string $productId,
        StockImportLocation $stockImportLocation,
        JsonApiErrors $errors,
        Context $context,
    ): int {
        if (!isset($normalizedRow['stock'])) {
            return 0;
        }
        $targetStock = $normalizedRow['stock'];

        switch ($stockImportLocation->getStockImportLocationType()) {
            case StockImportLocationType::StockArea:
                $stockArea = $stockImportLocation->getStockArea();
                if (count($stockArea->getWarehouseIds()) === 0) {
                    /** @var PickwareProductEntity $product */
                    $product = $this->entityManager->findOneBy(PickwareProductDefinition::class, ['productId' => $productId], $context);

                    return $targetStock - $product->getPhysicalStock();
                }

                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('warehouseId', $stockArea->getWarehouseIds()[0]));
                $criteria->addFilter(new EqualsFilter('productId', $productId));

                /** @var WarehouseStockEntity $warehouseStock */
                $warehouseStock = $this->entityManager->findOneBy(
                    WarehouseStockDefinition::class,
                    $criteria,
                    $context,
                );

                // The warehouseStock is only null if no stock is recorded for this product, so it defaults to 0
                return $targetStock - ($warehouseStock !== null ? $warehouseStock->getQuantity() : 0);
            case StockImportLocationType::StockLocationInWarehouse:
                $stockLocationReference = $stockImportLocation->getStockLocationReference();
                $locationType = $stockLocationReference->getLocationTypeTechnicalName();
                $criteria = new Criteria();
                if ($locationType === LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE) {
                    $criteria->addFilter(new EqualsFilter('warehouseId', $stockLocationReference->getPrimaryKey()));
                } else {
                    $criteria->addFilter(new EqualsFilter('binLocationId', $stockLocationReference->getPrimaryKey()));
                }
                $criteria->addFilter(new EqualsFilter('productId', $productId));

                /** @var StockEntity $stock */
                $stock = $this->entityManager->findOneBy(
                    StockDefinition::class,
                    $criteria,
                    $context,
                );

                // The stock is only null if no stock is recorded for this product, so it defaults to 0
                return $targetStock - ($stock !== null ? $stock->getQuantity() : 0);
        }
    }
}
