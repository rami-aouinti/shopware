<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier;

use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;

class Carrier
{
    public function __construct(
        private readonly string $technicalName,
        private readonly string $name,
        private readonly string $abbreviation,
        private readonly string $configDomain,
        private readonly ?string $shipmentConfigDescriptionFilePath = null,
        private readonly ?string $storefrontConfigDescriptionFilePath = null,
        private readonly ?string $returnShipmentConfigDescriptionFilePath = null,
        private readonly ?ParcelPackingConfiguration $defaultParcelPackingConfiguration = null,
        private readonly ?string $returnLabelMailTemplateTechnicalName = null,
        private readonly int $batchSize = 1,
        private readonly bool $supportsSenderAddressForShipments = true,
        private readonly bool $supportsReceiverAddressForReturnShipments = false,
        private readonly bool $supportsImporterOfRecordsAddress = false,
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function getConfigDomain(): string
    {
        return $this->configDomain;
    }

    public function getShipmentConfigDescription(): ConfigDescription
    {
        if ($this->shipmentConfigDescriptionFilePath === null) {
            return ConfigDescription::createEmpty();
        }

        return ConfigDescription::readFromYamlFile($this->shipmentConfigDescriptionFilePath);
    }

    public function getStorefrontConfigDescription(): ConfigDescription
    {
        if ($this->storefrontConfigDescriptionFilePath === null) {
            return ConfigDescription::createEmpty();
        }

        return ConfigDescription::readFromYamlFile($this->storefrontConfigDescriptionFilePath);
    }

    public function getReturnShipmentConfigDescription(): ConfigDescription
    {
        if ($this->returnShipmentConfigDescriptionFilePath === null) {
            return ConfigDescription::createEmpty();
        }

        return ConfigDescription::readFromYamlFile($this->returnShipmentConfigDescriptionFilePath);
    }

    public function getDefaultParcelPackingConfiguration(): ParcelPackingConfiguration
    {
        if ($this->defaultParcelPackingConfiguration === null) {
            return ParcelPackingConfiguration::createDefault();
        }

        return $this->defaultParcelPackingConfiguration;
    }

    public function getReturnLabelMailTemplateTechnicalName(): ?string
    {
        return $this->returnLabelMailTemplateTechnicalName;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function supportsSenderAddressForShipments(): bool
    {
        return $this->supportsSenderAddressForShipments;
    }

    public function supportsReceiverAddressForReturnShipments(): bool
    {
        return $this->supportsReceiverAddressForReturnShipments;
    }

    public function supportsImporterOfRecordsAddress(): bool
    {
        return $this->supportsImporterOfRecordsAddress;
    }
}
