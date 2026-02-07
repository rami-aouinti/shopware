<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<StocktakeCountingProcessItemEntity>
 */
class StocktakeCountingProcessItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stocktaking_stocktake_counting_process_item';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),

            // Stock in stock location of counting process at the time of the counting process item creation
            (new IntField('stock_in_stock_location_snapshot', 'stockInStockLocationSnapshot'))->addFlags(new Required()),

            (new IntField('absolute_stock_difference_in_stock_location', 'absoluteStockDifference'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('percentage_stock_difference_in_stock_location', 'percentageStockDifference'))->addFlags(new Computed(), new WriteProtected()),

            (new FkField(
                'counting_process_id',
                'countingProcessId',
                StocktakeCountingProcessDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'countingProcess',
                'counting_process_id',
                StocktakeCountingProcessDefinition::class,
                'id',
                false,
            ),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),
            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),

            // Associations with foreign keys on the other side

            (new OneToOneAssociationField(
                'snapshotItem',
                'id',
                'counting_process_item_id',
                StocktakeSnapshotItemDefinition::class,
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return StocktakeCountingProcessItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return StocktakeCountingProcessItemEntity::class;
    }
}
