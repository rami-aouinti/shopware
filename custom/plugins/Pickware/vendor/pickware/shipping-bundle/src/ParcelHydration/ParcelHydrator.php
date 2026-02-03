<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Parcel\Parcel;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class ParcelHydrator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
        private readonly ParcelItemHydrator $parcelItemHydrator,
    ) {}

    public function hydrateParcelFromOrder(
        string $orderId,
        ParcelHydrationConfiguration $config,
        Context $context,
    ): Parcel {
        // Consider inheritance when fetching products for inherited fields (e.g. name, weight)
        $orderContext = $this->contextFactory->deriveOrderContext($orderId, $context);
        $orderContext->setConsiderInheritance(true);
        /** @var OrderEntity $order */
        $order = $this->entityManager->findByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $orderContext,
            [
                'currency',
                'documents.documentType',
                'lineItems.product',
            ],
        );

        $parcel = new Parcel();
        $parcel->setCustomerReference($order->getOrderNumber());

        $parcelItems = $this->parcelItemHydrator->hydrateParcelItemsFromOrder($orderId, $config, $orderContext);
        $parcel->setItems($parcelItems);

        return $parcel;
    }
}
