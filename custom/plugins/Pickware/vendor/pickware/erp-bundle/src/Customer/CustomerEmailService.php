<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Customer;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class CustomerEmailService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * Returns the email address to use for sending invoice related documents to a customer.
     * If the customer has an alternative email address set, that address is returned.
     * Otherwise, the customer's regular email address is returned.
     */
    public function getEmailAddressForInvoiceDocuments(string $orderId, Context $context): string
    {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'orderCustomer.customer',
            ],
        );

        $customerEmailAddress = $order->getOrderCustomer()?->getEmail() ?? '';

        if ($order->getOrderCustomer()?->getCustomer() === null) {
            return $customerEmailAddress;
        }

        /** @var CustomerEntity $customer */
        $customer = $order->getOrderCustomer()->getCustomer();
        $customFields = $customer->getCustomFields() ?? [];

        /** @var string|null $alternativeInvoiceEmail */
        $alternativeInvoiceEmail = $customFields[AlternativeEmailCustomFieldSet::CUSTOM_FIELD_ALTERNATIVE_EMAIL] ?? null;

        if (is_string($alternativeInvoiceEmail) && $alternativeInvoiceEmail !== '') {
            return trim($alternativeInvoiceEmail);
        }

        return $customerEmailAddress;
    }
}
