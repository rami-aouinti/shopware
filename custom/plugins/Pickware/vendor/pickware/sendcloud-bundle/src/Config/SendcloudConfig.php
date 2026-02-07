<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Config;

use Pickware\SendcloudBundle\Api\SendcloudApiClientConfig;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SendcloudConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareSendcloudBundle.sendcloud';

    public function getApiCredentials(): SendcloudApiClientConfig
    {
        $this->config->assertNotEmpty('publicKey');
        $this->config->assertNotEmpty('secretKey');

        return new SendcloudApiClientConfig(
            publicKey: $this->config['publicKey'],
            secretKey: $this->config['secretKey'],
        );
    }
}
