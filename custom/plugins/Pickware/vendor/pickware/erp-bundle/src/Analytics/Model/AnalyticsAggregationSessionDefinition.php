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

use Pickware\DalBundle\Field\NonUuidFkField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<AnalyticsAggregationSessionEntity>
 */
class AnalyticsAggregationSessionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_analytics_aggregation_session';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AnalyticsAggregationSessionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AnalyticsAggregationSessionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new NonUuidFkField(
                'aggregation_technical_name',
                'aggregationTechnicalName',
                AnalyticsAggregationDefinition::class,
                'technicalName',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'aggregation',
                'aggregation_technical_name',
                AnalyticsAggregationDefinition::class,
                'technical_name',
            ),
            (new JsonField('config', 'config'))->addFlags(new Required()),
            (new FkField('user_id', 'userId', UserDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'user',
                'user_id',
                UserDefinition::class,
                'id',
            ),
            new DateTimeField('last_calculation', 'lastCalculation'),

            // Associations with foreign keys on the other side
            new OneToManyAssociationField(
                'reportConfigs',
                AnalyticsReportConfigDefinition::class,
                'aggregation_session_id',
                'id',
            ),
        ]);
    }
}
