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

use Pickware\PickwareErpStarter\Batch\BatchFeatureService;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\BatchPreservingDefaultBinLocationProviderFactory;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\DefaultBinLocationProviderFactory;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProvider;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This is a wrapper service around the DefaultBinLocationProviderFactory and BatchPreservingDefaultBinLocationProviderFactory.
 * Can be removed as soon as the minimum required ERP version guarantees that the batch preserving factory is available.
 */
class WmsDefaultBinLocationProviderFactory implements StockLocationProviderFactory
{
    public function __construct(
        #[Autowire(service: 'pickware_wms.default_bin_location_provider_factory')]
        private readonly DefaultBinLocationProviderFactory $defaultBinLocationProviderFactory,
        // Might be null with old ERP versions
        private readonly ?BatchFeatureService $batchFeatureService = null,
        #[Autowire(service: 'pickware_wms.batch_preserving_default_bin_location_provider_factory')]
        private readonly ?BatchPreservingDefaultBinLocationProviderFactory $batchPreservingDefaultBinLocationProviderFactory = null,
    ) {}

    public function makeStockLocationProvider(ProductQuantityImmutableCollection $productQuantities, StockArea $stockArea, Context $context): StockLocationProvider
    {
        if (
            $this->batchFeatureService?->isBatchManagementAvailable()
            && $this->batchPreservingDefaultBinLocationProviderFactory !== null
        ) {
            return $this->batchPreservingDefaultBinLocationProviderFactory->makeStockLocationProvider($productQuantities, $stockArea, $context);
        }

        return $this->defaultBinLocationProviderFactory->makeStockLocationProvider($productQuantities, $stockArea, $context);
    }
}
