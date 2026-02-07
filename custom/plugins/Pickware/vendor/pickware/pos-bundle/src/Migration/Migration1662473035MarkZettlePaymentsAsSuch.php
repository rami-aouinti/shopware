<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1662473035MarkZettlePaymentsAsSuch extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1662473035;
    }

    public function update(Connection $connection): void
    {
        // We have the following preconditions for this migration:
        // Until the execution of this migration, the only POS payment with a transaction id is a zettle payment.
        // Therefore we can detect such by this criteria. We add a key-value pair to the pickwarePosAdditionalInfo to
        // mark this payments as
        $connection->executeStatement('
            UPDATE  `order_transaction`
            SET custom_fields = JSON_MERGE_PATCH(
                custom_fields,
                json_object(
                    "pickwarePosTransactionId",  json_unquote(json_extract(custom_fields, "$.pickwarePosTransactionId")),
                    "pickwarePosAdditionalInfo", JSON_MERGE_PATCH(
                        json_object("bookingMethod", "zettle"),
                        json_extract(custom_fields, "$.pickwarePosAdditionalInfo")
                    )
                )
            )
            WHERE
                json_unquote(json_extract(custom_fields, "$.pickwarePosTransactionId")) IS NOT NULL
                && json_unquote(json_extract(custom_fields, "$.pickwarePosTransactionId")) != "null"
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
