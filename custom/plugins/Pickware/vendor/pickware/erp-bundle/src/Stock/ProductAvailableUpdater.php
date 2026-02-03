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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductAvailableUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductAvailableStockUpdatedEvent::class => 'productAvailableStockUpdated',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'productWritten',
        ];
    }

    public function productAvailableStockUpdated(ProductAvailableStockUpdatedEvent $event): void
    {
        $this->recalculateProductAvailable($event->getProductIds());
    }

    public function productWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (
                isset($payload['parentId'])
                || isset($payload['versionId'])
                || isset($payload['isCloseout'])
                || isset($payload['minPurchase'])
            ) {
                $productIds[] = $payload['id'];
            }
        }

        $this->recalculateProductAvailable($productIds);
    }

    public function recalculateProductAvailable(array $productIds): void
    {
        if (count($productIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->db, function() use ($productIds): void {
            $this->db->executeStatement(
                'UPDATE `product`
                LEFT JOIN `product` AS `parent`
                    ON `parent`.`id` = `product`.`parent_id` AND `parent`.`version_id` = `product`.`version_id`
                SET `product`.`available` =
                    IF(
                        -- If product is in closeout ...
                        COALESCE(`product`.`is_closeout`, `parent`.`is_closeout`, 0),
                        -- ... it is available if more stock is available than the minimum purchase ...
                        IFNULL(`product`.`available_stock`, 0) >= COALESCE(`product`.`min_purchase`, `parent`.`min_purchase`, 1),
                        -- ... else it is available always
                        1
                    ),
                    `product`.`updated_at` = UTC_TIMESTAMP(3)
                WHERE `product`.`version_id` = :liveVersionId
                    AND (`product`.`id` IN (:productIds) OR `product`.`parent_id` IN (:productIds))',
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'productIds' => array_map('hex2bin', $productIds),
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                ],
            );
        });
    }
}
