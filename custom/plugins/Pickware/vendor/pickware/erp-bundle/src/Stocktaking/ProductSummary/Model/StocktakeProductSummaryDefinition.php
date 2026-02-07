<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<StocktakeProductSummaryEntity>
 */
class StocktakeProductSummaryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stocktaking_stocktake_product_summary';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new FkField('stocktake_id', 'stocktakeId', StocktakeDefinition::class),
            new ManyToOneAssociationField('stocktake', 'stocktake_id', StocktakeDefinition::class, 'id'),

            (new IntField('counted_stock', 'countedStock'))->addFlags(new Required()),
            (new IntField('absolute_stock_difference', 'absoluteStockDifference'))->addFlags(new Required()),
            (new IntField('percentage_stock_difference', 'percentageStockDifference'))->addFlags(new Computed(), new WriteProtected()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return StocktakeProductSummaryCollection::class;
    }

    public function getEntityClass(): string
    {
        return StocktakeProductSummaryEntity::class;
    }
}
