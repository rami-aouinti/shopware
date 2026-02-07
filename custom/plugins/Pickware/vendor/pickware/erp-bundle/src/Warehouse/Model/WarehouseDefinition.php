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

use Pickware\PickwareErpStarter\Address\Model\AddressDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockDefinition;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<WarehouseEntity>
 */
class WarehouseDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_warehouse';
    public const ENTITY_LOADED_EVENT = self::ENTITY_NAME . '.loaded';
    public const ENTITY_PARTIAL_LOADED_EVENT = self::ENTITY_NAME . '.partial_loaded';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WarehouseEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WarehouseCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('code', 'code'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new BoolField('is_stock_available_for_sale', 'isStockAvailableForSale'))->addFlags(new Required()),
            (new BoolField('is_default', 'isDefault'))->addFlags(new Runtime()),
            (new BoolField('is_default_receiving', 'isDefaultReceiving'))->addFlags(new Runtime()),
            new StringField('timezone', 'timezone'),

            new CustomFields(),

            (new FkField('address_id', 'addressId', AddressDefinition::class, 'id'))->addFlags(),
            new OneToOneAssociationField('address', 'address_id', 'id', AddressDefinition::class, false),

            // Associations that exist only to define restrict delete / cascade delete / set null
            (new OneToManyAssociationField(
                'binLocations',
                BinLocationDefinition::class,
                'warehouse_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_warehouse_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_warehouse_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'warehouse_id',
                'id',
            ))->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField(
                'warehouseStocks',
                WarehouseStockDefinition::class,
                'warehouse_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'productWarehouseConfigurations',
                ProductWarehouseConfigurationDefinition::class,
                'warehouse_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'returnOrders',
                ReturnOrderDefinition::class,
                'warehouse_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                propertyName: 'stockValuationReports',
                referenceClass: ReportDefinition::class,
                referenceField: 'warehouse_id',
                localField: 'id',
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
