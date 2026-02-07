<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PurchaseListItemMappingResult
{
    /**
     * @param array<PurchaseListImportListItem> $purchaseListImportListItems
     * @param array<PurchaseListImportMessage> $purchaseListImportMessages
     */
    private function __construct(
        private array $purchaseListImportListItems,
        private array $purchaseListImportMessages,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * @return array<PurchaseListImportListItem>
     */
    public function getPurchaseListImportListItems(): array
    {
        return $this->purchaseListImportListItems;
    }

    public function addPurchaseListImportItem(PurchaseListImportListItem $purchaseListImportListItem): void
    {
        $this->purchaseListImportListItems[] = $purchaseListImportListItem;
    }

    /**
     * @return array<PurchaseListImportMessage>
     */
    public function getPurchaseListImportMessages(): array
    {
        return $this->purchaseListImportMessages;
    }

    public function addPurchaseListImportMessage(PurchaseListImportMessage $purchaseListImportMessage): void
    {
        $this->purchaseListImportMessages[] = $purchaseListImportMessage;
    }
}
