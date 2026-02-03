<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DeliveryNote;

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryNoteLineItemFilterer implements EventSubscriberInterface
{
    public const DOCUMENT_CONFIG_PRODUCTS_IN_DELIVERY_KEY = 'pickwareWmsProductsInDelivery';

    /**
     * @param OrderQuantitiesToShipCalculator|null $orderQuantitiesToShipCalculator Service is marked optional to maintain backwards compatibility with ERP
     *  Will be non-optional after WMS minimum requirement of ERP-4.4.0
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryNoteOrdersEvent::class => 'filterLineItemsFromEvent',
        ];
    }

    public function filterLineItemsFromEvent(DeliveryNoteOrdersEvent $event): void
    {
        /** @var OrderEntity $order */
        foreach ($event->getOrders() as $order) {
            if (!\array_key_exists($order->getId(), $event->getOperations())) {
                continue;
            }
            $operation = $event->getOperations()[$order->getId()];
            $customFields = $operation->getConfig()['custom'] ?? null;
            if ($customFields === null) {
                continue;
            }
            /** @var array<string, array{quantity: int}>|null $productsInDelivery */
            $productsInDelivery = $customFields[self::DOCUMENT_CONFIG_PRODUCTS_IN_DELIVERY_KEY] ?? null;
            if ($productsInDelivery === null) {
                continue;
            }
            if (!is_array($productsInDelivery)) {
                throw new InvalidArgumentException(sprintf(
                    'Expected type of config-key "%s" to be array, %s given.',
                    self::DOCUMENT_CONFIG_PRODUCTS_IN_DELIVERY_KEY,
                    gettype($productsInDelivery),
                ));
            }
            if (count($productsInDelivery) === 0) {
                throw DeliveryNoteException::deliveryNoteWouldBeEmpty(
                    $order->getId(),
                    $order->getOrderNumber(),
                );
            }

            // Old filtering logic maintained for backwards compatibility with ERP
            // Can be removed after WMS minimum requirement of ERP-4.4.0
            if (!$this->orderQuantitiesToShipCalculator) {
                $this->filterOrderLineItems($order, $productsInDelivery);
                $partialDeliveryNoteFilterEvent = new DeliveryNoteFilterEvent($order, $event->getContext());
                $this->eventDispatcher->dispatch($partialDeliveryNoteFilterEvent);

                continue;
            }

            $context = $event->getContext();
            $partialDeliveryNoteFilterEvent = new DeliveryNoteLineItemFilterEvent(
                $order->getId(),
                $this->getLineItemQuantitiesToBeShipped($order, $productsInDelivery, $context),
                [],
                $context,
            );
            /** @var DeliveryNoteLineItemFilterEvent $modifiedEvent */
            $modifiedEvent = $this->eventDispatcher->dispatch($partialDeliveryNoteFilterEvent);

            $order->setLineItems(
                $this->mergeDuplicateProductLineItems(
                    new OrderLineItemCollection(
                        $order->getLineItems()
                            ->filter(fn(OrderLineItemEntity $lineItem) => $modifiedEvent->getLineItemQuantities()->has($lineItem->getId()))
                            ->map(function(OrderLineItemEntity $lineItem) use ($modifiedEvent) {
                                $lineItem->setQuantity(
                                    $modifiedEvent->getLineItemQuantities()->get($lineItem->getId()),
                                );

                                return $lineItem;
                            }),
                    ),
                ),
            );
            $order->changeCustomFields($modifiedEvent->getCustomFields());

            // Dispatch deprecated event to ensure backwards compatibility with old product set bundle versions
            $this->eventDispatcher->dispatch(new DeliveryNoteFilterEvent(
                $order,
                $context,
            ));
        }
    }

    /**
     * @param array<string, array{quantity: int}> $productsInDelivery
     * @return CountingMap<string>
     */
    private function getLineItemQuantitiesToBeShipped(OrderEntity $orderEntity, array $productsInDelivery, Context $context): CountingMap
    {
        if (method_exists($this->orderQuantitiesToShipCalculator, 'calculateLineItemQuantitiesToShipForOrder')) {
            $orderLineItemQuantities = $this->orderQuantitiesToShipCalculator->calculateLineItemQuantitiesToShipForOrder(
                $orderEntity->getId(),
                $context,
            );
        } else {
            $lineItemsToShip = $this->orderQuantitiesToShipCalculator->calculateLineItemsToShipForOrder(
                $orderEntity->getId(),
                $context,
            );
            $countingMapData = [];
            foreach ($lineItemsToShip as $orderLineItemQuantity) {
                $countingMapData[$orderLineItemQuantity->getOrderLineItemId()] = $orderLineItemQuantity->getQuantity();
            }
            $orderLineItemQuantities = new CountingMap($countingMapData);
        }

        $lineItemQuantitiesToShip = new CountingMap();

        foreach ($productsInDelivery as $productId => $productInDelivery) {
            $quantityToShip = $productInDelivery['quantity'] ?? 0;
            $lineItemsForProduct = $orderEntity
                ->getLineItems()
                ->filter(fn(OrderLineItemEntity $lineItem) => $lineItem->getProductId() === $productId)->getElements();

            while ($quantityToShip > 0 && count($lineItemsForProduct) > 0) {
                $lineItem = array_shift($lineItemsForProduct);

                if (!$orderLineItemQuantities->has($lineItem->getId())) {
                    continue;
                }

                $availableQuantity = $orderLineItemQuantities->get($lineItem->getId());
                if ($quantityToShip >= $availableQuantity) {
                    $quantityToShip -= $availableQuantity;
                    $lineItemQuantitiesToShip->set($lineItem->getId(), $availableQuantity);
                } else {
                    $lineItemQuantitiesToShip->set($lineItem->getId(), $quantityToShip);
                    $quantityToShip = 0;
                }
            }
        }

        return $lineItemQuantitiesToShip;
    }

    /**
     * Old filtering logic maintained for backwards compatibility with ERP
     * Can be removed after WMS minimum requirement of ERP-4.4.0
     * @param array<string, array{quantity: int}> $productsInDelivery
     */
    private function filterOrderLineItems(OrderEntity $orderEntity, array $productsInDelivery): void
    {
        // Track used product ids. Order line items are not deduplicated by product whereas the given products in the
        // delivery are deduplicated. The first matching line item will be used and the other line items will be
        // ignored, if the same product is part of the order line items multiple times.
        $usedProductIds = [];
        $orderEntity->setLineItems(
            $orderEntity
                ->getLineItems()
                ->filter(function(OrderLineItemEntity $lineItem) use ($productsInDelivery, &$usedProductIds) {
                    if ($lineItem->getProductId() === null) {
                        return false;
                    }
                    if (in_array($lineItem->getProductId(), $usedProductIds, true)) {
                        return false;
                    }
                    $productInDelivery = $productsInDelivery[$lineItem->getProductId()] ?? null;
                    if ($productInDelivery === null) {
                        return false;
                    }
                    $lineItem->setQuantity($productInDelivery['quantity']);
                    $usedProductIds[] = $lineItem->getProductId();

                    return true;
                }),
        );
    }

    private function mergeDuplicateProductLineItems(OrderLineItemCollection $lineItems): OrderLineItemCollection
    {
        $mergedLineItems = [];
        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getProductId();
            if (!array_key_exists($productId, $mergedLineItems)) {
                $mergedLineItems[$productId] = $lineItem;

                continue;
            }
            $mergedLineItems[$productId]->setQuantity($mergedLineItems[$productId]->getQuantity() + $lineItem->getQuantity());
        }

        return new OrderLineItemCollection(array_values($mergedLineItems));
    }
}
