<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Stocking;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDestinationAssignmentSource;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\ProductOrthogonalStockingStrategy;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use RuntimeException;
use Shopware\Core\Framework\Context;

class GoodsReceiptStockDestinationAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductOrthogonalStockingStrategy $stockingStrategy,
    ) {}

    public function reassignGoodsReceiptStockDestinations(string $goodsReceiptId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['lineItems'],
        );

        /** @var ImmutableCollection<GoodsReceiptLineItemEntity> $lineItemsToReassign */
        $lineItemsToReassign = ImmutableCollection::create($goodsReceipt->getLineItems())->filter(
            fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null
                && $lineItem->getDestinationAssignmentSource() !== GoodsReceiptLineItemDestinationAssignmentSource::Manual,
        );

        $stockingRequest = new StockingRequest(
            $lineItemsToReassign
                ->map(self::getProductQuantityFromLineItem(...), ProductQuantityImmutableCollection::class)
                ->groupByProductId(),
            $goodsReceipt->getWarehouseId() ? StockArea::warehouse($goodsReceipt->getWarehouseId()) : StockArea::everywhere(),
        );
        $productQuantityLocations = $this->stockingStrategy->calculateStockingSolution($stockingRequest, $context);

        // Match the calculated stock destinations to the line items. Since we are using a product-orthogonal strategy,
        // the result is independent of the current stock in the warehouse. Thus, we don't need to keep track of the
        // quantities while reassigning, even if multiple line items share the same product or batch.
        $lineItemUpdatePayloads = [];
        foreach ($lineItemsToReassign as $lineItem) {
            $destination = $productQuantityLocations->first(
                fn(ProductQuantityLocation $item) => $item->getProductId() === $lineItem->getProductId()
                    && ($lineItem->getBatchId() === null || $item->getBatches()?->has($lineItem->getBatchId())),
            );
            if ($destination === null) {
                throw new RuntimeException(sprintf(
                    'The stocking strategy did not return a complete solution. Missing destination for product with ID %s and batch ID %s.',
                    $lineItem->getProductId(),
                    $lineItem->getBatchId() ?? 'null',
                ));
            }
            $lineItemUpdatePayloads[] = [
                'id' => $lineItem->getId(),
                'destinationAssignmentSource' => GoodsReceiptLineItemDestinationAssignmentSource::Automatic,
                'destinationBinLocationId' => match ($destination->getStockLocationReference()->getLocationTypeTechnicalName()) {
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION => $destination->getStockLocationReference()->getBinLocationId(),
                    LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE => null,
                    default => throw new RuntimeException(sprintf(
                        'Unexpected stock location destination type for goods receipt line item: %s',
                        $destination->getStockLocationReference()->getLocationTypeTechnicalName(),
                    )),
                },
            ];
        }

        $this->entityManager->update(
            GoodsReceiptLineItemDefinition::class,
            $lineItemUpdatePayloads,
            $context,
        );
    }

    private static function getProductQuantityFromLineItem(GoodsReceiptLineItemEntity $lineItem): ProductQuantity
    {
        return new ProductQuantity(
            $lineItem->getProductId(),
            $lineItem->getQuantity(),
            $lineItem->getBatchId() ? new ImmutableBatchQuantityMap([$lineItem->getBatchId() => $lineItem->getQuantity()]) : null,
        );
    }
}
