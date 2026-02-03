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

/**
 * @deprecated Will be removed in v4.0.0. Use the CarrierAdapterRegistry instead.
 */
interface CarrierAdapterRegistryInterface
{
    public function addCarrierAdapter(string $technicalName, AbstractCarrierAdapter $carrierAdapter): void;

    public function addCarrierAdapterWithFeatureFlags(string $technicalName, array $featureFlagNames, AbstractCarrierAdapter $carrierAdapter): void;

    public function hasCarrierAdapter(string $technicalName): bool;

    public function getCarrierPageFormats(): array;

    public function getCarrierAdapterByTechnicalName(string $technicalName): AbstractCarrierAdapter;

    public function getCancellationCapability(string $carrierTechnicalName): CancellationCapability;

    public function getReturnShipmentsCapability(string $carrierTechnicalName): ReturnShipmentsRegistrationCapability;

    public function hasReturnShipmentCapability(string $carrierTechnicalName): bool;

    public function getReturnShipmentCancellationCapability(string $carrierTechnicalName): ReturnShipmentCancellationCapability;
}
