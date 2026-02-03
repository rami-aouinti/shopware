<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment\Item;

use Pickware\DatevBundle\Config\AccountAssignment\AccountDetermination;
use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template Item of AccountRequestItem
 */
#[Exclude]
class AccountAssignment
{
    /**
     * @param Item $item
     * @param EntryBatchLogMessage[] $messages
     */
    public function __construct(
        private readonly AccountDetermination $accountDetermination,
        private array $messages,
        private readonly AccountRequestItem $item,
    ) {}

    public function getAccountDetermination(): AccountDetermination
    {
        return $this->accountDetermination;
    }

    /**
     * @return EntryBatchLogMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(EntryBatchLogMessage $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return Item
     */
    public function getItem(): AccountRequestItem
    {
        return $this->item;
    }

    public function getKey(): string
    {
        return $this->item->getKey();
    }
}
