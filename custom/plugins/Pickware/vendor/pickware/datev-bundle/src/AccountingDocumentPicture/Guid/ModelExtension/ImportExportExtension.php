<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Guid\ModelExtension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\AccountingDocumentGuidDefinition;
use Pickware\DatevBundle\AccountingDocumentPicture\Guid\Model\ImportExportAccountingDocumentGuidMappingDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ImportExportExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ManyToManyAssociationField(
                'accountingDocumentGuids',
                AccountingDocumentGuidDefinition::class,
                ImportExportAccountingDocumentGuidMappingDefinition::class,
                'import_export_id',
                'accounting_document_guid_id',
            )),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return ImportExportDefinition::class;
    }
}
