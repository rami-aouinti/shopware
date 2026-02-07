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

use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigEntity;
use Pickware\ShippingBundle\Shipment\Address;
use Shopware\Core\Framework\Context;

class ShipmentConfigurationService
{
    public function __construct(private readonly ContextResolver $contextResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function getShipmentConfigurationForOrder(
        ShippingMethodConfigEntity $shippingMethodConfig,
        string $orderId,
        bool $isReturnShipment,
        Context $context,
        Address $receiverAddress,
    ): array {
        $carrierEntity = $shippingMethodConfig->getCarrier();
        $configDescription = $carrierEntity->getShipmentConfigOptions();

        $baseConfig = $isReturnShipment ? $shippingMethodConfig->getReturnShipmentConfig() : $shippingMethodConfig->getShipmentConfig();

        $resolvedContextDefaults = $this->contextResolver->resolveDefaultConfigContext(
            $configDescription,
            $orderId,
            $context,
            $receiverAddress,
        );

        return [
            ...array_filter($resolvedContextDefaults, fn($value) => $value !== null && $value !== ''),
            ...array_filter($baseConfig, fn($value) => $value !== null && $value !== ''),
        ];
    }
}
