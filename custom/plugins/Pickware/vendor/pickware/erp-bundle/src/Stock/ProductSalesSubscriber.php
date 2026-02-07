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

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The product.sales update is queued in this subscriber. See this issue: https://github.com/pickware/shopware-plugins/issues/2852
 * The update itself is done asynchronously and periodically in a scheduled task. See this isse: https://github.com/pickware/shopware-plugins/issues/3408
 */
class ProductSalesSubscriber implements EventSubscriberInterface
{
    private Connection $connection;
    private ProductSalesUpdater $productSalesUpdater;

    public function __construct(Connection $connection, ProductSalesUpdater $productSalesUpdater)
    {
        $this->connection = $connection;
        $this->productSalesUpdater = $productSalesUpdater;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'stateChanged',
        ];
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $productIds = $this->connection->fetchFirstColumn(
            'SELECT HEX(`product_id`) FROM `order_line_item`
            WHERE
                `order_line_item`.`type` = :lineItemType
                AND `order_line_item`.`version_id` = :liveVersionId
                AND `order_line_item`.`order_id` = :orderId
                AND `order_line_item`.`product_id` IS NOT NULL;',
            [
                'orderId' => hex2bin($event->getEntityId()),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'lineItemType' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
        );

        $this->productSalesUpdater->addProductsToUpdateQueue($productIds);
    }
}
