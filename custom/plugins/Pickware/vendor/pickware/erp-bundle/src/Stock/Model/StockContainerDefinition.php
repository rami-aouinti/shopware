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

use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<StockContainerEntity>
 */
class StockContainerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_container';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockContainerEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockContainerCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('number', 'number'),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            // Reverse side associations
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_stock_container_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_stock_container_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'stock_container_id',
                'id',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
