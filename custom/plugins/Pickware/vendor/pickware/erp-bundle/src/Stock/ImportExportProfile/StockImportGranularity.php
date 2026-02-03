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

use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

enum StockImportGranularity: string
{
    case Product = 'product';
    case StockLocation = 'stock_location';
    case Warehouse = 'warehouse';

    /**
     * @return class-string<PickwareProductDefinition|ProductStockLocationConfigurationDefinition|ProductWarehouseConfigurationDefinition>
     */
    public function getReferencedEntityDefinitionClassName(): string
    {
        return match ($this) {
            self::Product => PickwareProductDefinition::class,
            self::StockLocation => ProductStockLocationConfigurationDefinition::class,
            self::Warehouse => ProductWarehouseConfigurationDefinition::class,
        };
    }

    public function getCriteriaFilterForReferencedEntity(StockImportLocation $stockImportLocation, string $productId): Filter
    {
        return match ($this) {
            self::Product => new EqualsFilter('productId', $productId),
            self::StockLocation => new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('productStockLocationMapping.productId', $productId),
                    new EqualsFilter(
                        'productStockLocationMapping.' . $stockImportLocation->getStockLocationReference()->getFilterForStockDefinition()->getField(),
                        $stockImportLocation->getStockLocationReference()->getFilterForStockDefinition()->getValue(),
                    ),
                ],
            ),
            self::Warehouse => new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('productId', $productId),
                    new EqualsFilter('warehouseId', $stockImportLocation->getStockArea()->getWarehouseId()),
                ],
            ),
        };
    }
}
