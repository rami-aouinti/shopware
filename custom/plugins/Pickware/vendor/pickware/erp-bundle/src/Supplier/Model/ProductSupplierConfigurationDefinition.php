<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ProductSupplierConfigurationEntity>
 */
class ProductSupplierConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_product_supplier_configuration';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';
    public const ENTITY_DELETED_EVENT = self::ENTITY_NAME . '.deleted';
    public const DEFAULT_MIN_PURCHASE = 1;
    public const DEFAULT_PURCHASE_STEPS = 1;
    public const DEFAULT_PURCHASE_PRICE = [
        'currencyId' => Defaults::CURRENCY,
        'net' => 0.0,
        'gross' => 0.0,
        'linked' => true,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSupplierConfigurationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSupplierConfigurationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'product',
                'product_id',
                ProductDefinition::class,
                'id',
                false,
            ),

            (new FkField('supplier_id', 'supplierId', SupplierDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'supplier',
                'supplier_id',
                SupplierDefinition::class,
                'id',
                autoload: false,
            ),

            new StringField('supplier_product_number', 'supplierProductNumber'),
            (new IntField('min_purchase', 'minPurchase'))->addFlags(new Required()),
            (new IntField('purchase_steps', 'purchaseSteps'))->addFlags(new Required()),
            (new PriceField('purchase_prices', 'purchasePrices'))->addFlags(new Required()),
            // The status of the default supplier of a product is stored on the PickwareProduct entity. Therefore, in
            // order to fetch product supplier configurations that belong to the default supplier, we need to construct
            // a query that checks if the supplier ID of a product supplier configuration is equal to the default
            // supplier ID stored at its corresponding Pickware product. However, Shopware's Criteria object does not
            // allow expressing this type of filtering condition. In order to still be able to fetch product supplier
            // configurations that belong to the default supplier via the DAL, we keep track of this "indexed" property
            // when updating the default supplier of a product.
            // An alternative to saving the default supplier on the Pickware product would be saving a reference to the
            // default "product supplier configuration". This would allow filtering product supplier configurations that
            // are marked as "default" but it would make cloning products via the DAL impossible, since the DAL would
            // only clone the product supplier configurations of a product without updating this default supplier
            // configuration reference.
            (new BoolField('supplier_is_default', 'supplierIsDefault'))->addFlags(new Required()),

            // Field to track delivery time in days for products per supplier
            new IntField('delivery_time_days', 'deliveryTimeDays'),

            // Associations with foreign keys on the other side
            (new OneToOneAssociationField(
                'purchaseListItem',
                'id',
                'product_supplier_configuration_id',
                PurchaseListItemDefinition::class,
                autoload: false,
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'minPurchase' => self::DEFAULT_MIN_PURCHASE,
            'purchaseSteps' => self::DEFAULT_PURCHASE_STEPS,
            'purchasePrices' => [self::DEFAULT_PURCHASE_PRICE],
            'supplierIsDefault' => false,
        ];
    }
}
