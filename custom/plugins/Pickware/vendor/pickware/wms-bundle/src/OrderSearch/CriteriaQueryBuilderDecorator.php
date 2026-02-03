<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderSearch;

use Doctrine\DBAL\Query\Join;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use ReflectionClass;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(CriteriaQueryBuilder::class)]
class CriteriaQueryBuilderDecorator extends CriteriaQueryBuilder
{
    public function __construct(
        #[AutowireDecorated]
        private readonly CriteriaQueryBuilder $decoratedInstance,
    ) {}

    /**
     * @param EntityDefinition<Entity> $definition
     */
    public function build(
        QueryBuilder $query,
        EntityDefinition $definition,
        Criteria $criteria,
        Context $context,
        array $paths = [],
    ): QueryBuilder {
        $query = $this->decoratedInstance->build($query, $definition, $criteria, $context, $paths);

        if (!($definition instanceof OrderDefinition)) {
            return $query;
        }

        $queryReflection = new ReflectionClass(DBALQueryBuilder::class);
        // The `sqlParts` property only exists until Doctrine DBAL 4.0, afterwards it was split into multiple properties
        if ($queryReflection->hasProperty('sqlParts')) {
            $sqlPartsProperty = $queryReflection->getProperty('sqlParts');
            $sqlPartsProperty->setAccessible(true);
            /** @var mixed[] $sqlParts */
            $sqlParts = $sqlPartsProperty->getValue($query);
        } else {
            // Doctrine DBAL 4.0 and newer
            $joinProperty = $queryReflection->getProperty('join');
            $joinProperty->setAccessible(true);
            $sqlParts['join'] = $joinProperty->getValue($query);
        }

        // Extend the join conditions of the query to only sort by shipping and payment method priorities of the
        // selected picking profile
        if (self::criteriaContainsSortingOfField($criteria, 'order.deliveries.shippingMethod.pickwareWmsPickingProfilePrioritizedShippingMethods.id')) {
            $filter = self::getEqualsFilterWithFieldOfAssociation(
                $criteria,
                filterFieldName: 'pickware_wms_picking_profile_prioritized_shipping_method.pickingProfileId',
                associationName: 'deliveries.shippingMethod.pickwareWmsPickingProfilePrioritizedShippingMethods',
            );
            if ($filter) {
                self::addFilterToJoinCondition(
                    $filter,
                    joinKey: '`order.deliveries.shippingMethod`',
                    joinTable: '`pickware_wms_picking_profile_prioritized_shipping_method`',
                    joinConditionExtensionFieldName: '`order.deliveries.shippingMethod.pickwareWmsPickingProfilePrioritizedShippingMethods`.`picking_profile_id`',
                    sqlParts: $sqlParts,
                );
                $query->setParameter('picking_profile_id', Uuid::fromHexToBytes($filter->getValue()));
            }
        }
        if (self::criteriaContainsSortingOfField($criteria, 'order.transactions.paymentMethod.pickwareWmsPickingProfilePrioritizedPaymentMethods.id')) {
            $filter = self::getEqualsFilterWithFieldOfAssociation(
                $criteria,
                filterFieldName: 'pickware_wms_picking_profile_prioritized_payment_method.pickingProfileId',
                associationName: 'transactions.paymentMethod.pickwareWmsPickingProfilePrioritizedPaymentMethods',
            );
            if ($filter) {
                self::addFilterToJoinCondition(
                    $filter,
                    joinKey: '`order.transactions.paymentMethod`',
                    joinTable: '`pickware_wms_picking_profile_prioritized_payment_method`',
                    joinConditionExtensionFieldName: '`order.transactions.paymentMethod.pickwareWmsPickingProfilePrioritizedPaymentMethods`.`picking_profile_id`',
                    sqlParts: $sqlParts,
                );
                $query->setParameter('picking_profile_id', Uuid::fromHexToBytes($filter->getValue()));
            }
        }

        if (isset($sqlPartsProperty)) {
            // Doctrine DBAL 3.x
            $sqlPartsProperty->setValue($query, $sqlParts);
        } else {
            // Doctrine DBAL 4.0 and newer
            $joinProperty->setValue($query, $sqlParts['join']);
        }

        return $query;
    }

    /**
     * @param EntityDefinition<Entity> $definition
     */
    public function addFilter(
        EntityDefinition $definition,
        ?Filter $filter,
        QueryBuilder $query,
        Context $context,
    ): void {
        $this->decoratedInstance->addFilter($definition, $filter, $query, $context);
    }

    /**
     * @param EntityDefinition<Entity> $definition
     */
    public function addSortings(
        EntityDefinition $definition,
        Criteria $criteria,
        array $sortings,
        QueryBuilder $query,
        Context $context,
    ): void {
        $this->decoratedInstance->addSortings($definition, $criteria, $sortings, $query, $context);
    }

    private static function getEqualsFilterWithFieldOfAssociation(
        Criteria $criteria,
        string $filterFieldName,
        string $associationName,
    ): ?EqualsFilter {
        return ImmutableCollection::create($criteria->getAssociation($associationName)->getFilters())
            ->first(fn(Filter $filter) => $filter instanceof EqualsFilter && $filter->getField() == $filterFieldName);
    }

    private static function criteriaContainsSortingOfField(Criteria $criteria, string $fieldName): bool
    {
        return in_array(
            $fieldName,
            array_map(fn(FieldSorting $fieldSorting) => $fieldSorting->getField(), $criteria->getSorting()),
            strict: true,
        );
    }

    private static function addFilterToJoinCondition(
        EqualsFilter $filter,
        string $joinKey,
        string $joinTable,
        string $joinConditionExtensionFieldName,
        mixed &$sqlParts,
    ): void {
        if ($filter !== null && isset($sqlParts['join'][$joinKey][0])) {
            $additionalCondition = ' AND ' . $joinConditionExtensionFieldName . ' = :picking_profile_id';
            foreach ($sqlParts['join'][$joinKey] as &$join) {
                if (class_exists(Join::class) && $join instanceof Join) {
                    // Doctrine DBAL 4.0 and newer
                    if ($join->table === $joinTable && $join->condition) {
                        $join = match ($join->type) {
                            'INNER' => Join::inner($join->table, $join->alias, $join->condition . $additionalCondition),
                            'LEFT' => Join::left($join->table, $join->alias, $join->condition . $additionalCondition),
                            'RIGHT' => Join::right($join->table, $join->alias, $join->condition . $additionalCondition),
                            default => $join,
                        };
                    }
                } elseif ($join['joinTable'] === $joinTable) {
                    // Doctrine DBAL 3.x
                    $join['joinCondition'] .= $additionalCondition;
                }
            }
        }
    }
}
