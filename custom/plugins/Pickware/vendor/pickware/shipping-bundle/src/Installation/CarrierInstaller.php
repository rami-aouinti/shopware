<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Carrier\Carrier;

class CarrierInstaller
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function installCarrier(Carrier $carrier): void
    {
        $this->db->executeStatement(
            'INSERT INTO `pickware_shipping_carrier` (
                `technical_name`,
                `name`,
                `abbreviation`,
                `config_domain`,
                `shipment_config_default_values`,
                `shipment_config_options`,
                `storefront_config_default_values`,
                `storefront_config_options`,
                `return_shipment_config_default_values`,
                `return_shipment_config_options`,
                `default_parcel_packing_configuration`,
                `return_label_mail_template_type_technical_name`,
                `batch_size`,
                `supports_sender_address_for_shipments`,
                `supports_receiver_address_for_return_shipments`,
                `supports_importer_of_records_address`,
                `created_at`
            ) VALUES (
                :technicalName,
                :name,
                :abbreviation,
                :configDomain,
                :shipmentConfigDefaultValues,
                :shipmentConfigOptions,
                :storefrontConfigDefaultValues,
                :storefrontConfigOptions,
                :returnShipmentConfigDefaultValues,
                :returnShipmentConfigOptions,
                :defaultParcelPackingConfiguration,
                :returnLabelMailTemplateTechnicalName,
                :batchSize,
                :supportsSenderAddressForShipments,
                :supportsReceiverAddressForReturnShipments,
                :supportsImporterOfRecordsAddress,
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`),
                `abbreviation` = VALUES(`abbreviation`),
                `config_domain` = VALUES(`config_domain`),
                `shipment_config_default_values` = VALUES(`shipment_config_default_values`),
                `shipment_config_options` = VALUES(`shipment_config_options`),
                `storefront_config_default_values` = VALUES(`storefront_config_default_values`),
                `storefront_config_options` = VALUES(`storefront_config_options`),
                `return_shipment_config_default_values` = VALUES(`return_shipment_config_default_values`),
                `return_shipment_config_options` = VALUES(`return_shipment_config_options`),
                `default_parcel_packing_configuration` = VALUES(`default_parcel_packing_configuration`),
                `return_label_mail_template_type_technical_name` = VALUES(`return_label_mail_template_type_technical_name`),
                `batch_size` = VALUES(`batch_size`),
                `supports_sender_address_for_shipments` = VALUES(`supports_sender_address_for_shipments`),
                `supports_receiver_address_for_return_shipments` = VALUES(`supports_receiver_address_for_return_shipments`),
                `supports_importer_of_records_address` = VALUES(`supports_importer_of_records_address`),
                `updated_at` = UTC_TIMESTAMP(3)',
            [
                'technicalName' => $carrier->getTechnicalName(),
                'name' => $carrier->getName(),
                'abbreviation' => $carrier->getAbbreviation(),
                'configDomain' => $carrier->getConfigDomain(),
                'shipmentConfigDefaultValues' => Json::stringify($carrier->getShipmentConfigDescription()->getDefaultValues()),
                'shipmentConfigOptions' => Json::stringify($carrier->getShipmentConfigDescription()->getOptions()),
                'storefrontConfigDefaultValues' => Json::stringify($carrier->getStorefrontConfigDescription()->getDefaultValues()),
                'storefrontConfigOptions' => Json::stringify($carrier->getStorefrontConfigDescription()->getOptions()),
                'returnShipmentConfigDefaultValues' => Json::stringify($carrier->getReturnShipmentConfigDescription()->getDefaultValues()),
                'returnShipmentConfigOptions' => Json::stringify($carrier->getReturnShipmentConfigDescription()->getOptions()),
                'defaultParcelPackingConfiguration' => Json::stringify($carrier->getDefaultParcelPackingConfiguration()),
                'returnLabelMailTemplateTechnicalName' => $carrier->getReturnLabelMailTemplateTechnicalName(),
                'batchSize' => $carrier->getBatchSize(),
                'supportsSenderAddressForShipments' => (int) $carrier->supportsSenderAddressForShipments(),
                'supportsReceiverAddressForReturnShipments' => (int) $carrier->supportsReceiverAddressForReturnShipments(),
                'supportsImporterOfRecordsAddress' => (int) $carrier->supportsImporterOfRecordsAddress(),
            ],
        );
    }
}
