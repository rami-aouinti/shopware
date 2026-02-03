<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPostWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PickwareErpPickwareOrderLineItemInitializer implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Post->getEventName(OrderLineItemDefinition::ENTITY_NAME) => [
                'orderLineItemPostWriteValidation',
            ],
        ];
    }

    public function orderLineItemPostWriteValidation(EntityPostWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderLineItemIds = ImmutableCollection::create($event->getCommands())
            ->filter(fn(WriteCommand $writeCommand) => $writeCommand instanceof InsertCommand)
            ->map(fn(WriteCommand $writeCommand) => $writeCommand->getPrimaryKey()['id'])
            ->map(bin2hex(...))
            ->asArray();

        $this->ensureLivePickwareErpPickwareOrderLineItemExists($orderLineItemIds);
    }

    private function ensureLivePickwareErpPickwareOrderLineItemExists(array $orderLineItemIds): void
    {
        if (count($orderLineItemIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_pickware_order_line_item` (
                `id`,
                `version_id`,
                `order_line_item_id`,
                `order_line_item_version_id`,
                `externally_fulfilled_quantity`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                :liveVersionId,
                `orderLineItem`.`id`,
                `orderLineItem`.`version_id`,
                0,
                UTC_TIMESTAMP(3)
            FROM `order_line_item` AS `orderLineItem`
            LEFT JOIN `pickware_erp_pickware_order_line_item` AS `pickwareErpOrderLineItem`
                ON `orderLineItem`.`id` = `pickwareErpOrderLineItem`.`order_line_item_id`
                AND `orderLineItem`.`version_id` = `pickwareErpOrderLineItem`.`order_line_item_version_id`
            WHERE
                `orderLineItem`.`id` IN (:ids)
                AND `orderLineItem`.`version_id` = :liveVersionId
                AND `pickwareErpOrderLineItem`.`id` IS NULL',
            [
                'ids' => array_map('hex2bin', $orderLineItemIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ],
        );
    }

    public function ensurePickwareErpPickwareOrderLineItemsExistForAllOrderLineItems(): void
    {
        RetryableTransaction::retryable($this->db, function(): void {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_pickware_order_line_item` (
                    `id`,
                    `version_id`,
                    `order_line_item_id`,
                    `order_line_item_version_id`,
                    `externally_fulfilled_quantity`,
                    `created_at`
                ) SELECT
                    ' . SqlUuid::UUID_V4_GENERATION . ',
                    `orderLineItem`.`version_id`,
                    `orderLineItem`.`id`,
                    `orderLineItem`.`version_id`,
                    0,
                    UTC_TIMESTAMP(3)
                FROM `order_line_item` AS `orderLineItem`
                LEFT JOIN `pickware_erp_pickware_order_line_item` AS `pickwareErpOrderLineItem`
                    ON `orderLineItem`.`id` = `pickwareErpOrderLineItem`.`order_line_item_id`
                    AND `orderLineItem`.`version_id` = `pickwareErpOrderLineItem`.`order_line_item_version_id`
                WHERE `pickwareErpOrderLineItem`.`id` IS NULL
                ',
            );
        });
    }
}
