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

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickingPropertyEntity>
 */
class PickingPropertyDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_picking_property';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new BoolField('show_on_order_documents', 'showOnOrderDocuments'),

            // Associations
            (new ManyToManyAssociationField(
                'products',
                ProductDefinition::class,
                PickingPropertyProductMappingDefinition::class,
                'picking_property_id',
                'product_id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PickingPropertyCollection::class;
    }

    public function getEntityClass(): string
    {
        return PickingPropertyEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'showOnOrderDocuments' => false,
        ];
    }
}
