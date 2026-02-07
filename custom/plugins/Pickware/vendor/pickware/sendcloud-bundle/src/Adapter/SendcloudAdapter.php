<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Adapter;

use Pickware\SendcloudBundle\FeatureFlag\SendcloudFeatureFlag;
use Pickware\SendcloudBundle\Installation\SendcloudCarrier;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\Capabilities\CancellationCapability;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => SendcloudCarrier::TECHNICAL_NAME,
        'featureFlagName' => SendcloudFeatureFlag::NAME,
    ],
)]
class SendcloudAdapter extends AbstractCarrierAdapter implements CancellationCapability, PageFormatProviding
{
    public function __construct(
        private readonly SendcloudShipmentRegistrationService $sendcloudShipmentRegistrationService,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->sendcloudShipmentRegistrationService->registerSendcloudShipments(
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
        return $this->sendcloudShipmentRegistrationService->cancelSendcloudShipments(
            $shipmentIds,
            $carrierConfig,
            $context,
        );
    }

    public function getPageFormats(): array
    {
        return SendcloudLabelSize::getSupportedPageFormats();
    }
}
