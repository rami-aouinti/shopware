<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class OrderStockMovementProcessMappingDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_order_stock_movement_process_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField(
                'order_id',
                'orderId',
                OrderDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            (new FixedReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class),

            (new FkField(
                'stock_movement_process_id',
                'stockMovementProcessId',
                StockMovementProcessDefinition::class,
            ))->addFlags(new PrimaryKey(), new Required()),
            new OneToOneAssociationField('stockMovementProcess', 'stock_movement_process_id', 'id', StockMovementProcessDefinition::class),
        ]);
    }
}
