<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Model;

use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<ReportEntity>
 */
class ReportDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_stock_valuation_report';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new DateField('reporting_day', 'reportingDay'))->addFlags(new Required()),
            (new TimeZoneField('reporting_day_time_zone', 'reportingDayTimeZone'))->addFlags(new Required()),
            new DateTimeField('until_date', 'untilDate'),
            (new BoolField('generated', 'generated'))->addFlags(new Required()),
            (new PhpEnumField('generation_step', 'generationStep', ReportGenerationStep::class))
                ->addFlags(new Required()),
            new LongTextField('comment', 'comment'),
            (new BoolField('preview', 'preview'))->addFlags(new Required()),
            (new PhpEnumField('method', 'method', ReportMethod::class))->addFlags(new Required()),

            new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),
            (new JsonField('warehouse_snapshot', 'warehouseSnapshot'))->addFlags(new Required()),

            (new OneToManyAssociationField(
                'rows',
                ReportRowDefinition::class,
                'report_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return ReportCollection::class;
    }

    public function getEntityClass(): string
    {
        return ReportEntity::class;
    }
}
