<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Adapter;

use Pickware\UpsBundle\Api\Services\AbstractPackageService;
use Pickware\UpsBundle\Api\Services\AdditionalHandlingService;
use Pickware\UpsBundle\Api\Services\DeclaredValueService;
use Pickware\UpsBundle\Api\Services\ShipmentServiceOption;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UpsShipmentConfig
{
    private array $shipmentConfig;

    public function __construct(array $shipmentConfig)
    {
        $this->shipmentConfig = $shipmentConfig;
    }

    /**
     * @return ShipmentServiceOption[]
     */
    public function getShipmentServiceOptions(): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['codEnabled'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::cashOnDelivery(
                'EUR',
                (string) $this->shipmentConfig['codAmount'],
            );
        }
        if ($this->shipmentConfig['deliverOnSaturday'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::saturdayDelivery();
        }

        return $serviceOptions;
    }

    /**
     * @return AbstractPackageService[]
     */
    public function getPackageServiceOptions(): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['additionalHandlingRequired'] ?? false) {
            $serviceOptions[] = new AdditionalHandlingService();
        }
        if ($this->shipmentConfig['additionalInsurance'] ?? false) {
            $serviceOptions[] = new DeclaredValueService();
        }

        return $serviceOptions;
    }

    public function getProduct(): string
    {
        $productCode = $this->shipmentConfig['product'] ?? '';
        if (!$productCode) {
            throw UpsAdapterException::noProductSpecified();
        }

        return $this->shipmentConfig['product'];
    }

    public function getPackagingType(): string
    {
        $packagingType = $this->shipmentConfig['packagingType'] ?? '';
        if (!$packagingType) {
            throw UpsAdapterException::noPackagingSpecified();
        }

        return $this->shipmentConfig['packagingType'];
    }

    public function shouldCreateCommercialInvoice()
    {
        return $this->shipmentConfig['createCommercialInvoice'] ?? false;
    }

    public function getIncoterm(): ?string
    {
        return $this->shipmentConfig['incoterm'] ?? null;
    }
}
