<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CashPointClosingProductExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwarePosCashPointClosingTransactionListItems',
                CashPointClosingTransactionLineItemDefinition::class,
                'product_id',
                'id',
            )),
            // There should be a `setNullOnDelete` flag, but this triggers our update restrictor on the transaction list
            // items. Instead, we rely on the foreign key with ON DELETE SET NULL to ensure this behaviour.
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return ProductDefinition::class;
    }
}
