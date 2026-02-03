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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * This entity is used to display product-stock-location combinations in the administration. It exists if and only if
 * there exists a stock entity or a configuration with a non-null entry for the product-stock-location combination.
 *
 * @extends EntityDefinition<ProductStockLocationMappingEntity>
 */
class ProductStockLocationMappingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_stock_location_mapping';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductStockLocationMappingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductStockLocationMappingCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            new FkField('bin_location_id', 'binLocationId', BinLocationDefinition::class, 'id'),
            new ManyToOneAssociationField('binLocation', 'bin_location_id', BinLocationDefinition::class, 'id'),

            new FkField('stock_id', 'stockId', StockDefinition::class, 'id'),
            new ManyToOneAssociationField('stock', 'stock_id', StockDefinition::class, 'id'),

            (new PhpEnumField('stock_location_type', 'stockLocationType', ConfigurableStockLocation::class))->addFlags(new Required()),

            (new OneToOneAssociationField('productStockLocationConfiguration', 'id', 'product_stock_location_mapping_id', ProductStockLocationConfigurationDefinition::class, false))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
