<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Doctrine\DBAL\Connection;

class DeliveryParcelTrackingCodeMatchingService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Fetches and returns the IDs of deliveries that contain an unshipped tracking code that matches a given
     * shipping label barcode value.
     *
     * The matching works by finding either tracking codes whose `trackingCode` is a substring of the given barcode value
     * or tracking codes where the barcode value is a substring of their `trackingCode`.
     * This accounts for the fact that shipping label barcodes often contain more information than just the respective
     * tracking code.
     *
     * Since tracking codes are not unique and might be reused by shipping providers a barcode value can have multiple
     * matches across deliveries. However, the WMS app uses this kind of matching to find a delivery for a
     * scanned shipping label barcode, which is done when shipping it. In that case having more than one result is
     * not desirable, especially because over time we would get more and more results for the same barcode value. To
     * work around this problem we only consider "unshipped" tracking codes, as the tracking codes we are interested in
     * when shipping a delivery have not been shipped yet, while older duplicates will very likely be shipped at
     * that point.
     * Please note that currently the problem described above is a non-issue, as we delete deliveries incl. their
     * tracking codes upon shipping them. However, we decided to keep this additional filter and respective explanation
     * to preserve that knowledge.
     */
    public function getIdsOfDeliveriesMatchingShippingLabelBarcodeValue(string $shippingLabelBarcodeValue): array
    {
        return $this->db->fetchFirstColumn(
            'SELECT
                DISTINCT LOWER(HEX(`pickware_wms_delivery_parcel`.`delivery_id`))
            FROM `pickware_wms_delivery_parcel`
            INNER JOIN `pickware_wms_delivery_parcel_tracking_code` AS `mapping`
                ON `pickware_wms_delivery_parcel`.`id` = `mapping`.`delivery_parcel_id`
            INNER JOIN `pickware_shipping_tracking_code` AS `tracking_code`
                ON `mapping`.`tracking_code_id` = `tracking_code`.`id`
                AND JSON_EXTRACT(`tracking_code`.`meta_information`, "$.cancelled") IS NULL OR JSON_EXTRACT(`tracking_code`.`meta_information`, "$.cancelled") = false
            WHERE
                `pickware_wms_delivery_parcel`.`shipped` = 0
                AND (
                    :shippingLabelBarcodeValue LIKE CONCAT("%", `tracking_code`.`tracking_code`, "%")
                    OR `tracking_code`.`tracking_code` LIKE CONCAT("%", :shippingLabelBarcodeValue, "%")
                )',
            [
                'shippingLabelBarcodeValue' => $shippingLabelBarcodeValue,
            ],
        );
    }
}
