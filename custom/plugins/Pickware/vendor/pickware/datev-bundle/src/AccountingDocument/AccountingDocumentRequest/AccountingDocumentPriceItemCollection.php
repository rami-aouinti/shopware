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

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

/**
 * @implements ImmutableCollection<AccountingDocumentPriceItem>
 */
class AccountingDocumentPriceItemCollection extends ImmutableCollection
{
    public static function fromArray(array $array, ?string $class = AccountingDocumentPriceItem::class): static
    {
        return parent::fromArray($array, $class);
    }

    public function filterNonContributingPriceItems(): self
    {
        // Filter out price items with an effective price of 0.00, as they should not be exported to DATEV
        return $this->filter(fn(AccountingDocumentPriceItem $priceItem) => abs($priceItem->getPrice()) >= 0.01);
    }
}
