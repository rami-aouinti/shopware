<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1683626781FixReturnOrderTaxStatus extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1683626781;
    }

    public function update(Connection $connection): void
    {
        // Before this update, return orders were created in tax status NET for all orders (including orders in tax
        // status GROSS). We fixed the return order creation but must migrate existing return orders when their tax
        // status is different from the order. This can only be the case for GROSS orders and NET return orders.
        // To explain the conversion, here is an example from the same return order price in NET, then GROSS:
        // in NET
        //    {
        //      "extensions": [],
        //      "netPrice": 150,
        //      "totalPrice": 178.5,
        //      "calculatedTaxes": [
        //        {
        //          "extensions": [],
        //          "tax": 28.5,
        //          "taxRate": 19,
        //          "price": 150
        //        }
        //      ],
        //      "taxRules": [
        //       {
        //          "extensions": [],
        //          "taxRate": 19,
        //          "percentage": 100
        //        }
        //      ],
        //      "positionPrice": 150,
        //      "taxStatus": "net",
        //      "rawTotal": 178.5
        //    }
        // in GROSS
        //    {
        //      "extensions": [],
        //      "netPrice": 126.05,
        //      "totalPrice": 150,
        //      "calculatedTaxes": [
        //        {
        //          "extensions": [],
        //          "tax": 23.95,
        //          "taxRate": 19,
        //          "price": 150
        //        }
        //      ],
        //      "taxRules": [
        //        {
        //          "extensions": [],
        //          "taxRate": 19,
        //          "percentage": 100
        //        }
        //      ],
        //      "positionPrice": 150,
        //      "taxStatus": "gross",
        //      "rawTotal": 150
        //    }
        $connection->executeStatement(
            'UPDATE `pickware_erp_return_order` returnOrder
            INNER JOIN `order` o ON o.`id` = returnOrder.`order_id` AND o.`version_id` = returnOrder.`order_version_id`

            SET returnOrder.price = JSON_OBJECT(
              "taxStatus", "gross",
              "extensions", JSON_EXTRACT(returnOrder.`price`,"$.extensions"),
              "totalPrice", JSON_EXTRACT(returnOrder.`price`,"$.netPrice"),
              "rawTotal", JSON_EXTRACT(returnOrder.`price`,"$.netPrice"),
              "taxRules", JSON_EXTRACT(returnOrder.`price`,"$.taxRules"),
              "positionPrice", JSON_EXTRACT(returnOrder.`price`,"$.positionPrice"),
              "netPrice", ROUND(
                  JSON_EXTRACT(returnOrder.`price`,"$.netPrice") * (100/(100 + JSON_EXTRACT(returnOrder.`price`,"$.calculatedTaxes[0].taxRate"))),
                  2
              ),
              "calculatedTaxes", JSON_ARRAY(JSON_OBJECT(
                  "price", JSON_EXTRACT(returnOrder.`price`,"$.netPrice"),
                  "taxRate", JSON_EXTRACT(returnOrder.`price`,"$.calculatedTaxes[0].taxRate"),
                  "extensions", JSON_EXTRACT(returnOrder.`price`,"$.calculatedTaxes[0].extensions"),
                  "tax", ROUND(
                      JSON_EXTRACT(returnOrder.`price`,"$.netPrice") * (1 - 100/(100 + JSON_EXTRACT(returnOrder.`price`,"$.calculatedTaxes[0].taxRate"))),
                      2
                  )
              )
            ))

            WHERE json_unquote(JSON_EXTRACT(o.`price`,"$.taxStatus")) = "gross"
            AND json_unquote(JSON_EXTRACT(returnOrder.`price`,"$.taxStatus")) = "net";',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
