<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<PickEventEntity>
 */
class PickEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_pick_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new IdField('product_reference_id', 'productReferenceId'))->addFlags(new Required()),
            (new JsonField('product_snapshot', 'productSnapshot'))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_reference_id', ProductDefinition::class, 'id'),
            new FloatField('product_weight', 'productWeight'),

            (new IdField('user_reference_id', 'userReferenceId'))->addFlags(new Required()),
            (new JsonField('user_snapshot', 'userSnapshot'))->addFlags(new Required()),
            new ManyToOneAssociationField('user', 'user_reference_id', UserDefinition::class, 'id'),

            (new IdField('warehouse_reference_id', 'warehouseReferenceId'))->addFlags(new Required()),
            (new JsonField('warehouse_snapshot', 'warehouseSnapshot'))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_reference_id', WarehouseDefinition::class, 'id'),

            new IdField('bin_location_reference_id', 'binLocationReferenceId'),
            new JsonField('bin_location_snapshot', 'binLocationSnapshot'),
            new ManyToOneAssociationField('binLocation', 'bin_location_reference_id', BinLocationDefinition::class, 'id'),

            (new IdField('picking_process_reference_id', 'pickingProcessReferenceId'))->addFlags(new Required()),
            (new JsonField('picking_process_snapshot', 'pickingProcessSnapshot'))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcess', 'picking_process_reference_id', PickingProcessDefinition::class, 'id'),

            (new StringField('picking_mode', 'pickingMode'))->addFlags(new Required()),

            new IdField('picking_profile_reference_id', 'pickingProfileReferenceId'),
            new JsonField('picking_profile_snapshot', 'pickingProfileSnapshot'),
            new ManyToOneAssociationField('pickingProfile', 'picking_profile_reference_id', PickingProfileDefinition::class, 'id'),

            (new IntField('picked_quantity', 'pickedQuantity'))->addFlags(new Required()),
            (new DateTimeField('pick_created_at', 'pickCreatedAt'))->addFlags(new Required()),
            (new DateTimeField('pick_created_at_day', 'pickCreatedAtDay'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('pick_created_at_hour', 'pickCreatedAtHour'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('pick_created_at_weekday', 'pickCreatedAtWeekday'))->addFlags(new Computed(), new WriteProtected()),

            // This cannot be a datetime field because shopware can only store dates in UTC
            (new StringField('pick_created_at_localtime', 'pickCreatedAtLocaltime'))->addFlags(new Required()),
            (new StringField('pick_created_at_localtime_timezone', 'pickCreatedAtLocaltimeTimezone'))->addFlags(new Required()),
            (new IntField('pick_created_at_localtime_hour', 'pickCreatedAtLocaltimeHour'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('pick_created_at_localtime_weekday', 'pickCreatedAtLocaltimeWeekday'))->addFlags(new Computed(), new WriteProtected()),

            (new OneToManyAssociationField(
                'userRoles',
                PickEventUserRoleDefinition::class,
                'pick_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
