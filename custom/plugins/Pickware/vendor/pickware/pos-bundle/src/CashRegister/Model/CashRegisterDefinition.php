<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Pickware\PickwarePos\CashRegister\FiscalizationConfiguration;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<CashRegisterEntity>
 */
class CashRegisterDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_cash_register';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CashRegisterEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CashRegisterCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('branch_store_id', 'branchStoreId', BranchStoreDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('branchStore', 'branch_store_id', BranchStoreDefinition::class, 'id'),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('device_uuid', 'deviceUuid'),
            new JsonSerializableObjectField(
                'fiscalization_configuration',
                'fiscalizationConfiguration',
                FiscalizationConfiguration::class,
            ),

            new IntField('transaction_number_prefix', 'transactionNumberPrefix'),

            // Associations with foreign keys on the other side
            new OneToManyAssociationField(
                'cashPointClosings',
                CashPointClosingDefinition::class,
                'cash_register_id',
                'id',
            ),
            new OneToManyAssociationField(
                'cashPointClosingTransactions',
                CashPointClosingTransactionDefinition::class,
                'cash_register_id',
                'id',
            ),
        ]);
    }
}
