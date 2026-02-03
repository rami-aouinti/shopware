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

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ReturnOrderLineItemEntity>
 */
class ReturnOrderLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_return_order_line_item';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_PROMOTION,
        self::TYPE_CUSTOM,
        self::TYPE_CREDIT,
    ];
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PROMOTION = 'promotion';
    public const TYPE_CUSTOM = 'custom';
    public const TYPE_CREDIT = 'credit';
    public const REASONS = [
        self::REASON_UNKNOWN,
        self::REASON_SIZE_TOO_LARGE,
        self::REASON_SIZE_TOO_SMALL,
        self::REASON_UNWANTED,
        self::REASON_NOT_AS_DESCRIBED,
        self::REASON_WRONG_ITEM,
        self::REASON_DEFECTIVE,
        self::REASON_STYLE,
        self::REASON_COLOR,
        self::REASON_OTHER,
    ];
    public const REASON_UNKNOWN = 'unknown';
    public const REASON_SIZE_TOO_LARGE = 'size_too_large';
    public const REASON_SIZE_TOO_SMALL = 'size_too_small';
    public const REASON_UNWANTED = 'unwanted';
    public const REASON_NOT_AS_DESCRIBED = 'not_as_described';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_DEFECTIVE = 'defective';
    public const REASON_STYLE = 'style';
    public const REASON_COLOR = 'color';
    public const REASON_OTHER = 'other';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            // The type of return order line item corresponds to the type of order line item. This field used to be an
            // EnumField, but we softened this restriction to allow creation of return order line items for different
            // and even custom order line item types.
            (new StringField('type', 'type'))->addFlags(new Required()),

            // The "label" of the line item, usually the name of the product or the name of the coupon.
            (new LongTextField('name', 'name'))->addFlags(new Required()),
            // In return orders the quantity can be 0 when the return order is in the draft state and the price may be negative or positive.
            (new IntField('quantity', 'quantity', 0))->addFlags(new Required()),
            (new IntField('position', 'position'))->addFlags(new Required()),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->addFlags(new Required()),

            // The price for a return order line item is positive if you want to refund that amount. It is negative if you
            // want to reduce the total refunded amount, e.g. because one product was broken, or you want
            // to invoice shipping costs.
            // This field also contains the quantity, but this is secondary, use the actual field "quantity" as primary
            // source for the quantity. Try to keep the quantity in the price consistent with the actual quantity.
            (new CalculatedPriceField('price', 'price'))->addFlags(new Required()),
            // The following are calculated read only fields (calculated by the database).
            (new FloatField('unit_price', 'unitPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new Computed(), new WriteProtected()),

            // It is non-optional on the database (with a fallback value).
            (new EnumField('reason', 'reason', self::REASONS))->addFlags(new Required()),

            new FkField('product_id', 'productId', ProductDefinition::class),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),
            new StringField('product_number', 'productNumber', 255),

            (new FkField('return_order_id', 'returnOrderId', ReturnOrderDefinition::class))->addFlags(new Required()),
            (new FixedReferenceVersionField(ReturnOrderDefinition::class, 'return_order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('returnOrder', 'return_order_id', ReturnOrderDefinition::class, 'id'),

            new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class),
            new FixedReferenceVersionField(OrderLineItemDefinition::class, 'order_line_item_version_id'),
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return ['position' => 1];
    }

    public function getParentDefinitionClass(): string
    {
        return ReturnOrderDefinition::class;
    }

    public function getCollectionClass(): string
    {
        return ReturnOrderLineItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return ReturnOrderLineItemEntity::class;
    }
}
