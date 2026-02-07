<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Stocking\StockLocationProvider;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\ProductStockLocationMap;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Shopware\Core\Framework\Context;

class DisabledStockManagementLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly EntityManager $entityManager,
    ) {}

    public function makeStockLocationProvider(
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): ProductStockLocationMap {
        $unknownStockLocationReference = match ($stockArea->getStockAreaType()) {
            StockAreaType::Warehouse => StockLocationReference::warehouse($stockArea->getWarehouseId()),
            StockAreaType::Everywhere => StockLocationReference::warehouse($this->config->getDefaultWarehouseId()),
            default => throw new InvalidArgumentException('The stock area type  ' . $stockArea->getStockAreaType() . 'is not supported yet.'),
        };

        $productIdsWithDisabledStockManagement = $this->entityManager
            ->findBy(
                PickwareProductDefinition::class,
                [
                    'productId' => $productQuantities->getProductIds()->asArray(),
                    'isStockManagementDisabled' => true,
                ],
                $context,
            )
            ->map(fn(PickwareProductEntity $product) => $product->getProductId());

        return new ProductStockLocationMap(array_combine(
            $productIdsWithDisabledStockManagement,
            array_fill(0, count($productIdsWithDisabledStockManagement), $unknownStockLocationReference),
        ));
    }
}
