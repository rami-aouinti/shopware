<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentPriceItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosAccountingDocumentRequestItem
{
    public function __construct(
        private readonly AccountingDocumentPriceItem $price,
        private readonly RevenueAccountRequestItem $accountRequestItem,
        private readonly DebtorAccountRequestItem $contraAccountRequestItem,
    ) {}

    public function getPrice(): AccountingDocumentPriceItem
    {
        return $this->price;
    }

    public function getAccountRequestItem(): RevenueAccountRequestItem
    {
        return $this->accountRequestItem;
    }

    public function getContraAccountRequestItem(): DebtorAccountRequestItem
    {
        return $this->contraAccountRequestItem;
    }
}
