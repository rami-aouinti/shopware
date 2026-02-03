<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ProductWarehouseConfigurationEntity>
 */
class ProductWarehouseConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_warehouse_configuration';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductWarehouseConfigurationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductWarehouseConfigurationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            (new FkField('default_bin_location_id', 'defaultBinLocationId', BinLocationDefinition::class, 'id')),
            new ManyToOneAssociationField('defaultBinLocation', 'default_bin_location_id', BinLocationDefinition::class, 'id'),

            (new IntField('reorder_point', 'reorderPoint'))->addFlags(new Required()),
            new IntField('target_maximum_quantity', 'targetMaximumQuantity'),
            new IntField('stock_below_reorder_point', 'stockBelowReorderPoint'),

            new FkField('warehouse_stock_id', 'warehouseStockId', WarehouseStockDefinition::class, 'id'),
            new OneToOneAssociationField(
                'warehouseStock',
                'warehouse_stock_id',
                'id',
                WarehouseStockDefinition::class,
                false,
            ),
        ]);
    }
}
