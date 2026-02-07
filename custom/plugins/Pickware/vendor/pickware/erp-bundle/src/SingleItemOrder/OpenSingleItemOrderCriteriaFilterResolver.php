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

use LogicException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class OpenSingleItemOrderCriteriaFilterResolver
{
    public function __construct(
        private readonly SingleItemOrderCalculator $singleItemOrderCalculator,
    ) {}

    public function resolveOpenSingleItemOrderFilter(Criteria $criteria): void
    {
        $filterContext = $this->removeOpenSingleItemOrderFilters($criteria);

        if (!$filterContext->hasFiltersToApply()) {
            return;
        }

        // Elasticsearch cannot filter according to the single item order status as there are too many ids to fetch.
        $criteria->removeState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $filterContext->applyFilters($this->singleItemOrderCalculator);
    }

    private function removeOpenSingleItemOrderFilters(Criteria $criteria): OpenSingleItemOrderCriteriaFilterContext
    {
        $filters = $criteria->getFilters();

        // 1. Remove any open single item order filters defined in nested `multi` filters
        $filterContext = $this->replaceAllOpenSingleItemOrderFilters($filters);

        // 2. Remove any open single item order filters defined in the criteria's root filters
        $openSingleItemOrderFilter = $this->removeAndValidateOpenSingleItemOrderFilter($filters);
        if ($openSingleItemOrderFilter !== null) {
            $fieldPrefix = $this->extractFieldPrefix($openSingleItemOrderFilter->getField());
            $filterContext->addOpenSingleItemOrderFilter(
                $fieldPrefix,
                function(Filter $resolvedFilter) use ($criteria): void {
                    $criteria->addFilter($resolvedFilter);
                },
            );
        }

        $criteria->resetFilters();
        $criteria->addFilter(...array_values($filters));

        // 3. Remove open single item order filters from associations recursively
        foreach ($criteria->getAssociations() as $associationCriteria) {
            $filterContext->merge($this->removeOpenSingleItemOrderFilters($associationCriteria));
        }

        return $filterContext;
    }

    /**
     * Recursively replaces all open single item order filters in the given `$filters` and collects them in the context.
     *
     * @param Filter[] $filters
     */
    private function replaceAllOpenSingleItemOrderFilters(array &$filters): OpenSingleItemOrderCriteriaFilterContext
    {
        $filterContext = new OpenSingleItemOrderCriteriaFilterContext();

        foreach ($filters as $filterIndex => $filter) {
            if (!($filter instanceof MultiFilter)) {
                continue;
            }

            $filterChildren = $filter->getQueries();
            $originalChildCount = count($filterChildren);

            // Recursively remove any open single item order filters from nested `multi` filters
            $filterContext->merge($this->replaceAllOpenSingleItemOrderFilters($filterChildren));

            // Remove the open single item order filter from the current `multi` filter
            $openSingleItemOrderFilter = $this->removeAndValidateOpenSingleItemOrderFilter($filterChildren);

            // Check if any changes were made (either nested changes or direct filter removal)
            $hasNestedChanges = count($filterChildren) !== $originalChildCount || $filterChildren !== $filter->getQueries();
            if ($openSingleItemOrderFilter === null && !$hasNestedChanges) {
                // No changes were made to this filter or its children
                continue;
            }

            $newFilter = match (get_class($filter)) {
                NotFilter::class => new NotFilter($filter->getOperator(), array_values($filterChildren)),
                default => new MultiFilter($filter->getOperator(), array_values($filterChildren)),
            };
            $filters[$filterIndex] = $newFilter;

            if ($openSingleItemOrderFilter !== null) {
                $fieldPrefix = $this->extractFieldPrefix($openSingleItemOrderFilter->getField());
                $filterContext->addOpenSingleItemOrderFilter(
                    $fieldPrefix,
                    function(Filter $resolvedFilter) use ($newFilter): void {
                        $newFilter->addQuery($resolvedFilter);
                    },
                );
            }
        }

        return $filterContext;
    }

    /**
     * Finds and removes an open single item order filter from the given filters.
     * Validates that the filter value is `true` (filtering for `false` is not supported).
     *
     * @param Filter[] $filters
     */
    private function removeAndValidateOpenSingleItemOrderFilter(array &$filters): ?EqualsFilter
    {
        foreach ($filters as $index => $filter) {
            if ($this->isOpenSingleItemOrderFilter($filter)) {
                unset($filters[$index]);

                if ($filter->getValue() !== true) {
                    throw OpenSingleItemOrderException::filteringForNonOpenSingleItemOrdersNotSupported();
                }

                return $filter;
            }
        }

        return null;
    }

    /**
     * Extracts the field prefix from an isOpenSingleItemOrder filter field.
     * E.g., 'test.a.b.pickwareErpSingleItemOrder.isOpenSingleItemOrder' returns 'test.a.b.'
     * E.g., 'pickwareErpSingleItemOrder.isOpenSingleItemOrder' returns ''
     */
    private function extractFieldPrefix(string $filterField): string
    {
        $suffix = 'pickwareErpSingleItemOrder.isOpenSingleItemOrder';
        if (str_ends_with($filterField, $suffix)) {
            return mb_substr($filterField, 0, -mb_strlen($suffix));
        }

        throw new LogicException('Unexpected filter field: ' . $filterField);
    }

    /**
     * @phpstan-assert-if-true EqualsFilter $filter
     */
    private function isOpenSingleItemOrderFilter(Filter $filter): bool
    {
        return $filter instanceof EqualsFilter
            && str_ends_with($filter->getField(), 'pickwareErpSingleItemOrder.isOpenSingleItemOrder');
    }
}
