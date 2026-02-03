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

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\PickwarePos\CashPointClosing\CustomAggregation\CashPointClosingCustomAggregation;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
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
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<CashPointClosingEntity>
 */
class CashPointClosingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_cash_point_closing';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CashPointClosingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CashPointClosingCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            // The ID of the fiskaly cash register this cash point closing belongs to. This is the same ID used as the
            // "client ID" when signing a transaction with the fiskaly KassenSichV API.
            //
            // Note: All elements in `cashPointClosingTransactions` must have the same `cashRegisterFiskalyClientUuid`
            //       as an entity of this type!
            new StringField('cash_register_fiskaly_client_uuid', 'cashRegisterFiskalyClientUuid'),

            // An "ascending, consecutive, non-resettable" number that "must not be repeated within a cash register".
            // Combining this with `cashRegisterFiskalyClientUuid` uniquely identifies a cash point closing.
            (new IntField('number', 'number'))->addFlags(new Required()),

            (new DateTimeField('export_creation_date', 'exportCreationDate'))->addFlags(new Required()),
            (new JsonField('cash_statement', 'cashStatement'))->addFlags(new Required()),

            (new FkField('cash_register_id', 'cashRegisterId', CashRegisterDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('cashRegister', 'cash_register_id', 'id', CashRegisterDefinition::class, false),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),
            (new JsonField('user_snapshot', 'userSnapshot'))->addFlags(new Required()),

            // Custom aggregation values for display purposes. Should not be used for further calculation.
            (new JsonSerializableObjectField(
                'custom_aggregation',
                'customAggregation',
                CashPointClosingCustomAggregation::class,
            ))->addFlags(new Required()),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'cashPointClosingTransactions',
                CashPointClosingTransactionDefinition::class,
                'cash_point_closing_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
