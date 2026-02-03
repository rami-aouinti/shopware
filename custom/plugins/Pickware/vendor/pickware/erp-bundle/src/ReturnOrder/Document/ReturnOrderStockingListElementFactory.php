<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Document;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;

class ReturnOrderStockingListElementFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockingStrategy $stockingStrategy,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return ReturnOrderStockingListElement[]
     */
    public function createReturnOrderStockingListElements(string $returnOrderId, Context $context): array
    {
        if ($this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME)) {
            // Note: When the feature flag is removed, the whole code for generating a return order stocking list has
            // to be removed as well.
            throw new InvalidArgumentException(sprintf(
                'Creating a return order stocking list is not allowed when the feature flag "%s" is enabled.',
                GoodsReceiptForReturnOrderDevFeatureFlag::NAME,
            ));
        }

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            [
                'warehouse',
                'sourceStockMovements.destinationWarehouse',
                'sourceStockMovements.destinationBinLocation',
                'sourceStockMovements.product.options',
            ],
        );

        $stockMovementsToInternal = $returnOrder->getSourceStockMovements()->filter(
            fn(StockMovementEntity $stockMovement) => $stockMovement->getDestinationWarehouse() || $stockMovement->getDestinationBinLocation(),
        );
        if ($stockMovementsToInternal->count() > 0) {
            // If stock was already stocked _from_ the return order, we display all stock movements _from_ the return
            // order into an internal stock location on the return order stocking list
            $returnOrderStockingListElements = $this->createReturnOrderStockingListElementsFromStockMovements($stockMovementsToInternal);
        } else {
            // Otherwise show the stocking solution (no stock movements yet) instead.
            $returnOrderStockingListElements = $this->createReturnOrderStockingListElementsFromStockingSolution($returnOrderId, $context);
        }

        usort(
            $returnOrderStockingListElements,
            function(ReturnOrderStockingListElement $returnOrderStockingListElementA, ReturnOrderStockingListElement $returnOrderStockingListElementB): int {
                $binLocationCodeA = $returnOrderStockingListElementA->binLocation?->getCode() ?? '';
                $binLocationCodeB = $returnOrderStockingListElementB->binLocation?->getCode() ?? '';
                $binLocationComparison = strcasecmp($binLocationCodeA, $binLocationCodeB);
                if ($binLocationComparison === 0) {
                    $productNumberA = $returnOrderStockingListElementA->product->getProductNumber();
                    $productNumberB = $returnOrderStockingListElementB->product->getProductNumber();

                    return strcasecmp($productNumberA, $productNumberB);
                }

                return $binLocationComparison;
            },
        );

        $productNamesById = $this->productNameFormatterService->getFormattedProductNames(
            array_map(fn(ReturnOrderStockingListElement $element) => $element->product->getId(), $returnOrderStockingListElements),
            [],
            $context,
        );
        foreach ($returnOrderStockingListElements as &$element) {
            $element->product->setName($productNamesById[$element->product->getId()]);
        }

        return $returnOrderStockingListElements;
    }

    /**
     * @return ReturnOrderStockingListElement[]
     */
    private function createReturnOrderStockingListElementsFromStockMovements(
        StockMovementCollection $stockMovementsToInternal,
    ): array {
        return $stockMovementsToInternal->fmap(
            fn(StockMovementEntity $stockMovement) => new ReturnOrderStockingListElement(
                $stockMovement->getProduct(),
                $stockMovement->getDestinationWarehouse(),
                $stockMovement->getDestinationBinLocation(),
                $stockMovement->getQuantity(),
            ),
        );
    }

    /**
     * @return ReturnOrderStockingListElement[]
     */
    private function createReturnOrderStockingListElementsFromStockingSolution(
        string $returnOrderId,
        Context $context,
    ): array {
        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            [
                'warehouse',
                'lineItems.product.options',
                'sourceStockMovements.destinationWarehouse',
                'sourceStockMovements.destinationBinLocation',
            ],
        );

        $returnOrderProductLineItems = $returnOrder->getLineItems()->filter(
            fn(ReturnOrderLineItemEntity $lineItem) => $lineItem->getProductId() !== null,
        );
        $stockingRequest = new StockingRequest(
            $returnOrderProductLineItems->fmap(
                fn(ReturnOrderLineItemEntity $returnOrderLineItem) => new ProductQuantity(
                    $returnOrderLineItem->getProductId(),
                    $returnOrderLineItem->getQuantity(),
                ),
            ),
            $returnOrder->getWarehouseId(),
        );
        $productQuantityLocations = $this->stockingStrategy->calculateStockingSolution(
            $stockingRequest,
            $context,
        );
        $stockMovements = $productQuantityLocations->createStockMovementsWithSource(
            StockLocationReference::returnOrder($returnOrderId),
        );

        $products = new ProductCollection($returnOrderProductLineItems->fmap(
            fn(ReturnOrderLineItemEntity $lineItem) => $lineItem->getProduct(),
        ));
        $binLocations = $this->entityManager->findBy(
            BinLocationDefinition::class,
            [
                'id' => array_map(
                    fn(StockMovement $stockMovement) => $stockMovement->getDestination()->getPrimaryKey(),
                    array_filter(
                        $stockMovements,
                        fn(StockMovement $stockMovement) => $stockMovement->getDestination()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                    ),
                ),
            ],
            $context,
        );

        return array_map(
            function(StockMovement $stockMovement) use ($returnOrder, $products, $binLocations): ReturnOrderStockingListElement {
                $destinationBinLocation = null;
                if ($stockMovement->getDestination()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
                    $destinationBinLocation = $binLocations->get($stockMovement->getDestination()->getPrimaryKey());
                }

                return new ReturnOrderStockingListElement(
                    $products->get($stockMovement->getProductId()),
                    $destinationBinLocation ? null : $returnOrder->getWarehouse(),
                    $destinationBinLocation,
                    $stockMovement->getQuantity(),
                );
            },
            $stockMovements,
        );
    }
}
