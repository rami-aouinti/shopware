<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Config;

use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;
use Pickware\SwissPostBundle\Api\SwissPostApiClientConfig;

class SwissPostConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareSwissPostBundle.swiss-post';

    public function getApiCredentials(): SwissPostApiClientConfig
    {
        $this->config->assertNotEmpty('clientId');
        $this->config->assertNotEmpty('clientSecret');

        return new SwissPostApiClientConfig(
            $this->config['clientId'],
            $this->config['clientSecret'],
        );
    }

    public function getFrankingLicense(): string
    {
        $this->config->assertNotEmpty('frankingLicense');

        return $this->config['frankingLicense'];
    }

    public function getPostFrankingLicense(): string
    {
        $this->config->assertNotEmpty('postFrankingLicense');

        return $this->config['postFrankingLicense'];
    }

    public function getRegisteredIntlFrankingLicense(): string
    {
        $this->config->assertNotEmpty('registeredIntlFrankingLicense');

        return $this->config['registeredIntlFrankingLicense'];
    }

    public function getDomicilePostOffice(): string
    {
        $this->config->assertNotEmpty('domicilePostOffice');

        return $this->config['domicilePostOffice'];
    }

    public function useTestingWebservice(): bool
    {
        return $this->config['useTestingWebservice'] ?? false;
    }
}
