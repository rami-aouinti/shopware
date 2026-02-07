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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @method void add(AccountingDocumentGuidEntity $entity)
 * @method void set(string $key, AccountingDocumentGuidEntity $entity)
 * @method AccountingDocumentGuidEntity[] getIterator()
 * @method AccountingDocumentGuidEntity[] getElements()
 * @method AccountingDocumentGuidEntity|null get(string $key)
 * @method AccountingDocumentGuidEntity|null first()
 * @method AccountingDocumentGuidEntity|null last()
 *
 * @extends EntityCollection<AccountingDocumentGuidEntity>
 */
#[Exclude]
class AccountingDocumentGuidCollection extends EntityCollection {}
