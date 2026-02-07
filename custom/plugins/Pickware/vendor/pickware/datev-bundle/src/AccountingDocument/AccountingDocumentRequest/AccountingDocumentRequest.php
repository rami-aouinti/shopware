<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest;

use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentRequest
{
    public function __construct(
        /** @var ImmutableCollection<AccountingDocumentRequestItem> $items */
        private readonly ImmutableCollection $items,
    ) {}

    /**
     * @return ImmutableCollection<RevenueAccountRequestItem>
     */
    public function getAccountRequestItems(): ImmutableCollection
    {
        return $this->items->map(fn(AccountingDocumentRequestItem $item) => $item->getAccountRequestItem());
    }

    /**
     * @return ImmutableCollection<DebtorAccountRequestItem>
     */
    public function getContraAccountRequestItems(): ImmutableCollection
    {
        return $this->items->map(fn(AccountingDocumentRequestItem $item) => $item->getContraAccountRequestItem());
    }

    /**
     * @return ImmutableCollection<AccountingDocumentRequestItem>
     */
    public function getItems(): ImmutableCollection
    {
        return $this->items;
    }
}
