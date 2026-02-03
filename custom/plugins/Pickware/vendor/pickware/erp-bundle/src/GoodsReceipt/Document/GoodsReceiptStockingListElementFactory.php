<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Document;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDestinationAssignmentSource;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Stocking\GoodsReceiptStockDestinationAssignmentService;
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

class GoodsReceiptStockingListElementFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockingStrategy $stockingStrategy,
        private readonly GoodsReceiptStockDestinationAssignmentService $stockDestinationAssignmentService,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return GoodsReceiptStockingListElement[]
     */
    public function createGoodsReceiptStockingListElements(string $goodsReceiptId, Context $context): array
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'warehouse',
                'sourceStockMovements.destinationWarehouse',
                'sourceStockMovements.destinationBinLocation',
                'sourceStockMovements.product.options',
            ],
        );

        $stockMovementsToInternal = $goodsReceipt->getSourceStockMovements()->filter(
            fn(StockMovementEntity $stockMovement) => $stockMovement->getDestinationWarehouse() || $stockMovement->getDestinationBinLocation(),
        );
        if ($stockMovementsToInternal->count() > 0) {
            // If stock was already stocked _from_ the goods receipt, we display all stock movements _from_ the goods
            // receipt into an internal stock location on the goods receipt stocking list
            $goodsReceiptStockingListElements = $this->createGoodsReceiptStockingListElementsFromStockMovements($stockMovementsToInternal);
        } else {
            // Otherwise show the stocking solution (no stock movements yet) instead.
            if ($this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)) {
                $goodsReceiptStockingListElements = $this->createGoodsReceiptStockingListElementsFromStockDestinations($goodsReceiptId, $context);
            } else {
                $goodsReceiptStockingListElements = $this->createGoodsReceiptStockingListElementsFromStockingSolution($goodsReceiptId, $context);
            }
        }

        usort(
            $goodsReceiptStockingListElements,
            function(GoodsReceiptStockingListElement $goodsReceiptStockingListElementA, GoodsReceiptStockingListElement $goodsReceiptStockingListElementB): int {
                $binLocationCodeA = $goodsReceiptStockingListElementA->binLocation?->getCode() ?? '';
                $binLocationCodeB = $goodsReceiptStockingListElementB->binLocation?->getCode() ?? '';

                $binLocationComparison = strcasecmp($binLocationCodeA, $binLocationCodeB);
                if ($binLocationComparison === 0) {
                    $productNumberA = $goodsReceiptStockingListElementA->product->getProductNumber();
                    $productNumberB = $goodsReceiptStockingListElementB->product->getProductNumber();

                    return strcasecmp($productNumberA, $productNumberB);
                }

                return $binLocationComparison;
            },
        );

        $productNamesById = $this->productNameFormatterService->getFormattedProductNames(
            array_map(fn(GoodsReceiptStockingListElement $element) => $element->product->getId(), $goodsReceiptStockingListElements),
            [],
            $context,
        );
        foreach ($goodsReceiptStockingListElements as &$element) {
            $element->product->setName($productNamesById[$element->product->getId()]);
        }

        return $goodsReceiptStockingListElements;
    }

    /**
     * @return GoodsReceiptStockingListElement[]
     */
    private function createGoodsReceiptStockingListElementsFromStockMovements(
        StockMovementCollection $stockMovementsToInternal,
    ): array {
        return $stockMovementsToInternal->fmap(
            fn(StockMovementEntity $stockMovement) => new GoodsReceiptStockingListElement(
                $stockMovement->getProduct(),
                $stockMovement->getDestinationWarehouse(),
                $stockMovement->getDestinationBinLocation(),
                $stockMovement->getQuantity(),
            ),
        );
    }

    /**
     * @return GoodsReceiptStockingListElement[]
     */
    private function createGoodsReceiptStockingListElementsFromStockingSolution(
        string $goodsReceiptId,
        Context $context,
    ): array {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'warehouse',
                'lineItems.product.options',
                'sourceStockMovements.destinationWarehouse',
                'sourceStockMovements.destinationBinLocation',
            ],
        );

        $goodsReceiptProductLineItems = $goodsReceipt->getLineItems()->filter(
            fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null,
        );
        $stockingRequest = new StockingRequest(
            $goodsReceiptProductLineItems->fmap(
                fn(GoodsReceiptLineItemEntity $goodsReceiptLineItem) => new ProductQuantity(
                    $goodsReceiptLineItem->getProductId(),
                    $goodsReceiptLineItem->getQuantity(),
                ),
            ),
            $goodsReceipt->getWarehouseId(),
        );
        $productQuantityLocations = $this->stockingStrategy->calculateStockingSolution(
            $stockingRequest,
            $context,
        );
        $stockMovements = $productQuantityLocations->createStockMovementsWithSource(
            StockLocationReference::goodsReceipt($goodsReceiptId),
        );

        $products = new ProductCollection($goodsReceiptProductLineItems->fmap(
            fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProduct(),
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
            function(StockMovement $stockMovement) use ($goodsReceipt, $products, $binLocations): GoodsReceiptStockingListElement {
                $destinationBinLocation = null;
                if ($stockMovement->getDestination()->getLocationTypeTechnicalName() === LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION) {
                    $destinationBinLocation = $binLocations->get($stockMovement->getDestination()->getPrimaryKey());
                }

                return new GoodsReceiptStockingListElement(
                    $products->get($stockMovement->getProductId()),
                    $destinationBinLocation ? null : $goodsReceipt->getWarehouse(),
                    $destinationBinLocation,
                    $stockMovement->getQuantity(),
                );
            },
            $stockMovements,
        );
    }

    /**
     * @return GoodsReceiptStockingListElement[]
     */
    private function createGoodsReceiptStockingListElementsFromStockDestinations(
        string $goodsReceiptId,
        Context $context,
    ): array {
        $lineItemsWithoutDestination = $this->entityManager->count(
            GoodsReceiptLineItemDefinition::class,
            'id',
            [
                'goodsReceiptId' => $goodsReceiptId,
                'destinationAssignmentSource' => GoodsReceiptLineItemDestinationAssignmentSource::Unset,
            ],
            $context,
        );
        if ($lineItemsWithoutDestination > 0) {
            $this->stockDestinationAssignmentService->reassignGoodsReceiptStockDestinations($goodsReceiptId, $context);
        }

        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'warehouse',
                'lineItems.product.options',
                'lineItems.destinationBinLocation',
            ],
        );

        return $goodsReceipt->getLineItems()->fmap(function(GoodsReceiptLineItemEntity $lineItem) use ($goodsReceipt) {
            if (!$lineItem->getProductId()) {
                return null;
            }

            return new GoodsReceiptStockingListElement(
                $lineItem->getProduct(),
                $lineItem->getDestinationBinLocationId() === null ? $goodsReceipt->getWarehouse() : null,
                $lineItem->getDestinationBinLocation(),
                $lineItem->getQuantity(),
            );
        });
    }
}
