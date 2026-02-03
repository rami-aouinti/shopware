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
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1636624220AddPriceMatrixToClickAndCollectShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1636624220;
    }

    public function update(Connection $connection): void
    {
        $existingClickAndCollectShippingMethod = $connection->fetchOne(
            'SELECT * FROM `shipping_method`
            WHERE `id` = :id',
            [
                'id' => hex2bin('b7805a59e9df43cc8a51c0a1704026d3'),
            ],
        );
        if (!$existingClickAndCollectShippingMethod) {
            return;
        }

        $existingClickAndCollectShippingMethodPrice = $connection->fetchOne(
            'SELECT * FROM `shipping_method_price`
            WHERE `shipping_method_id` = :id',
            [
                'id' => hex2bin('b7805a59e9df43cc8a51c0a1704026d3'),
            ],
        );
        if ($existingClickAndCollectShippingMethodPrice) {
            return;
        }

        $connection->executeStatement(
            'INSERT INTO `shipping_method_price` (
                `id`,
                `shipping_method_id`,
                `calculation`,
                `currency_price`,
                `quantity_start`,
                `created_at`
            ) VALUES(
                :id,
                :shippingMethodId,
                2,
                :currencyPrice,
                0,
                UTC_TIMESTAMP(3)
            )',
            [
                'id' => Uuid::randomBytes(),
                'shippingMethodId' => hex2bin('b7805a59e9df43cc8a51c0a1704026d3'),
                'currencyPrice' => Json::stringify([
                    'c' . Defaults::CURRENCY => [
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 0.0,
                        'gross' => 0.0,
                        'linked' => false,
                    ],
                ]),
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
