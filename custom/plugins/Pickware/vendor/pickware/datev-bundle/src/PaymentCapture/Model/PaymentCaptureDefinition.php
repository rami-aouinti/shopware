<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<PaymentCaptureEntity>
 */
class PaymentCaptureDefinition extends EntityDefinition
{
    public const TYPE_AUTOMATIC = 'automatic';
    public const TYPE_MANUAL = 'manual';
    private const ALLOWED_TYPES = [
        self::TYPE_AUTOMATIC,
        self::TYPE_MANUAL,
    ];

    public function getEntityName(): string
    {
        return 'pickware_datev_payment_capture';
    }

    public function getCollectionClass(): string
    {
        return PaymentCaptureCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentCaptureEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new EnumField('type', 'type', self::ALLOWED_TYPES))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            new FloatField('original_amount', 'originalAmount'),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id'),
            new StringField('export_comment', 'exportComment'),
            new LongTextField('internal_comment', 'internalComment'),
            new StringField('transaction_reference', 'transactionReference'),
            (new DateTimeField('transaction_date', 'transactionDate'))->addFlags(new Required()),

            (new FkField('order_id', 'orderId', OrderDefinition::class, 'id'))
                ->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'order',
                'order_id',
                OrderDefinition::class,
                'id',
            ),

            new FkField('order_transaction_id', 'orderTransactionId', OrderTransactionDefinition::class, 'id'),
            new FixedReferenceVersionField(OrderTransactionDefinition::class, 'order_transaction_version_id'),
            new ManyToOneAssociationField(
                'orderTransaction',
                'order_transaction_id',
                OrderTransactionDefinition::class,
                'id',
            ),

            new FkField('state_machine_history_id', 'stateMachineHistoryId', StateMachineHistoryDefinition::class, 'id'),
            new ManyToOneAssociationField(
                'stateMachineHistory',
                'state_machine_history_id',
                StateMachineHistoryDefinition::class,
                'id',
            ),

            new FkField('return_order_refund_id', 'returnOrderRefundId', ReturnOrderRefundDefinition::class, 'id'),
            new FixedReferenceVersionField(ReturnOrderRefundDefinition::class, 'return_order_refund_version_id'),
            new ManyToOneAssociationField(
                'returnOrderRefund',
                'return_order_refund_id',
                ReturnOrderRefundDefinition::class,
                'id',
            ),

            new FkField('user_id', 'userId', UserDefinition::class, 'id'),
            new JsonField('user_snapshot', 'userSnapshot'),
            new ManyToOneAssociationField(
                'user',
                'user_id',
                UserDefinition::class,
                'id',
            ),
        ]);
    }
}
