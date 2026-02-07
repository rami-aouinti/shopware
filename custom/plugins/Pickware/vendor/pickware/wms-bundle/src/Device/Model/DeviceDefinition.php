<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Device\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<DeviceEntity>
 */
class DeviceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_device';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),

            // Associations with foreign keys on the other side

            // There are currently two associations: "stockingProcesses" and "pickingProcesses" but since there are no
            // use cases where the DAL needs to know about them and the relationship is many-to-many, we do not register
            // them yet to avoid the trouble of adding the many-to-many mapping tables. If you need it, just add it.
        ]);
    }

    public function getCollectionClass(): string
    {
        return DeviceCollection::class;
    }

    public function getEntityClass(): string
    {
        return DeviceEntity::class;
    }
}
