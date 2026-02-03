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

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<UsageReportEntity>
 */
class UsageReportDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_usage_report_usage_report';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UsageReportEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UsageReportCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('uuid', 'uuid'))->addFlags(new Computed(), new WriteProtected()),
            new IntField('order_count', 'orderCount'),
            new DateTimeField('reported_at', 'reportedAt'),
            (new DateTimeField('usage_interval_inclusive_start', 'usageIntervalInclusiveStart'))->addFlags(new Required()),
            (new DateTimeField('usage_interval_exclusive_end', 'usageIntervalExclusiveEnd'))->addFlags(new Required()),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'orders',
                UsageReportOrderDefinition::class,
                'usage_report_id',
                'id',
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
