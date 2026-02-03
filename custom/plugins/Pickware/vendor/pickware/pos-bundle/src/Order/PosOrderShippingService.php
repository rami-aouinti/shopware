<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Order;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderShipping\NotEnoughStockException;
use Pickware\PickwareErpStarter\OrderShipping\OrderShippingService;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareWms\Delivery\DeliveryService;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class PosOrderShippingService
{
    private OrderShippingService $orderShippingService;
    private StockMovementService $stockMovementService;
    private EntityManager $entityManager;
    private ?DeliveryService $deliveryService;

    public function __construct(
        OrderShippingService $orderShippingService,
        StockMovementService $stockMovementService,
        EntityManager $entityManager,
        ?DeliveryService $deliveryService,
    ) {
        $this->orderShippingService = $orderShippingService;
        $this->stockMovementService = $stockMovementService;
        $this->entityManager = $entityManager;
        $this->deliveryService = $deliveryService;
    }

    /**
     * Moves stock from the warehouse into the order by using the default picking strategy. This method will not fail if
     * there is not enough stock available
     *
     * If there is not enough stock in the warehouse the missing stock is automatically booked in before the movement.
     *
     * @return ProductQuantity[] Amount of stock that has been added if the origin stock was not sufficient
     */
    public function forceShipOrderCompletely(
        string $orderId,
        string $warehouseId,
        Context $context,
    ): array {
        if ($this->deliveryService) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderId', $orderId));
            $criteria->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('state.technicalName', DeliveryStateMachine::STATE_CANCELLED)],
            ));

            /** @var DeliveryEntity $delivery */
            $delivery = $this->entityManager->findOneBy(
                DeliveryDefinition::class,
                $criteria,
                $context,
                ['state'],
            );

            if ($delivery) {
                if ($delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_PICKED) {
                    $this->deliveryService->skipDocumentCreation($delivery->getId(), $context);
                }
                $this->deliveryService->ship($delivery->getId(), $context);

                return [];
            }
        }

        $stockShortage = [];
        try {
            $this->orderShippingService->shipOrderCompletely($orderId, $warehouseId, $context);
        } catch (NotEnoughStockException $exception) {
            $stockShortage = $exception->getStockShortage();
            $stockCorrections = $this->getStockCorrections($warehouseId, $stockShortage, $context);

            $source = $context->getSource();
            $userId = ($source instanceof AdminApiSource) ? $source->getUserId() : null;
            $stockMovements = array_map(fn(ProductQuantity $shortage) => StockMovement::create([
                'productId' => $shortage->getProductId(),
                'quantity' => $shortage->getQuantity() + ($stockCorrections[$shortage->getProductId()] ?? 0),
                'source' => StockLocationReference::unknown(),
                'destination' => StockLocationReference::warehouse($warehouseId),
                'comment' => 'Automatic stock booking of goods sold at Pickware POS, which had insufficient ' .
                    'stock at the time of sale.',
                'userId' => $userId,
            ]), $exception->getStockShortage());
            $this->stockMovementService->moveStock($stockMovements, $context);
            $this->orderShippingService->shipOrderCompletely($orderId, $warehouseId, $context);
        }

        return $stockShortage;
    }

    /**
     * Get the amount of stock necessary to set quantity of stock on the warehouse (unknown location) to at least 0.
     *
     * See issue: https://github.com/pickware/shopware-plugins/issues/2265
     *
     * Why we need this:
     *
     * TLDR: We restock the warehouse (location unknown) until we can fulfill the requirement that is physically
     * present (and therefore must be true) at the POS.
     *
     * The picking strategy always fails when the sum of stock on stock locations with positive stock is not
     * enough to pick a product. (Stock locations with negative stock are ignored.)
     * The picking strategy tells us how much stock is missing to pick that product completely. So this quantity must
     * be added to an already non-negative bin location for the picking strategy to succeed.
     *
     * What can we assume for the POS:
     * For example, if the customer puts 6 pieces of a product on the counter and wants to buy them, but the system
     * only knows about 4 pieces in positive stock locations, we can conclude: Somewhere in the warehouse the customer
     * has found 2 more pieces of that product which the system does not know of.
     *
     * But "Somewhere in the warehouse" means the warehouse (unknown location) itself.
     * So in other words: In the warehouse (on unknown location) there are either actually exactly 2 (if it is
     * negative), or 2 more than known so far.
     *
     * How the recovery works:
     *
     * If the picking fails, we first correct the warehouse (unknown location) to have a stock of at least 0 (this is
     * what this method calculates) and then add the actual "stock-shortage".
     */
    private function getStockCorrections(
        string $warehouseId,
        array $stockShortage,
        Context $context,
    ): array {
        // Get stocks on unknown bin locations with negative stocks
        $locationFilters = array_map(
            fn(ProductQuantity $shortage) => new MultiFilter(MultiFilter::CONNECTION_AND, [
                StockLocationReference::warehouse($warehouseId)->getFilterForStockDefinition(),
                new EqualsFilter('productId', $shortage->getProductId()),
            ]),
            $stockShortage,
        );
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new RangeFilter('quantity', [RangeFilter::LT => 0]),
                new MultiFilter(MultiFilter::CONNECTION_OR, $locationFilters),
            ]),
        );

        // Return an array productId => quantity, where quantity is the quantity of stock necessary for the product to
        // be put on the unknown stock location to set the stock there to exactly 0
        /** @var StockCollection $negativeStocksOnUnknownBinLocation */
        $negativeStocksOnUnknownBinLocation = $this->entityManager->findBy(
            StockDefinition::class,
            $criteria,
            $context,
        );
        $stockCorrections = [];
        foreach ($negativeStocksOnUnknownBinLocation as $stock) {
            $stockCorrections[$stock->getProductId()] = -1 * $stock->getQuantity();
        }

        return $stockCorrections;
    }
}
