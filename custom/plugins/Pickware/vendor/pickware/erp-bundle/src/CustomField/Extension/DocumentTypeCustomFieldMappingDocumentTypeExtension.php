<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField\Extension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\PickwareErpStarter\CustomField\DocumentTypeCustomFieldMappingDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DocumentTypeCustomFieldMappingDocumentTypeExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareErpDocumentTypeCustomFieldMappings',
                DocumentTypeCustomFieldMappingDefinition::class,
                'document_type_id',
                'id',
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return DocumentTypeDefinition::class;
    }
}
