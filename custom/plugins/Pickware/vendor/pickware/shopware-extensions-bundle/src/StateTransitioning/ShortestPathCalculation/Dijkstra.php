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

class Dijkstra implements ShortestPathCalculator
{
    public function calculateShortestPath(array $edges, string $startNodeId, string $destinationNodeId): ?array
    {
        $solution = $this->solve($edges, $startNodeId);

        return $solution->getPathToDestinationNode($destinationNodeId);
    }

    /**
     * @param WeightedEdge[] $edges
     */
    private function solve(array $edges, string $startNodeId): ShortestPathMap
    {
        $solution = new ShortestPathMap();

        $seenNodeIds = [$startNodeId];
        $currentEdges = array_filter($edges, fn(WeightedEdge $edge) => $edge->fromNodeId === $startNodeId);
        while (true) {
            // Filter edges so all destination nodes are unseen yet. All seen nodes already have their shortest path.
            $currentEdges = array_filter($currentEdges, fn(WeightedEdge $edge) => !in_array($edge->toNodeId, $seenNodeIds));
            if (count($currentEdges) === 0) {
                break;
            }

            /** @var WeightedEdge $shortestEdgeToNextNode */
            $shortestEdgeToNextNode = array_reduce(
                $currentEdges,
                function(?WeightedEdge $shortestEdge, WeightedEdge $edge): WeightedEdge {
                    if (!$shortestEdge || $edge->weight < $shortestEdge->weight) {
                        return $edge;
                    }

                    return $shortestEdge;
                },
                null,
            );

            // The lightest of the current edges leads the shortest path to a new node. That's what Dijkstra is all
            // about.
            $nextNodeId = $shortestEdgeToNextNode->toNodeId;
            $solution->addPathToDestinationNodeId(
                $nextNodeId,
                array_merge(
                    $solution->getPathToDestinationNode($shortestEdgeToNextNode->fromNodeId) ?? [],
                    [$shortestEdgeToNextNode],
                ),
            );

            $seenNodeIds[] = $nextNodeId;
            $currentEdges = array_merge(
                $currentEdges,
                array_filter($edges, fn(WeightedEdge $edge) => $edge->fromNodeId === $nextNodeId),
            );
        }

        return $solution;
    }
}
