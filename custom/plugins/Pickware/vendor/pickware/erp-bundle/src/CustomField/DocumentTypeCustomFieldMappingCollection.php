<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DocumentTypeCustomFieldMappingEntity>
 * @method void                                        add(DocumentTypeCustomFieldMappingEntity $entity)
 * @method void                                        set(string $key, DocumentTypeCustomFieldMappingEntity $entity)
 * @method DocumentTypeCustomFieldMappingEntity[]     getIterator()
 * @method DocumentTypeCustomFieldMappingEntity[]     getElements()
 * @method DocumentTypeCustomFieldMappingEntity|null  get(string $key)
 * @method DocumentTypeCustomFieldMappingEntity|null  first()
 * @method DocumentTypeCustomFieldMappingEntity|null  last()
 */
class DocumentTypeCustomFieldMappingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DocumentTypeCustomFieldMappingEntity::class;
    }
}
