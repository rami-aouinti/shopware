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

use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PurchaseEntity>
 */
class PurchaseDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_valuation_report_purchase';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField(storageName: 'id', propertyName: 'id'))->addFlags(new PrimaryKey()),

            (new FkField(
                storageName: 'report_row_id',
                propertyName: 'reportRowId',
                referenceClass: ReportRowDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                propertyName: 'reportRow',
                storageName: 'report_row_id',
                referenceClass: ReportRowDefinition::class,
                referenceField: 'id',
            ),

            (new DateField(storageName: 'date', propertyName: 'date'))->addFlags(new Required()),
            (new FloatField(
                storageName: 'purchase_price_net',
                propertyName: 'purchasePriceNet',
            ))->addFlags(new Required()),
            (new IntField(storageName: 'quantity', propertyName: 'quantity'))->addFlags(new Required()),
            (new IntField(
                storageName: 'quantity_used_for_valuation',
                propertyName: 'quantityUsedForValuation',
            ))->addFlags(new Required()),
            (new PhpEnumField(
                storageName: 'type',
                propertyName: 'type',
                enumName: PurchaseType::class,
            ))->addFlags(new Required()),

            new FkField(
                storageName: 'goods_receipt_line_item_id',
                propertyName: 'goodsReceiptLineItemId',
                referenceClass: GoodsReceiptLineItemDefinition::class,
            ),
            new OneToOneAssociationField(
                propertyName: 'goodsReceiptLineItem',
                storageName: 'goods_receipt_line_item_id',
                referenceField: 'id',
                referenceClass: GoodsReceiptLineItemDefinition::class,
                autoload: false,
            ),

            new FkField(
                storageName: 'carry_over_report_row_id',
                propertyName: 'carryOverReportRowId',
                referenceClass: ReportRowDefinition::class,
            ),
            new OneToOneAssociationField(
                propertyName: 'carryOverReportRow',
                storageName: 'carry_over_report_row_id',
                referenceField: 'id',
                referenceClass: ReportRowDefinition::class,
                autoload: false,
            ),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PurchaseCollection::class;
    }

    public function getEntityClass(): string
    {
        return PurchaseEntity::class;
    }
}
