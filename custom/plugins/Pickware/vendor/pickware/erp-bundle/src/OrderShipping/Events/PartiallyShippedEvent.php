<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping\Events;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Contracts\EventDispatcher\Event;

class PartiallyShippedEvent extends Event implements OrderAware, MailAware, FlowEventAware, A11yRenderedDocumentAware
{
    public const EVENT_NAME = 'pickware_erp.order_shipping.partially_shipped';

    private Context $context;
    private string $orderId;
    private string $salesChannelId;
    private MailRecipientStruct $mailRecipientStruct;

    public function __construct(
        Context $context,
        string $orderId,
        string $salesChannelId,
        MailRecipientStruct $mailRecipientStruct,
    ) {
        $this->context = $context;
        $this->orderId = $orderId;
        $this->salesChannelId = $salesChannelId;
        $this->mailRecipientStruct = $mailRecipientStruct;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getA11yDocumentIds(): array
    {
        return [];
    }

    public static function createFromOrder(Context $context, OrderEntity $order): self
    {
        if ($order->getOrderCustomer() === null) {
            throw new AssociationNotLoadedException('orderCustomer', $order);
        }

        $mailRecipientStruct = new MailRecipientStruct([
            $order->getOrderCustomer()->getEmail() => sprintf(
                '%s %s',
                $order->getOrderCustomer()->getFirstName(),
                $order->getOrderCustomer()->getLastName(),
            ),
        ]);

        return new self($context, $order->getId(), $order->getSalesChannelId(), $mailRecipientStruct);
    }
}
