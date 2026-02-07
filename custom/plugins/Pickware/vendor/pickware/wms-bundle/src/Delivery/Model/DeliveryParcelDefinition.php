<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<DeliveryParcelEntity>
 */
class DeliveryParcelDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery_parcel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DeliveryParcelEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DeliveryParcelCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField(
                'delivery_id',
                'deliveryId',
                DeliveryDefinition::class,
                'id',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('delivery', 'delivery_id', DeliveryDefinition::class, 'id'),

            (new ManyToManyAssociationField(
                'trackingCodes',
                TrackingCodeDefinition::class,
                DeliveryParcelTrackingCodeDefinition::class,
                'delivery_parcel_id',
                'tracking_code_id',
            ))->addFlags(new CascadeDelete()),

            (new BoolField('shipped', 'shipped'))->addFlags(new Required()),
        ]);
    }
}
