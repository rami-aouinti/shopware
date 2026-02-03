<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockFlow;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockFlow implements JsonSerializable
{
    public int $incoming;
    public int $outgoing;

    public function __construct(int $incoming, int $outgoing)
    {
        $this->incoming = $incoming;
        $this->outgoing = $outgoing;
    }

    public function jsonSerialize(): array
    {
        return [
            'incoming' => $this->incoming,
            'outgoing' => $this->outgoing,
        ];
    }
}
