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
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<CashPointClosingTransactionEntity>
 */
class CashPointClosingTransactionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_cash_point_closing_transaction';
    public const TYPE_BELEG = 'Beleg';
    public const TYPE_AVTRANSFER = 'AVTransfer';
    public const TYPE_AVBESTELLUNG = 'AVBestellung';
    public const TYPE_AVTRAINING = 'AVTraining';
    public const TYPE_AVBELEGSTORNO = 'AVBelegstorno';
    public const TYPE_AVBELEGABBRUCH = 'AVBelegabbruch';
    public const TYPE_AVSACHBEZUG = 'AVSachbezug';
    public const TYPE_AVSONSTIGE = 'AVSonstige';
    public const TYPE_AVRECHNUNG = 'AVRechnung';
    public const TYPES = [
        self::TYPE_BELEG,
        self::TYPE_AVTRANSFER,
        self::TYPE_AVBESTELLUNG,
        self::TYPE_AVTRAINING,
        self::TYPE_AVBELEGSTORNO,
        self::TYPE_AVBELEGABBRUCH,
        self::TYPE_AVSACHBEZUG,
        self::TYPE_AVSONSTIGE,
        self::TYPE_AVRECHNUNG,
    ];
    public const BUYER_TYPE_CUSTOMER = 'Kunde';
    public const BUYER_TYPE_WORKER = 'Mitarbeiter';
    public const BUYER_TYPES = [
        self::BUYER_TYPE_CUSTOMER,
        self::BUYER_TYPE_WORKER,
    ];
    public const PAYMENT_TYPE_BAR = 'Bar';
    public const PAYMENT_TYPE_UNBAR = 'Unbar';
    public const PAYMENT_TYPE_ECKARTE = 'ECKarte';
    public const PAYMENT_TYPE_KREDITKARTE = 'Kreditkarte';
    public const PAYMENT_TYPE_ELZAHLUNGSDIENSTLEISTER = 'ElZahlungsdienstleister';
    public const PAYMENT_TYPE_GUTHABENKARTE = 'GuthabenKarte';
    public const PAYMENT_TYPE_KEINE = 'Keine';
    public const PAYMENT_TYPES = [
        self::PAYMENT_TYPE_BAR,
        self::PAYMENT_TYPE_UNBAR,
        self::PAYMENT_TYPE_ECKARTE,
        self::PAYMENT_TYPE_KREDITKARTE,
        self::PAYMENT_TYPE_ELZAHLUNGSDIENSTLEISTER,
        self::PAYMENT_TYPE_GUTHABENKARTE,
        self::PAYMENT_TYPE_KEINE,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CashPointClosingTransactionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CashPointClosingTransactionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('cash_register_id', 'cashRegisterId', CashRegisterDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('cashRegister', 'cash_register_id', 'id', CashRegisterDefinition::class, false),

            (new FkField('cash_point_closing_id', 'cashPointClosingId', CashPointClosingDefinition::class, 'id'))->addFlags(new ApiAware()),
            new OneToOneAssociationField('cashPointClosing', 'cash_point_closing_id', 'id', CashRegisterDefinition::class, false),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id'),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            (new JsonField('user_snapshot', 'userSnapshot'))->addFlags(new Required()),

            new FkField('customer_id', 'customerId', CustomerDefinition::class, 'id'),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id'),
            (new JsonField('buyer', 'buyer'))->addFlags(new Required()),

            // A number that must "be kept consecutive" for each cash registers. Although it is allowed to be repeating
            // during the life time of a cash register we keep it unique just like we do for
            // `CashPointClosingEntity.number`. Hence combining this with `cashRegisterFiskalyClientUuid` uniquely
            // identifies a cash point closing transaction.
            (new IntField('number', 'number'))->addFlags(new Required()),

            // The DSFinV-K transaction type. This must be one of the values defined in `self::TYPES`.
            (new EnumField('type', 'type', self::TYPES))->addFlags(new Required()),

            new StringField('name', 'name'),
            (new DateTimeField('start', 'start'))->addFlags(new Required()),
            (new DateTimeField('end', 'end'))->addFlags(new Required()),
            (new JsonField('total', 'total'))->addFlags(new Required()),
            (new JsonField('payment', 'payment'))->addFlags(new Required()),
            new StringField('comment', 'comment'),
            new JsonField('vat_table', 'vatTable'),

            // Can contain different kinds of fiscalization context, depending on the fiscalization method used by the
            // cash register that creates this transaction. Supported types are:
            // - fiskalyDe (containing a fiskaly client uuid and a signature result)
            // - fiskalyAt (containing a receipt number)
            // - null (if the transaction was not (yet) fiscalized)
            new JsonField('fiscalization_context', 'fiscalizationContext'),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'cashPointClosingTransactionLineItems',
                CashPointClosingTransactionLineItemDefinition::class,
                'cash_point_closing_transaction_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
