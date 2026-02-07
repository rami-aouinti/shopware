<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickingPropertyOrderRecordValueEntity>
 */
class PickingPropertyOrderRecordValueDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_picking_property_order_record_value';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField(
                'picking_property_order_record_id',
                'pickingPropertyOrderRecordId',
                PickingPropertyOrderRecordDefinition::class,
            ))->addFlags(new Required()),
            new ManyToOneAssociationField(
                'pickingPropertyOrderRecord',
                'picking_property_order_record_id',
                PickingPropertyOrderRecordDefinition::class,
            ),

            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('value', 'value'))->addFlags(new Required()),
        ]);
    }

    public function getCollectionClass(): string
    {
        return PickingPropertyOrderRecordValueCollection::class;
    }

    public function getEntityClass(): string
    {
        return PickingPropertyOrderRecordValueEntity::class;
    }
}
