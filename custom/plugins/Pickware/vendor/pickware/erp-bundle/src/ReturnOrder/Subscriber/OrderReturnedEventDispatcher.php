<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Events\CompletelyReturnedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Events\PartiallyReturnedEvent;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnedEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [GoodsReceiptLineItemDefinition::ENTITY_WRITTEN_EVENT => 'onGoodsReceiptLineItemWritten'];
    }

    public function onGoodsReceiptLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $goodsReceiptLineItemIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (isset($payload['returnOrderId']) || isset($payload['productId']) || isset($payload['quantity'])) {
                $goodsReceiptLineItemIds[] = $payload['id'];
            }
        }
        if (count($goodsReceiptLineItemIds) === 0) {
            return;
        }

        // switch to system scope to prevent ACL errors
        $entityWrittenEvent->getContext()->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $context) => $this->dispatchOrderReturnedEvents($goodsReceiptLineItemIds, $context),
        );
    }

    private function dispatchOrderReturnedEvents(array $goodsReceiptLineItemIds, Context $context): void
    {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['pickwareErpReturnOrders.goodsReceiptLineItems.id' => $goodsReceiptLineItemIds],
            $context,
            [
                'lineItems',
                'orderCustomer',
                'pickwareErpReturnOrders.goodsReceiptLineItems',
            ],
        );

        foreach ($orders as $order) {
            $orderProductQuantities = ImmutableCollection::create($order->getLineItems())
                ->filter(fn(OrderLineItemEntity $lineItem) => $lineItem->getProductId() !== null)
                ->map(
                    fn(OrderLineItemEntity $lineItem) => new ProductQuantity(
                        productId: $lineItem->getProductId(),
                        quantity: $lineItem->getQuantity(),
                    ),
                    returnType: ProductQuantityImmutableCollection::class,
                )
                ->groupByProductId();

            /** @var ReturnOrderCollection $returnOrders */
            $returnOrders = $order->getExtension('pickwareErpReturnOrders');
            $returnedProductQuantities = ImmutableCollection::create($returnOrders)
                ->flatMap(fn(ReturnOrderEntity $returnOrder) => $returnOrder->getGoodsReceiptLineItems()->getElements())
                ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null)
                ->map(
                    fn(GoodsReceiptLineItemEntity $lineItem) => new ProductQuantity(
                        productId: $lineItem->getProductId(),
                        quantity: $lineItem->getQuantity(),
                    ),
                    returnType: ProductQuantityImmutableCollection::class,
                )
                ->groupByProductId();

            $notReceivedProductQuantities = $orderProductQuantities
                ->subtract($returnedProductQuantities)
                ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() > 0);

            if ($notReceivedProductQuantities->count() === 0) {
                $this->eventDispatcher->dispatch(CompletelyReturnedEvent::createFromOrder($context, $order));
            } else {
                $this->eventDispatcher->dispatch(PartiallyReturnedEvent::createFromOrder($context, $order));
            }
        }
    }
}
