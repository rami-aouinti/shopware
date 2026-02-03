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

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\DalBundle\Field\PhpEnumField;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
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
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<DeliveryLifecycleEventEntity>
 */
class DeliveryLifecycleEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_delivery_lifecycle_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DeliveryLifecycleEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DeliveryLifecycleEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new PhpEnumField('event_technical_name', 'eventTechnicalName', DeliveryLifecycleEventType::class))->addFlags(new Required()),

            (new IdField('delivery_reference_id', 'deliveryReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('delivery', 'delivery_reference_id', DeliveryDefinition::class, 'id'),

            (new IdField('user_reference_id', 'userReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('user', 'user_reference_id', UserDefinition::class, 'id'),
            (new JsonField('user_snapshot', 'userSnapshot'))->addFlags(new Required()),

            (new IdField('order_reference_id', 'orderReferenceId'))->addFlags(new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('order', 'order_reference_id', OrderDefinition::class, 'id'),
            (new JsonField('order_snapshot', 'orderSnapshot'))->addFlags(new Required()),

            (new IdField('sales_channel_reference_id', 'salesChannelReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_reference_id', SalesChannelDefinition::class, 'id'),
            (new JsonField('sales_channel_snapshot', 'salesChannelSnapshot'))->addFlags(new Required()),

            (new IdField('picking_process_reference_id', 'pickingProcessReferenceId'))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcess', 'picking_process_reference_id', PickingProcessDefinition::class, 'id'),
            (new JsonField('picking_process_snapshot', 'pickingProcessSnapshot'))->addFlags(new Required()),

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
                DeliveryLifecycleEventUserRoleDefinition::class,
                'delivery_lifecycle_event_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }
}
