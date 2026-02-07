<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<CarrierEntity>
 */
class CarrierDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shipping_carrier';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CarrierCollection::class;
    }

    public function getEntityClass(): string
    {
        return CarrierEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('abbreviation', 'abbreviation'))->addFlags(new Required()),
            (new StringField('config_domain', 'configDomain'))->addFlags(new Required()),
            (new JsonField('shipment_config_options', 'shipmentConfigOptions'))->addFlags(new Required()),
            (new JsonField('shipment_config_default_values', 'shipmentConfigDefaultValues'))->addFlags(new Required()),
            (new JsonField('storefront_config_options', 'storefrontConfigOptions'))->addFlags(new Required()),
            (new JsonField('storefront_config_default_values', 'storefrontConfigDefaultValues'))->addFlags(new Required()),
            (new JsonField('return_shipment_config_options', 'returnShipmentConfigOptions'))->addFlags(new Required()),
            (new JsonField('return_shipment_config_default_values', 'returnShipmentConfigDefaultValues'))->addFlags(new Required()),
            (new JsonSerializableObjectField(
                'default_parcel_packing_configuration',
                'defaultParcelPackingConfiguration',
                ParcelPackingConfiguration::class,
            ))->addFlags(new Required()),
            (new JsonField('capabilities', 'capabilities'))->addFlags(new Runtime(dependsOn: ['technicalName'])),
            (new BoolField('active', 'active'))->addFlags(new Runtime(dependsOn: ['technicalName'])),

            new NonUuidFkField(
                'return_label_mail_template_type_technical_name',
                'returnLabelMailTemplateTypeTechnicalName',
                MailTemplateTypeDefinition::class,
            ),
            new ManyToOneAssociationField(
                'returnLabelMailTemplateType',
                'return_label_mail_template_type_technical_name',
                MailTemplateTypeDefinition::class,
                'technical_name',
            ),
            (new IntField('batch_size', 'batchSize'))->addFlags(new Required()),
            (new BoolField('supports_sender_address_for_shipments', 'supportsSenderAddressForShipments'))->addFlags(new Required()),
            (new BoolField('supports_receiver_address_for_return_shipments', 'supportsReceiverAddressForReturnShipments'))->addFlags(new Required()),
            (new BoolField('supports_importer_of_records_address', 'supportsImporterOfRecordsAddress'))->addFlags(new Required()),
        ]);
    }
}
