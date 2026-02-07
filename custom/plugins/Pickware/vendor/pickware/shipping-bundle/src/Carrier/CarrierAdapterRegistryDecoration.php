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

use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentCancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;

trait CarrierAdapterRegistryDecoration
{
    private CarrierAdapterRegistryInterface $decoratedInstance;

    public function addCarrierAdapter(string $technicalName, AbstractCarrierAdapter $carrierAdapter): void
    {
        $this->decoratedInstance->addCarrierAdapter($technicalName, $carrierAdapter);
    }

    public function addCarrierAdapterWithFeatureFlags(string $technicalName, array $featureFlagNames, AbstractCarrierAdapter $carrierAdapter): void
    {
        $this->decoratedInstance->addCarrierAdapterWithFeatureFlags($technicalName, $featureFlagNames, $carrierAdapter);
    }

    public function hasCarrierAdapter(string $technicalName): bool
    {
        return $this->decoratedInstance->hasCarrierAdapter($technicalName);
    }

    public function getCarrierPageFormats(): array
    {
        return $this->decoratedInstance->getCarrierPageFormats();
    }

    public function getCarrierAdapterByTechnicalName(string $technicalName): AbstractCarrierAdapter
    {
        return $this->decoratedInstance->getCarrierAdapterByTechnicalName($technicalName);
    }

    public function getCancellationCapability(string $carrierTechnicalName): CancellationCapability
    {
        return $this->decoratedInstance->getCancellationCapability($carrierTechnicalName);
    }

    public function getReturnShipmentsCapability(string $carrierTechnicalName): ReturnShipmentsRegistrationCapability
    {
        return $this->decoratedInstance->getReturnShipmentsCapability($carrierTechnicalName);
    }

    public function hasReturnShipmentCapability(string $carrierTechnicalName): bool
    {
        return $this->decoratedInstance->hasReturnShipmentCapability($carrierTechnicalName);
    }

    public function getReturnShipmentCancellationCapability(string $carrierTechnicalName): ReturnShipmentCancellationCapability
    {
        return $this->decoratedInstance->getReturnShipmentCancellationCapability($carrierTechnicalName);
    }
}
