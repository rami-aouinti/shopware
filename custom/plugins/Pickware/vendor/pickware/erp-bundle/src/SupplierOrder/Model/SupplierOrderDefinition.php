<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model;

use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\SupplierOrderGoodsReceiptMappingDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderPaymentStateMachine;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\Tag\TagDefinition;

/**
 * @extends EntityDefinition<SupplierOrderEntity>
 */
class SupplierOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_supplier_order';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SupplierOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SupplierOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('supplier_id', 'supplierId', SupplierDefinition::class, 'id')),
            (new ManyToOneAssociationField('supplier', 'supplier_id', SupplierDefinition::class, 'id'))->addFlags(new SetNullOnDelete()),
            (new JsonField('supplier_snapshot', 'supplierSnapshot'))->addFlags(new Required()),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'))->addFlags(new SetNullOnDelete()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),
            (new JsonField('warehouse_snapshot', 'warehouseSnapshot'))->addFlags(new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id'),
            (new CashRoundingConfigField('item_rounding', 'itemRounding'))->addFlags(new Required()),
            (new CashRoundingConfigField('total_rounding', 'totalRounding'))->addFlags(new Required()),

            new CustomFields(),

            (new StateMachineStateField(
                'state_id',
                'stateId',
                SupplierOrderStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            (new ManyToOneAssociationField(
                'state',
                'state_id',
                StateMachineStateDefinition::class,
                'id',
            ))->addFlags(new ApiAware()),
            (new StateMachineStateField(
                'payment_state_id',
                'paymentStateId',
                SupplierOrderPaymentStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            (new ManyToOneAssociationField(
                'paymentState',
                'payment_state_id',
                StateMachineStateDefinition::class,
                'id',
            ))->addFlags(new ApiAware()),

            (new StringField('number', 'number'))->addFlags(new Required()),
            new StringField('supplier_order_number', 'supplierOrderNumber'),
            (new DateTimeField('order_date_time', 'orderDateTime'))->addFlags(new Required()),
            new DateTimeField('due_date', 'dueDate'),
            new DateTimeField('expected_delivery_date', 'expectedDeliveryDate'),
            new DateTimeField('actual_delivery_date', 'actualDeliveryDate'),

            (new CartPriceField('price', 'price'))->addFlags(new Required()),
            (new FloatField('amount_total', 'amountTotal'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new Computed(), new WriteProtected()),

            new LongTextField('internal_comment', 'internalComment'),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'lineItems',
                SupplierOrderLineItemDefinition::class,
                'supplier_order_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_supplier_order_id',
                'id',
            )),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_supplier_order_id',
                'id',
            )),

            (new ManyToManyAssociationField(
                'goodsReceipts',
                GoodsReceiptDefinition::class,
                SupplierOrderGoodsReceiptMappingDefinition::class,
                'supplier_order_id',
                'goods_receipt_id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'goodsReceiptLineItems',
                GoodsReceiptLineItemDefinition::class,
                'supplier_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            new ManyToManyAssociationField(
                'tags',
                TagDefinition::class,
                SupplierOrderTagDefinition::class,
                'supplier_order_id',
                'tag_id',
            ),
        ]);
    }
}
