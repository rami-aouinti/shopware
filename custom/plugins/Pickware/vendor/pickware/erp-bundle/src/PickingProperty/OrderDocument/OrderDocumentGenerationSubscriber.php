<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\OrderDocument;

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\PickingProperty\OrderDocumentPickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDocumentGenerationSubscriber implements EventSubscriberInterface
{
    public const ADDITIONAL_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY = 'pickwareErpPickingAdditionalProperties';
    public const OVERWRITE_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY = 'pickwareErpPickingOverwriteProperties';

    public function __construct(
        private readonly OrderDocumentPickingPropertyService $orderDocumentPickingPropertyService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceOrdersEvent::class => 'addPickingPropertiesToShopwareOrderDocuments',
            DeliveryNoteOrdersEvent::class => 'addPickingPropertiesToShopwareOrderDocuments',
            StornoOrdersEvent::class => 'addPickingPropertiesToShopwareOrderDocuments',
        ];
    }

    /**
     * Adds the picking property record values to the line items if a matching picking property (plugin settings) should
     * be displayed on invoice documents.
     */
    public function addPickingPropertiesToShopwareOrderDocuments(DocumentOrderEvent $event): void
    {
        $orders = $event->getOrders();

        // Can be empty, but we continue because the document config may still contain custom picking properties.
        $pickingPropertyRecords = $this->orderDocumentPickingPropertyService->getOrderDocumentPickingPropertyRecordsForOrderDocumentCreation(
            $orders->getIds(),
            $event->getContext(),
        );

        // The orders might be chunked by different order languages, so we need to iterate over the orders instead
        // of the operations, as the operations should include all orders of all languages.
        foreach ($orders as $order) {
            $operation = $event->getOperations()[$order->getId()] ?? null;
            if ($operation === null) {
                throw new InvalidArgumentException(
                    sprintf('No operation found for order with id "%s".', $order->getId()),
                );
            }
            $pickingPropertyRecordsForOrder = $pickingPropertyRecords->filter(
                fn(OrderDocumentPickingPropertyRecord $record) => $record->orderId === $order->getId(),
            );

            // We support adding or overwriting picking properties on the order document (i.e. wms can inject picking
            // properties that are unknown to erp).
            $documentConfigCustomFields = $operation->getConfig()['custom'] ?? null;
            $overWritePickingProperties = $documentConfigCustomFields[self::OVERWRITE_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY] ?? null;
            $additionalWritePickingProperties = $documentConfigCustomFields[self::ADDITIONAL_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY] ?? new ImmutableCollection();
            if ($overWritePickingProperties) {
                $pickingPropertyRecordsForOrder = $documentConfigCustomFields[self::OVERWRITE_ORDER_DOCUMENT_PICKING_PROPERTIES_RECORDS_KEY];
            } elseif ($additionalWritePickingProperties) {
                $pickingPropertyRecordsForOrder = $pickingPropertyRecordsForOrder->merge($additionalWritePickingProperties);
            }

            $pickingPropertyRecordsForOrder = $this->orderDocumentPickingPropertyService->filterOrderDocumentPickingPropertyRecordsForDocuments(
                $pickingPropertyRecordsForOrder,
                $event->getContext(),
            );

            /** @var OrderLineItemEntity $lineItem */
            foreach ($order->getLineItems() as $lineItem) {
                $pickingPropertyRecordValueLines = $this
                    ->getPickingPropertyOrderRecordsForLineItem($pickingPropertyRecordsForOrder, $lineItem)
                    ->map(fn(OrderDocumentPickingPropertyRecord $record) => $this->createPickingPropertyValueLineFromRecordValues(
                        $record->pickingPropertyRecord->getPickingPropertyRecordValues(),
                    ));

                $lineItem->setPayload(array_merge(
                    $lineItem->getPayload(),
                    ['pwErpPickingPropertyRecordValues' => $pickingPropertyRecordValueLines],
                ));
            }
        }
    }

    /**
     * Matches the given order line item to the picking property order records the same way as it is shown in the order
     * detail page of the administration (see the PickingPropertyOrderDetailStore).
     *
     * Known limitation: We match by product here. This means that if the same product is part of the order multiple
     * times, the picking property record values will be displayed multiple times.
     *
     * @param ImmutableCollection<OrderDocumentPickingPropertyRecord> $pickingPropertyRecords
     * @return ImmutableCollection<OrderDocumentPickingPropertyRecord>
     */
    private function getPickingPropertyOrderRecordsForLineItem(
        ImmutableCollection $pickingPropertyRecords,
        OrderLineItemEntity $lineItem,
    ): ImmutableCollection {
        return $pickingPropertyRecords->filter(
            function(OrderDocumentPickingPropertyRecord $record) use ($lineItem): bool {
                if ($lineItem->getProductId() !== null) {
                    // When the line item has a product reference, we use it and only it.
                    return $record->pickingPropertyRecord->getProductId() === $lineItem->getProductId();
                }

                $lineItemProductNumber = $lineItem->getPayload()['productNumber'] ?? null;
                $productSnapshot = $record->pickingPropertyRecord->getProductSnapshot();
                $recordProductNumber = $productSnapshot['productNumber'] ?? null;
                $recordProductName = $productSnapshot['name'] ?? null;
                $productNumberFuzzyMatching = ($lineItemProductNumber && $recordProductNumber && $lineItemProductNumber === $recordProductNumber);
                if (!$productNumberFuzzyMatching || !$recordProductName) {
                    return false;
                }

                if ($recordProductName === $lineItem->getLabel() || str_contains($lineItem->getLabel(), $recordProductName)) {
                    // When the product was deleted, but the product number from snapshot matches and the label matches
                    // (fuzzy) as well, we use it.
                    return true;
                }

                return false;
            },
        );
    }

    /**
     * @param PickingPropertyRecordValue[] $recordValues
     */
    private function createPickingPropertyValueLineFromRecordValues(array $recordValues): string
    {
        // Sort the picking property record values by name to ensure a consistent order across all order line
        // items on the document
        usort(
            $recordValues,
            fn(PickingPropertyRecordValue $a, PickingPropertyRecordValue $b) => strnatcasecmp($a->getName(), $b->getName()),
        );

        return implode(
            ' | ',
            array_map(
                fn(PickingPropertyRecordValue $recordValue) => sprintf(
                    '%s: %s',
                    $recordValue->getName(),
                    $recordValue->getValue(),
                ),
                $recordValues,
            ),
        );
    }
}
