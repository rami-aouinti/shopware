<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Pickware\ShippingBundle\Shipment\Address;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShippingBundle\Shipment\ShipmentType;

class CommonShippingConfig
{
    use ConfigDecoratorTrait;

    public const CONFIG_DOMAIN = 'PickwareShippingBundle.common';

    public static function createDefault(): self
    {
        return new self(new Config(self::CONFIG_DOMAIN, [
            'customsInformationTypeOfShipment' => ShipmentType::SaleOfGoods->value,
        ]));
    }

    public function getSenderAddress(): Address
    {
        $senderAddress = [];

        foreach ($this->config as $key => $value) {
            if (mb_strpos($key, 'senderAddress') === 0) {
                $senderAddress[lcfirst(str_replace('senderAddress', '', $key))] = $value;
            }
        }

        return Address::fromArray($senderAddress);
    }

    /**
     * @return string[]
     */
    public function getCashOnDeliveryPaymentMethodIds(): array
    {
        return $this->config['cashOnDeliveryPaymentMethodIds'] ?? [];
    }

    public function prioritizeProductCustomsValueForParcelItemCustomsValue(): bool
    {
        return (bool) ($this->config['prioritizeProductCustomsValueForParcelItemCustomsValue'] ?? false);
    }

    public function assignCustomsInformationToShipmentBlueprint(ShipmentBlueprint $shipmentBlueprint): void
    {
        $shipmentBlueprint->setTypeOfShipment(
            isset($this->config['customsInformationTypeOfShipment']) ? ShipmentType::tryFrom($this->config['customsInformationTypeOfShipment']) : ShipmentType::Other,
        );
        $shipmentBlueprint->setExplanationIfTypeOfShipmentIsOther(
            $this->config['customsInformationExplanation'] ?? null,
        );
        $shipmentBlueprint->setComment($this->config['customsInformationComment'] ?? null);
        $shipmentBlueprint->setOfficeOfOrigin($this->config['customsInformationOfficeOfOrigin'] ?? null);
        $shipmentBlueprint->setPermitNumbers(
            $this->config->getMultilineConfigValueAsArray('customsInformationPermitNumbers'),
        );
        $shipmentBlueprint->setCertificateNumbers(
            $this->config->getMultilineConfigValueAsArray('customsInformationCertificateNumbers'),
        );
    }
}
