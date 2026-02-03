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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<BatchStockMappingEntity>
 */
class BatchStockMappingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_batch_stock_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BatchStockMappingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BatchStockMappingCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('stock_id', 'stockId', StockDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('stock', 'stock_id', StockDefinition::class, 'id'),

            (new FkField('batch_id', 'batchId', BatchDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('batch', 'batch_id', BatchDefinition::class, 'id'),

            // This entity references the product so that no batch can be assigned to a stock that does not belong to
            // the product of the batch
            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
        ]);
    }
}
