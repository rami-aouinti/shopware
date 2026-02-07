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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

/**
 * @extends EntityDefinition<ReturnOrderRefundEntity>
 */
class ReturnOrderRefundDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_return_order_refund';
    public const ENTITY_WRITTEN_EVENT = self::ENTITY_NAME . '.written';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new VersionField())->addFlags(new ApiAware()),

            (new JsonSerializableObjectField(
                'money_value',
                'moneyValue',
                MoneyValue::class,
            ))->addFlags(new Required()),
            // The following are calculated read only fields (calculated by the database)
            (new FloatField('amount', 'amount'))->addFlags(new Computed()),
            (new StringField('currency_iso_code', 'currencyIsoCode'))->addFlags(new Computed()),
            new StringField('transaction_id', 'transactionId'),
            (new JsonField('transaction_information', 'transactionInformation', [], []))->addFlags(new Required()),

            (new StateMachineStateField(
                'state_id',
                'stateId',
                ReturnOrderRefundStateMachine::TECHNICAL_NAME,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class, 'id'),

            // Currently, only one refund is allowed for a return order. We plan to extend this to multiple refunds in the
            // future
            (new FkField('return_order_id', 'returnOrderId', ReturnOrderDefinition::class))->addFlags(new Required()),
            (new FixedReferenceVersionField(ReturnOrderDefinition::class, 'return_order_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField(
                'returnOrder',
                'return_order_id',
                'id',
                ReturnOrderDefinition::class,
                false, // $autoload
            ),

            (new FkField(
                'payment_method_id',
                'paymentMethodId',
                PaymentMethodDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id'),
        ]);
    }

    public function getCollectionClass(): string
    {
        return ReturnOrderRefundCollection::class;
    }

    public function getEntityClass(): string
    {
        return ReturnOrderRefundEntity::class;
    }

    public function getParentDefinitionClass(): string
    {
        return ReturnOrderDefinition::class;
    }

    public function getDefaults(): array
    {
        return [
            'transactionInformation' => [],
        ];
    }
}
