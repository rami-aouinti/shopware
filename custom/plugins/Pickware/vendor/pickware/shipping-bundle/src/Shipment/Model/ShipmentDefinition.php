<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\ShippingBundle\Carrier\Model\CarrierDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @extends EntityDefinition<ShipmentEntity>
 */
class ShipmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shipping_shipment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShipmentCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShipmentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new JsonSerializableObjectField('shipment_blueprint', 'shipmentBlueprint', ShipmentBlueprint::class))->addFlags(new Required()),
            new JsonField('meta_information', 'metaInformation'),
            (new BoolField('cancelled', 'cancelled'))->addFlags(new Required()),
            (new BoolField('is_return_shipment', 'isReturnShipment'))->addFlags(new Required()),
            (new BoolField('cash_on_delivery_enabled', 'cashOnDeliveryEnabled'))->addFlags(new Required()),

            (new NonUuidFkField(
                'carrier_technical_name',
                'carrierTechnicalName',
                CarrierDefinition::class,
                'technicalName',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'carrier',
                'carrier_technical_name',
                CarrierDefinition::class,
                'technical_name',
            ),

            (new OneToManyAssociationField(
                'trackingCodes',
                TrackingCodeDefinition::class,
                'shipment_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                DocumentShipmentMappingDefinition::class,
                'shipment_id',
                'document_id',
            ))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField(
                'orders',
                OrderDefinition::class,
                ShipmentOrderMappingDefinition::class,
                'shipment_id',
                'order_id',
            ))->addFlags(new CascadeDelete()),

            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class, 'id'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id'),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'cancelled' => false,
            'isReturnShipment' => false,
            'cashOnDeliveryEnabled' => false,
        ];
    }
}
