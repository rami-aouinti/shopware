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

class Migration1707394945ChangeReturnOrderLineItemPercentagePricesToAbsolutePrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1707394945;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_erp_return_order_line_item` AS returnOrderLineItem
            SET `price_definition` = JSON_OBJECT(
                "type", "absolute",
                "filter", null,
                "quantity", returnOrderLineItem.`quantity`,
                "price", returnOrderLineItem.`unit_price`
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(returnOrderLineItem.`price_definition`,"$.type")) = "percentage";',
        );

        // Before this migration, prices could not be recalculated if the quantity was changed to 0. So we need to
        // update the `price` according to that quantity. (unit price and total price are taken from the `price` json)
        $connection->executeStatement(
            'UPDATE `pickware_erp_return_order_line_item` AS returnOrderLineItem
            SET `price` = JSON_OBJECT(
                "quantity", 0,
                "unitPrice", returnOrderLineItem.`unit_price`,
                "totalPrice", 0.0,
                "taxRules", JSON_EXTRACT(returnOrderLineItem.`price`,"$.taxRules"),
                "calculatedTaxes", JSON_ARRAY(
                    JSON_OBJECT(
                        "tax", 0.0,
                        "price", 0.0,
                        "taxRate", JSON_UNQUOTE(JSON_EXTRACT(returnOrderLineItem.`price`,"$.taxRules[0].taxRate")),
                        "extensions", JSON_ARRAY()
                    )
                ),
                "listPrice", null,
                "referencePrice", null,
                "regulationPrice", null
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(returnOrderLineItem.`price_definition`,"$.type")) = "absolute"
            AND returnOrderLineItem.`quantity` = 0;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
