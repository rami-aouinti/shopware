<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\ShippingBundle\Carrier\Model\CarrierDefinition;
use Pickware\ShippingBundle\ParcelPacking\ParcelPackingConfiguration;
use Pickware\ShippingBundle\Privacy\PrivacyConfiguration;
use Pickware\ShippingBundle\Shipment\AddressConfiguration;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ShippingMethodConfigEntity>
 */
class ShippingMethodConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shipping_shipping_method_config';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShippingMethodConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShippingMethodConfigEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new OneToOneAssociationField(
                'shippingMethod',
                'shipping_method_id',
                'id',
                ShippingMethodDefinition::class,
            ),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class, 'id'))->addFlags(
                new Required(),
            ),

            new ManyToOneAssociationField(
                'carrier',
                'carrier_technical_name',
                CarrierDefinition::class,
                'technical_name',
            ),
            (new NonUuidFkField(
                'carrier_technical_name',
                'carrierTechnicalName',
                CarrierDefinition::class,
                'technicalName',
            ))->addFlags(new Required()),

            (new JsonField('shipment_config', 'shipmentConfig'))->addFlags(new Required()),
            (new JsonField('storefront_config', 'storefrontConfig'))->addFlags(new Required()),
            (new JsonField('return_shipment_config', 'returnShipmentConfig'))->addFlags(new Required()),

            (new JsonSerializableObjectField(
                'parcel_packing_configuration',
                'parcelPackingConfiguration',
                ParcelPackingConfiguration::class,
            ))->addFlags(new Required()),

            (new JsonSerializableObjectField(
                'privacy_configuration',
                'privacyConfiguration',
                PrivacyConfiguration::class,
            ))->addFlags(new Required()),

            (new JsonSerializableObjectField(
                'address_configuration',
                'addressConfiguration',
                AddressConfiguration::class,
            ))->addFlags(new Required()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'shipmentConfig' => [],
            'storefrontConfig' => [],
            'returnShipmentConfig' => [],
            'parcelPackingConfiguration' => new ParcelPackingConfiguration(),
            'privacyConfiguration' => new PrivacyConfiguration(),
            'addressConfiguration' => new AddressConfiguration(),
        ];
    }
}
