<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Installation;

use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\DpdBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\ShippingBundle\Carrier\Carrier;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;

class DpdCarrier extends Carrier
{
    public const TECHNICAL_NAME = 'dpd';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            name: 'DPD',
            abbreviation: 'DPD',
            configDomain: DpdConfig::CONFIG_DOMAIN,
            shipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ShipmentConfigDescription.yaml',
            storefrontConfigDescriptionFilePath: null,
            returnShipmentConfigDescriptionFilePath: __DIR__ . '/../Resources/config/ReturnShipmentConfigDescription.yaml',
            defaultParcelPackingConfiguration: new ParcelPackingConfiguration(
                maxParcelWeight: new Weight(20, 'kg'),
                defaultBoxDimensions: new BoxDimensions(
                    width: new Length(120, 'cm'),
                    height: new Length(60, 'cm'),
                    length: new Length(60, 'cm'),
                ),
            ),
            returnLabelMailTemplateTechnicalName: ReturnLabelMailTemplate::TECHNICAL_NAME,
            batchSize: 10,
            supportsSenderAddressForShipments: true,
            supportsReceiverAddressForReturnShipments: false,
            supportsImporterOfRecordsAddress: false,
        );
    }
}
