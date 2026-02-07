<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class WeightedEdge
{
    public string $id;
    public string $fromNodeId;
    public string $toNodeId;
    public int $weight;

    public function __construct(string $id, string $fromNodeId, string $toNodeId, int $weight)
    {
        $this->id = $id;
        $this->fromNodeId = $fromNodeId;
        $this->toNodeId = $toNodeId;
        $this->weight = $weight;
    }
}
