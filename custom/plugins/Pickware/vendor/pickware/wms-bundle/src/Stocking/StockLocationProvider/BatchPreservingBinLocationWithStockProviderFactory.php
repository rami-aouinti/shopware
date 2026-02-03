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

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\AbstractBatchPreservingBinLocationProviderFactory;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This service is excluded from autowiring in the wms-bundle services.yaml, because the base class might not be
 * available with old ERP versions. It is conditionally loaded in the bundle base class.
 */
class BatchPreservingBinLocationWithStockProviderFactory extends AbstractBatchPreservingBinLocationProviderFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
        #[Autowire(service: 'pickware_wms.bin_location_property_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
    ) {
        parent::__construct($this->entityManager, $this->featureFlagService, $this->stockLocationSorter);
    }

    public function createBinLocationCriteria(ProductQuantityImmutableCollection $productQuantities): Criteria
    {
        return (new Criteria())
            ->addFilter(new EqualsAnyFilter('stocks.productId', $productQuantities->getProductIds()->asArray()))
            ->addAssociation('stocks');
    }

    public function checkBinLocationCanStockProduct(BinLocationEntity $binLocation, string $productId): bool
    {
        return $binLocation->getStockForProduct($productId) !== null;
    }
}
