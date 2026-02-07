<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Config;

use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;
use Pickware\UpsBundle\Adapter\UpsLabelSize;
use Pickware\UpsBundle\Api\UpsApiClientConfig;

class UpsConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareUpsBundle.ups';

    public static function createDefault(): self
    {
        return new self(new Config(self::CONFIG_DOMAIN, [
            'labelSize' => UpsLabelSize::A5->value,
        ]));
    }

    public function getApiCredentials(): UpsApiClientConfig
    {
        $this->config->assertNotEmpty('clientId');
        $this->config->assertNotEmpty('clientSecret');

        return new UpsApiClientConfig(
            $this->config['clientId'],
            $this->config['clientSecret'],
            $this->config['useTestingEndpoint'] ?? false,
        );
    }

    public function getShipperNumber(): string
    {
        $this->config->assertNotEmpty('shipperNumber');

        return $this->config['shipperNumber'];
    }

    public function isNegotiatedRatesEnabled(): bool
    {
        return $this->config['negotiatedRates'] ?? false;
    }

    public function isDispatchNotificationEnabled(): bool
    {
        return $this->config['enableDispatchNotification'] ?? false;
    }

    public function getCustomTextForDispatchNotifications(): string
    {
        return $this->config['customTextForDispatchNotifications'] ?? '';
    }

    public function isDeliveryNotificationEnabled(): bool
    {
        return $this->config['enableDeliveryNotification'] ?? false;
    }

    public function getLabelSize(): UpsLabelSize
    {
        $this->config->assertNotEmpty('labelSize');

        return UpsLabelSize::from($this->config['labelSize']);
    }

    public function useTestingEndpoint(): bool
    {
        return $this->config['useTestingEndpoint'] ?? false;
    }
}
