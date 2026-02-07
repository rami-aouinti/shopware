<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use Pickware\DsvBundle\Api\DsvPackageType;
use Pickware\DsvBundle\Api\DsvProduct;
use Pickware\DsvBundle\Api\Services\AbstractShipmentServiceOption;
use Pickware\DsvBundle\Api\Services\B2CShipmentServiceOption;
use Pickware\DsvBundle\Api\Services\PreAdviceEmailDeliveryShipmentServiceOption;
use Pickware\DsvBundle\Api\Services\PrivateDeliveryShipmentServiceOption;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DsvShipmentConfig
{
    public function __construct(private readonly array $shipmentConfig) {}

    public function getProduct(): DsvProduct
    {
        return DsvProduct::from($this->shipmentConfig['product'] ?? '');
    }

    public function getPackageType(): DsvPackageType
    {
        return DsvPackageType::from($this->shipmentConfig['packageType'] ?? '');
    }

    public function getIncoterm(): ?string
    {
        return $this->shipmentConfig['incoterm'] ?? null;
    }

    public function getPackageDescription(): ?string
    {
        return $this->shipmentConfig['packageDescription'] ?? null;
    }

    /**
     * @return AbstractShipmentServiceOption[]
     */
    public function getShipmentServiceOptions(): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['b2C'] ?? false) {
            $serviceOptions[] = new B2CShipmentServiceOption();
        }

        if ($this->shipmentConfig['privateDeliveryCurbSide'] ?? false) {
            $serviceOptions[] = new PrivateDeliveryShipmentServiceOption();
        }

        if ($this->shipmentConfig['preAdviceMailDelivery'] ?? false) {
            $serviceOptions[] = new PreAdviceEmailDeliveryShipmentServiceOption();
        }

        return $serviceOptions;
    }
}
