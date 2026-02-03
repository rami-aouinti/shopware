<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProperty\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickingPropertyDeliveryRecordEntity>
 */
class PickingPropertyDeliveryRecordDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_property_delivery_record';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField(
                'product_id',
                'productId',
                ProductDefinition::class,
            ))->addFlags(new Required()),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class),

            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),

            new FkField('delivery_id', 'deliveryId', DeliveryDefinition::class),
            new ManyToOneAssociationField('delivery', 'delivery_id', DeliveryDefinition::class),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'values',
                PickingPropertyDeliveryRecordValueDefinition::class,
                'picking_property_delivery_record_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PickingPropertyDeliveryRecordCollection::class;
    }

    public function getEntityClass(): string
    {
        return PickingPropertyDeliveryRecordEntity::class;
    }
}
