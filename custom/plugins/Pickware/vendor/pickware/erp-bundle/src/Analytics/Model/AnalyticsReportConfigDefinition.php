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
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<AnalyticsReportConfigEntity>
 */
class AnalyticsReportConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_analytics_report_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AnalyticsReportConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AnalyticsReportConfigCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new NonUuidFkField(
                'report_technical_name',
                'reportTechnicalName',
                AnalyticsReportDefinition::class,
                'technicalName',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'report',
                'report_technical_name',
                AnalyticsReportDefinition::class,
                'technical_name',
            ),
            (new FkField(
                'aggregation_session_id',
                'aggregationSessionId',
                AnalyticsAggregationSessionDefinition::class,
                'id',
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'aggregationSession',
                'aggregation_session_id',
                AnalyticsAggregationSessionDefinition::class,
                'id',
            ),
            (new JsonField('list_query', 'listQuery')),
            (new JsonField('calculator_config', 'calculatorConfig'))->addFlags(new Required()),
        ]);
    }
}
