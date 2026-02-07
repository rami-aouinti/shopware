<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Analytics\AnalyticsReportListItemDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsReportConfigDefinition;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\DemandPlanningAnalyticsReport;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<DemandPlanningListItemEntity>
 */
class DemandPlanningListItemDefinition extends EntityDefinition implements AnalyticsReportListItemDefinition
{
    public const ENTITY_NAME = 'pickware_erp_demand_planning_list_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DemandPlanningListItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DemandPlanningListItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('report_config_id', 'reportConfigId', AnalyticsReportConfigDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('reportConfig', 'report_config_id', AnalyticsReportConfigDefinition::class, 'id'),

            (new FkField('product_id', 'productId', ProductDefinition::class, 'id'))->addFlags(new Required()),
            (new FixedReferenceVersionField(ProductDefinition::class, 'product_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'),

            new IntField('sales', 'sales'),
            new IntField('sales_prediction', 'salesPrediction'),
            new IntField('reserved_stock', 'reservedStock'),
            new IntField('available_stock', 'availableStock'),
            new IntField('stock', 'stock'),
            new IntField('reorder_point', 'reorderPoint'),
            new IntField('incoming_stock', 'incomingStock'),
            new IntField('purchase_suggestion', 'purchaseSuggestion'),
        ]);
    }

    public function getDefaults(): array
    {
        return [
            'sales' => 0,
            'salesPrediction' => 0,
            'reservedStock' => 0,
            'purchaseSuggestion' => 0,
        ];
    }

    public function getReportTechnicalName(): string
    {
        return DemandPlanningAnalyticsReport::TECHNICAL_NAME;
    }
}
