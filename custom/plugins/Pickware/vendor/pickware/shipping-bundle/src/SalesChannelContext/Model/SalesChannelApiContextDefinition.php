<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\SalesChannelContext\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<SalesChannelApiContextEntity>
 * This table references the same API token as the `sales_channel_api_context` table, but does not have
 * a foreign key on purpose. Shopware uses the `REPLACE` statement to update the sales channel API context
 * (see {@link Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister}), which gets handled
 * by MySQL like a `DELETE` followed by an `INSERT`. Thus, a foreign key with `ON DELETE CASCADE` would lead
 * to our entry being deleted every time. Instead, we have a scheduled task that deletes all entries from
 * this table that do not have a corresponding entry in the shopware table.
 */
class SalesChannelApiContextDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_shipping_sales_channel_api_context';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelApiContextEntityCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelApiContextEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new StringField('sales_channel_context_token', 'salesChannelContextToken'))
                    ->addFlags(new PrimaryKey(), new ApiAware(), new Required()),

                (new JsonField('payload', 'payload'))
                    ->addFlags(new ApiAware()),
            ],
        );
    }
}
