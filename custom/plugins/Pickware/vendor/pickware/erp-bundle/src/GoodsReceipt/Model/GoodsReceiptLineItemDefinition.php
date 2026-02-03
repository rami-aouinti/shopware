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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\StockValuation\Model\PurchaseDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<GoodsReceiptLineItemEntity>
 */
class GoodsReceiptLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_goods_receipt_line_item';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GoodsReceiptLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GoodsReceiptLineItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('goods_receipt_id', 'goodsReceiptId', GoodsReceiptDefinition::class, 'id'))
                ->addFlags(new Required()),
            new ManyToOneAssociationField('goodsReceipt', 'goods_receipt_id', GoodsReceiptDefinition::class, 'id'),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),

            new FkField('product_id', 'productId', ProductDefinition::class, 'id'),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new FkField('batch_id', 'batchId', BatchDefinition::class, 'id'),
            new ManyToOneAssociationField('batch', 'batch_id', BatchDefinition::class, 'id'),

            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),

            (new PhpEnumField(
                'destination_assignment_source',
                'destinationAssignmentSource',
                GoodsReceiptLineItemDestinationAssignmentSource::class,
            ))->addFlags(new Required()),

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

            new CalculatedPriceField('price', 'price'),
            new PriceDefinitionField('price_definition', 'priceDefinition'),
            (new FloatField('unit_price', 'unitPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new Computed(), new WriteProtected()),

            new FkField('supplier_order_id', 'supplierOrderId', SupplierOrderDefinition::class, 'id'),
            new ManyToOneAssociationField('supplierOrder', 'supplier_order_id', SupplierOrderDefinition::class, 'id'),
            new FkField('return_order_id', 'returnOrderId', ReturnOrderDefinition::class, 'id'),
            new ManyToOneAssociationField('returnOrder', 'return_order_id', ReturnOrderDefinition::class, 'id'),

            new OneToOneAssociationField(
                propertyName: 'purchase',
                storageName: 'id',
                referenceField: 'goods_receipt_line_item_id',
                referenceClass: PurchaseDefinition::class,
                autoload: false,
            ),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'destinationAssignmentSource' => GoodsReceiptLineItemDestinationAssignmentSource::Unset,
        ];
    }
}
