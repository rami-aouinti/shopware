<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Installation;

use Pickware\ShippingBundle\Carrier\Carrier;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\SwissPostBundle\Config\SwissPostConfig;
use Pickware\SwissPostBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class SwissPostCarrier extends Carrier
{
    public const TECHNICAL_NAME = 'swissPost';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            name: 'Schweizerische Post',
            abbreviation: 'Schweizerische Post',
            configDomain: SwissPostConfig::CONFIG_DOMAIN,
            shipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            storefrontConfigDescriptionFilePath: null,
            returnShipmentConfigDescriptionFilePath: null,
            defaultParcelPackingConfiguration: new ParcelPackingConfiguration(
                maxParcelWeight: new Weight(30, 'kg'),
            ),
            returnLabelMailTemplateTechnicalName: ReturnLabelMailTemplate::TECHNICAL_NAME,
            batchSize: 10,
            supportsSenderAddressForShipments: true,
            supportsReceiverAddressForReturnShipments: true,
            supportsImporterOfRecordsAddress: false,
        );
    }
}
