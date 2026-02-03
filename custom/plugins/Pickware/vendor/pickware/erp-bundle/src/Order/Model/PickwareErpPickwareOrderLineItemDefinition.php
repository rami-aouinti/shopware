<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickwareErpPickwareOrderLineItemEntity>
 */
class PickwareErpPickwareOrderLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_pickware_order_line_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickwareErpPickwareOrderLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickwareErpPickwareOrderLineItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderLineItemDefinition::class, 'order_line_item_version_id'))->addFlags(new Required()),
            (new OneToOneAssociationField(
                'orderLineItem',
                'order_line_item_id',
                'id',
                OrderLineItemDefinition::class,
                autoload: false,
            )),

            (new IntField('externally_fulfilled_quantity', 'externallyFulfilledQuantity', minValue: 0))->addFlags(new Required()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'externallyFulfilledQuantity' => 0,
        ];
    }
}
