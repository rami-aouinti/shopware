<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\Model;

use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<IndividualDebtorAccountInformationEntity>
 */
class IndividualDebtorAccountInformationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_datev_individual_debtor_account_information';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return IndividualDebtorAccountInformationCollection::class;
    }

    public function getEntityClass(): string
    {
        return IndividualDebtorAccountInformationEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new IntField('account', 'account', 0, 99999999))->addFlags(new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class, 'id'))
                ->addFlags(new Required()),
            new OneToOneAssociationField('customer', 'customer_id', 'id', CustomerDefinition::class),
            (new FkField('import_export_id', 'importExportId', ImportExportDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField(
                'importExports',
                'import_export_id',
                ImportExportDefinition::class,
                'id',
            )),
        ]);
    }
}
