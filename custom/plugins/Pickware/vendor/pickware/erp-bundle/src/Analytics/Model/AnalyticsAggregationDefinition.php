<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<AnalyticsAggregationEntity>
 */
class AnalyticsAggregationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_analytics_aggregation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new PrimaryKey(), new Required()),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'reports',
                AnalyticsReportDefinition::class,
                'aggregation_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField(
                'aggregationSessions',
                AnalyticsAggregationSessionDefinition::class,
                'aggregation_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return AnalyticsAggregationCollection::class;
    }

    public function getEntityClass(): string
    {
        return AnalyticsAggregationEntity::class;
    }
}
