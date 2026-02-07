<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;

class OrderPickabilityCriteriaFilterResolver
{
    private OrderPickabilityCalculator $orderPickabilityCalculator;

    public function __construct(OrderPickabilityCalculator $orderPickabilityCalculator)
    {
        $this->orderPickabilityCalculator = $orderPickabilityCalculator;
    }

    public function resolveOrderPickabilityFilter(Criteria $criteria): void
    {
        $pickabilityFilterContext = self::removeOrderPickabilityFilters($criteria);
        if ($pickabilityFilterContext->hasFiltersToApply()) {
            // Elasticsearch can not filter according to the pickability status as there are too many ids to fetch.
            $criteria->removeState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $pickabilityFilterContext->applyFilters($this->orderPickabilityCalculator);
        }
    }

    private static function removeOrderPickabilityFilters(Criteria $criteria): OrderPickabilityCriteriaFilterContext
    {
        $filters = $criteria->getFilters();

        // 1. Remove any pickability filters defined as `multi` filters
        $pickabilityFilterContext = self::replaceAllPickabilityFilters($filters);

        // 2. Remove any pickability filters defined in the criteria's root filters, which are a conjunction of all
        // elements, just like a `multi` filter with operator `AND`.
        list($validWarehouseIds, $validPickabilityStatus) = self::removePickabilityFilterComponents($filters);
        if ($validWarehouseIds !== null && $validPickabilityStatus !== null) {
            $pickabilityFilterContext->addPickabilityStatusFilter(
                $validWarehouseIds,
                $validPickabilityStatus,
                function(Filter $pickabilityFilter) use ($criteria): void {
                    $criteria->addFilter($pickabilityFilter);
                },
            );
        }
        $isUnknownPickabilityFilterUsedInRoot = self::removeUnknownPickabilityFilterComponent($filters);
        if ($isUnknownPickabilityFilterUsedInRoot) {
            $pickabilityFilterContext->addUnknownPickabilityFilter(
                function(Filter $pickabilityFilter) use ($criteria): void {
                    $criteria->addFilter($pickabilityFilter);
                },
            );
        }

        self::validateAbsenceOfUnsupportedPickabilityFieldFilters($filters);

        $criteria->resetFilters();
        $criteria->addFilter(...array_values($filters));

        // Remove more order pickability filters from the associations recursively
        foreach ($criteria->getAssociations() as $associationCriteria) {
            $pickabilityFilterContext->merge(self::removeOrderPickabilityFilters($associationCriteria));
        }

        return $pickabilityFilterContext;
    }

    /**
     * Recursively replaces all pickability filters in the given `$filters`, builds respective pickability filter
     * injections and collects all filtered warehouse IDs.
     *
     * @param Filter[] $filters
     */
    private static function replaceAllPickabilityFilters(array &$filters): OrderPickabilityCriteriaFilterContext
    {
        $pickabilityFilterContext = new OrderPickabilityCriteriaFilterContext();
        foreach ($filters as $filterIndex => $filter) {
            // Only actual instances of `MultiFilter` but none of its subclasses like `NotFilter` etc. can contain valid
            // pickability filter elements
            if (!($filter instanceof MultiFilter) || is_subclass_of($filter, MultiFilter::class)) {
                continue;
            }

            $filterChildren = $filter->getQueries();

            // Recursively remove any pickability filters from nested `multi` filters
            $pickabilityFilterContext->merge(self::replaceAllPickabilityFilters($filterChildren));

            $validWarehouseIds = null;
            $validPickabilityStatus = null;
            if ($filter->getOperator() === MultiFilter::CONNECTION_AND) {
                // Remove any pickability filters from the current `multi` filter
                list($validWarehouseIds, $validPickabilityStatus) = self::removePickabilityFilterComponents(
                    $filterChildren,
                );
            }
            $isUnknownPickabilityFilterUsedInRoot = self::removeUnknownPickabilityFilterComponent($filterChildren);

            self::validateAbsenceOfUnsupportedPickabilityFieldFilters($filterChildren);

            $isPickabilityFilterSet = $validWarehouseIds !== null && $validPickabilityStatus !== null;
            if (!$isPickabilityFilterSet && !$isUnknownPickabilityFilterUsedInRoot && count($filterChildren) === 0) {
                continue;
            }

            $newFilter = new MultiFilter($filter->getOperator(), array_values($filterChildren));
            $filters[$filterIndex] = $newFilter;
            if ($isPickabilityFilterSet) {
                $pickabilityFilterContext->addPickabilityStatusFilter(
                    $validWarehouseIds,
                    $validPickabilityStatus,
                    function(Filter $pickabilityFilter) use ($newFilter): void {
                        $newFilter->addQuery($pickabilityFilter);
                    },
                );
            }
            if ($isUnknownPickabilityFilterUsedInRoot) {
                $pickabilityFilterContext->addUnknownPickabilityFilter(
                    function(Filter $pickabilityFilter) use ($newFilter): void {
                        $newFilter->addQuery($pickabilityFilter);
                    },
                );
            }
        }

        return $pickabilityFilterContext;
    }

