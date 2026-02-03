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

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockCollection;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class WarehouseStockNotAvailableForSaleService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @return CountingMap<string>
     */
    public function getUnavailableStockByWarehouseOfProduct(string $productId, Context $context): CountingMap
    {
        /**
         * @var WarehouseStockCollection $warehouseStocks
         */
        $warehouseStocks = $this->entityManager->findBy(
            WarehouseStockDefinition::class,
            (new Criteria())->addFilter(
                new EqualsFilter('productId', $productId),
                new EqualsFilter('warehouse.isStockAvailableForSale', false),
                new RangeFilter('quantity', ['gt' => 0]),
            ),
            $context,
            ['warehouse'],
        );

        $quantityByWarehouseName = new CountingMap();
        foreach ($warehouseStocks as $warehouseStock) {
            $quantityByWarehouseName->set($warehouseStock->getWarehouse()->getName(), $warehouseStock->getQuantity());
        }

        return $quantityByWarehouseName;
    }
}
