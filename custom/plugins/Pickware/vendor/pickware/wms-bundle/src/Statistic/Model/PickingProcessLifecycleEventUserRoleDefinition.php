<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PickingProcessLifecycleEventUserRoleEntity>
 */
class PickingProcessLifecycleEventUserRoleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_wms_picking_process_lifecycle_event_user_role';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PickingProcessLifecycleEventUserRoleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PickingProcessLifecycleEventUserRoleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('picking_process_lifecycle_event_id', 'pickingProcessLifecycleEventId', PickingProcessLifecycleEventDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('pickingProcessLifecycleEvent', 'picking_process_lifecycle_event_id', PickingProcessLifecycleEventDefinition::class, 'id'),

            (new IdField('user_role_reference_id', 'userRoleReferenceId'))->addFlags(new Required()),
            (new JsonField('user_role_snapshot', 'userRoleSnapshot'))->addFlags(new Required()),
        ]);
    }
}
