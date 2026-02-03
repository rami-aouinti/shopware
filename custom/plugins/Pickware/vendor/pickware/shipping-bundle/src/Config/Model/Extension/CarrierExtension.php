<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config\Model\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\ShippingBundle\Carrier\Model\CarrierDefinition;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CarrierExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'shippingMethodConfigs',
                ShippingMethodConfigDefinition::class,
                'carrier_technical_name',
                'technical_name',
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return CarrierDefinition::class;
    }
}
