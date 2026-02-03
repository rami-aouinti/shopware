<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IncomingStockUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityPreWriteValidationEventDispatcher::getEventName(GoodsReceiptLineItemDefinition::ENTITY_NAME) => 'requestChangeSet',
            GoodsReceiptDefinition::ENTITY_WRITTEN_EVENT => 'goodsReceiptWritten',
            GoodsReceiptLineItemDefinition::ENTITY_WRITTEN_EVENT => 'goodsReceiptLineItemWritten',
            GoodsReceiptLineItemDefinition::ENTITY_DELETED_EVENT => 'goodsReceiptLineItemDeleted',
            EntityPreWriteValidationEventDispatcher::getEventName(SupplierOrderLineItemDefinition::ENTITY_NAME) => 'requestChangeSet',
            SupplierOrderLineItemDefinition::ENTITY_WRITTEN_EVENT => 'supplierOrderLineItemWritten',
            SupplierOrderLineItemDefinition::ENTITY_DELETED_EVENT => 'supplierOrderLineItemDeleted',
            SupplierOrderDefinition::ENTITY_WRITTEN_EVENT => 'supplierOrderWritten',
        ];
    }

    public function goodsReceiptWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $goodsReceiptIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!array_key_exists('stateId', $payload)) {
                // Only update incoming stock when the goods receipt state changes
                continue;
            }

            $goodsReceiptIds[] = $payload['id'];
        }

        if (count($goodsReceiptIds) === 0) {
            return;
        }

        $goodsReceiptLineItemProductIds = $this->db->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`product_id`))
            FROM `pickware_erp_goods_receipt_line_item`
            WHERE `goods_receipt_id` IN (:goodsReceiptIds)
                AND `supplier_order_id` IS NOT NULL',
            ['goodsReceiptIds' => array_map('hex2bin', $goodsReceiptIds)],
            ['goodsReceiptIds' => ArrayParameterType::STRING],
        );

        $this->recalculateIncomingStock(array_filter($goodsReceiptLineItemProductIds));
    }

    public function requestChangeSet($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command instanceof ChangeSetAware) {
                $command->requestChangeSet();
            }
        }
    }

    public function goodsReceiptLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $goodsReceiptLineItemIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!array_key_exists('quantity', $payload)) {
                // Only update incoming stock when the goods receipt line item quantity changes
                continue;
            }
            $goodsReceiptLineItemIds[] = $payload['id'];
        }
        if (count($goodsReceiptLineItemIds) === 0) {
            return;
        }

        $goodsReceiptLineItemProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(`product_id`)) AS productId
            FROM `pickware_erp_goods_receipt_line_item`
            WHERE `id` IN (:goodsReceiptLineItemIds)
            -- Only update incoming stock when the goods receipt belongs to a supplier order
            AND `supplier_order_id` IS NOT NULL',
            ['goodsReceiptLineItemIds' => array_map('hex2bin', $goodsReceiptLineItemIds)],
            ['goodsReceiptLineItemIds' => ArrayParameterType::STRING],
        );
        $productIds = array_filter(array_column($goodsReceiptLineItemProductIds, 'productId'));

        $this->recalculateIncomingStock($productIds);
    }

    public function goodsReceiptLineItemDeleted(EntityDeletedEvent $entityDeletedEvent): void
    {
        if ($entityDeletedEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($entityDeletedEvent->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            if (!$changeSet->getBefore('supplier_order_id')) {
                // Only update incoming stock when the goods receipt belongs to a supplier order
                continue;
            }
            $productIds[] = bin2hex($changeSet->getBefore('product_id'));
        }

        $this->recalculateIncomingStock($productIds);
    }

    public function supplierOrderLineItemDeleted(EntityDeletedEvent $entityDeletedEvent): void
    {
        if ($entityDeletedEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($entityDeletedEvent->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $productId = $changeSet->getBefore('product_id');
            // do not recalculate incoming stock for deleted products
            if ($productId === null) {
                continue;
            }
            $productIds[] = bin2hex($productId);
        }

        $this->recalculateIncomingStock($productIds);
    }

    public function supplierOrderLineItemWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $supplierOrderLineItemIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $supplierOrderLineItemIds[] = $payload['id'];
        }

        if (count($supplierOrderLineItemIds) === 0) {
            return;
        }

        $supplierOrderLineItemProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(product_id)) AS productId
            FROM pickware_erp_supplier_order_line_item
            WHERE id IN (:supplierOrderLineItemIds)',
            ['supplierOrderLineItemIds' => array_map('hex2bin', $supplierOrderLineItemIds)],
            ['supplierOrderLineItemIds' => ArrayParameterType::STRING],
        );
        $productIds = array_filter(array_column($supplierOrderLineItemProductIds, 'productId'));

        $this->recalculateIncomingStock($productIds);
    }

    public function supplierOrderWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $supplierOrderIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            // It may happen that the payload is missing, for example if only a referencing entity was changed. In that
            // case we just ignore that supplier order. (We cannot determine which one was referenced here anyway.)
            if (!isset($payload['id'])) {
                continue;
            }
            $supplierOrderIds[] = $payload['id'];
        }

        if (count($supplierOrderIds) === 0) {
            return;
        }

        $supplierOrderProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(product_id)) AS productId
            FROM pickware_erp_supplier_order_line_item
            JOIN pickware_erp_supplier_order ON pickware_erp_supplier_order_line_item.supplier_order_id = pickware_erp_supplier_order.id
            WHERE pickware_erp_supplier_order.id IN (:supplierOrderIds)',
            ['supplierOrderIds' => array_map('hex2bin', $supplierOrderIds)],
            ['supplierOrderIds' => ArrayParameterType::STRING],
        );
        $productIds = array_filter(array_column($supplierOrderProductIds, 'productId'));

        $this->recalculateIncomingStock($productIds);
    }

    /**
     * DEPENDS ON pickware products being initialized
     */
    public function recalculateIncomingStock(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        $completedSupplierOrderStateTechnicalNames = [
            SupplierOrderStateMachine::STATE_CANCELLED,
            SupplierOrderStateMachine::STATE_COMPLETED,
            SupplierOrderStateMachine::STATE_DELIVERED,
        ];

        // This query is structured in the following way:
        // 1. Supplier order line items are inner joined with supplier orders and left joined with goods receipt line items
        // 2. The result of these two joins is grouped by supplier order line item id to calculate the incoming stock per
        //    product and supplier order
        //     - Note that this step assumes the unique key on (supplier_order_id, product_id, product_version_id) in
        //       pickware_erp_supplier_order_line_item exists, but it also needs to support multiple goods receipt line
        //       items per product in the same goods receipt
        //     -> The unique key allows us to not SUM the supplier order line item quantity, picking any quantity will be fine
        //     -> Grouping by supplier order line item id is the same as grouping by (supplier_order_id, product_id, product_version_id)
        // 3. The result of this is re-grouped only by product to sum up the incoming stock per product
        $this->db->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_pickware_product`
                LEFT JOIN (
                    SELECT
                        `product`.`id`,
                        `product`.`version_id`,
                        IFNULL(SUM(`openSupplierOrderLineItems`.`supplier_order_incoming_stock`), 0) as `incoming_stock`
                    FROM `product`
                    LEFT JOIN (
                        SELECT
                        GREATEST(
                            0,
                            IFNULL(`supplierOrderLineItem`.`quantity`, 0) - IFNULL(SUM(
                                IF(`goodsReceiptState`.`technical_name` NOT IN (:goodsReceiptIgnoreStates), `goodsReceiptLineItem`.`quantity`, 0)
                            ), 0)
                        ) AS `supplier_order_incoming_stock`,
                        `supplierOrderLineItem`.`product_id`,
                        `supplierOrderLineItem`.`product_version_id`
                    FROM `pickware_erp_supplier_order_line_item` AS `supplierOrderLineItem`
                    LEFT JOIN `pickware_erp_goods_receipt_line_item` AS `goodsReceiptLineItem`
                        ON `supplierOrderLineItem`.`supplier_order_id` = `goodsReceiptLineItem`.`supplier_order_id`
                        AND `supplierOrderLineItem`.`product_id` = `goodsReceiptLineItem`.`product_id`
                        AND `supplierOrderLineItem`.`product_version_id` = `goodsReceiptLineItem`.`product_version_id`
                    LEFT JOIN `pickware_erp_goods_receipt` AS `goodsReceipt`
                        ON `goodsReceipt`.`id` = `goodsReceiptLineItem`.`goods_receipt_id`
                    LEFT JOIN `state_machine_state` AS `goodsReceiptState`
                        ON `goodsReceiptState`.`id` = `goodsReceipt`.`state_id`
                    INNER JOIN `pickware_erp_supplier_order` AS `supplierOrder`
                        ON `supplierOrder`.`id` = `supplierOrderLineItem`.`supplier_order_id`
                    LEFT JOIN `state_machine_state` AS `supplierOrderState`
                        ON `supplierOrderState`.`id` = `supplierOrder`.`state_id`
                    WHERE
                        `supplierOrderState`.`technical_name` NOT IN (:completedSupplierOrderStateTechnicalNames)
                    GROUP BY
                        `supplierOrderLineItem`.`id`
                    ) AS `openSupplierOrderLineItems`
                        ON `openSupplierOrderLineItems`.`product_id` = `product`.`id`
                        AND `openSupplierOrderLineItems`.`product_version_id` = `product`.`version_id`
                    GROUP BY
                        `product`.`id`,
                        `product`.`version_id`
                ) AS `productIncomingStock`
                    ON `productIncomingStock`.`id` = `pickware_erp_pickware_product`.`product_id`
                    AND `productIncomingStock`.`version_id` = `pickware_erp_pickware_product`.`product_version_id`
                SET
                    `pickware_erp_pickware_product`.`incoming_stock` = `productIncomingStock`.`incoming_stock`
                WHERE
                    `pickware_erp_pickware_product`.`product_version_id` = :liveVersionId
                    AND `pickware_erp_pickware_product`.`product_id` IN (:productIds)
                SQL,
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productIds' => array_map('hex2bin', $productIds),
                'completedSupplierOrderStateTechnicalNames' => $completedSupplierOrderStateTechnicalNames,
                'goodsReceiptIgnoreStates' => [
                    GoodsReceiptStateMachine::STATE_CREATED,
                ],
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'completedSupplierOrderStateTechnicalNames' => ArrayParameterType::STRING,
                'goodsReceiptIgnoreStates' => ArrayParameterType::STRING,
            ],
        );
    }
}
