<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(DocumentTypeEntity $entity)
 * @method void             set(string $key, DocumentTypeEntity $entity)
 * @method DocumentTypeEntity[]    getIterator()
 * @method DocumentTypeEntity[]    getElements()
 * @method DocumentTypeEntity|null get(string $key)
 * @method DocumentTypeEntity|null first()
 * @method DocumentTypeEntity|null last()
 */
class DocumentTypeCollection extends EntityCollection {}
