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

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\DhlExpressBundle\FeatureFlag\DhlExpressFeatureFlag;
use Pickware\DhlExpressBundle\Installation\DhlExpressCarrier;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\MultiTrackingCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentCancellationCapability;
use Pickware\ShippingBundle\Carrier\Capabilities\ReturnShipmentsRegistrationCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => DhlExpressCarrier::TECHNICAL_NAME,
        'featureFlagName' => DhlExpressFeatureFlag::NAME,
    ],
)]
class DhlExpressAdapter extends AbstractCarrierAdapter implements CancellationCapability, MultiTrackingCapability, ReturnShipmentsRegistrationCapability, ReturnShipmentCancellationCapability, PageFormatProviding
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DhlExpressShipmentRegistrationService $dhlExpressShipmentRegistrationService,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->dhlExpressShipmentRegistrationService->registerDhlExpressShipments(
            $shipmentIds,
            $carrierConfig,
            $context,
        );
    }

    public function cancelShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->dhlExpressShipmentRegistrationService->cancelDhlExpressShipments($shipmentIds, $context);
    }

    public function generateTrackingUrlForTrackingCodes(array $trackingCodeIds, Context $context): string
    {
        $trackingCodes = $this->entityManager->findBy(
            TrackingCodeDefinition::class,
            ['id' => $trackingCodeIds],
            $context,
        );
        $shipmentNumbers = EntityCollectionExtension::getField($trackingCodes, 'trackingCode');

        return self::getTrackingUrlForTrackingNumbers($shipmentNumbers);
    }

    public function registerReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->dhlExpressShipmentRegistrationService->registerDhlExpressShipments(
            $shipmentIds,
            $carrierConfig,
            $context,
        );
    }

    public function cancelReturnShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->dhlExpressShipmentRegistrationService->cancelDhlExpressShipments($shipmentIds, $context);
    }

    public static function getTrackingUrlForTrackingNumbers(array $shipmentNumbers): string
    {
        return sprintf(
            'https://www.dhl.com/de-de/home/tracking/tracking-express.html?tracking-id=%s&submit=1',
            implode(',', $shipmentNumbers),
        );
    }

    public function getPageFormats(): array
    {
        return DhlExpressLabelSize::getSupportedPageFormats();
    }
}
