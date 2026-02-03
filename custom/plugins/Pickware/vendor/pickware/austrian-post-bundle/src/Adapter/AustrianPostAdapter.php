<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Adapter;

use Pickware\AustrianPostBundle\FeatureFlag\AustrianPostProdFeatureFlag;
use Pickware\AustrianPostBundle\Installation\AustrianPostCarrier;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\CashOnDeliveryCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentCancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => AustrianPostCarrier::TECHNICAL_NAME,
        'featureFlagNames' => [AustrianPostProdFeatureFlag::NAME],
    ],
)]
class AustrianPostAdapter extends AbstractCarrierAdapter implements PageFormatProviding, CancellationCapability, ReturnShipmentsRegistrationCapability, ReturnShipmentCancellationCapability, CashOnDeliveryCapability
{
    public function __construct(
        private readonly AustrianPostShipmentRegistrationService $austrianPostShipmentRegistrationService,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->austrianPostShipmentRegistrationService->registerShipments(
            shipmentIds: $shipmentIds,
            carrierConfig: $carrierConfig,
            context: $context,
        );
    }

    public function registerReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->austrianPostShipmentRegistrationService->registerShipments(
            shipmentIds: $shipmentIds,
            carrierConfig: $carrierConfig,
            context: $context,
        );
    }

    public function cancelShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->austrianPostShipmentRegistrationService->cancelShipments(
            shipmentIds: $shipmentIds,
            carrierConfig: $carrierConfig,
            context: $context,
        );
    }

    public function cancelReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->austrianPostShipmentRegistrationService->cancelShipments(
            shipmentIds: $shipmentIds,
            carrierConfig: $carrierConfig,
            context: $context,
        );
    }

    public function getPageFormats(): array
    {
        return AustrianPostLabelSize::getSupportedPageFormats();
    }

    public function enableCashOnDeliveryInShipmentConfig(array &$shipmentConfig, MoneyValue $amount): void
    {
        $shipmentConfig['codAmount'] = $amount->getValue();
        $shipmentConfig['codEnabled'] = true;
    }

    public function isCashOnDeliveryEnabledInShipmentConfig(array $shipmentConfig): bool
    {
        return $shipmentConfig['codEnabled'] ?? false;
    }
}
