<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigEntity;
use Pickware\ShippingBundle\Privacy\EmailTransferAgreement\EmailTransferAgreement;
use Pickware\ShippingBundle\Privacy\EmailTransferAgreement\EmailTransferAgreementCustomField;
use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class PrivacyService
{
    public const DATA_REMOVED_CODE__EMAIL_TRANSFER_DISABLED = 'EMAIL_TRANSFER_DISABLED';
    public const DATA_REMOVED_CODE__EMAIL_TRANSFER_NOT_ALLOWED = 'EMAIL_TRANSFER_NOT_ALLOWED';
    public const DATA_REMOVED_CODE__PHONE_TRANSFER_DISABLED = 'PHONE_TRANSFER_DISABLED';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function removePersonalDataFromOrderAddress(
        Address $address,
        string $orderId,
        Context $context,
    ): RemovedFieldTree {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getOneBy(
            OrderDefinition::class,
            ['id' => $orderId],
            $context,
            ['deliveries.shippingMethod.pickwareShippingShippingMethodConfig'],
        );

        $orderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());
        if ($orderDelivery === null || $orderDelivery->getShippingMethod() === null) {
            return new RemovedFieldTree();
        }

        /** @var ?ShippingMethodConfigEntity $shippingMethodConfig */
        $shippingMethodConfig = $orderDelivery->getShippingMethod()->getExtension('pickwareShippingShippingMethodConfig');
        if ($shippingMethodConfig === null) {
            return new RemovedFieldTree();
        }

        $removedFields = new RemovedFieldTree();

        if (!$shippingMethodConfig->getPrivacyConfiguration()->isPhoneTransferAllowed()) {
            $address->setPhone('');
            $removedFields->addRemovedField(
                RemovedFieldNode::fromReason('phone', self::DATA_REMOVED_CODE__PHONE_TRANSFER_DISABLED),
            );
        }

        match ($shippingMethodConfig->getPrivacyConfiguration()->getEmailTransferPolicy()) {
            DataTransferPolicy::Always => null,
            DataTransferPolicy::Never => $this->removeEmailForSetting($address, $removedFields),
            DataTransferPolicy::AskCustomer => $this->removeEmailForOrder($address, $order, $removedFields),
        };

        return $removedFields;
    }

    private function removeEmailForSetting(Address $address, RemovedFieldTree $removedFields): void
    {
        $address->setEmail('');
        $removedFields->addRemovedField(
            RemovedFieldNode::fromReason('email', self::DATA_REMOVED_CODE__EMAIL_TRANSFER_DISABLED),
        );
    }

    private function removeEmailForOrder(Address $address, OrderEntity $order, RemovedFieldTree $removedFields): void
    {
        // If the feature flag was disabled but the transfer policy is set to ask_customer, we allow the transfer.
        if (!$this->featureFlagService->isActive('pickware-shipping-bundle.feature.data-transfer-ask-customer-policy')) {
            return;
        }

        $customFields = $order->getCustomFields();
        if ($customFields === null || !array_key_exists(EmailTransferAgreementCustomField::TECHNICAL_NAME, $customFields)) {
            $address->setEmail('');
            $removedFields->addRemovedField(
                RemovedFieldNode::fromReason('email', self::DATA_REMOVED_CODE__EMAIL_TRANSFER_NOT_ALLOWED),
            );

            return;
        }

        $dataTransferAgreement = EmailTransferAgreement::fromCustomFieldSet($customFields);
        if ($dataTransferAgreement->allowEmailTransfer) {
            return;
        }

        $address->setEmail('');
        $removedFields->addRemovedField(
            RemovedFieldNode::fromReason('email', self::DATA_REMOVED_CODE__EMAIL_TRANSFER_NOT_ALLOWED),
        );
    }
}
