<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickingProfileEntity>
 */
class PickingProfileDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_profile';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new StringField('name', 'name'))->addFlags(new Required()),
            (new IntField('position', 'position', 1))->addFlags(new Required()),
            (new BoolField('is_partial_delivery_allowed', 'isPartialDeliveryAllowed'))->addFlags(new Required()),
            new JsonField('filter', 'filter'),

            (new OneToManyAssociationField(
                'prioritizedShippingMethods',
                PickingProfilePrioritizedShippingMethodDefinition::class,
                'picking_profile_id',
                'id',
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'prioritizedPaymentMethods',
                PickingProfilePrioritizedPaymentMethodDefinition::class,
                'picking_profile_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PickingProfileCollection::class;
    }

    public function getEntityClass(): string
    {
        return PickingProfileEntity::class;
    }
}
