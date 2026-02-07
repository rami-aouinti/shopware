<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\ShippingBundle\Carrier\Model\CarrierEntity;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\ShippingBundle\Privacy\PrivacyConfiguration;
use Pickware\ShippingBundle\Shipment\AddressConfiguration;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShippingMethodConfigEntity extends Entity
{
    use EntityIdTrait;

    protected ?ShippingMethodEntity $shippingMethod = null;
    protected string $shippingMethodId;
    protected ?CarrierEntity $carrier = null;
    protected string $carrierTechnicalName;
    protected array $shipmentConfig;
    protected array $storefrontConfig;
    protected array $returnShipmentConfig;
    protected ParcelPackingConfiguration $parcelPackingConfiguration;
    protected PrivacyConfiguration $privacyConfiguration;
    protected AddressConfiguration $addressConfiguration;

    public function getShippingMethod(): ShippingMethodEntity
    {
        if ($this->shippingMethod === null && $this->shippingMethodId !== null) {
            throw new AssociationNotLoadedException('shippingMethod', $this);
        }

        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethodId = $shippingMethod->getId();
        $this->shippingMethod = $shippingMethod;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        if ($this->shippingMethod !== null && $this->shippingMethod->getId() !== $shippingMethodId) {
            $this->shippingMethod = null;
        }
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getCarrier(): CarrierEntity
    {
        if ($this->carrier === null && $this->carrierTechnicalName !== null) {
            throw new AssociationNotLoadedException('carrier', $this);
        }

        return $this->carrier;
    }

    public function setCarrier(CarrierEntity $carrier): void
    {
        $this->carrier = $carrier;
        $this->carrierTechnicalName = $carrier->getTechnicalName();
    }

    public function getCarrierTechnicalName(): string
    {
        return $this->carrierTechnicalName;
    }

    public function setCarrierTechnicalName(string $carrierTechnicalName): void
    {
        if ($this->carrier !== null && $this->carrier->getTechnicalName() !== $carrierTechnicalName) {
            $this->carrier = null;
        }
        $this->carrierTechnicalName = $carrierTechnicalName;
    }

    public function getShipmentConfig(): array
    {
        return $this->shipmentConfig;
    }

    public function setShipmentConfig(array $config): void
    {
        $this->shipmentConfig = $config;
    }

    public function getStorefrontConfig(): array
    {
        return $this->storefrontConfig;
    }

    public function setStorefrontConfig(array $config): void
    {
        $this->storefrontConfig = $config;
    }

    public function getReturnShipmentConfig(): array
    {
        return $this->returnShipmentConfig;
    }

    public function setReturnShipmentConfig(array $config): void
    {
        $this->returnShipmentConfig = $config;
    }

    public function getParcelPackingConfiguration(): ParcelPackingConfiguration
    {
        return $this->parcelPackingConfiguration;
    }

    public function setParcelPackingConfiguration(ParcelPackingConfiguration $parcelPackingConfiguration): void
    {
        $this->parcelPackingConfiguration = $parcelPackingConfiguration;
    }

    public function getPrivacyConfiguration(): PrivacyConfiguration
    {
        return $this->privacyConfiguration;
    }

    public function setPrivacyConfiguration(PrivacyConfiguration $privacyConfiguration): void
    {
        $this->privacyConfiguration = $privacyConfiguration;
    }

    public function getAddressConfiguration(): AddressConfiguration
    {
        return $this->addressConfiguration;
    }

    public function setAddressConfiguration(AddressConfiguration $addressConfiguration): void
    {
        $this->addressConfiguration = $addressConfiguration;
    }
}
