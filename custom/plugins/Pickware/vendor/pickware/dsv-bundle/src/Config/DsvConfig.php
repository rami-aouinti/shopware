<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Config;

use Pickware\DsvBundle\Api\DsvApiClientConfig;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareDsvBundle.dsv';

    public function getApiCredentials(): DsvApiClientConfig
    {
        $this->config->assertNotEmpty('username');
        $this->config->assertNotEmpty('password');

        return new DsvApiClientConfig(
            username: $this->config['username'],
            password: $this->config['password'],
            shouldUseTestingEndpoint: $this->config['useTestingEndpoint'] ?? false,
        );
    }

    public function getCustomerNumber(): string
    {
        $this->config->assertNotEmpty('customerNumber');

        return $this->config['customerNumber'];
    }
}
