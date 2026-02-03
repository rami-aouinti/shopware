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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<SupplierOrderLineItemEntity>
 */
class SupplierOrderLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_supplier_order_line_item';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SupplierOrderLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SupplierOrderLineItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('supplier_order_id', 'supplierOrderId', SupplierOrderDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('supplierOrder', 'supplier_order_id', SupplierOrderDefinition::class, 'id'),

            new FkField('product_id', 'productId', ProductDefinition::class, 'id'),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),
            (new JsonField('product_supplier_configuration_snapshot', 'productSupplierConfigurationSnapshot'))->addFlags(new Required()),

            (new CalculatedPriceField('price', 'price'))->addFlags(new Required()),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->addFlags(new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('unit_price', 'unitPrice'))->addFlags(new Computed(), new WriteProtected()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new Computed(), new WriteProtected()),

            new DateTimeField('expected_delivery_date', 'expectedDeliveryDate'),
            new DateTimeField('actual_delivery_date', 'actualDeliveryDate'),
        ]);
    }
}
