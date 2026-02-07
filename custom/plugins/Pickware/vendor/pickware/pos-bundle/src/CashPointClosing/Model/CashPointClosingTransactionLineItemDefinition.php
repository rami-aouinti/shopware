<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<CashPointClosingTransactionLineItemEntity>
 */
class CashPointClosingTransactionLineItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_cash_point_closing_transaction_line_item';
    public const TYPE_ANFANGSBESTAND = 'Anfangsbestand';
    public const TYPE_UMSATZ = 'Umsatz';
    public const TYPE_PFAND = 'Pfand';
    public const TYPE_PFANDRUECKZAHLUNG = 'PfandRueckzahlung';
    public const TYPE_MEHRZWECKGUTSCHEINKAUF = 'MehrzweckgutscheinKauf';
    public const TYPE_MEHRZWECKGUTSCHEINEINLOESUNG = 'MehrzweckgutscheinEinloesung';
    public const TYPE_EINZWECKGUTSCHEINKAUF = 'EinzweckgutscheinKauf';
    public const TYPE_EINZWECKGUTSCHEINEINLOESUNG = 'EinzweckgutscheinEinloesung';
    public const TYPE_FORDERUNGSENTSTEHUNG = 'Forderungsentstehung';
    public const TYPE_FORDERUNGSAUFLOESUNG = 'Forderungsaufloesung';
    public const TYPE_ANZAHLUNGSEINSTELLUNG = 'Anzahlungseinstellung';
    public const TYPE_ANZAHLUNGSAUFLOESUNG = 'Anzahlungsaufloesung';
    public const TYPE_PRIVATEINLAGE = 'Privateinlage';
    public const TYPE_PRIVATENTNAHME = 'Privatentnahme';
    public const TYPE_GELDTRANSIT = 'Geldtransit';
    public const TYPE_DIFFERENZSOLLIST = 'DifferenzSollIst';
    public const TYPE_TRINKGELDAG = 'TrinkgeldAG';
    public const TYPE_TRINKGELDAN = 'TrinkgeldAN';
    public const TYPE_AUSZAHLUNG = 'Auszahlung';
    public const TYPE_EINZAHLUNG = 'Einzahlung';
    public const TYPE_RABATT = 'Rabatt';
    public const TYPE_AUFSCHLAG = 'Aufschlag';
    public const TYPE_LOHNZAHLUNG = 'Lohnzahlung';
    public const TYPE_ZUSCHUSSECHT = 'ZuschussEcht';
    public const TYPE_ZUSCHUSSUNECHT = 'ZuschussUnecht';
    public const TYPES = [
        self::TYPE_ANFANGSBESTAND,
        self::TYPE_UMSATZ,
        self::TYPE_PFAND,
        self::TYPE_PFANDRUECKZAHLUNG,
        self::TYPE_MEHRZWECKGUTSCHEINKAUF,
        self::TYPE_MEHRZWECKGUTSCHEINEINLOESUNG,
        self::TYPE_EINZWECKGUTSCHEINKAUF,
        self::TYPE_EINZWECKGUTSCHEINEINLOESUNG,
        self::TYPE_FORDERUNGSENTSTEHUNG,
        self::TYPE_FORDERUNGSAUFLOESUNG,
        self::TYPE_ANZAHLUNGSEINSTELLUNG,
        self::TYPE_ANZAHLUNGSAUFLOESUNG,
        self::TYPE_PRIVATEINLAGE,
        self::TYPE_PRIVATENTNAHME,
        self::TYPE_GELDTRANSIT,
        self::TYPE_DIFFERENZSOLLIST,
        self::TYPE_TRINKGELDAG,
        self::TYPE_TRINKGELDAN,
        self::TYPE_AUSZAHLUNG,
        self::TYPE_EINZAHLUNG,
        self::TYPE_RABATT,
        self::TYPE_AUFSCHLAG,
        self::TYPE_LOHNZAHLUNG,
        self::TYPE_ZUSCHUSSECHT,
        self::TYPE_ZUSCHUSSUNECHT,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CashPointClosingTransactionLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CashPointClosingTransactionLineItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('cash_point_closing_transaction_id', 'cashPointClosingTransactionId', CashPointClosingTransactionDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('cashPointClosingTransaction', 'cash_point_closing_transaction_id', 'id', CashPointClosingTransactionDefinition::class, false),

            new FkField('referenced_cash_point_closing_transaction_id', 'referencedCashPointClosingTransactionId', CashPointClosingTransactionDefinition::class, 'id'),
            new OneToOneAssociationField('referencedCashPointClosingTransaction', 'referenced_cash_point_closing_transaction_id', 'id', CashPointClosingTransactionDefinition::class, false),

            new FkField('product_id', 'productId', ProductDefinition::class, 'id'),
            new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            (new EnumField('type', 'type', self::TYPES))->addFlags(new Required()),
            (new StringField('product_number', 'productNumber'))->addFlags(new Required()),
            new StringField('gtin', 'gtin'),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('voucher_id', 'voucherId'),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new JsonField('vat_table', 'vatTable'))->addFlags(new Required()),
            (new JsonField('price_per_unit', 'pricePerUnit'))->addFlags(new Required()),
            (new JsonField('total', 'total'))->addFlags(new Required()),
            new JsonField('discount', 'discount'),
        ]);
    }
}
