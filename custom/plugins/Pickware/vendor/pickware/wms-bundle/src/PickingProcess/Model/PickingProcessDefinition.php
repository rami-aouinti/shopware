<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\PickingProcess\PickingProcessStateMachine;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessDefinition;
use Pickware\PickwareWms\Statistic\Model\PickEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<PickingProcessEntity>
 */
class PickingProcessDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_process';
    public const PICKING_MODES = [
        self::PICKING_MODE_SINGLE,
        self::PICKING_MODE_PRE_COLLECTED_BATCH_PICKING,
        self::PICKING_MODE_PICK_TO_BOX_BATCH_PICKING,
        self::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING,
    ];
    public const PICKING_MODE_SINGLE = 'single';
    public const PICKING_MODE_PRE_COLLECTED_BATCH_PICKING = 'preCollectedBatchPicking';
    public const PICKING_MODE_PICK_TO_BOX_BATCH_PICKING = 'pickToBoxBatchPicking';
    public const PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING = 'singleItemOrdersPicking';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickingProcessEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickingProcessCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new FkField('user_id', 'userId', UserDefinition::class, 'id'),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),

            new FkField('device_id', 'deviceId', DeviceDefinition::class, 'id'),
            new ManyToOneAssociationField('device', 'device_id', DeviceDefinition::class, 'id'),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            (new StateMachineStateField('state_id', 'stateId', PickingProcessStateMachine::TECHNICAL_NAME))->addFlags(new Required()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class, 'id'),

            new FkField(
                'pre_collecting_stock_container_id',
                'preCollectingStockContainerId',
                StockContainerDefinition::class,
            ),
            new OneToOneAssociationField(
                'preCollectingStockContainer',
                'pre_collecting_stock_container_id',
                'id',
                StockContainerDefinition::class,
                false,
            ),

            (new StringField('number', 'number'))->addFlags(new Required()),

            (new OneToManyAssociationField(
                'reservedItems',
                PickingProcessReservedItemDefinition::class,
                'picking_process_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new FkField('shipping_process_id', 'shippingProcessId', ShippingProcessDefinition::class))
                ->addFlags(new SetNullOnDelete()),
            new ManyToOneAssociationField(
                'shippingProcess',
                'shipping_process_id',
                ShippingProcessDefinition::class,
                'id',
            ),

            (new OneToManyAssociationField(
                'deliveries',
                DeliveryDefinition::class,
                'picking_process_id',
                'id',
            ))->addFlags(new CascadeDelete()),

            (new EnumField(
                'picking_mode',
                'pickingMode',
                self::PICKING_MODES,
            ))->addFlags(new Required()),

            (new OneToManyAssociationField(
                'lifecycleEvents',
                PickingProcessLifecycleEventDefinition::class,
                'picking_process_reference_id',
            ))->addFlags(new SetNullOnDelete()),

            (new OneToManyAssociationField(
                'pickEvents',
                PickEventDefinition::class,
                'picking_process_reference_id',
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
