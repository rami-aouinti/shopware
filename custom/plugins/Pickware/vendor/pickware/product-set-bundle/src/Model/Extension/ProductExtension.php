<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'pickwareProductSetProductSet',
                'id',
                'product_id',
                ProductSetDefinition::class,
                false,
            ))->addFlags(new CascadeDelete()),
        );

        $collection->add(
            (new OneToManyAssociationField(
                'pickwareProductSetProductSetConfigurations',
                ProductSetConfigurationDefinition::class,
                'product_id',
                'id',
            ))->addFlags(new CascadeDelete(cloneRelevant: false)),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return ProductDefinition::class;
    }
}
