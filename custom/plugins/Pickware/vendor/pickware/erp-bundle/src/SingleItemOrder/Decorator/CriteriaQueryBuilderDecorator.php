<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder\Decorator;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\SingleItemOrder\OpenSingleItemOrderCriteriaFilterResolver;
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderDevFeatureFlag;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(CriteriaQueryBuilder::class)]
class CriteriaQueryBuilderDecorator extends CriteriaQueryBuilder
{
    public function __construct(
        #[AutowireDecorated]
        private readonly CriteriaQueryBuilder $decoratedInstance,
        private readonly OpenSingleItemOrderCriteriaFilterResolver $openSingleItemOrderCriteriaFilterResolver,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @param EntityDefinition<Entity> $definition
     * @param list<string> $paths
     */
    public function build(
        QueryBuilder $query,
        EntityDefinition $definition,
        Criteria $criteria,
        Context $context,
        array $paths = [],
    ): QueryBuilder {
        if ($this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            $this->openSingleItemOrderCriteriaFilterResolver->resolveOpenSingleItemOrderFilter($criteria);
        }

        return $this->decoratedInstance->build($query, $definition, $criteria, $context, $paths);
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
     * @param list<FieldSorting> $sortings
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
}
