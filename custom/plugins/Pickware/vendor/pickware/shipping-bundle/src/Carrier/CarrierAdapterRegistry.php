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

use Exception;
use LogicException;
use OutOfBoundsException;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentCancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;

class CarrierAdapterRegistry implements CarrierAdapterRegistryInterface
{
    public const CONTAINER_TAG = 'pickware_shipping.carrier_adapter';

    /**
     * @var AbstractCarrierAdapter[]
     */
    private array $carrierAdapters = [];

    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    /**
     * @deprecated Remove with v4.0.0. Use `addCarrierAdapterWithFeatureFlags` instead.
     */
    public function addCarrierAdapter(string $technicalName, AbstractCarrierAdapter $carrierAdapter): void
    {
        $this->carrierAdapters[$technicalName] = $carrierAdapter;
    }

    /**
     * @deprecated Rename to `addCarrierAdapter` with v4.0.0
     */
    public function addCarrierAdapterWithFeatureFlags(string $technicalName, array $featureFlagNames, AbstractCarrierAdapter $carrierAdapter): void
    {
        foreach ($featureFlagNames as $featureFlagName) {
            if (!$this->featureFlagService->isActive($featureFlagName)) {
                return;
            }
        }

        $this->carrierAdapters[$technicalName] = $carrierAdapter;
    }

    public function hasCarrierAdapter(string $technicalName): bool
    {
        return array_key_exists($technicalName, $this->carrierAdapters);
    }

    public function getCarrierPageFormats(): array
    {
        $carrierPageFormats = [];
        foreach ($this->carrierAdapters as $technicalName => $carrierAdapter) {
            if ($carrierAdapter instanceof PageFormatProviding) {
                $pageFormats = $carrierAdapter->getPageFormats();
                if (count(array_filter($pageFormats, fn(PageFormat $pageFormat) => $pageFormat->getId() === null)) > 0) {
                    throw new LogicException(sprintf('Carrier "%s" has a pageformat without a configured id', $technicalName));
                }
                $carrierPageFormats[] = $pageFormats;
            }
        }

        return array_values(array_merge(...$carrierPageFormats));
    }

    public function getCarrierAdapterByTechnicalName(string $technicalName): AbstractCarrierAdapter
    {
        if (!$this->hasCarrierAdapter($technicalName)) {
            throw new OutOfBoundsException(sprintf(
                'CarrierAdapter for Carrier with technical name "%s" is not active.',
                $technicalName,
            ));
        }

        return $this->carrierAdapters[$technicalName];
    }

    public function getCancellationCapability(string $carrierTechnicalName): CancellationCapability
    {
        $carrierAdapter = $this->getCarrierAdapterByTechnicalName($carrierTechnicalName);
        if (!$carrierAdapter instanceof CancellationCapability) {
            throw new Exception(sprintf('Carrier "%s" is not capable of cancellations.', $carrierTechnicalName));
        }

        return $carrierAdapter;
    }

    public function getReturnShipmentsCapability(string $carrierTechnicalName): ReturnShipmentsRegistrationCapability
    {
        $carrierAdapter = $this->getCarrierAdapterByTechnicalName($carrierTechnicalName);
        if (!$carrierAdapter instanceof ReturnShipmentsRegistrationCapability) {
            throw new Exception(sprintf('Carrier "%s" is not capable of return label registration.', $carrierTechnicalName));
        }

        return $carrierAdapter;
    }

    public function hasReturnShipmentCapability(string $carrierTechnicalName): bool
    {
        $carrierAdapter = $this->getCarrierAdapterByTechnicalName($carrierTechnicalName);

        return $carrierAdapter instanceof ReturnShipmentsRegistrationCapability;
    }

    public function getReturnShipmentCancellationCapability(string $carrierTechnicalName): ReturnShipmentCancellationCapability
    {
        $carrierAdapter = $this->getCarrierAdapterByTechnicalName($carrierTechnicalName);
        if (!$carrierAdapter instanceof ReturnShipmentCancellationCapability) {
            throw new Exception(sprintf('Carrier "%s" is not capable of return cancellations.', $carrierTechnicalName));
        }

        return $carrierAdapter;
    }
}
