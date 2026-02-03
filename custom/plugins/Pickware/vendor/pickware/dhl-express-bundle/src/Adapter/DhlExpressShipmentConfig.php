<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Adapter;

use Pickware\DhlExpressBundle\Api\DhlExpressProduct;
use Pickware\DhlExpressBundle\Api\Services\AbstractShipmentOption;
use Pickware\DhlExpressBundle\Api\Services\ShipmentServiceOption;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DhlExpressShipmentConfig
{
    public function __construct(private readonly array $shipmentConfig) {}

    public function getProduct(): DhlExpressProduct
    {
        $product = $this->shipmentConfig['product'] ?? '';

        if (!$product) {
            throw DhlExpressAdapterException::noProductSpecified();
        }

        $deliveryTime = $this->shipmentConfig['deliveryTime'] ?? null;
        $contentType = $this->shipmentConfig['contentType'] ?? null;

        return DhlExpressProduct::getExpressProductByConfiguration($product, $contentType, $deliveryTime);
    }

    public function getIncoterm(): ?string
    {
        return $this->shipmentConfig['incoterm'] ?? null;
    }

    /**
     * @return AbstractShipmentOption[]
     */
    public function getShipmentServiceOptions(): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['saturdayDelivery'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::saturdayDelivery();
        }

        if ($this->shipmentConfig['additionalInsurance'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::additionalInsurance(
                $this->shipmentConfig['insuranceAmount'],
                'EUR',
            );
        }

        return $serviceOptions;
    }
}
