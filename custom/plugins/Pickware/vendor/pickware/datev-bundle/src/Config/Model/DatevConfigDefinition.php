<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @extends EntityDefinition<DatevConfigEntity>
 */
class DatevConfigDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'pickware_datev_config';
    }

    public function getCollectionClass(): string
    {
        return DatevConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return DatevConfigEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new JsonSerializableObjectField(
                'values',
                'values',
                ConfigValues::class,
            ))->addFlags(new Required()),

            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class, 'id'),
            new OneToOneAssociationField(
                'salesChannel',
                'sales_channel_id',
                'id',
                SalesChannelDefinition::class,
                false,
            ),
        ]);
    }
}
