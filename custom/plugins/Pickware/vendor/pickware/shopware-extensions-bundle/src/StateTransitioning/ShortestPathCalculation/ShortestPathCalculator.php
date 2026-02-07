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

interface ShortestPathCalculator
{
    /**
     * @param WeightedEdge[] $edges
     * @return WeightedEdge[]|null the shortest path to the destination node. A sorted list of weighted edges. Returns
     * null if no solution could be found.
     */
    public function calculateShortestPath(array $edges, string $startNodeId, string $destinationNodeId): ?array;
}
