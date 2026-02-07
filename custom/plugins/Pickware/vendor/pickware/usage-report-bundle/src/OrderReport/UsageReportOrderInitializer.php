<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\OrderReport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPostWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\UsageReportBundle\Model\UsageReportOrderType;
use Pickware\UsageReportBundle\PickwareUsageReportBundle;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsageReportOrderInitializer implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Post->getEventName(OrderDefinition::ENTITY_NAME) => 'onOrderPostWriteValidation',
        ];
    }

    public function onOrderPostWriteValidation(EntityPostWriteValidationEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderIds = [];
        foreach ($entityWrittenEvent->getCommands() as $command) {
            if ($command instanceof InsertCommand) {
                $orderIds[] = bin2hex($command->getPrimaryKey()['id']);
            }
        }

        if (count($orderIds) === 0) {
            return;
        }

        $this->ensureUsageReportOrdersExist($orderIds, $entityWrittenEvent->getContext());
    }

    private function ensureUsageReportOrdersExist(array $orderIds, Context $context): void
    {
        if (count($orderIds) === 0) {
            return;
        }

        $usageReportOrderIdFilterEvent = new UsageReportOrderIdFilterEvent($orderIds, $context);
        $this->eventDispatcher->dispatch($usageReportOrderIdFilterEvent);

        $orderTypeCollection = $usageReportOrderIdFilterEvent->getOrderTypeCollection();

        foreach (UsageReportOrderType::cases() as $type) {
            $typeOrderIds = $orderTypeCollection->getOrderIdsByType($type);
            if (count($typeOrderIds) === 0) {
                continue;
            }

            $this->db->executeStatement(
                'INSERT INTO `pickware_usage_report_order` (
                `id`,
                `order_id`,
                `order_version_id`,
                `order_type`,
                `ordered_at`,
                `order_created_at`,
                `order_created_at_hour`,
                `order_snapshot`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `order`.`id`,
                `order`.`version_id`,
                :orderType,
                `order`.`order_date_time`,
                `order`.`created_at`,
                DATE_FORMAT(`order`.`created_at`, "%Y-%m-%d %H:00:00.000"),
                JSON_OBJECT(
                    "orderNumber", `order`.`order_number`,
                    "orderDateTime", `order`.`order_date_time`
                ),
                UTC_TIMESTAMP(3)
            FROM `order`
            LEFT JOIN `pickware_usage_report_order`
                ON `pickware_usage_report_order`.`order_id` = `order`.`id`
                AND `pickware_usage_report_order`.`order_version_id` = `order`.`version_id`
            WHERE `order`.`id` IN (:ids)
                AND `order`.`version_id` = :liveVersionId
                AND `pickware_usage_report_order`.`id` IS NULL',
                [
                    'ids' => array_map('hex2bin', $typeOrderIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'orderType' => $type->value,
                ],
                [
                    'ids' => ArrayParameterType::STRING,
                ],
            );
        }
    }

    /**
     * Ensures usage report orders exist for all orders in the system.
     *
     * This method does not use the event system (UsageReportOrderIdFilterEvent) because it is designed for
     * auto-fixing scenarios where we need to create missing usage report orders for historical data. The Shopify
     * integration does not require this auto-fixing logic, as Shopify orders are properly categorized during
     * normal order creation via the event system.
     *
     * The method directly classifies orders based on the sales channel type to maintain backwards compatibility
     * and avoid the overhead of loading all order IDs into memory and dispatching events for potentially
     * large datasets.
     */
    public function ensureUsageReportOrdersExistForAllOrders(): void
    {
        $this->db->executeStatement(
            'INSERT INTO `pickware_usage_report_order` (
                `id`,
                `order_id`,
                `order_version_id`,
                `order_type`,
                `ordered_at`,
                `order_created_at`,
                `order_created_at_hour`,
                `order_snapshot`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `order`.`id`,
                `order`.`version_id`,
                IF(`sales_channel`.`type_id` = :posSalesChannelTypeId, :pickwarePosType, :regularType),
                `order`.`order_date_time`,
                `order`.`created_at`,
                DATE_FORMAT(`order`.`created_at`, "%Y-%m-%d %H:00:00.000"),
                JSON_OBJECT(
                    "orderNumber", `order`.`order_number`,
                    "orderDateTime", `order`.`order_date_time`
                ),
                UTC_TIMESTAMP(3)
            FROM `order`
            LEFT JOIN `sales_channel` ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `pickware_usage_report_order`
                ON `pickware_usage_report_order`.`order_id` = `order`.`id`
                AND `pickware_usage_report_order`.`order_version_id` = `order`.`version_id`
            WHERE `order`.`version_id` = :liveVersionId
                AND `pickware_usage_report_order`.`id` IS NULL',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'posSalesChannelTypeId' => hex2bin(PickwareUsageReportBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'pickwarePosType' => UsageReportOrderType::PickwarePos->value,
                'regularType' => UsageReportOrderType::Regular->value,
            ],
        );
    }
}
