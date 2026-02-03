<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Adapter;

use Pickware\ShippingBundle\Shipment\Address;
use Pickware\SwissPostBundle\Api\Services\AbstractShipmentOption;
use Pickware\SwissPostBundle\Api\Services\CashOnDeliveryOption;
use Pickware\SwissPostBundle\Api\Services\DeliveryInstructionsOption;
use Pickware\SwissPostBundle\Api\Services\ShipmentNotificationOption;
use Pickware\SwissPostBundle\Api\Services\ShipmentServiceOption;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class SwissPostShipmentConfig
{
    public function __construct(private readonly array $shipmentConfig) {}

    public function getProductCodes(): array
    {
        $productCodes = $this->shipmentConfig['product'] ?? '';
        if (empty($productCodes)) {
            throw SwissPostAdapterException::noProductSpecified();
        }

        return explode(',', $productCodes);
    }

    /**
     * @return AbstractShipmentOption[]
     */
    public function getShipmentServiceOptions(Address $receiverAddress): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['saturdayDelivery'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::saturdayDelivery();
        }
        if ($this->shipmentConfig['signature'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::signature();
        }
        if ($this->shipmentConfig['codEnabled'] ?? false) {
            $serviceOptions[] = new CashOnDeliveryOption((string) $this->shipmentConfig['codAmount']);
        }
        if (
            array_key_exists('deliveryInstructions', $this->shipmentConfig)
            && $this->shipmentConfig['deliveryInstructions'] !== 'none'
        ) {
            if ($this->shipmentConfig['deliveryInstructions'] === 'ZAW3217') {
                if (!array_key_exists('deliveryDate', $this->shipmentConfig)) {
                    throw SwissPostAdapterException::noDeliveryDateSpecified();
                }

                $serviceOptions[] = new DeliveryInstructionsOption(
                    $this->shipmentConfig['deliveryInstructions'],
                    $this->shipmentConfig['deliveryDate'],
                );
            } else {
                $serviceOptions[] = new DeliveryInstructionsOption($this->shipmentConfig['deliveryInstructions']);
            }
        }
        if ($this->shipmentConfig['notifications'] ?? false) {
            $notificationOptions = ShipmentNotificationOption::notifications(
                $this->shipmentConfig['notifications'],
                $receiverAddress->getEmail(),
            );
            array_push($serviceOptions, ...$notificationOptions);
        }

        return $serviceOptions;
    }
}
