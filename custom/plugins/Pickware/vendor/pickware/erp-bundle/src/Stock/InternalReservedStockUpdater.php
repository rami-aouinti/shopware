<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Exception;
use Pickware\DalBundle\DatabaseBulkInsertService;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\DalBundle\IdResolver\CachedStateIdService;
use function Pickware\DebugBundle\Profiling\trace;
use Pickware\DebugBundle\Profiling\TracingTag;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\PaperTrail\ErpPaperTrailUri;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareErpStarter\Stock\Event\DetermineOrderExternallyManagedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InternalReservedStockUpdater implements EventSubscriberInterface
{
    private const ORDER_STATE_IGNORE_LIST = [
        OrderStates::STATE_CANCELLED,
        OrderStates::STATE_COMPLETED,
    ];
    private const ORDER_DELIVERY_STATE_IGNORE_LIST = [
        OrderDeliveryStates::STATE_CANCELLED,
        OrderDeliveryStates::STATE_SHIPPED,
    ];

    // Also used in to-ship calculation. See Pickware\PickwareErpStarter\Picking\OrderToShipCalculator.php
    public const RETURN_ORDER_STATE_ALLOW_STATE = ReturnOrderStateMachine::STATE_COMPLETED;

    private int $suppressReservedStockCalculationNestingLevel = 0;

    /** @var list<string> $productsRequiringReservedStockRecalculation */
    private array $productsRequiringReservedStockRecalculation = [];

    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DatabaseBulkInsertService $bulkInsertWithUpdate,
        private readonly FeatureFlagService $featureFlagService,
        private readonly CachedStateIdService $cachedStateIdService,
        private readonly PaperTrailUriProvider $paperTrailUriProvider,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityPreWriteValidationEventDispatcher::getEventName(OrderLineItemDefinition::ENTITY_NAME) => 'triggerOrderLineItemChangeSet',
            StockUpdatedForStockMovementsEvent::class => 'stockUpdatedForStockMovements',
            OrderEvents::ORDER_WRITTEN_EVENT => 'orderWritten',
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'orderDeliveryWritten',
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'orderLineItemWritten',
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => 'orderLineItemWritten',
            ReturnOrderLineItemDefinition::ENTITY_WRITTEN_EVENT => 'returnOrderLineItemWritten',
            ReturnOrderDefinition::ENTITY_WRITTEN_EVENT => 'returnOrderWritten',
        ];
    }

    /**
     * Does nothing. This method is only kept for compatibility reasons.
     *
     * @deprecated Removed with 5.0.0 Use deferReservedStockCalculation instead
     */
    public function suppressReservedStockCalculation(callable $callback): mixed
    {
        return $callback();
    }

    /**
     * As for now, the reserved stock calculation can take some time. In certain scenarios this calculation is triggered
     * multiple times in a row, or generally too often inside an encapsulated process. For these scenarios we want to be
     * able to defer a reserved stock calculation until to the end of the process.
     *
     * See https://github.com/pickware/shopware-plugins/issues/7356
     */
    public function deferReservedStockCalculation(callable $callback, Context $context): mixed
    {
        $this->suppressReservedStockCalculationNestingLevel += 1;
        try {
            return $callback();
        } finally {
            $this->suppressReservedStockCalculationNestingLevel -= 1;

            if ($this->suppressReservedStockCalculationNestingLevel === 0) {
                $productIds = $this->productsRequiringReservedStockRecalculation;
                $this->productsRequiringReservedStockRecalculation = [];
                $this->recalculateProductReservedStock($productIds, $context);
            }
        }
    }

    public function triggerOrderLineItemChangeSet(EntityPreWriteValidationEvent $event): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        foreach ($event->getCommands() as $command) {
            if (
                $command instanceof ChangeSetAware && (
                    $command instanceof DeleteCommand
                    || $command->hasField('product_id')
                    || $command->hasField('type')
                    || $command->hasField('quantity')
                )
            ) {
                $command->requestChangeSet();
            }
        }
    }

    public function stockUpdatedForStockMovements(StockUpdatedForStockMovementsEvent $event): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        $productIds = [];
        $orderIds = [];
        foreach ($event->getStockMovements() as $stockMovement) {
            $sourceOrderId = $stockMovement['sourceOrderId'] ?? null;
            $destinationOrderId = $stockMovement['destinationOrderId'] ?? null;
            if ($sourceOrderId !== null) {
                $orderIds[] = $sourceOrderId;
                $productIds[] = $stockMovement['productId'];
            }

            if ($destinationOrderId !== null) {
                $orderIds[] = $destinationOrderId;
                $productIds[] = $stockMovement['productId'];
            }
        }

        if ($this->checkAllOrdersExternallyManaged($orderIds)) {
            return;
        }

        $this->recalculateProductReservedStock($productIds, $event->getContext());
    }

    public function orderWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                // When the order has been created right now, it has no order line items yet. So we can ignore it.
                continue;
            }
            $payload = $writeResult->getPayload();
            if (isset($payload['stateId'])) {
                $orderIds[] = $writeResult->getPrimaryKey();
            }
        }

        if ($this->checkAllOrdersExternallyManaged($orderIds) || count($orderIds) === 0) {
            return;
        }

        $products = $this->db->fetchAllAssociative(
            'SELECT LOWER(HEX(`order_line_item`.`product_id`)) AS `id`
            FROM `order_line_item`
            WHERE `order_line_item`.`order_id` IN (:orderIds)
                AND `order_line_item`.`version_id` = :liveVersionId
                AND `order_line_item`.`order_version_id` = :liveVersionId
                AND `order_line_item`.`product_version_id` = :liveVersionId
                AND `order_line_item`.`type` = :typeProduct
                AND `order_line_item`.`product_id` IS NOT NULL',
            [
                'orderIds' => array_map('hex2bin', $orderIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'typeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
            [
                'orderIds' => ArrayParameterType::BINARY,
            ],
        );

        $productIds = array_column($products, 'id');

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('order-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Internally reserved stock update triggered',
            [
                'trigger' => 'order-update',
                'orderIds' => $entityWrittenEvent->getIds(),
            ],
        );
        $this->recalculateProductReservedStock($productIds, $entityWrittenEvent->getContext());
        $this->paperTrailLoggingService->logPaperTrailEvent('Internally reserved stock update finished');
        $this->paperTrailUriProvider->reset();
    }

    public function orderDeliveryWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderDeliveryIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                // When the order delivery has been created right now, the corresponding order has no order line items
                // yet. So we can ignore it.
                continue;
            }

            $payload = $writeResult->getPayload();
            if (isset($payload['stateId'])) {
                $orderDeliveryIds[] = $payload['id'];
            }
        }

        if (count($orderDeliveryIds) === 0) {
            return;
        }
        $orderIds = $this->db->fetchFirstColumn(
            'SELECT LOWER(HEX(`order_id`)) FROM `order_delivery` WHERE `id` IN (:orderDeliveryIds)',
            ['orderDeliveryIds' => Uuid::fromHexToBytesList($orderDeliveryIds)],
            ['orderDeliveryIds' => ArrayParameterType::BINARY],
        );
        if ($this->checkAllOrdersExternallyManaged($orderIds)) {
            return;
        }

        $orderDeliveries = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`order_line_item`.`product_id`)) AS `productId`
            FROM `order_delivery`
            INNER JOIN `order`
                ON `order`.`id` = `order_delivery`.`order_id`
                AND `order`.`version_id` = `order_delivery`.`order_version_id`
            INNER JOIN `order_line_item`
                ON `order`.`id` = `order_line_item`.`order_id`
                AND `order`.`version_id` = `order_line_item`.`order_version_id`
            WHERE `order_delivery`.`id` IN (:orderDeliveryIds)
                AND `order_line_item`.`product_id` IS NOT NULL
                AND `order_line_item`.`product_version_id` = :liveVersionId
                AND `order_line_item`.`type` = :typeProduct',
            [
                'orderDeliveryIds' => array_map('hex2bin', $orderDeliveryIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'typeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
            [
                'orderDeliveryIds' => ArrayParameterType::BINARY,
            ],
        );
        $productIds = array_values(array_unique(array_column($orderDeliveries, 'productId')));

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('order-delivery-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Internally reserved stock update triggered',
            [
                'trigger' => 'order-delivery-update',
                'orderDeliveryIds' => $entityWrittenEvent->getIds(),
            ],
        );
        $this->recalculateProductReservedStock($productIds, $entityWrittenEvent->getContext());
        $this->paperTrailLoggingService->logPaperTrailEvent('Internally reserved stock update finished');
        $this->paperTrailUriProvider->reset();
    }

    public function orderLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $orderIds = $this->db->fetchFirstColumn(
            <<<SQL
                SELECT LOWER(HEX(`order_id`)) FROM `order_line_item`
                WHERE `order_line_item`.`id` IN (:lineItemIds)
                AND `order_line_item`.`version_id` = :liveVersionId
                SQL,
            [
                'lineItemIds' => Uuid::fromHexToBytesList($entityWrittenEvent->getIds()),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'lineItemIds' => ArrayParameterType::BINARY,
            ],
        );

        if ($this->checkAllOrdersExternallyManaged($orderIds)) {
            return;
        }

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('order-line-item-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Internally reserved stock update triggered',
            [
                'trigger' => 'order-line-item-update',
                'orderLineItemIds' => $entityWrittenEvent->getIds(),
            ],
        );
        $this->lineItemWritten($entityWrittenEvent, LineItem::PRODUCT_LINE_ITEM_TYPE);
        $this->paperTrailLoggingService->logPaperTrailEvent('Internally reserved stock update finished');
        $this->paperTrailUriProvider->reset();
    }

    public function returnOrderLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        $orderIds = $this->db->fetchFirstColumn(
            <<<SQL
                SELECT LOWER(HEX(`order_id`)) FROM `order_line_item`
                LEFT JOIN `pickware_erp_return_order_line_item`
                ON `order_line_item`.`id` = `pickware_erp_return_order_line_item`.`order_line_item_id`
                AND `order_line_item`.`version_id` = `pickware_erp_return_order_line_item`.`order_line_item_version_id`
                WHERE `pickware_erp_return_order_line_item`.`id` IN (:returnOrderLineItemIds)
                AND `order_line_item`.`version_id` = :liveVersionId
                SQL,
            [
                'returnOrderLineItemIds' => Uuid::fromHexToBytesList($entityWrittenEvent->getIds()),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'returnOrderLineItemIds' => ArrayParameterType::BINARY,
            ],
        );

        if ($this->checkAllOrdersExternallyManaged($orderIds)) {
            return;
        }

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('return-order-line-item-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Internally reserved stock update triggered',
            [
                'trigger' => 'return-order-line-item-update',
                'returnOrderLineItemIds' => $entityWrittenEvent->getIds(),
            ],
        );
        $this->lineItemWritten($entityWrittenEvent, ReturnOrderLineItemDefinition::TYPE_PRODUCT);
        $this->paperTrailLoggingService->logPaperTrailEvent('Internally reserved stock update finished');
        $this->paperTrailUriProvider->reset();
    }

    public function returnOrderWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $returnOrderIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $payload = $writeResult->getPayload();
            if (isset($payload['stateId'])) {
                $returnOrderIds[] = $payload['id'];
            }
        }

        if (count($returnOrderIds) === 0) {
            return;
        }

        $orderIds = $this->db->fetchFirstColumn(
            <<<SQL
                SELECT LOWER(HEX(`pickware_erp_return_order`.`order_id`))
                FROM `pickware_erp_return_order`
                WHERE `pickware_erp_return_order`.`id` IN (:returnOrderIds)
                AND `pickware_erp_return_order`.`version_id` = :liveVersionId
                SQL,
            [
                'returnOrderIds' => Uuid::fromHexToBytesList($returnOrderIds),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'returnOrderIds' => ArrayParameterType::BINARY,
            ],
        );

        if ($this->checkAllOrdersExternallyManaged($orderIds)) {
            return;
        }

        $returnOrderProducts = $this->db->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`returnOrderLineItem`.`product_id`)) AS `productId`
            FROM
                `pickware_erp_return_order_line_item` AS returnOrderLineItem
            WHERE
                `returnOrderLineItem`.`return_order_id` IN (:returnOrderIds)
                AND `returnOrderLineItem`.`version_id` = :liveVersionId
                AND `returnOrderLineItem`.`product_id` IS NOT NULL',
            [
                'returnOrderIds' => Uuid::fromHexToBytesList($returnOrderIds),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'returnOrderIds' => ArrayParameterType::BINARY,
            ],
        );

        $productIds = array_values(array_unique(array_column($returnOrderProducts, 'productId')));

        $this->paperTrailUriProvider->registerUri(ErpPaperTrailUri::withProcess('return-order-update'));
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Internally reserved stock update triggered',
            [
                'trigger' => 'return-order-update',
                'returnOrderIds' => $entityWrittenEvent->getIds(),
            ],
        );
        $this->recalculateProductReservedStock($productIds, $entityWrittenEvent->getContext());
        $this->paperTrailLoggingService->logPaperTrailEvent('Internally reserved stock update finished');
        $this->paperTrailUriProvider->reset();
    }

    /**
     * Updates the old and the new product, if the product of a line item is changed.
     */
    public function lineItemWritten(EntityWrittenEvent $entityWrittenEvent, string $productLineItemType): void
    {
        if ($this->featureFlagService->isActive(DisableProductReservedStockUpdaterFeatureFlag::NAME)) {
            return;
        }
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            // $writeResult->getExistence() can be null, but we have no idea why and also not what this means.
            $payload = $writeResult->getPayload();
            $existence = $writeResult->getExistence();
            $isNewLineItem = (
                $existence === null
                && $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT
            ) || (
                $existence !== null && !$existence->exists()
            );
            if (
                $isNewLineItem
                && isset($payload['productId'])
                && ($payload['type'] ?? null) === $productLineItemType
            ) {
                // This is a newly-created line item
                $productIds[] = $writeResult->getPayload()['productId'];

                continue;
            }

            $changeSet = $writeResult->getChangeSet();
            if ($changeSet) {
                if (
                    $changeSet->hasChanged('product_id')
                    || $changeSet->hasChanged('type')
                    || $changeSet->hasChanged('quantity')
                ) {
                    $productIdBefore = $changeSet->getBefore('product_id');
                    if ($productIdBefore) {
                        $productIds[] = bin2hex($productIdBefore);
                    }
                    $productIdAfter = $changeSet->getAfter('product_id');
                    if ($productIdAfter) {
                        // $productIdAfter === null, when product_id was not changed
                        $productIds[] = bin2hex($productIdAfter);
                    }
                }
            }
        }
        $productIds = array_values(array_filter(array_unique($productIds)));

        $this->recalculateProductReservedStock($productIds, $entityWrittenEvent->getContext());
    }

    /**
     * DEPENDS ON pickware products being initialized
     *
     * @param string[] $productIds
     */
    public function recalculateProductReservedStock(array $productIds, ?Context $context = null): void
    {
        $tags = [TracingTag::Stacktrace->getKey() => (new Exception())->getTraceAsString()];

        trace(__METHOD__, function() use ($context, $productIds): void {
            $this->doRecalculateProductReservedStock($productIds, $context);
        }, tags: $tags);
    }

    /**
     * @param list<string> $productIds
     */
    private function doRecalculateProductReservedStock(array $productIds, ?Context $context): void
    {
        if ($this->suppressReservedStockCalculationNestingLevel > 0) {
            $this->productsRequiringReservedStockRecalculation = [
                ...$this->productsRequiringReservedStockRecalculation,
                ...$productIds,
            ];

            return;
        }

        if (count($productIds) === 0) {
            return;
        }

        if ($context === null) {
            trigger_error('The context parameter is not optional anymore. Please provide a context.', E_USER_DEPRECATED);
            $context = Context::createCLIContext();
        }

        $reservedStockCalculationEvent = new ReservedStockCalculationExtensionEvent($context);
        $this->eventDispatcher->dispatch($reservedStockCalculationEvent);

        // By splitting the SELECT and the UPDATE query we work around a performance problem. If the queries were
        // executed in one UPDATE ... JOIN query the query time would rise unexpectedly.

        RetryableTransaction::retryable($this->db, function() use ($productIds, $reservedStockCalculationEvent): void {
            $existingProductIds = $this->db->fetchFirstColumn(
                'SELECT `id` FROM `product` WHERE `id` IN (:productIds) AND `version_id` = :liveVersionId FOR UPDATE',
                [
                    'productIds' => Uuid::fromHexToBytesList($productIds),
                    'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['productIds' => ArrayParameterType::BINARY],
            );

            // For performance reasons we pre-fetch the ignored state IDs instead of adding another join when filtering
            $ignoredOrderStateIds = $this->cachedStateIdService->getOrderStateIds(self::ORDER_STATE_IGNORE_LIST);
            $ignoredOrderDeliveryStateIds = $this->cachedStateIdService->getOrderDeliveryStateIds(self::ORDER_DELIVERY_STATE_IGNORE_LIST);
            $allowedReturnOrderStateId = $this->cachedStateIdService->getStateIds(ReturnOrderStateMachine::TECHNICAL_NAME, [self::RETURN_ORDER_STATE_ALLOW_STATE])[0];

            $pickwareProductReservedStocks = $this->db->fetchAllAssociative(
                <<<SQL
                    # Query: SELECT for reserved stock calculation
                    WITH
                        `relevant_order` AS (
                            SELECT DISTINCT
                                `order`.`id` AS `id`,
                                `order`.`version_id` AS `version_id`
                            FROM `order`
                            INNER JOIN `order_line_item`
                                ON `order_line_item`.`order_id` = `order`.`id`
                                AND `order_line_item`.`order_version_id` = `order`.`version_id`
                                AND `order_line_item`.`product_id` IN (:productIds)
                                AND `order_line_item`.`product_version_id` = :liveVersionId
                                AND `order_line_item`.`type` = :orderLineItemTypeProduct
                            WHERE
                                `order`.`state_id` NOT IN (:ignoredOrderStateIds)
                                AND `order`.`version_id` = :liveVersionId
                        )
                    SELECT
                        `order_product_quantity`.`id` AS `id`,
                        `order_product_quantity`.`product_id` AS `product_id`,
                        `order_product_quantity`.`product_version_id` AS `product_version_id`,
                        GREATEST(
                            0, SUM(`order_product_quantity`.`quantity`) - SUM(IFNULL(`stock`.`quantity`, 0)) - SUM(IFNULL(`order_product_quantity`.`total_return_quantity`, 0))
                        ) AS `internal_reserved_stock`,
                        UTC_TIMESTAMP(3) as `updated_at`,
                        UTC_TIMESTAMP(3) as `created_at`
                    FROM (
                        SELECT
                            `pickware_product`.`id` AS `id`,
                            `order_line_item`.`product_id` AS `product_id`,
                            `order_line_item`.`product_version_id` AS `product_version_id`,
                            `order`.`id` AS `order_id`,
                            `order`.`version_id` AS `order_version_id`,
                            `return_order_line_item_aggregated`.`total_return_quantity` AS `total_return_quantity`,
                            SUM(GREATEST(IFNULL(`order_line_item`.`quantity`, 0) - IFNULL(`pickware_order_line_item`.`externally_fulfilled_quantity`, 0), 0)) AS `quantity`
                        FROM `relevant_order` AS `order`
                        INNER JOIN `order_line_item`
                            ON `order_line_item`.`order_id` = `order`.`id`
                            AND `order_line_item`.`order_version_id` = `order`.`version_id`
                            AND `order_line_item`.`product_id` IN (:productIds)
                            AND `order_line_item`.`product_version_id` = :liveVersionId
                            AND `order_line_item`.`type` = :orderLineItemTypeProduct
                        INNER JOIN `pickware_erp_pickware_product` AS `pickware_product`
                            ON `pickware_product`.`product_id` = `order_line_item`.`product_id`
                            AND `pickware_product`.`product_version_id` = `order_line_item`.`product_version_id`
                        LEFT JOIN `pickware_erp_pickware_order_line_item` AS `pickware_order_line_item`
                            ON `pickware_order_line_item`.`order_line_item_id` = `order_line_item`.`id`
                            AND `pickware_order_line_item`.`order_line_item_version_id` = `order_line_item`.`version_id`
                        LEFT JOIN (
                            SELECT
                                `return_order_line_item`.`order_line_item_id`,
                                `return_order_line_item`.`order_line_item_version_id`,
                                SUM(`return_order_line_item`.`quantity`) AS `total_return_quantity`
                            FROM `pickware_erp_return_order` AS `return_order`
                            INNER JOIN `pickware_erp_return_order_line_item` AS `return_order_line_item`
                                ON `return_order_line_item`.`return_order_id` = `return_order`.`id`
                                AND `return_order_line_item`.`return_order_version_id` = `return_order`.`version_id`
                                AND `return_order_line_item`.`product_id` IN (:productIds)
                                AND `return_order_line_item`.`product_version_id` = :liveVersionId
                            WHERE
                                `return_order`.`state_id` = :allowedReturnOrderStateId
                                AND `return_order`.`version_id` = :liveVersionId
                            GROUP BY
                                `return_order_line_item`.`order_line_item_id`,
                                `return_order_line_item`.`order_line_item_version_id`
                        ) AS `return_order_line_item_aggregated`
                            ON `return_order_line_item_aggregated`.`order_line_item_id` = `order_line_item`.`id`
                            AND `return_order_line_item_aggregated`.`order_line_item_version_id` = `order_line_item`.`version_id`

                        -- Select a single order delivery with the highest shippingCosts.unitPrice as the primary order
                        -- delivery for the order. This selection strategy is adapted from how order deliveries are selected
                        -- in the administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
                        LEFT JOIN (
                            SELECT
                                `order_delivery`.`order_id` AS `order_id`,
                                `order_delivery`.`order_version_id` AS `order_version_id`,
                                `order_delivery`.`state_id` AS `state_id`,
                                ROW_NUMBER() OVER (
                                    PARTITION BY
                                        `order_delivery`.`order_id`,
                                        `order_delivery`.`order_version_id`
                                    ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) AS DECIMAL) DESC
                                ) AS `row_number`
                            FROM `relevant_order` AS `order`
                            INNER JOIN `order_delivery`
                                ON `order_delivery`.`order_id` = `order`.`id`
                                AND `order_delivery`.`order_version_id` = `order`.`version_id`
                        ) AS `primary_order_delivery`
                            ON `primary_order_delivery`.`order_id` = `order`.`id`
                            AND `primary_order_delivery`.`order_version_id` = `order`.`version_id`
                            AND `primary_order_delivery`.`row_number` = 1

                        {$reservedStockCalculationEvent->getAdditionalJoinsSQL()}

                        WHERE
                            (
                                -- Order deliveries do not have to exist starting with SW6.4.19.0 when digital products were
                                -- introduced. In such a case only the order state should determine if the order reserves stock
                                -- or not.
                                `primary_order_delivery`.`state_id` IS NULL
                                OR `primary_order_delivery`.`state_id` NOT IN (:ignoredOrderDeliveryStateIds)
                            )
                            {$reservedStockCalculationEvent->getAdditionalWhereConditionsSQL()}
                        GROUP BY
                            `order_line_item`.`product_id`,
                            `order_line_item`.`product_version_id`,
                            `order`.`id`,
                            `order`.`version_id`
                    ) AS `order_product_quantity`
                    LEFT JOIN `pickware_erp_stock` AS `stock`
                        ON `stock`.`product_id` = `order_product_quantity`.`product_id`
                        AND `stock`.`product_version_id` = `order_product_quantity`.`product_version_id`
                        AND `stock`.`order_id` = `order_product_quantity`.`order_id`
                        AND `stock`.`order_version_id` = `order_product_quantity`.`order_version_id`
                    GROUP BY
                        `order_product_quantity`.`product_id`,
                        `order_product_quantity`.`product_version_id`
                    SQL,
                [
                    'ignoredOrderStateIds' => Uuid::fromHexToBytesList($ignoredOrderStateIds),
                    'ignoredOrderDeliveryStateIds' => Uuid::fromHexToBytesList($ignoredOrderDeliveryStateIds),
                    'allowedReturnOrderStateId' => Uuid::fromHexToBytes($allowedReturnOrderStateId),
                    'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'productIds' => Uuid::fromHexToBytesList($productIds),
                    'orderLineItemTypeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                ],
                [
                    'ignoredOrderStateIds' => ArrayParameterType::BINARY,
                    'ignoredOrderDeliveryStateIds' => ArrayParameterType::BINARY,
                    'productIds' => ArrayParameterType::BINARY,
                ],
            );

            // Set existing but not updated products to 0 reserved stock
            $updatedProductIds = array_unique(array_map(
                fn($row) => $row['product_id'],
                $pickwareProductReservedStocks,
            ));
            $nonUpdatedProductIds = array_diff($existingProductIds, $updatedProductIds);
            // INSERT INTO ... ON DUPLICATE KEY UPDATE is used here because MySQL does not support bulk update queries.
            // Luckily the pickware_erp_pickware_product exists already anyway so only the update clause is relevant.
            $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
                'pickware_erp_pickware_product',
                array_map(
                    fn($nonUpdatedProductId) => [
                        'id' => Uuid::randomBytes(),
                        'product_id' => $nonUpdatedProductId,
                        'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                        'internal_reserved_stock' => 0,
                        'updated_at' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'created_at' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                    $nonUpdatedProductIds,
                ),
                [],
                ['internal_reserved_stock'],
            );

            // While testing optimizations on a larger shop system we saw that 5000 is a batch size which has great
            // performance while also having a size large enough that smaller shops can update everything in one go to
            // not waste performance on those systems.
            // Further references: https://github.com/pickware/shopware-plugins/issues/3324 and linked tickets
            $batches = array_chunk($pickwareProductReservedStocks, 5000);
            foreach ($batches as $batch) {
                $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
                    'pickware_erp_pickware_product',
                    $batch,
                    [],
                    ['internal_reserved_stock'],
                );
            }

            $this->paperTrailLoggingService->logPaperTrailEvent(
                'Internally reserved stock was recalculated for products',
                [
                    'productIds' => $productIds,
                    'productIdsWithZeroInternalReservedStock' => Uuid::fromBytesToHexList($nonUpdatedProductIds),
                    'newInternalReservedStockByProductId' => array_merge(...array_map(
                        fn(array $row) => [Uuid::fromBytesToHex($row['product_id']) => $row['internal_reserved_stock']],
                        $pickwareProductReservedStocks,
                    )),
                ],
            );
        });

        // This event is dispatched outside the transaction because in case the event was inside the transaction and
        // subsequent queries, executed in this event, deadlock, the whole transaction, including the heavy reserved
        // stock calculation, would be retried.
        $this->eventDispatcher->dispatch(new ProductReservedStockUpdatedEvent($productIds, $context));
    }

    /**
     * Check if all given orders are externally managed (e.g., by Shopify).
     * Returns true if all orders are externally managed, false if at least one order is not.
     *
     * @param array<string> $orderIds
     */
    private function checkAllOrdersExternallyManaged(array $orderIds): bool
    {
        if (count($orderIds) === 0) {
            // Defensively return false in case order id fetching fails, such that reserved stock is updated.
            // An example of this happening is when the order is deleted before the reserved stock is updated.
            return false;
        }

        $event = new DetermineOrderExternallyManagedEvent($orderIds);
        $this->eventDispatcher->dispatch($event);

        return $event->areAllOrdersExternallyManaged();
    }
}
