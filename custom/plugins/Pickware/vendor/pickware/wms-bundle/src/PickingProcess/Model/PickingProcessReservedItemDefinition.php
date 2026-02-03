<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
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
 * @extends EntityDefinition<PickingProcessReservedItemEntity>
 */
class PickingProcessReservedItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_process_reserved_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickingProcessReservedItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickingProcessReservedItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new FkField('picking_process_id', 'pickingProcessId', PickingProcessDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcess', 'picking_process_id', PickingProcessDefinition::class, 'id'),

            (new NonUuidFkField('location_type_technical_name', 'locationTypeTechnicalName', LocationTypeDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('locationType', 'location_type_technical_name', LocationTypeDefinition::class, 'technical_name'),

            new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            new FkField('bin_location_id', 'binLocationId', BinLocationDefinition::class, 'id'),
            new ManyToOneAssociationField('binLocation', 'bin_location_id', BinLocationDefinition::class, 'id'),

            new FkField('order_id', 'orderId', OrderDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            new FkField('return_order_id', 'returnOrderId', ReturnOrderDefinition::class),
            new FixedReferenceVersionField(ReturnOrderDefinition::class, 'return_order_version_id'),
            new ManyToOneAssociationField('returnOrder', 'return_order_id', ReturnOrderDefinition::class, 'id'),

            new FkField('stock_container_id', 'stockContainerId', StockContainerDefinition::class, 'id'),
            new ManyToOneAssociationField('stockContainer', 'stock_container_id', StockContainerDefinition::class, 'id'),

            new NonUuidFkField('special_stock_location_technical_name', 'specialStockLocationTechnicalName', SpecialStockLocationDefinition::class, 'technical_name'),
            new ManyToOneAssociationField('specialStockLocation', 'special_stock_location_technical_name', SpecialStockLocationDefinition::class, 'technical_name'),
            (new IntField('quantity', 'quantity', 1))->addFlags(new Required()),
            (new IntField('position', 'position'))->addFlags(new Required()),
        ]);
    }
}
