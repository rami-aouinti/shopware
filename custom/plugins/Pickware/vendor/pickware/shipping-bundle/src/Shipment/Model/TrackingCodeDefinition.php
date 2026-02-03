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

use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<TrackingCodeEntity>
 */
class TrackingCodeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shipping_tracking_code';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TrackingCodeCollection::class;
    }

    public function getEntityClass(): string
    {
        return TrackingCodeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('tracking_code', 'trackingCode'))->addFlags(new Required()),
            new StringField('tracking_url', 'trackingUrl', 65535),
            (new JsonField('meta_information', 'metaInformation'))->addFlags(new Required()),
            (new PhpEnumField('shipping_direction', 'shippingDirection', ShippingDirection::class))->addFlags(new Required()),

            (new FkField('shipment_id', 'shipmentId', ShipmentDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'shipment',
                'shipment_id',
                ShipmentDefinition::class,
                'id',
            ),

            (new ManyToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                DocumentTrackingCodeMappingDefinition::class,
                'tracking_code_id',
                'document_id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    /**
     * @deprecated tag:4.0.0 'shippingDirection' will be required
     */
    public function getDefaults(): array
    {
        return [
            'metaInformation' => [],
            'shippingDirection' => ShippingDirection::Outgoing,
        ];
    }
}
