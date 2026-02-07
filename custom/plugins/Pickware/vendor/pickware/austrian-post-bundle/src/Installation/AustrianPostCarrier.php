<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Installation;

use Pickware\AustrianPostBundle\Config\AustrianPostConfig;
use Pickware\AustrianPostBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\ShippingBundle\Carrier\Carrier;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class AustrianPostCarrier extends Carrier
{
    public const TECHNICAL_NAME = 'austrianPost';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            name: 'Österreichische Post',
            abbreviation: 'Österreichische Post',
            configDomain: AustrianPostConfig::CONFIG_DOMAIN,
            shipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            storefrontConfigDescriptionFilePath: null,
            returnShipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ReturnShipmentConfigDescription.yaml',
            defaultParcelPackingConfiguration: new ParcelPackingConfiguration(
                maxParcelWeight: new Weight(31.5, 'kg'),
                defaultBoxDimensions: null,
            ),
            returnLabelMailTemplateTechnicalName: ReturnLabelMailTemplate::TECHNICAL_NAME,
            batchSize: 10,
            supportsSenderAddressForShipments: true,
            supportsReceiverAddressForReturnShipments: true,
            supportsImporterOfRecordsAddress: false,
        );
    }
}