    /**
     * Finds and removes a combination of a "pickability warehouseId" and "pickability status" filter components from
     * the given `$filters`.
     *
     * @param Filter[] $filters
     * @return array{string[], array<mixed>}|null A tuple of the pickability filter's valid warehouse IDs and pickability status, if set.
     *         Otherwise null.
     */
    private static function removePickabilityFilterComponents(array &$filters): ?array
    {
        $pickabilityWarehouseIdFilter = self::removeSingleFieldFilter(
            $filters,
            'pickwareErpOrderPickabilities.warehouseId',
        );
        $pickabilityStatusFilter = self::removeSingleFieldFilter(
            $filters,
            'pickwareErpOrderPickabilities.orderPickabilityStatus',
        );
        if ($pickabilityWarehouseIdFilter === null && $pickabilityStatusFilter === null) {
            return null;
        }
        if ($pickabilityWarehouseIdFilter !== null && $pickabilityStatusFilter === null) {
            throw OrderPickabilityException::missingPickabilityStatusFilter();
        }
        if ($pickabilityWarehouseIdFilter === null && $pickabilityStatusFilter !== null) {
            throw OrderPickabilityException::missingWarehouseFilter();
        }
        $warehouseIds = (is_array($pickabilityWarehouseIdFilter->getValue())) ? $pickabilityWarehouseIdFilter->getValue() : [$pickabilityWarehouseIdFilter->getValue()];
        $warehouseIds = array_values(array_filter($warehouseIds));

        return [
            $warehouseIds,
            (is_array($pickabilityStatusFilter->getValue())) ? $pickabilityStatusFilter->getValue() : [$pickabilityStatusFilter->getValue()],
        ];
    }

    /**
     * Finds and removes an "unknown pickability" (aka "no pickability") filter, defined by a filter component for a
     * NULL pickability ID, from the given `$filters`.
     *
     * @param Filter[] $filters
     * @return bool True, if a valid "unknown pickability filter" was found and removed. Otherwise false.
     */
    private static function removeUnknownPickabilityFilterComponent(array &$filters): bool
    {
        $pickabilityIdFilter = self::removeSingleFieldFilter(
            $filters,
            'pickwareErpOrderPickabilities.id',
        );

        return $pickabilityIdFilter !== null && $pickabilityIdFilter->getValue() === null;
    }

    /**
     * @param Filter[] $filters
     */
    private static function removeSingleFieldFilter(array &$filters, string $fieldName): ?SingleFieldFilter
    {
        foreach ($filters as $index => $filter) {
            if ($filter instanceof SingleFieldFilter && str_ends_with($filter->getField(), $fieldName)) {
                unset($filters[$index]);

                return $filter;
            }
        }

        return null;
    }

    /**
     * @param Filter[] $filters
     */
    private static function validateAbsenceOfUnsupportedPickabilityFieldFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            if ($filter instanceof SingleFieldFilter && str_starts_with($filter->getField(), 'pickwareErpOrderPickabilities')) {
                throw OrderPickabilityException::unsupportedOrderPickabilitiesFieldFilter($filter->getField());
            }
        }
    }
}
