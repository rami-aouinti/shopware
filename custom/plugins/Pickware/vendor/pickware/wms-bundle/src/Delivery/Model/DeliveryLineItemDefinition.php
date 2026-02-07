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

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<DeliveryLineItemEntity>
 * A line item records how many items still have to be shipped for the order of a delivery
 *
 * When picking products for a delivery, it's important to keep track of how many items still need to be shipped for
 * its order. When starting or continuing a picking process, each delivery is updated with information about the
 * products that still need to be shipped for the referenced order. This information is stored as a delivery line item
 * then.
 *
 * The delivery line items are only updated when starting or continuing a picking process but NOT when picking stock
 * into a delivery.
 *
 * However, it's important to note that delivery line items only contain information about what products need to be
 * shipped, and not what has already been shipped, will be shipped, or has been picked. This information can be found
 * in the stock of the assigned stock container.
 */
class DeliveryLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery_line_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField('delivery_id', 'deliveryId', DeliveryDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('delivery', 'delivery_id', DeliveryDefinition::class, 'id'),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return DeliveryLineItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return DeliveryLineItemEntity::class;
    }
}
