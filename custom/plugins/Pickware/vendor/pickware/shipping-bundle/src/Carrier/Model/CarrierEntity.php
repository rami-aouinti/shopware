<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class CarrierEntity extends Entity
{
    protected string $technicalName;
    protected string $name;
    protected string $abbreviation;
    protected string $configDomain;
    protected int $batchSize;
    protected ?array $capabilities;
    protected bool $active;
    protected array $shipmentConfigDefaultValues;
    protected array $shipmentConfigOptions;
    protected array $storefrontConfigDefaultValues;
    protected array $storefrontConfigOptions;
    protected array $returnShipmentConfigDefaultValues;
    protected array $returnShipmentConfigOptions;
    protected ParcelPackingConfiguration $defaultParcelPackingConfiguration;
    protected ?MailTemplateTypeEntity $returnLabelMailTemplateType = null;
    protected ?string $returnLabelMailTemplateTypeTechnicalName;
    protected bool $supportsSenderAddressForShipments;
    protected bool $supportsReceiverAddressForReturnShipments;
    protected bool $supportsImporterOfRecordsAddress;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
        $this->_uniqueIdentifier = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): void
    {
        $this->abbreviation = $abbreviation;
    }

    public function getConfigDomain(): string
    {
        return $this->configDomain;
    }

    public function setConfigDomain(string $configDomain): void
    {
        $this->configDomain = $configDomain;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    public function getShipmentConfigDefaultValues(): array
    {
        return $this->shipmentConfigDefaultValues;
    }

    public function setShipmentConfigDefaultValues(array $configDefaultValues): void
    {
        $this->shipmentConfigDefaultValues = $configDefaultValues;
    }

    public function getShipmentConfigOptions(): array
    {
        return $this->shipmentConfigOptions;
    }

    public function setShipmentConfigOptions(array $configOptions): void
    {
        $this->shipmentConfigOptions = $configOptions;
    }

    public function getStorefrontConfigDefaultValues(): array
    {
        return $this->storefrontConfigDefaultValues;
    }

    public function setStorefrontConfigDefaultValues(array $configDefaultValues): void
    {
        $this->storefrontConfigDefaultValues = $configDefaultValues;
    }

    public function getStorefrontConfigOptions(): array
    {
        return $this->storefrontConfigOptions;
    }

    public function setStorefrontConfigOptions(array $configOptions): void
    {
        $this->storefrontConfigOptions = $configOptions;
    }

    public function getReturnShipmentConfigDefaultValues(): array
    {
        return $this->returnShipmentConfigDefaultValues;
    }

    public function setReturnShipmentConfigDefaultValues(array $configDefaultValues): void
    {
        $this->returnShipmentConfigDefaultValues = $configDefaultValues;
    }

    public function getReturnShipmentConfigOptions(): array
    {
        return $this->returnShipmentConfigOptions;
    }

    public function setReturnShipmentConfigOptions(array $configOptions): void
    {
        $this->returnShipmentConfigOptions = $configOptions;
    }

    public function getDefaultParcelPackingConfiguration(): ParcelPackingConfiguration
    {
        return $this->defaultParcelPackingConfiguration;
    }

    public function setDefaultParcelPackingConfiguration(
        ParcelPackingConfiguration $defaultParcelPackingConfiguration,
    ): void {
        $this->defaultParcelPackingConfiguration = $defaultParcelPackingConfiguration;
    }

    public function getCapabilities(): ?array
    {
        return $this->capabilities;
    }

    public function setCapabilities(?array $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getReturnLabelMailTemplateTypeTechnicalName(): ?string
    {
        return $this->returnLabelMailTemplateTypeTechnicalName;
    }

    public function setReturnLabelMailTemplateTypeTechnicalName(?string $returnLabelMailTemplateTypeTechnicalName): void
    {
        if (
            $this->returnLabelMailTemplateType
            && $this->returnLabelMailTemplateType->getTechnicalName() !== $returnLabelMailTemplateTypeTechnicalName
        ) {
            $this->returnLabelMailTemplateType = null;
        }
        $this->returnLabelMailTemplateTypeTechnicalName = $returnLabelMailTemplateTypeTechnicalName;
    }

    public function getReturnLabelMailTemplateType(): ?MailTemplateTypeEntity
    {
        if (!$this->returnLabelMailTemplateType && $this->returnLabelMailTemplateTypeTechnicalName) {
            throw new AssociationNotLoadedException('returnLabelMailTemplateType', $this);
        }

        return $this->returnLabelMailTemplateType;
    }

    public function setReturnLabelMailTemplateType(?MailTemplateTypeEntity $returnLabelMailTemplateType): void
    {
        if ($returnLabelMailTemplateType) {
            $this->returnLabelMailTemplateTypeTechnicalName = $returnLabelMailTemplateType->getTechnicalName();
        }
        $this->returnLabelMailTemplateType = $returnLabelMailTemplateType;
    }

    public function getSupportsSenderAddressForShipments(): bool
    {
        return $this->supportsSenderAddressForShipments;
    }

    public function setSupportsSenderAddressForShipments(bool $supportsSenderAddressForShipments): void
    {
        $this->supportsSenderAddressForShipments = $supportsSenderAddressForShipments;
    }

    public function getSupportsReceiverAddressForReturnShipments(): bool
    {
        return $this->supportsReceiverAddressForReturnShipments;
    }

    public function setSupportsReceiverAddressForReturnShipments(bool $supportsReceiverAddressForReturnShipments): void
    {
        $this->supportsReceiverAddressForReturnShipments = $supportsReceiverAddressForReturnShipments;
    }

    public function getSupportsImporterOfRecordsAddress(): bool
    {
        return $this->supportsImporterOfRecordsAddress;
    }

    public function setSupportsImporterOfRecordsAddress(bool $supportsImporterOfRecordsAddress): void
    {
        $this->supportsImporterOfRecordsAddress = $supportsImporterOfRecordsAddress;
    }
}
