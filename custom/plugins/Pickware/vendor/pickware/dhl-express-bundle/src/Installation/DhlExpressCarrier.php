<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Installation;

use Pickware\DhlExpressBundle\Config\DhlExpressConfig;
use Pickware\DhlExpressBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\ShippingBundle\Carrier\Carrier;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class DhlExpressCarrier extends Carrier
{
    public const TECHNICAL_NAME = 'dhlExpress';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            name: 'DHL Express',
            abbreviation: 'DHL Express',
            configDomain: DhlExpressConfig::CONFIG_DOMAIN,
            shipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            storefrontConfigDescriptionFilePath: null,
            returnShipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            defaultParcelPackingConfiguration: new ParcelPackingConfiguration(
                maxParcelWeight: new Weight(500, 'kg'),
                defaultBoxDimensions: new BoxDimensions(
                    width: new Length(20, 'cm'),
                    height: new Length(20, 'cm'),
                    length: new Length(20, 'cm'),
                ),
            ),
            returnLabelMailTemplateTechnicalName: ReturnLabelMailTemplate::TECHNICAL_NAME,
            batchSize: 10,
            supportsSenderAddressForShipments: true,
            supportsReceiverAddressForReturnShipments: true,
            supportsImporterOfRecordsAddress: true,
        );
    }
}
