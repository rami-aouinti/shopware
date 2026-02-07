<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Migration;

use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * This migration ensures that cancelled tracking codes are removed from the tracking codes list of related order
 * deliveries
 */
class Migration1631691913UpdateTrackingCodes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1631691913;
    }

    public function update(Connection $connection): void
    {
        // Build a list of all order deliveries related to at least one tracking code, which has been cancelled
        $orderDeliveriesWithCancelledTrackingCodes = $connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(MIN(delivery.id))) AS orderDeliveryId,
                LOWER(HEX(MIN(delivery.version_id))) AS orderDeliveryVersionId,
                MIN(delivery.tracking_codes) AS trackingCodes,
                -- Unfortunately JSON_ARRAYAGG() is not available in mySQL 5.7.21 (minimum system requirement)
                CONCAT(\'["\', GROUP_CONCAT(tracking_code.tracking_code SEPARATOR \'","\'), \'"]\') AS cancelledTrackingCodes
            FROM order_delivery AS delivery
            LEFT JOIN pickware_shipping_shipment_order_mapping AS order_mapping
                ON order_mapping.order_id = delivery.order_id
            LEFT JOIN pickware_shipping_tracking_code AS tracking_code
                ON tracking_code.shipment_id = order_mapping.shipment_id
            WHERE
                JSON_EXTRACT(tracking_code.meta_information, "$.cancelled") IS TRUE
                AND delivery.version_id = :liveVersionId
            GROUP BY delivery.id',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );

        foreach ($orderDeliveriesWithCancelledTrackingCodes as $orderDeliveryWithCancelledTrackingCodes) {
            $trackingCodes = Json::decodeToObject($orderDeliveryWithCancelledTrackingCodes['trackingCodes']);
            $cancelledTrackingCodes = Json::decodeToObject($orderDeliveryWithCancelledTrackingCodes['cancelledTrackingCodes']);
            $uncancelledTrackingCodes = array_values(array_diff($trackingCodes, $cancelledTrackingCodes));

            // Update the order delivery if there are cancelled tracking codes, which haven't been removed from the
            // stored list of tracking codes yet
            if (count($uncancelledTrackingCodes) < count($trackingCodes)) {
                $connection->executeStatement(
                    'UPDATE order_delivery
                    SET tracking_codes = :trackingCodes
                    WHERE id = :orderDeliveryId and version_id = :orderDeliveryVersionId',
                    [
                        'trackingCodes' => Json::stringify($uncancelledTrackingCodes),
                        'orderDeliveryId' => hex2bin($orderDeliveryWithCancelledTrackingCodes['orderDeliveryId']),
                        'orderDeliveryVersionId' => hex2bin($orderDeliveryWithCancelledTrackingCodes['orderDeliveryVersionId']),
                    ],
                );
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
