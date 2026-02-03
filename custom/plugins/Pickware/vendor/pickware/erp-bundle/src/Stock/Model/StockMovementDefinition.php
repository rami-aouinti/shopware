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
use Pickware\DalBundle\Field\NonUuidFkField;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMovementMappingDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<StockMovementEntity>
 */
class StockMovementDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_movement';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockMovementEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockMovementCollection::class;
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new IntField('quantity', 'quantity', 1))->addFlags(new Required()),
            new LongTextField('comment', 'comment'),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new FkField('stock_movement_process_id', 'stockMovementProcessId', StockMovementProcessDefinition::class, 'id'),
            new ManyToOneAssociationField('stockMovementProcess', 'stock_movement_process_id', StockMovementProcessDefinition::class, 'id'),

            new JsonField('source_location_snapshot', 'sourceLocationSnapshot'),

            (new NonUuidFkField(
                'source_location_type_technical_name',
                'sourceLocationTypeTechnicalName',
                LocationTypeDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'sourceLocationType',
                'source_location_type_technical_name',
                LocationTypeDefinition::class,
                'technical_name',
            ),

            new FkField(
                'source_warehouse_id',
                'sourceWarehouseId',
                WarehouseDefinition::class,
                'id',
            ),
            new ManyToOneAssociationField(
                'sourceWarehouse',
                'source_warehouse_id',
                WarehouseDefinition::class,
                'id',
            ),

            new FkField(
                'source_bin_location_id',
                'sourceBinLocationId',
                BinLocationDefinition::class,
                'id',
            ),
            new ManyToOneAssociationField(
                'sourceBinLocation',
                'source_bin_location_id',
                BinLocationDefinition::class,
                'id',
            ),

            new FkField('source_order_id', 'sourceOrderId', OrderDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderDefinition::class, 'source_order_version_id'),
            new ManyToOneAssociationField('sourceOrder', 'source_order_id', OrderDefinition::class, 'id'),

            new FkField('source_return_order_id', 'sourceReturnOrderId', ReturnOrderDefinition::class),
            new FixedReferenceVersionField(ReturnOrderDefinition::class, 'source_return_order_version_id'),
            new ManyToOneAssociationField('sourceReturnOrder', 'source_return_order_id', ReturnOrderDefinition::class, 'id'),

            new FkField('source_stock_container_id', 'sourceStockContainerId', StockContainerDefinition::class, 'id'),
            new ManyToOneAssociationField('sourceStockContainer', 'source_stock_container_id', StockContainerDefinition::class, 'id'),

            new FkField('source_goods_receipt_id', 'sourceGoodsReceiptId', GoodsReceiptDefinition::class, 'id'),
            new ManyToOneAssociationField('sourceGoodsReceipt', 'source_goods_receipt_id', GoodsReceiptDefinition::class, 'id'),

            new NonUuidFkField(
                'source_special_stock_location_technical_name',
                'sourceSpecialStockLocationTechnicalName',
                SpecialStockLocationDefinition::class,
                'technical_name',
            ),
            new ManyToOneAssociationField(
                'sourceSpecialStockLocation',
                'source_special_stock_location_technical_name',
                SpecialStockLocationDefinition::class,
                'technical_name',
            ),

            new JsonField('destination_location_snapshot', 'destinationLocationSnapshot'),

            (new NonUuidFkField(
                'destination_location_type_technical_name',
                'destinationLocationTypeTechnicalName',
                LocationTypeDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'destinationLocationType',
                'destination_location_type_technical_name',
                LocationTypeDefinition::class,
                'technical_name',
            ),

            new FkField(
                'destination_warehouse_id',
                'destinationWarehouseId',
                WarehouseDefinition::class,
                'id',
            ),
            new ManyToOneAssociationField(
                'destinationWarehouse',
                'destination_warehouse_id',
                WarehouseDefinition::class,
                'id',
            ),

            new FkField(
                'destination_bin_location_id',
                'destinationBinLocationId',
                BinLocationDefinition::class,
                'id',
            ),
            new ManyToOneAssociationField(
                'destinationBinLocation',
                'destination_bin_location_id',
                BinLocationDefinition::class,
                'id',
            ),

            new FkField('destination_order_id', 'destinationOrderId', OrderDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderDefinition::class, 'destination_order_version_id'),
            new ManyToOneAssociationField('destinationOrder', 'destination_order_id', OrderDefinition::class, 'id'),

            new FkField('destination_return_order_id', 'destinationReturnOrderId', ReturnOrderDefinition::class),
            new FixedReferenceVersionField(ReturnOrderDefinition::class, 'destination_return_order_version_id'),
            new ManyToOneAssociationField('destinationReturnOrder', 'destination_return_order_id', ReturnOrderDefinition::class, 'id'),

            new FkField('destination_stock_container_id', 'destinationStockContainerId', StockContainerDefinition::class, 'id'),
            new ManyToOneAssociationField('destinationStockContainer', 'destination_stock_container_id', StockContainerDefinition::class, 'id'),

            new FkField('destination_goods_receipt_id', 'destinationGoodsReceiptId', GoodsReceiptDefinition::class, 'id'),
            new ManyToOneAssociationField('destinationGoodsReceipt', 'destination_goods_receipt_id', GoodsReceiptDefinition::class, 'id'),

            new NonUuidFkField(
                'destination_special_stock_location_technical_name',
                'destinationSpecialStockLocationTechnicalName',
                SpecialStockLocationDefinition::class,
                'technical_name',
            ),
            new ManyToOneAssociationField(
                'destinationSpecialStockLocation',
                'destination_special_stock_location_technical_name',
                SpecialStockLocationDefinition::class,
                'technical_name',
            ),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),

            // Reverse side associations
            (new OneToManyAssociationField(
                'batchMappings',
                BatchStockMovementMappingDefinition::class,
                'stock_movement_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
