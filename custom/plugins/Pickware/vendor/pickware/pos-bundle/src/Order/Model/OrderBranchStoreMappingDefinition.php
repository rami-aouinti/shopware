<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Order\Model;

use Pickware\DalBundle\Field\FixedReferenceVersionField;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<Entity>
 */
class OrderBranchStoreMappingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_pos_order_branch_store_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new FixedReferenceVersionField(OrderDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            (new FkField(
                'branch_store_id',
                'branchStoreId',
                BranchStoreDefinition::class,
            ))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('branchStore', 'branch_store_id', BranchStoreDefinition::class, 'id'),
        ]);
    }
}
