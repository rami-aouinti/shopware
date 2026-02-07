<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\ReturnOrderGoodsReceiptMappingDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<ReturnOrderEntity>
 */
class ReturnOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_return_order';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            // Total amount to be refunded for this return order. Since we have no shipping costs in return orders the positionPrice
            // will always equal the totalPrice. It is named "price" to avoid collision with the calculated field
            // "amount total".
            (new CartPriceField('price', 'price'))->addFlags(new Required()),
            // The following are calculated read only fields (calculated by the database)
            (new FloatField('amount_total', 'amountTotal'))->addFlags(
                new Computed(),
                new WriteProtected(),
                new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING),
            ),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new Computed(), new WriteProtected()),

            // A comment that can be entered by a worker of the shop
            new LongTextField('internal_comment', 'internalComment'),

            (new NumberRangeField('number', 'number', 255))->addFlags(
                new Required(),
                new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false),
            ),

            (new CalculatedPriceField('shipping_costs', 'shippingCosts')),

            (new OneToManyAssociationField(
                'lineItems',
                ReturnOrderLineItemDefinition::class,
                'return_order_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            // Currently, only one refund is allowed for a return order. We plan to extend this to multiple refunds in the
            // future
            (new OneToOneAssociationField(
                'refund',
                'id',
                'return_order_id',
                ReturnOrderRefundDefinition::class,
                false,
            ))->addFlags(new CascadeDelete()),

            (new StateMachineStateField(
                'state_id',
                'stateId',
                ReturnOrderStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class, 'id'),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            // The user that has created to return order
            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),

            // Reverse side associations
            (new OneToManyAssociationField(
                'sourceStockMovements',
                StockMovementDefinition::class,
                'source_return_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'destinationStockMovements',
                StockMovementDefinition::class,
                'destination_return_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'stocks',
                StockDefinition::class,
                'return_order_id',
                'id',
            ))->addFlags(new RestrictDelete()),

            (new ManyToManyAssociationField(
                'goodsReceipts',
                GoodsReceiptDefinition::class,
                ReturnOrderGoodsReceiptMappingDefinition::class,
                'return_order_id',
                'goods_receipt_id',
            ))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField(
                'goodsReceiptLineItems',
                GoodsReceiptLineItemDefinition::class,
                'return_order_id',
                'id',
            ))->addFlags(new SetNullOnDelete()),

            new ManyToManyAssociationField(
                'tags',
                TagDefinition::class,
                ReturnOrderTagDefinition::class,
                'return_order_id',
                'tag_id',
            ),
        ]);
    }

    public function getCollectionClass(): string
    {
        return ReturnOrderCollection::class;
    }

    public function getEntityClass(): string
    {
        return ReturnOrderEntity::class;
    }
}
