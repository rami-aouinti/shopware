<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Installation;

use Pickware\DsvBundle\Config\DsvConfig;
use Pickware\ShippingBundle\Carrier\Carrier;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class DsvCarrier extends Carrier
{
    public const TECHNICAL_NAME = 'dsv';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            name: 'DSV',
            abbreviation: 'DSV',
            configDomain: DsvConfig::CONFIG_DOMAIN,
            shipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            storefrontConfigDescriptionFilePath: null,
            returnShipmentConfigDescriptionFilePath: null,
            defaultParcelPackingConfiguration: new ParcelPackingConfiguration(
                maxParcelWeight: new Weight(999, 'kg'),
                defaultBoxDimensions: new BoxDimensions(
                    width: new Length(80, 'cm'),
                    height: new Length(50, 'cm'),
                    length: new Length(120, 'cm'),
                ),
            ),
            returnLabelMailTemplateTechnicalName: null,
            batchSize: 10,
            supportsSenderAddressForShipments: true,
            supportsReceiverAddressForReturnShipments: false,
            supportsImporterOfRecordsAddress: false,
        );
    }
}
