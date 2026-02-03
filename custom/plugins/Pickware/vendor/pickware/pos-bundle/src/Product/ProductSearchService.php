<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Product;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

class ProductSearchService
{
    private Connection $connection;
    private CriteriaQueryBuilder $criteriaQueryBuilder;
    private EntityManager $entityManager;

    public function __construct(
        Connection $connection,
        CriteriaQueryBuilder $criteriaQueryBuilder,
        EntityManager $entityManager,
    ) {
        $this->connection = $connection;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Filter[] $filter
     * @return MainProductVariantCount[]
     */
    public function findMainProductVariantCountsOfProductsMatching(array $filter, Context $context): array
    {
        $mainProductIdsAndProductCountCriteria = new Criteria();
        $mainProductIdsAndProductCountCriteria->addFilter(...$filter);
        $mainProductIdsAndProductCountCriteria->setLimit(10_000);

        $queryBuilder = new QueryBuilder($this->connection);
        $entityDefinition = $this->entityManager->getEntityDefinition(ProductDefinition::class);
        $queryBuilder = $this->criteriaQueryBuilder->build(
            $queryBuilder,
            $entityDefinition,
            $mainProductIdsAndProductCountCriteria,
            $context,
        );

        $queryBuilder->select(
            'LOWER(HEX(COALESCE(`product`.`parent_id`, `product`.`id`))) AS mainProductId',
            'COUNT(DISTINCT `product`.`id`) AS variantCount',
        );
        $queryBuilder->groupBy('mainProductId');

        // We can use `$queryBuilder->executeQuery()->fetchAllAssociative()` instead once our min version is v6.4.18.0.
        $mainProductIdsAndVariantCount = $this->connection
            ->executeQuery(
                $queryBuilder->getSQL(),
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes(),
            )
            ->fetchAllAssociative();

        return array_map(
            fn($mainProductIdAndVariantCount) => new MainProductVariantCount(
                $mainProductIdAndVariantCount['mainProductId'],
                (int)$mainProductIdAndVariantCount['variantCount'],
            ),
            $mainProductIdsAndVariantCount,
        );
    }
}
