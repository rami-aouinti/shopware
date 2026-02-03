<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\Picking\ProductsToShipCalculator;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryLineItemDefinition;
use Shopware\Core\Framework\Context;

class DeliveryLineItemCalculator
{
    /**
     * @param ProductsToShipCalculator|null $productToShipCalculatorToShipCalculator Maintained for backwards compatibility with ERP, will be removed after WMS minimum requirement of ERP-4.4.0
     * @param OrderQuantitiesToShipCalculator|null $orderQuantitiesToShipCalculator Marked optional to maintain backwards compatibility with ERP, will be non-optional after WMS minimum requirement of ERP-4.4.0
     */
    public function __construct(
        readonly private EntityManager $entityManager,
        readonly private ?ProductsToShipCalculator $productToShipCalculatorToShipCalculator = null,
        readonly private ?OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator = null,
    ) {}

    /**
     * @param string[] $deliveryIds
     */
    public function recalculateDeliveryLineItems(array $deliveryIds, Context $context): void
    {
        /** @var DeliveryCollection $deliveries */
        $deliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            ['id' => $deliveryIds],
            $context,
        );

        $this->entityManager->deleteByCriteria(
            DeliveryLineItemDefinition::class,
            ['deliveryId' => $deliveryIds],
            $context,
        );

        // Old calculation logic maintained for backwards compatibility with ERP
        // Can be removed after WMS minimum requirement of ERP-4.4.0
        if ($this->orderQuantitiesToShipCalculator === null) {
            $productsToShipByOrderId = $this->productToShipCalculatorToShipCalculator->calculateProductsToShipForOrders(
                orderIds: EntityCollectionExtension::getField($deliveries, 'orderId'),
                context: $context,
            );
        } else {
            $productsToShipByOrderId = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrders(
                orderIds: EntityCollectionExtension::getField($deliveries, 'orderId'),
                context: $context,
            );
        }

        $payloads = [];
        foreach ($deliveries as $delivery) {
            $productsToShip = $productsToShipByOrderId[$delivery->getOrderId()];
            $payloads[] = $productsToShip
                ->map(
                    fn(ProductQuantity $productQuantity) => [
                        'deliveryId' => $delivery->getId(),
                        'productId' => $productQuantity->getProductId(),
                        'quantity' => $productQuantity->getQuantity(),
                    ],
                )
                ->asArray();
        }

        $payload = array_merge(...$payloads);
        $this->entityManager->create(DeliveryLineItemDefinition::class, $payload, $context);
    }
}
