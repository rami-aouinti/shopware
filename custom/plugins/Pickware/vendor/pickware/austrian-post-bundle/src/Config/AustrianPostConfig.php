<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Config;

use Pickware\AustrianPostBundle\Adapter\AustrianPostLabelSize;
use Pickware\AustrianPostBundle\Api\AustrianPostApiClientConfig;
use Pickware\ShippingBundle\Config\ConfigDecoratorTrait;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareAustrianPostBundle.austrian-post';

    public function getApiClientConfig(): AustrianPostApiClientConfig
    {
        $this->config->assertNotEmpty('clientId');
        $this->config->assertNotEmpty('orgUnitId');
        $this->config->assertNotEmpty('orgUnitGuid');

        return new AustrianPostApiClientConfig(
            $this->config['clientId'],
            $this->config['orgUnitId'],
            $this->config['orgUnitGuid'],
            $this->config['useTestingEndpoint'] ?? false,
        );
    }

    public function getLabelSize(): AustrianPostLabelSize
    {
        $this->config->assertNotEmpty('labelSize');

        return AustrianPostLabelSize::from($this->config['labelSize']);
    }

    public function getPrinterSettings(): AustrianPostPrinterConfig
    {
        $this->config->assertNotEmpty('labelSize');

        return new AustrianPostPrinterConfig(labelSize: AustrianPostLabelSize::from($this->config['labelSize']));
    }

    public function getBankTransferData(): ?AustrianPostBankDataConfig
    {
        $this->config->assertNotEmpty('bankTransferDataIban');
        $this->config->assertNotEmpty('bankTransferDataBic');
        $this->config->assertNotEmpty('bankTransferDataAccountOwnerName');

        return new AustrianPostBankDataConfig(
            accountOwnerName: $this->config['bankTransferDataAccountOwnerName'],
            iban: $this->config['bankTransferDataIban'],
            bic: $this->config['bankTransferDataBic'],
        );
    }
}
