<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

class OpenSingleItemOrderCriteriaFilterContext
{
    /**
     * @var array<array{fieldPrefix: string, applyFilter: callable(Filter): void}>
     */
    private array $filterInjections = [];

    /**
     * @param string $fieldPrefix The prefix before `pickwareErpSingleItemOrder.isOpenSingleItemOrder` in the original filter field
     * @param callable(Filter): void $applyFilter Callback to apply the resolved filter
     */
    public function addOpenSingleItemOrderFilter(
        string $fieldPrefix,
        callable $applyFilter,
    ): void {
        $this->filterInjections[] = [
            'fieldPrefix' => $fieldPrefix,
            'applyFilter' => $applyFilter,
        ];
    }

    public function hasFiltersToApply(): bool
    {
        return count($this->filterInjections) > 0;
    }

    public function applyFilters(SingleItemOrderCalculator $calculator): void
    {
        if (count($this->filterInjections) === 0) {
            return;
        }

        /** @var ?string[] $openSingleItemOrderIds */
        $openSingleItemOrderIds = null;

        foreach ($this->filterInjections as $injection) {
            if ($openSingleItemOrderIds === null) {
                $openSingleItemOrderIds = $calculator->getAllOpenSingleItemOrderIds();
            }

            $resolvedFilter = self::createResolvedFilter($openSingleItemOrderIds, $injection['fieldPrefix']);
            $injection['applyFilter']($resolvedFilter);
        }
    }

    public function merge(self $otherContext): void
    {
        $this->filterInjections = array_merge($this->filterInjections, $otherContext->filterInjections);
    }

    /**
     * @param string[] $openSingleItemOrderIds
     * @param string $fieldPrefix The prefix to use for the id field (e.g., 'test.a.b.' results in 'test.a.b.id')
     */
    private static function createResolvedFilter(array $openSingleItemOrderIds, string $fieldPrefix): Filter
    {
        $idField = $fieldPrefix . 'id';

        // Filter for orders that ARE open single item orders
        if (count($openSingleItemOrderIds) > 0) {
            return new EqualsAnyFilter($idField, $openSingleItemOrderIds);
        }

        // No open single item orders exist, return filter that matches nothing
        return new EqualsFilter($idField, null);
    }
}
