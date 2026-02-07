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
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class ImportExportAccountingDocumentGuidMappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'pickware_datev_import_export_accounting_document_guid_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('import_export_id', 'importExportId', ImportExportDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('importExport', 'import_export_id', ImportExportDefinition::class),

            (new FkField('accounting_document_guid_id', 'accountingDocumentGuidId', AccountingDocumentGuidDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new ManyToOneAssociationField('accountingDocumentGuid', 'accounting_document_guid_id', AccountingDocumentGuidDefinition::class),
        ]);
    }
}
