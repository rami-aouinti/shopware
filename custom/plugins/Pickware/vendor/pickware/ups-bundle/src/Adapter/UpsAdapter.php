<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Adapter;

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\CashOnDeliveryCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\MultiTrackingCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentCancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Pickware\UpsBundle\FeatureFlag\UpsFeatureFlag;
use Pickware\UpsBundle\Installation\UpsCarrier;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => UpsCarrier::TECHNICAL_NAME,
        'featureFlagName' => UpsFeatureFlag::NAME,
    ],
)]
class UpsAdapter extends AbstractCarrierAdapter implements CancellationCapability, MultiTrackingCapability, ReturnShipmentsRegistrationCapability, ReturnShipmentCancellationCapability, CashOnDeliveryCapability, PageFormatProviding
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UpsShipmentRegistrationService $upsShipmentRegistrationService,
    ) {}

    /**
     * @param string[] $shipmentNumbers
     */
    public static function getTrackingUrlForTrackingNumbers(array $shipmentNumbers): string
    {
        return sprintf(
            'https://www.ups.com/track?trackNums=%s',
            implode(urlencode("\r\n"), $shipmentNumbers),
        );
    }

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->upsShipmentRegistrationService->registerUpsShipments($shipmentIds, $carrierConfig, $context);
    }

    public function cancelShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->upsShipmentRegistrationService->cancelUpsShipment($shipmentIds, $carrierConfig, $context);
    }

    public function registerReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->upsShipmentRegistrationService->registerUpsShipments($shipmentIds, $carrierConfig, $context);
    }

    public function cancelReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->upsShipmentRegistrationService->cancelUpsShipment($shipmentIds, $carrierConfig, $context);
    }

    public function generateTrackingUrlForTrackingCodes(array $trackingCodeIds, Context $context): string
    {
        $trackingCodes = $this->entityManager->findBy(
            TrackingCodeDefinition::class,
            ['id' => $trackingCodeIds],
            $context,
        );
        $trackingCodes = EntityCollectionExtension::getField($trackingCodes, 'trackingCode');

        return self::getTrackingUrlForTrackingNumbers($trackingCodes);
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

    public function getPageFormats(): array
    {
        return UpsLabelSize::getSupportedPageFormats();
    }
}
