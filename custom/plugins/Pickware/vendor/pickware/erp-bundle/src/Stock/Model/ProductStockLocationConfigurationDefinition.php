<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ProductStockLocationConfigurationEntity>
 */
class ProductStockLocationConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_stock_location_configuration';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductStockLocationConfigurationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductStockLocationConfigurationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_stock_location_mapping_id', 'productStockLocationMappingId', ProductStockLocationMappingDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('productStockLocationMapping', 'product_stock_location_mapping_id', ProductStockLocationMappingDefinition::class, 'id'),

            (new IntField('reorder_point', 'reorderPoint'))->addFlags(new Required()),
            new IntField('target_maximum_quantity', 'targetMaximumQuantity'),
            (new IntField('stock_below_reorder_point', 'stockBelowReorderPoint'))->addFlags(new WriteProtected()),
        ]);
    }
}
