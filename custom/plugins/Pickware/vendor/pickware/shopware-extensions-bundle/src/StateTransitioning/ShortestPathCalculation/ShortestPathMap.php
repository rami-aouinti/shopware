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

/**
 * Represents a shortest-path-tree from a single source node to any other node in the graph. But instead of building a
 * traversable tree, we are using a map.
 */
#[Exclude]
class ShortestPathMap
{
    /**
     * @var WeightedEdge[][] Array of Edges by destination node id
     */
    private array $solution = [];

    /**
     * @param WeightedEdge[] $edges
     */
    public function addPathToDestinationNodeId(string $destinationNodeId, array $edges): void
    {
        $this->solution[$destinationNodeId] = $edges;
    }

    /**
     * @return WeightedEdge[]|null
     */
    public function getPathToDestinationNode(string $destinationNodeId): ?array
    {
        if (!array_key_exists($destinationNodeId, $this->solution)) {
            return null;
        }

        return $this->solution[$destinationNodeId];
    }
}
