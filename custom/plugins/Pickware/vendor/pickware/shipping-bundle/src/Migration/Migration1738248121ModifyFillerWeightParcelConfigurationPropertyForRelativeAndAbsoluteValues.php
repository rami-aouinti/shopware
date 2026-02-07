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
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1738248121ModifyFillerWeightParcelConfigurationPropertyForRelativeAndAbsoluteValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1738248121;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config`
                SET
                    `parcel_packing_configuration` = JSON_SET(
                        `parcel_packing_configuration`,
                        "$.fillerWeightAbsoluteSurchargePerParcel",
                        JSON_EXTRACT(
                            `parcel_packing_configuration`,
                            "$.fillerWeightPerParcel"
                        ),
                        "$.fillerWeightRelativeSurchargePerParcel",
                        0
                    );',
        );

        $connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config`
                SET
                    `parcel_packing_configuration` = JSON_REMOVE(
                        `parcel_packing_configuration`,
                        "$.fillerWeightPerParcel"
                    );',
        );
    }
}
