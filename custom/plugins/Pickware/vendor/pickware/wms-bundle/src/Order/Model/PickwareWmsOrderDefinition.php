<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Order\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickwareWmsOrderEntity>
 */
class PickwareWmsOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickwareWmsOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickwareWmsOrderCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'isSingleItemOrder' => false,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            (new FkField('order_id', 'orderId', OrderDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField(
                propertyName: 'order',
                storageName: 'order_id',
                referenceField: 'id',
                referenceClass: OrderDefinition::class,
                autoload: false,
            ),

            (new BoolField('is_single_item_order', 'isSingleItemOrder'))->addFlags(new Required()),
        ]);
    }
}
