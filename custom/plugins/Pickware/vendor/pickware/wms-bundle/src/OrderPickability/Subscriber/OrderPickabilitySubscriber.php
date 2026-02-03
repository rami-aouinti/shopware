<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderPickability\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\OrderPickability\CollectAdditionalOrdersWithoutPickabilityEvent;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityQueryExtensionEvent;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPickabilitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection) {}

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPickabilityQueryExtensionEvent::class => 'addReservedWarehouseStockQuery',
            CollectAdditionalOrdersWithoutPickabilityEvent::class => 'addAdditionalOrdersWithoutPickability',
        ];
    }

    public function addReservedWarehouseStockQuery(OrderPickabilityQueryExtensionEvent $event): void
    {
        $event->setReservedWarehouseStockQuery(
            '(
                SELECT
                    `reserved_item`.`product_id`,
                    `reserved_item`.`product_version_id`,
                    IFNULL(`reserved_item`.`warehouse_id`, `pickware_erp_bin_location`.`warehouse_id`) AS `warehouse_id`,
                    SUM(`reserved_item`.`quantity`) AS `quantity`
                FROM `pickware_wms_picking_process_reserved_item` AS `reserved_item`
                LEFT JOIN `pickware_erp_bin_location`
                    ON `pickware_erp_bin_location`.`id` = `reserved_item`.`bin_location_id`
                WHERE
                    `reserved_item`.`warehouse_id` IS NOT NULL
                    OR `reserved_item`.`bin_location_id` IS NOT NULL
                GROUP BY
                    `reserved_item`.`product_id`,
                    `reserved_item`.`product_version_id`,
                    `warehouse_id`
            )',
        );
    }

    public function addAdditionalOrdersWithoutPickability(CollectAdditionalOrdersWithoutPickabilityEvent $event): void
    {
        $ordersInPendingDeliveries = $this->connection->fetchFirstColumn(
            query: <<<SQL
                SELECT
                    LOWER(HEX(`pickware_wms_delivery`.`order_id`))
                FROM `pickware_wms_delivery`
                INNER JOIN `state_machine_state`
                    ON `state_machine_state`.`id` = `pickware_wms_delivery`.`state_id`
                WHERE
                    `state_machine_state`.`technical_name` NOT IN (:concludedStates)
                SQL,
            params: ['concludedStates' => DeliveryStateMachine::CONCLUDED_STATES],
            types: ['concludedStates' => ArrayParameterType::STRING],
        );

        $event->addOrdersWithoutPickability($ordersInPendingDeliveries);
    }
}
