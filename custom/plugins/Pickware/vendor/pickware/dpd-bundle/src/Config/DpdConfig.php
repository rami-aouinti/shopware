<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Config;

use Pickware\DpdBundle\Adapter\DpdLabelSize;
use Pickware\DpdBundle\Api\DpdApiClientConfig;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;

class DpdConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareDpdBundle.dpd';

    public static function createDefault(): self
    {
        return new self(new Config(self::CONFIG_DOMAIN, [
            'labelSize' => 'A6',
        ]));
    }

    public function getApiClientConfig(string $localeCode): DpdApiClientConfig
    {
        $this->config->assertNotEmpty('delisId');
        $this->config->assertNotEmpty('password');

        return new DpdApiClientConfig(
            delisId: $this->config['delisId'],
            password: $this->config['password'],
            shouldUseTestingEndpoint: $this->config['useTestingEndpoint'] ?? false,
            localeCode: $localeCode,
        );
    }

    public function getCustomerNumber(): string
    {
        $this->config->assertNotEmpty('customerNumber');

        return $this->config['customerNumber'];
    }

    public function getSendingDepotId(): string
    {
        $this->config->assertNotEmpty('sendingDepotId');

        return $this->config['sendingDepotId'];
    }

    public function getLabelSize(): DpdLabelSize
    {
        $this->config->assertNotEmpty('labelSize');

        return DpdLabelSize::from($this->config['labelSize']);
    }
}
