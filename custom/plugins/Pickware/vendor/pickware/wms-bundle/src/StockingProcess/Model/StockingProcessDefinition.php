<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess\Model;

use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\StockingProcess\StockingProcessStateMachine;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\User\UserDefinition;

/**
 * @extends EntityDefinition<StockingProcessEntity>
 */
class StockingProcessDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_stocking_process';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockingProcessEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockingProcessCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('number', 'number'))->addFlags(new Required()),

            (new StateMachineStateField('state_id', 'stateId', StockingProcessStateMachine::TECHNICAL_NAME))->addFlags(new Required()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class, 'id'),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            new FkField('user_id', 'userId', UserDefinition::class, 'id'),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id'),

            new FkField('device_id', 'deviceId', DeviceDefinition::class, 'id'),
            new ManyToOneAssociationField('device', 'device_id', DeviceDefinition::class, 'id'),

            (new OneToManyAssociationField('sources', StockingProcessSourceDefinition::class, 'stocking_process_id', 'id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('lineItems', StockingProcessLineItemDefinition::class, 'stocking_process_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
