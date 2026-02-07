<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<UsageReportOrderEntity>
 */
class UsageReportOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_usage_report_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UsageReportOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UsageReportOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new DateTimeField('ordered_at', 'orderedAt'))->addFlags(new Required()),
            (new DateTimeField('order_created_at', 'orderCreatedAt'))->addFlags(new Required()),
            (new DateTimeField('order_created_at_hour', 'orderCreatedAtHour'))->addFlags(new Required()),
            (new EnumField('order_type', 'orderType', UsageReportOrderType::values()))->addFlags(new Required()),

            new JsonField('order_snapshot', 'orderSnapshot'),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new OneToOneAssociationField(
                'order',
                'order_id',
                'id',
                OrderDefinition::class,
                autoload: false,
            ),

            new FkField('usage_report_id', 'usageReportId', UsageReportDefinition::class),
            new ManyToOneAssociationField(
                'usageReport',
                'usage_report_id',
                UsageReportDefinition::class,
                'id',
                autoload: false,
            ),
        ]);
    }
}
