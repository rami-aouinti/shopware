<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
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
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<GoodsReceiptEntity>
 */
class GoodsReceiptDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_goods_receipt';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GoodsReceiptEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GoodsReceiptCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new FkField('currency_id', 'currencyId', CurrencyDefinition::class, 'id'),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id'),
            new FloatField('currency_factor', 'currencyFactor'),
            new CashRoundingConfigField('item_rounding', 'itemRounding'),
            new CashRoundingConfigField('total_rounding', 'totalRounding'),

            new CartPriceField('price', 'price'),
            (new FloatField('amount_total', 'amountTotal'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new Computed(), new WriteProtected()),

            new FkField('user_id', 'userId', UserDefinition::class, 'id'),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            new JsonField('user_snapshot', 'userSnapshot'),
            new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),
            new JsonField('warehouse_snapshot', 'warehouseSnapshot'),

            new FkField('supplier_id', 'supplierId', SupplierDefinition::class),
            new ManyToOneAssociationField('supplier', 'supplier_id', SupplierDefinition::class, 'id'),
            new JsonField('supplier_snapshot', 'supplierSnapshot'),

            (new PhpEnumField('type', 'type', GoodsReceiptType::class))->addFlags(new Required()),

            (new ManyToManyAssociationField(
                'supplierOrders',
                SupplierOrderDefinition::class,
                SupplierOrderGoodsReceiptMappingDefinition::class,
                'goods_receipt_id',
                'supplier_order_id',
            ))->addFlags(new CascadeDelete()),

            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id'),
            new JsonField('customer_snapshot', 'customerSnapshot'),

            (new ManyToManyAssociationField(
                'returnOrders',
                ReturnOrderDefinition::class,
                ReturnOrderGoodsReceiptMappingDefinition::class,
                'goods_receipt_id',
                'return_order_id',
            ))->addFlags(new CascadeDelete()),

            // A ManyToManyAssociationField is used even though this actually is a one-to-many association. This is
            // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
            // transparent for users of the DAL and the API. The one-to-many characteristics are enforced by a unique
            // index on the mapping table.
            (new ManyToManyAssociationField(
                'documents',
                DocumentDefinition::class,
                GoodsReceiptDocumentMappingDefinition::class,
                'goods_receipt_id',
                'pickware_document_id',
            ))->addFlags(new CascadeDelete()),

            new ManyToManyAssociationField(
                'tags',
                TagDefinition::class,
                GoodsReceiptTagDefinition::class,
                'goods_receipt_id',
                'tag_id',
            ),

            (new NumberRangeField('number', 'number', 255))->addFlags(new Required()),
            new LongTextField('comment', 'comment'),
            (new StateMachineStateField(
                'state_id',
                'stateId',
                GoodsReceiptStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            (new ManyToOneAssociationField(
                'state',
                'state_id',
                StateMachineStateDefinition::class,
                'id',
            ))->addFlags(new ApiAware()),

            // Reverse side associations
            (new OneToManyAssociationField(
                'lineItems',
                GoodsReceiptLineItemDefinition::class,
                'goods_receipt_id',
                'id',
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_goods_receipt_id',
                'id',
            )),
            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_goods_receipt_id',
                'id',
            )),
            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'goods_receipt_id',
                'id',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
