<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Logging\OrderLoggingService;
use Pickware\PickwareErpStarter\OrderShipping\Events\CompletelyShippedEvent;
use Pickware\PickwareErpStarter\OrderShipping\Events\PartiallyShippedEvent;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyService;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcess;
use Pickware\PickwareErpStarter\StockMovementProcess\StockMovementProcessService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderParcelService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly EntityManager $entityManager,
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PickingPropertyService $pickingPropertyService,
        private readonly OrderLoggingService $orderLoggingService,
        private readonly StockMovementProcessService $stockMovementProcessService,
    ) {}

    /**
     * @param list<ProductQuantityLocation>|ImmutableCollection<ProductQuantityLocation>|ProductQuantityLocationImmutableCollection $stockToShip Source where the stock is shipped from.
     * @param TrackingCode[] $trackingCodes
     * @param PickingPropertyRecord[] $pickingPropertyRecords
     */
    public function shipParcelForOrder(
        array|ImmutableCollection $stockToShip,
        string $orderId,
        array $trackingCodes,
        Context $context,
        array $pickingPropertyRecords = [],
        ?StockMovementProcess $stockMovementProcess = null,
    ): void {
        if (is_array($stockToShip)) {
            /** @deprecated From 5.0.0 the method will accept ImmutableCollection only as type for first param */
            $stockToShip = new ImmutableCollection($stockToShip);
        }

        $productsToShip = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrdersExcludingExternalFulfillments(
            orderIds: [$orderId],
            context: $context,
        )[$orderId];
        $quantitiesToShip = $stockToShip->map(
            fn(ProductQuantityLocation $stock) => new ProductQuantity($stock->getProductId(), $stock->getQuantity()),
            ProductQuantityImmutableCollection::class,
        );
        $leftOverQuantities = $productsToShip->subtract($quantitiesToShip);

        $overfulfilledQuantities = $leftOverQuantities->filter(fn(ProductQuantity $stock) => $stock->getQuantity() < 0);
        if ($overfulfilledQuantities->count() > 0) {
            throw new OrderOverfulfilledException(
                orderId: $orderId,
                overfulfilledQuantities: $overfulfilledQuantities->map(
                    fn(ProductQuantity $productQuantity) => $productQuantity->negate(),
                    returnType: ProductQuantityImmutableCollection::class,
                ),
            );
        }

        $stockMovements = $stockToShip->map(
            fn(ProductQuantityLocation $productQuantityLocation) => StockMovement::create(
                [
                    'productId' => $productQuantityLocation->getProductId(),
                    'source' => $productQuantityLocation->getStockLocationReference(),
                    'destination' => StockLocationReference::order($orderId),
                    'quantity' => $productQuantityLocation->getQuantity(),
                ],
            ),
        );

        $this->entityManager->runInTransactionWithRetry(
            function() use ($orderId, $stockMovements, $pickingPropertyRecords, $context, $stockMovementProcess): void {
                $this->stockMovementService->moveStock($stockMovements->asArray(), $context);

                if ($stockMovementProcess) {
                    $stockMovementProcess->setStockMovementIds($stockMovements->map(fn(StockMovement $stockMovement) => $stockMovement->getId())->asArray());
                    $this->stockMovementProcessService->create(ImmutableCollection::create([$stockMovementProcess]), $context);
                }

                $this->pickingPropertyService->createPickingPropertyRecordsForOrder(
                    $orderId,
                    $pickingPropertyRecords,
                    $context,
                );
            },
        );

        $this->eventDispatcher->dispatch(
            new ParcelShippedEvent(
                $stockToShip->asArray(),
                $orderId,
                $trackingCodes,
                $context,
            ),
        );
        $this->orderLoggingService->logOrderShipment($orderId);

        $this->triggerFlowForChangedOrderStock($orderId, $context);
    }

    public function triggerFlowForChangedOrderStock(string $orderId, Context $context): void
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'deliveries',
                'pickwareErpDestinationStockMovements.product.pickwareErpPickwareProduct',
            ],
        );

        /** @var StockMovementCollection $stockMovementsIntoOrder */
        $stockMovementsIntoOrder = $order->getExtension('pickwareErpDestinationStockMovements');
        $alreadyShippedProducts = ImmutableCollection::create($stockMovementsIntoOrder)
            ->map(fn(StockMovementEntity $stockMovement) => $stockMovement->getProduct())
            ->deduplicate();

        if ($alreadyShippedProducts->checkAllElementsSatisfy($this->isShippedAutomatically(...))) {
            return;
        }

        $unfulfilledProducts = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrdersIncludingExternalFulfillments(
            orderIds: [$orderId],
            context: $context,
        )[$orderId];

        $isPartialDelivery = !$unfulfilledProducts->isEmpty();
        if ($isPartialDelivery) {
            $this->eventDispatcher->dispatch(PartiallyShippedEvent::createFromOrder($context, $order));
        } else {
            $this->eventDispatcher->dispatch(CompletelyShippedEvent::createFromOrder($context, $order));
        }
    }

    private function isShippedAutomatically(ProductEntity $product): bool
    {
        return $product->getExtension('pickwareErpPickwareProduct')?->getShipAutomatically() ?? false;
    }
}
