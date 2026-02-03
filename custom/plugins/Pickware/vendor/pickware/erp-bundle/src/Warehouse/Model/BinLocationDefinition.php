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

use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<BinLocationEntity>
 */
class BinLocationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_bin_location';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BinLocationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BinLocationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('code', 'code'))->addFlags(new Required()),
            (new IntField('position', 'position')),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            // Associations that exist only to define restrict delete / cascade delete / set null
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_bin_location_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_bin_location_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'bin_location_id',
                'id',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'productWarehouseConfigurations',
                ProductWarehouseConfigurationDefinition::class,
                'default_bin_location_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'pickwareErpProductStockLocationMappings',
                ProductStockLocationMappingDefinition::class,
                'bin_location_id',
                'id',
            ))->addFlags(new CascadeDelete(false)),

            (new OneToManyAssociationField(
                'destinationGoodsReceiptLineItems',
                GoodsReceiptLineItemDefinition::class,
                'destination_bin_location_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
