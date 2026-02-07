<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Model;

use Pickware\DalBundle\Field\EnumField;
use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<OrderPickabilityEntity>
 * Please note: This definition does not have a corresponding table in the database and only exists to make the Shopware
 * DAL happy e.g. when validating Criteria objects.
 *
 * @phpstan-type OrderPickabilityStatus OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE | OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE | OrderPickabilityDefinition::PICKABILITY_STATUS_NOT_PICKABLE
 */
class OrderPickabilityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_order_pickability';
    public const PICKABILITY_STATUS_COMPLETELY_PICKABLE = 'completely_pickable';
    public const PICKABILITY_STATUS_PARTIALLY_PICKABLE = 'partially_pickable';
    public const PICKABILITY_STATUS_NOT_PICKABLE = 'not_pickable';
    public const PICKABILITY_STATES = [
        self::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
        self::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
        self::PICKABILITY_STATUS_NOT_PICKABLE,
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OrderPickabilityEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderPickabilityCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('warehouse_id', 'warehouseId', WarehouseDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('warehouse', 'warehouse_id', WarehouseDefinition::class, 'id'),

            (new FkField('order_id', 'orderId', OrderDefinition::class, 'id'))->addFlags(new Required()),
            new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            new EnumField('order_pickability_status', 'orderPickabilityStatus', self::PICKABILITY_STATES),
        ]);
    }
}
