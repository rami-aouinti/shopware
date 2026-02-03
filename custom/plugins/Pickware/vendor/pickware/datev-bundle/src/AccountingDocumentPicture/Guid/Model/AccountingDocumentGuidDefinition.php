<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model;

use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<AccountingDocumentGuidEntity>
 */
class AccountingDocumentGuidDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_datev_accounting_document_guid';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AccountingDocumentGuidCollection::class;
    }

    public function getEntityClass(): string
    {
        return AccountingDocumentGuidEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('guid', 'guid'))->addFlags(new Required()),
            (new FkField('document_id', 'documentId', DocumentDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('document', 'document_id', 'id', DocumentDefinition::class),
            (new ManyToManyAssociationField(
                'importExports',
                ImportExportDefinition::class,
                ImportExportAccountingDocumentGuidMappingDefinition::class,
                'accounting_document_guid_id',
                'import_export_id',
            )),
        ]);
    }
}
