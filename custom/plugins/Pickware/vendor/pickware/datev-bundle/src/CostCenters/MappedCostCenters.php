<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CostCenters;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class MappedCostCenters
{
    /**
     * @param EntryBatchLogMessage[] $messages
     */
    public function __construct(
        private readonly ?string $costCenter1,
        private readonly ?string $costCenter2,
        private readonly array $messages = [],
    ) {}

    public function getCostCenter1(): ?string
    {
        return $this->costCenter1;
    }

    public function getCostCenter2(): ?string
    {
        return $this->costCenter2;
    }

    /**
     * @return EntryBatchLogMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
