<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\OrderStockMovementProcessMappingDefinition;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        // A ManyToManyAssociationField is used even though this actually is a one-to-many association. This is
        // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
        // transparent for users of the DAL and the API. The one-to-many characteristics are enforced by a unique
        // index on the mapping table.
        $collection->add(
            (new ManyToManyAssociationField(
                'pickwareErpStockMovementProcesses',
                StockMovementProcessDefinition::class,
                OrderStockMovementProcessMappingDefinition::class,
                'order_id',
                'stock_movement_process_id',
            ))->addFlags(new CascadeDelete(cloneRelevant: false)),
        );
    }

    protected function getEntityDefinitionClassName(): string
    {
        return OrderDefinition::class;
    }
}
