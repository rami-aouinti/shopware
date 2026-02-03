<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch;

class EntryBatchRecordCollection
{
    /**
     * @var EntryBatchRecord[]
     */
    private array $entries = [];

    /**
     * @var EntryBatchLogMessage[]
     */
    private array $logMessages = [];

    public function addEntries(EntryBatchRecord ...$entries): void
    {
        $this->entries = array_merge($this->entries, $entries);
    }

    public function addLogMessages(EntryBatchLogMessage ...$logMessages): void
    {
        $this->logMessages = array_merge($this->logMessages, $logMessages);
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return EntryBatchLogMessage[]
     */
    public function getLogMessages(): array
    {
        return $this->logMessages;
    }

    public function mergeWith(EntryBatchRecordCollection $other): void
    {
        $this->addEntries(...$other->getEntries());
        $this->logMessages = array_merge($this->logMessages, $other->logMessages);
    }
}
