<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Model;

use Pickware\DalBundle\Field\CalendarDateField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<BatchEntity>
 */
class BatchDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_batch';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BatchEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BatchCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new StringField('number', 'number')),
            new LongTextField('comment', 'comment'),
            new CalendarDateField('production_date', 'productionDate'),
            new CalendarDateField('best_before_date', 'bestBeforeDate'),

            (new IntField('physical_stock', 'physicalStock'))->addFlags(new Required(), new WriteProtected()),

            (new CustomFields())->addFlags(new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            new JsonField('user_snapshot', 'userSnapshot'),

            // Reverse side associations
            (new OneToManyAssociationField(
                'stockMappings',
                BatchStockMappingDefinition::class,
                'batch_id',
                'id',
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'stockMovementMappings',
                BatchStockMovementMappingDefinition::class,
                'batch_id',
                'id',
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'goodsReceiptLineItems',
                GoodsReceiptLineItemDefinition::class,
                'batch_id',
                'id',
            ))->addFlags(new RestrictDelete()),

            new ManyToManyAssociationField(
                'tags',
                TagDefinition::class,
                BatchTagDefinition::class,
                'batch_id',
                'tag_id',
            ),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'physicalStock' => 0,
            'customFields' => [],
        ];
    }
}
