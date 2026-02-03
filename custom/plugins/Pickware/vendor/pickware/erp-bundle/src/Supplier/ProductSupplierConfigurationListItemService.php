<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationListItemCollection;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationListItemEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSupplierConfigurationListItemService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly CriteriaQueryBuilder $criteriaQueryBuilder,
    ) {}

    /**
     * This method returns entities over a public interface by design. Product supplier configuration list items are not
     * stored in the database but are generated on-demand from existing product and product supplier configuration
     * entities from product criteria. Therefore, passing these entities by ID is not possible.
     * Since this method accepts a criteria, the callee can specify the necessary associations and be sure about their
     * presence on the requested entities.
     */
    public function getProductSupplierConfigurationListItemCollection(
        Criteria $productCriteria,
        Context $context,
    ): ProductSupplierConfigurationListItemCollection {
        $generationResult = $this->generateProductSupplierConfigurationListItems($productCriteria, $context);

        return $this->createProductSupplierConfigurationListItemCollection(
            $productCriteria,
            $generationResult->getProductSupplierConfigurationListItemReferenceCollection(),
            $context,
        );
    }

    public function createProductSupplierConfigurationListItemCollection(
        Criteria $productCriteria,
        ProductSupplierConfigurationListItemReferenceCollection $productSupplierConfigurationListItemReferences,
        Context $context,
    ): ProductSupplierConfigurationListItemCollection {
        // Create a copy of the given criteria, so we can safely modify it.
        $productCriteriaCopy = clone $productCriteria;
        // We are only interested in the original criteria's associations, since the product supplier configuration list
        // item references are already correctly sorted and paginated. Unfortunately the simplest way of transferring
        // the associations to a different criteria is to copy the criteria and reset any other property.
        $productCriteriaCopy
            ->resetSorting()
            ->resetFilters()
            ->resetPostFilters()
            ->setLimit(null)
            ->setOffset(null);

        $productCriteriaCopy->addFilter(new EqualsAnyFilter('id', $productSupplierConfigurationListItemReferences->getProductIds()));
        if (count($productSupplierConfigurationListItemReferences->getProductSupplierConfigurationIds()) > 0) {
            $productCriteriaCopy
                ->addAssociation('extensions.pickwareErpProductSupplierConfigurations')
                ->getAssociation('extensions.pickwareErpProductSupplierConfigurations')
                ->addFilter(new EqualsAnyFilter('id', $productSupplierConfigurationListItemReferences->getProductSupplierConfigurationIds()));
        }

        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, $productCriteriaCopy, $context);
        $productSupplierConfigurationListItemCollection = new ProductSupplierConfigurationListItemCollection();

        // It is important to iterate the references because they contain the original sorting of the associations. This
        // association sorting would be lost by fetching 'product's with the dal (i.e. iterating $products here).
        foreach ($productSupplierConfigurationListItemReferences as $productStockReference) {
            // Logically the corresponding product and product supplier configurations should always exist here.
            $product = $products->get($productStockReference->getProductId());
            if ($productStockReference->getProductSupplierConfigurationId()) {
                $supplierConfigurationEntity = $product->getExtension('pickwareErpProductSupplierConfigurations')->get($productStockReference->getProductSupplierConfigurationId());
                $itemToAdd = $this->createProductSupplierConfigurationListItemEntity($product, $supplierConfigurationEntity);
            } else {
                $itemToAdd = $this->createProductSupplierConfigurationListItemEntity($product, null);
            }

            $productSupplierConfigurationListItemCollection->add($itemToAdd);
            if ($productCriteriaCopy->getLimit() && $productSupplierConfigurationListItemCollection->count() >= $productCriteriaCopy->getLimit()) {
                break;
            }
        }

        return $productSupplierConfigurationListItemCollection;
    }

    private function createProductSupplierConfigurationListItemEntity(
        ProductEntity $product,
        ?ProductSupplierConfigurationEntity $supplierConfigurationEntity,
    ): ProductSupplierConfigurationListItemEntity {
        $productSupplierConfigurationListItem = new ProductSupplierConfigurationListItemEntity();
        $productSupplierConfigurationListItem->setId(Uuid::randomHex());
        $productSupplierConfigurationListItem->setProductId($product->getId());
        $productSupplierConfigurationListItem->setProduct($product);
        $productSupplierConfigurationListItem->setProductSupplierConfigurationId($supplierConfigurationEntity?->getId());
        $productSupplierConfigurationListItem->setProductSupplierConfiguration($supplierConfigurationEntity);

        return $productSupplierConfigurationListItem;
    }

    public function generateProductSupplierConfigurationListItems(Criteria $productCriteria, Context $context): ProductSupplierConfigurationListItemGenerationResult
    {
        // Create a copy of the given criteria, so we can safely modify it.
        $productCriteriaCopy = clone $productCriteria;
        $productCriteriaCopy->resetAssociations();

        // Add a filter that is always true but that forces the query builder to join pickware product supplier
        // configuration table.
        $productCriteriaCopy->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('extensions.pickwareErpProductSupplierConfigurations.id', null),
                new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('extensions.pickwareErpProductSupplierConfigurations.id', null)]),
            ],
        ));
        $definition = $this->entityManager->getEntityDefinition(ProductDefinition::class);
        $query = new QueryBuilder($this->connection);
        $query->addSelect('LOWER(HEX(`product`.id)) AS id');
        $query->addSelect('LOWER(HEX(`product.pickwareErpProductSupplierConfigurations`.id)) AS productSupplierConfigurationId');
        $query = $this->criteriaQueryBuilder->build($query, $definition, $productCriteriaCopy, $context);
        if ($productCriteriaCopy->getOffset() !== null) {
            $query->setFirstResult($productCriteriaCopy->getOffset());
        }
        if ($productCriteriaCopy->getLimit() !== null) {
            $query->setMaxResults($productCriteriaCopy->getLimit());
        }

        // Main difference to Shopware's entity searcher: we do not group only by the primary entity (product) but by
        // the combination of product and product supplier configuration to mimic a LEFT JOIN where the product supplier
        // configuration may be null.
        $query->addGroupBy('`product`.id', '`product.pickwareErpProductSupplierConfigurations`.id');
        $rows = $query->executeQuery()->fetchAllAssociative();

        return new ProductSupplierConfigurationListItemGenerationResult(
            productSupplierConfigurationListItemReferenceCollection: new ProductSupplierConfigurationListItemReferenceCollection(
                array_map(
                    fn(array $row) => new ProductSupplierConfigurationListItemReference(
                        $row['id'],
                        $row['productSupplierConfigurationId'],
                    ),
                    $rows,
                ),
            ),
            total: $this->getTotalCount($query),
        );
    }

    /**
     * Mostly copy of Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher::getTotalCount()
     */
    private function getTotalCount(QueryBuilder $query): int
    {
        $query->resetOrderBy();
        $query->setMaxResults(null);
        $query->setFirstResult(0);

        $total = new QueryBuilder($this->connection);
        $total->select('COUNT(*)')
            ->from(\sprintf('(%s) total', $query->getSQL()))
            ->setParameters($query->getParameters(), $query->getParameterTypes());

        return (int) $total->executeQuery()->fetchOne();
    }
}
