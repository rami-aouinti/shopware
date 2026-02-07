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
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigDefinition;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigEntity;
use Pickware\ShippingBundle\Privacy\EmailTransferAgreement\EmailTransferAgreement;
use Pickware\ShippingBundle\Privacy\EmailTransferAgreement\EmailTransferAgreementCustomField;
use Pickware\ShippingBundle\SalesChannelContext\Model\SalesChannelApiContextDefinition;
use Pickware\ShippingBundle\SalesChannelContext\Model\SalesChannelApiContextEntity;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfirmDataTransferInStorefrontSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_PAYLOAD_KEY = 'allow_email_transfer';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            CartConvertedEvent::class => 'onCartConverted',
        ];
    }

    /**
     * Loads the necessary config values for the frontend to render the checkbox.
     */
    public function onCheckoutConfirmPageLoaded(PageLoadedEvent $event): void
    {
        /** @var ?ShippingMethodConfigEntity $shippingMethodConfiguration */
        $shippingMethodConfiguration = $this->entityManager->findOneBy(
            ShippingMethodConfigDefinition::class,
            [
                'shippingMethodId' => $event->getSalesChannelContext()->getShippingMethod()->getId(),
            ],
            $event->getContext(),
        );
        if ($shippingMethodConfiguration === null) {
            return;
        }

        $dataTransferPageExtension = new PrivacyConfigPageExtension(
            $shippingMethodConfiguration->getPrivacyConfiguration()->getEmailTransferPolicy(),
        );

        $event->getPage()->addExtension(
            PrivacyConfigPageExtension::PAGE_EXTENSION_NAME,
            $dataTransferPageExtension,
        );
    }

    /**
     * Saves the data transfer agreement in the order.
     */
    public function onCartConverted(CartConvertedEvent $event): void
    {
        /** @var SalesChannelApiContextEntity $pickwareSalesChannelContext */
        $pickwareSalesChannelContext = $this->entityManager->findByPrimaryKey(
            SalesChannelApiContextDefinition::class,
            $event->getSalesChannelContext()->getToken(),
            $event->getContext(),
        );
        if (!$pickwareSalesChannelContext) {
            return;
        }
        $payload = $pickwareSalesChannelContext->getValue(['shipping']) ?? [];
        if (!array_key_exists(self::CONTEXT_PAYLOAD_KEY, $payload)) {
            return;
        }
        $dataTransferAgreement = EmailTransferAgreement::fromSalesChannelContextPayload($payload);

        $convertedCart = $event->getConvertedCart();
        $convertedCart['customFields'] ??= [];
        $convertedCart['customFields'][EmailTransferAgreementCustomField::TECHNICAL_NAME] = $dataTransferAgreement;
        $event->setConvertedCart($convertedCart);
    }
}
