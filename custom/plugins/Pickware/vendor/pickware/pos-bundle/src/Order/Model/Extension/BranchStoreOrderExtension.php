<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Order\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Pickware\PickwarePos\Order\Model\OrderBranchStoreMappingDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BranchStoreOrderExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            // A ManyToManyAssociationField is used even though this actually is a many-to-one association. This is
            // because we have a mapping table and with ManyToManyAssociationFields we can make that mapping table
            // transparent for users of the DAL and the API. The many-to-one characteristics are enforced by a unique
            // index on the mapping table.
            (new ManyToManyAssociationField(
                'pickwarePosBranchStores',
                BranchStoreDefinition::class,
                OrderBranchStoreMappingDefinition::class,
                'order_id',
                'branch_store_id',
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return OrderDefinition::class;
    }
}
