<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDocumentBatchInfoSubscriber implements EventSubscriberInterface
{
    /**
     * Document config key used by WMS to pass the stock container ID for batch information retrieval.
     */
    public const STOCK_CONTAINER_ID_CONFIG_KEY = 'pickwareErpStockContainerId';

    public function __construct(
        private readonly OrderDocumentBatchInfoService $batchService,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceOrdersEvent::class => 'addBatchInfoToDocument',
            DeliveryNoteOrdersEvent::class => 'addBatchInfoToDocument',
            StornoOrdersEvent::class => 'addBatchInfoToDocument',
        ];
    }

    public function addBatchInfoToDocument(DocumentOrderEvent $event): void
    {
        if (!$this->isBatchManagementActive()) {
            return;
        }
        if (!$this->featureFlagService->isActive(BatchInformationOnOrderDocumentDevFeatureFlag::NAME)) {
            return;
        }

        $orders = $event->getOrders();
        $context = $event->getContext();

        $stockContainerIdsByOrder = $this->extractStockContainerIds($event);
        // Fetch batch info from stock containers (WMS flow)
        $batchInfoByStockContainer = $context->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $systemContext) => $this->batchService->getBatchInfoByStockContainers(
                array_values($stockContainerIdsByOrder),
                $systemContext,
            ),
        );
        $batchInfoByOrder = [];
        foreach ($stockContainerIdsByOrder as $orderId => $stockContainerId) {
            $batchInfoByOrder[$orderId] = $batchInfoByStockContainer[$stockContainerId] ?? new ProductBatchInfoMap();
        }

        // Fetch batch info from order stock for remaining orders (ERP flow and WMS fallback)
        $orderIdsWithoutStockContainer = array_diff(
            array_map(fn(OrderEntity $order) => $order->getId(), iterator_to_array($orders)),
            array_keys($stockContainerIdsByOrder),
        );
        $orderBasedBatchInfo = $context->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $systemContext) => $this->batchService->getBatchInfoByOrders(
                $orderIdsWithoutStockContainer,
                $systemContext,
            ),
        );
        $batchInfoByOrder = array_merge($batchInfoByOrder, $orderBasedBatchInfo);

        foreach ($orders as $order) {
            $batchInfoMap = $batchInfoByOrder[$order->getId()] ?? new ProductBatchInfoMap();
            if ($batchInfoMap->isEmpty()) {
                continue;
            }

            $requiresQuantityValidation = $event instanceof InvoiceOrdersEvent || $event instanceof StornoOrdersEvent;
            if ($requiresQuantityValidation) {
                $expectedQuantities = $this->getExpectedQuantitiesByProduct($order->getLineItems());
                // We don't want to show batch information for invoices and cancellation invoices if the batch
                // quantities in the stock do not match the order line item quantities to avoid confusion.
                // (For example when the order is shipped partially and the invoice in generated after the first partial
                // shipment.)
                if ($batchInfoMap->hasQuantityMismatch($expectedQuantities)) {
                    continue;
                }
            }

            foreach ($order->getLineItems() ?? [] as $lineItem) {
                $productId = $lineItem->getProductId();
                if ($productId === null) {
                    continue;
                }

                $batchInfo = $batchInfoMap->get($productId);
                if ($batchInfo->isEmpty()) {
                    continue;
                }

                $lineItem->setPayload(array_merge(
                    $lineItem->getPayload(),
                    ['pwErpBatchInfo' => $batchInfo->toPayload()],
                ));
            }
        }
    }

    /**
     * @return array<string, string> orderId => stockContainerId
     */
    private function extractStockContainerIds(DocumentOrderEvent $event): array
    {
        $stockContainerIds = [];
        foreach ($event->getOrders() as $order) {
            $operation = $event->getOperations()[$order->getId()] ?? null;
            $stockContainerId = $operation?->getConfig()['custom'][self::STOCK_CONTAINER_ID_CONFIG_KEY] ?? null;

            if ($stockContainerId !== null) {
                $stockContainerIds[$order->getId()] = $stockContainerId;
            }
        }

        return $stockContainerIds;
    }

    /**
     * @return array<string, int> productId => quantity
     */
    private function getExpectedQuantitiesByProduct(?OrderLineItemCollection $lineItems): array
    {
        if ($lineItems === null) {
            return [];
        }

        $quantities = [];
        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getProductId();
            if ($productId !== null) {
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $lineItem->getQuantity();
            }
        }

        return $quantities;
    }

    private function isBatchManagementActive(): bool
    {
        return $this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)
            && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME);
    }
}
