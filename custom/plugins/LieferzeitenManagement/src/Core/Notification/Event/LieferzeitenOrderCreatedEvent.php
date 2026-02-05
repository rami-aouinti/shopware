<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Notification\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\AssociationNotLoadedException;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Contracts\EventDispatcher\Event;

class LieferzeitenOrderCreatedEvent extends Event implements OrderAware, MailAware, FlowEventAware
{
    public const EVENT_NAME = 'lieferzeiten.notification.order_created';

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

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->mailRecipientStruct;
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
