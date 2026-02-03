<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Logging;

use Doctrine\DBAL\Connection;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderLoggingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function logOrderShipment(string $orderId): void
    {
        if (!$this->featureFlagService->isActive(OrderLoggingProdFeatureFlag::NAME)) {
            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_order_log` (
                `id`,
                `order_id`,
                `order_shipment_created_at`
            ) VALUES (
                :id,
                :order_id,
                UTC_TIMESTAMP(3)
            )',
            [
                'id' => Uuid::randomBytes(),
                'order_id' => hex2bin($orderId),
            ],
        );
    }
}
