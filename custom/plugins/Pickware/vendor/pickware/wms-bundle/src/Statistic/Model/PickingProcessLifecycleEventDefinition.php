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

use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<PickingProcessLifecycleEventEntity>
 */
class PickingProcessLifecycleEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_process_lifecycle_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickingProcessLifecycleEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickingProcessLifecycleEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new PhpEnumField('event_technical_name', 'eventTechnicalName', PickingProcessLifecycleEventType::class))->addFlags(new Required()),

            (new IdField('picking_process_reference_id', 'pickingProcessReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcess', 'picking_process_reference_id', PickingProcessDefinition::class, 'id'),
            (new JsonField('picking_process_snapshot', 'pickingProcessSnapshot'))->addFlags(new Required()),

            new IdField('user_reference_id', 'userReferenceId'),
            new ManyToOneAssociationField('user', 'user_reference_id', UserDefinition::class, 'id'),
            new JsonField('user_snapshot', 'userSnapshot'),

            (new IdField('warehouse_reference_id', 'warehouseReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_reference_id', WarehouseDefinition::class, 'id'),
            (new JsonField('warehouse_snapshot', 'warehouseSnapshot'))->addFlags(new Required()),

            (new StringField('picking_mode', 'pickingMode'))->addFlags(new Required()),

            new IdField('picking_profile_reference_id', 'pickingProfileReferenceId'),
            new ManyToOneAssociationField('pickingProfile', 'picking_profile_reference_id', PickingProfileDefinition::class, 'id'),
            new JsonField('picking_profile_snapshot', 'pickingProfileSnapshot'),

            new IdField('device_reference_id', 'deviceReferenceId'),
            new ManyToOneAssociationField('device', 'device_reference_id', DeviceDefinition::class, 'id'),
            new JsonField('device_snapshot', 'deviceSnapshot'),

            (new DateTimeField('event_created_at', 'eventCreatedAt'))->addFlags(new Required()),
            (new DateTimeField('event_created_at_day', 'eventCreatedAtDay'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('event_created_at_hour', 'eventCreatedAtHour'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('event_created_at_weekday', 'eventCreatedAtWeekday'))->addFlags(new Computed(), new WriteProtected()),

            // This cannot be a datetime field because shopware can only store dates in UTC
            (new StringField('event_created_at_localtime', 'eventCreatedAtLocaltime'))->addFlags(new Required()),
            (new StringField('event_created_at_localtime_timezone', 'eventCreatedAtLocaltimeTimezone'))->addFlags(new Required()),
            (new IntField('event_created_at_localtime_hour', 'eventCreatedAtLocaltimeHour'))->addFlags(new Computed(), new WriteProtected()),
            (new IntField('event_created_at_localtime_weekday', 'eventCreatedAtLocaltimeWeekday'))->addFlags(new Computed(), new WriteProtected()),

            (new OneToManyAssociationField(
                'userRoles',
                PickingProcessLifecycleEventUserRoleDefinition::class,
                'picking_process_lifecycle_event_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
