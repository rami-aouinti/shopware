<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountAssignmentState
{
    /**
     * @param ImmutableCollection<EntryBatchLogMessage> $messages
     */
    public function __construct(
        private readonly AccountDetermination $accountDetermination,
        private readonly ImmutableCollection $messages,
    ) {}

    public function getAccountDetermination(): AccountDetermination
    {
        return $this->accountDetermination;
    }

    /**
     * @return ImmutableCollection<EntryBatchLogMessage>
     */
    public function getMessages(): ImmutableCollection
    {
        return $this->messages;
    }
}
