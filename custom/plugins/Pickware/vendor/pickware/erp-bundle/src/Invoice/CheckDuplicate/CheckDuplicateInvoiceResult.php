<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice\CheckDuplicate;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class CheckDuplicateInvoiceResult
{
    public function __construct(
        /** @var DocumentGenerateOperation[] $operationWithOpenInvoices */
        public array $operationWithOpenInvoices,
        /** @var DocumentGenerateOperation[] $operationWithoutOpenInvoices */
        public array $operationWithoutOpenInvoices,
    ) {}
}
