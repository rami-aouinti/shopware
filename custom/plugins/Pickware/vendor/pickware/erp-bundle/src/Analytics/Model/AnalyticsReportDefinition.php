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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<AnalyticsReportEntity>
 */
class AnalyticsReportDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_analytics_report';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('technical_name', 'technicalName'))->addFlags(new PrimaryKey(), new Required()),
            (new NonUuidFkField(
                'aggregation_technical_name',
                'aggregationTechnicalName',
                AnalyticsAggregationDefinition::class,
                'technical_name',
            ))->addFlags(new Required()),
            (new ManyToOneAssociationField(
                'aggregation',
                'aggregation_technical_name',
                AnalyticsAggregationDefinition::class,
                'technical_name',
            )),

            // Associations with foreign keys on the other side
            (new OneToManyAssociationField(
                'reportConfigs',
                AnalyticsReportConfigDefinition::class,
                'report_technical_name',
                'technical_name',
            ))->addFlags(new RestrictDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return AnalyticsReportCollection::class;
    }

    public function getEntityClass(): string
    {
        return AnalyticsReportEntity::class;
    }
}
