<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\Model;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickwareWmsShippingMethodConfigEntity>
 */
class PickwareWmsShippingMethodConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_shipping_method_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class, 'id'))->addFlags(new Required()),
            (new OneToOneAssociationField(
                propertyName: 'shippingMethod',
                storageName: 'shipping_method_id',
                referenceField: 'id',
                referenceClass: ShippingMethodDefinition::class,
                autoload: false,
            ))->addFlags(new CascadeDelete()),
            (new BoolField('create_enclosed_return_label', 'createEnclosedReturnLabel'))->addFlags(new Required()),
        ]);
    }

    public function getEntityClass(): string
    {
        return PickwareWmsShippingMethodConfigEntity::class;
    }
}
