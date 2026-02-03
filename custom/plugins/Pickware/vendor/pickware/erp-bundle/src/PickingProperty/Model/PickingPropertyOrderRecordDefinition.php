<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\OrderDefinition;
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
 * @extends EntityDefinition<PickingPropertyOrderRecordEntity>
 */
class PickingPropertyOrderRecordDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_picking_property_order_record';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            new FkField('product_id', 'productId', ProductDefinition::class),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class),

            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),

            new FkField('order_id', 'orderId', OrderDefinition::class),
            new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'values',
                PickingPropertyOrderRecordValueDefinition::class,
                'picking_property_order_record_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PickingPropertyOrderRecordCollection::class;
    }

    public function getEntityClass(): string
    {
        return PickingPropertyOrderRecordEntity::class;
    }
}
