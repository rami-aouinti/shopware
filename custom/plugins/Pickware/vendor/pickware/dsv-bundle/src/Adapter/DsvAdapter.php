<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use Pickware\DsvBundle\FeatureFlag\DsvFeatureFlag;
use Pickware\DsvBundle\Installation\DsvCarrier;
use Pickware\ShippingBundle\Carrier\AbstractCarrierAdapter;
use Pickware\ShippingBundle\Carrier\CarrierAdapterRegistry;
use Pickware\ShippingBundle\Carrier\PageFormatProviding;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(
    name: CarrierAdapterRegistry::CONTAINER_TAG,
    attributes: [
        'technicalName' => DsvCarrier::TECHNICAL_NAME,
        'featureFlagName' => DsvFeatureFlag::NAME,
    ],
)]
class DsvAdapter extends AbstractCarrierAdapter implements PageFormatProviding
{
    public function __construct(
        private readonly DsvShipmentRegistrationService $dsvShipmentRegistrationService,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        return $this->dsvShipmentRegistrationService->registerDsvShipments(
            $shipmentIds,
            $carrierConfig,
            $context,
        );
    }

    public function getPageFormats(): array
    {
        return DsvLabelSize::getSupportedPageFormats();
    }
}
