<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Product;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;

class ProductTopSellerService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getTopSellerIds(string $salesChannelId, int $limit): array
    {
        $orderLineItemProductIds = $this->db->fetchAllAssociative(
            'SELECT DISTINCT LOWER(HEX(`order_line_item`.`product_id`)) AS `productId`
            FROM `order_line_item`
            INNER JOIN `order` ON `order_line_item`.`order_id` = `order`.`id`
            WHERE `order_line_item`.`product_id` IS NOT NULL
            AND `order_line_item`.`version_id` = :liveVersionId
            AND `order`.`version_id` = :liveVersionId
            AND `order`.`sales_channel_id` = :salesChannelId
            AND `order`.`order_date_time` > (UTC_TIMESTAMP(3) - INTERVAL 1 MONTH)
            GROUP BY `order_line_item`.`product_id`
            ORDER BY SUM(`order_line_item`.`quantity`) DESC
            LIMIT :limit',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'salesChannelId' => hex2bin($salesChannelId),
                'limit' => $limit,
            ],
            [
                'limit' => ParameterType::INTEGER,
            ],
        );

        return array_column($orderLineItemProductIds, 'productId');
    }
}
