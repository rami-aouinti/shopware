<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Pickware\DpdBundle\Api\DpdProduct;
use Pickware\DpdBundle\Api\Services\ShipmentServiceOption;
use Pickware\ShippingBundle\Shipment\Address;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DpdShipmentConfig
{
    /**
     * @param array<string, string|bool|array<int>> $shipmentConfig
     */
    public function __construct(private readonly array $shipmentConfig) {}

    public function getProduct(): DpdProduct
    {
        $productCode = $this->shipmentConfig['product'] ?? '';

        if ($productCode === '' || !is_string($productCode)) {
            throw DpdAdapterException::undefinedProductCode();
        }

        return DpdProduct::getByCode($productCode);
    }

    /**
     * @return ShipmentServiceOption[]
     */
    public function getShipmentServiceOptions(Address $receiverAddress): array
    {
        $serviceOptions = [];
        if ($this->shipmentConfig['food'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::food();
        }
        if ($this->shipmentConfig['exWorksDelivery'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::exWorksDelivery();
        }
        if ($this->shipmentConfig['saturdayDelivery'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::saturdayDelivery();
        }
        if ($this->shipmentConfig['personalDelivery'] ?? false) {
            if (
                !array_key_exists('personalDeliveryType', $this->shipmentConfig)
                || !is_string($this->shipmentConfig['personalDeliveryType'])
            ) {
                throw DpdAdapterException::personalDeliveryTypeMissing();
            }
            $serviceOptions[] = ShipmentServiceOption::personalDelivery(
                $this->shipmentConfig['personalDeliveryType'],
                $receiverAddress->getFirstName(),
            );
        }
        if ($this->shipmentConfig['predict'] ?? false) {
            $serviceOptions[] = ShipmentServiceOption::predict(
                $receiverAddress->getEmail(),
                $receiverAddress->getCountryIso(),
            );
        }
        if ($this->shipmentConfig['proactiveNotification'] ?? false) {
            if (
                !array_key_exists('proactiveNotificationEvents', $this->shipmentConfig)
                || !is_array($this->shipmentConfig['proactiveNotificationEvents'])
            ) {
                throw DpdAdapterException::proactiveNotificationEventsMissing();
            }
            $serviceOptions[] = ShipmentServiceOption::proactiveNotification(
                $this->shipmentConfig['proactiveNotificationEvents'],
                $receiverAddress->getEmail(),
                $receiverAddress->getCountryIso(),
            );
        }

        return $serviceOptions;
    }
}
