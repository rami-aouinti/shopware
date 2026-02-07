<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Address\Model;

use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;

/**
 * @extends EntityDefinition<AddressEntity>
 */
class AddressDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_erp_address';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AddressCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new FkField('salutation_id', 'salutationId', SalutationDefinition::class, 'id'),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id'),

            new StringField('first_name', 'firstName'),
            new StringField('last_name', 'lastName'),
            new StringField('title', 'title'),
            new StringField('email', 'email'),
            new StringField('phone', 'phone'),
            new StringField('fax', 'fax'),
            new StringField('website', 'website'),
            new StringField('company', 'company'),
            new StringField('department', 'department'),
            new StringField('position', 'position'),

            new StringField('street', 'street'),
            new StringField('house_number', 'houseNumber'),
            new StringField('zip_code', 'zipCode'),
            new StringField('city', 'city'),
            new StringField('state', 'state'),
            new StringField('province', 'province'),
            new StringField('address_addition', 'addressAddition'),
            new StringField('country_iso', 'countryIso'),

            new LongTextField('comment', 'comment'),
            new LongTextField('vat_id', 'vatId'),

            // Associations with foreign keys on the other side
            (new OneToOneAssociationField(
                'warehouse',
                'id',
                'address_id',
                WarehouseDefinition::class,
                false,
            ))->addFlags(new RestrictDelete()),
            (new OneToOneAssociationField(
                'supplier',
                'id',
                'address_id',
                SupplierDefinition::class,
                false,
            ))->addFlags(new RestrictDelete()),
        ]);
    }
}
