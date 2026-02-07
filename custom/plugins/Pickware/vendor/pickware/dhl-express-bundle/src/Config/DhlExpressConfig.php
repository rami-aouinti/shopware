<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Config;

use Pickware\DhlExpressBundle\Api\DhlExpressApiClientConfig;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;

class DhlExpressConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareDhlExpressBundle.dhl-express';

    public function getApiCredentials(): DhlExpressApiClientConfig
    {
        $this->config->assertNotEmpty('username');
        $this->config->assertNotEmpty('password');

        return new DhlExpressApiClientConfig(
            $this->config['username'],
            $this->config['password'],
            $this->config['useTestingEndpoint'] ?? false,
        );
    }

    public function getShipperNumber(): string
    {
        $this->config->assertNotEmpty('shipperNumber');

        return $this->config['shipperNumber'];
    }

    public function isDispatchNotificationEnabled(): bool
    {
        return $this->config['enableDispatchNotification'] ?? false;
    }
}
