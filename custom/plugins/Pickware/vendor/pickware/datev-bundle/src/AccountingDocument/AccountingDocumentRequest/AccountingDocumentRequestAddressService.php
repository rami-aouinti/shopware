<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest;

use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class AccountingDocumentRequestAddressService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function getCountryIsoCodeForOrder(string $orderId, Context $context): ?string
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'deliveries.shippingOrderAddress.country',
                'billingAddress.country',
            ],
        );

        $primaryDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
        if ($primaryDelivery) {
            $address = $primaryDelivery->getShippingOrderAddress();
        } else {
            // The billing address will be used as a fallback when no delivery exists (e.g. an order with only digital products)
            $address = $order->getBillingAddress();
        }

        // The table definition for order deliveries does not define a foreign key for addresses, which allows
        // deliveries to load without an address (even though required in the entity definition). For more
        // information see: https://issues.shopware.com/issues/NEXT-29142
        return $address?->getCountry()->getIso();
    }
}
