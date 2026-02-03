<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use LogicException;
use Pickware\PickwareErpStarter\Batch\BatchQuantity;
use Pickware\PickwareErpStarter\Batch\BatchQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\BatchAwareStockLocationProvider;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProvider;
use Pickware\PickwareErpStarter\Stocking\StockLocationProvider\StockLocationProviderFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SingleStockLocationStockingStrategy implements StockingStrategy
{
    /**
     * @param StockLocationProviderFactory[] $stockLocationProviderFactories
     */
    public function __construct(
        private readonly array $stockLocationProviderFactories,
    ) {}

    public function calculateStockingSolution(
        StockingRequest $stockingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        /** @var StockLocationProvider[] $stockLocationProviders */
        $stockLocationProviders = [];
        foreach ($this->stockLocationProviderFactories as $factory) {
            $stockLocationProviders[] = $factory->makeStockLocationProvider(
                productQuantities: $stockingRequest->getProductQuantities(),
                stockArea: $stockingRequest->getStockArea(),
                context: $context,
            );
        }

        return $stockingRequest
            ->getProductQuantities()
            ->asBatchQuantities()
            ->map(
                fn(BatchQuantity $batchQuantity) => $this
                    ->getStockLocationForProductAndBatch($stockLocationProviders, $batchQuantity)
                    ->asProductQuantityLocation(),
                ProductQuantityLocationImmutableCollection::class,
            );
    }

    /**
     * @param StockLocationProvider[] $stockLocationProviders
     */
    private function getStockLocationForProductAndBatch(array $stockLocationProviders, BatchQuantity $batchQuantity): BatchQuantityLocation
    {
        foreach ($stockLocationProviders as $stockLocationProvider) {
            if ($batchQuantity->getBatchId() !== null && $stockLocationProvider instanceof BatchAwareStockLocationProvider) {
                $stockLocation = $stockLocationProvider->getNextStockLocationForProductAndBatch($batchQuantity->getProductId(), $batchQuantity->getBatchId());
            } else {
                $stockLocation = $stockLocationProvider->getNextStockLocationForProduct($batchQuantity->getProductId());
            }
            if ($stockLocation) {
                return new BatchQuantityLocation(
                    location: $stockLocation,
                    productId: $batchQuantity->getProductId(),
                    batchId: $batchQuantity->getBatchId(),
                    quantity: $batchQuantity->getQuantity(),
                );
            }
        }

        throw new LogicException(sprintf(
            'No stock location provider did return a stock location for product with ID %s and batch ID %s. ' .
            'This is a programming error. Please make sure that at least the last stock location provider always ' .
            'returns a stock location.',
            $batchQuantity->getProductId(),
            $batchQuantity->getBatchId() ?? 'null',
        ));
    }
}
