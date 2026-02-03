<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ReportRowEntity>
 */
class ReportRowDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_valuation_report_row';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField('report_id', 'reportId', ReportDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('report', 'report_id', ReportDefinition::class, 'id'),

            new FkField('product_id', 'productId', ProductDefinition::class, 'id'),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),
            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),

            (new IntField('stock', 'stock'))->addFlags(new Required()),
            new FloatField('valuation_net', 'valuationNet'),
            new FloatField('valuation_gross', 'valuationGross'),
            (new FloatField('tax_rate', 'taxRate'))->addFlags(new Required()),
            (new FloatField('average_purchase_price_net', 'averagePurchasePriceNet'))->addFlags(new Required()),
            (new IntField('surplus_stock', 'surplusStock'))->addFlags(new Required()),
            (new FloatField('surplus_purchase_price_net', 'surplusPurchasePriceNet'))->addFlags(new Required()),

            (new OneToManyAssociationField(
                'purchases',
                PurchaseDefinition::class,
                'report_row_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            new OneToOneAssociationField(
                propertyName: 'carryOverPurchase',
                storageName: 'id',
                referenceField: 'carry_over_report_row_id',
                referenceClass: PurchaseDefinition::class,
                autoload: false,
            ),
        ]);
    }

    public function getCollectionClass(): string
    {
        return ReportRowCollection::class;
    }

    public function getEntityClass(): string
    {
        return ReportRowEntity::class;
    }
}
