<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Decorator;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Prevent StockMovements from getting cloned when cloning a product.
 */
#[AsDecorator('product.repository')]
class ProductRepositoryDecorator extends EntityRepository
{
    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $cloneBehaviour = null): EntityWrittenContainerEvent
    {
        $cloneBehaviour = $cloneBehaviour ?: new CloneBehavior();
        $overwrites = $cloneBehaviour->getOverwrites();
        // If no new stock value is specified, set the new stock of the cloned product to 0.
        $overwrites['stock'] ??= 0;
        // Dont clone stocktake data
        $overwrites['extensions']['pickwareStocktakeCountingProcessItems'] = null;
        $overwrites['extensions']['pickwareStocktakeProductSummaries'] = null;

        return $this->decoratedInstance->clone($id, $context, $newId, new CloneBehavior(
            $overwrites,
            $cloneBehaviour->cloneChildren(),
        ));
    }

    /**
     * @param EntityRepository<EntityCollection<ProductEntity>> $decoratedInstance
     */
    public function __construct(
        #[AutowireDecorated]
        private readonly EntityRepository $decoratedInstance,
    ) {}

    /**
     * @return EntityDefinition<ProductEntity>
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->decoratedInstance->getDefinition();
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->decoratedInstance->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->decoratedInstance->searchIds($criteria, $context);
    }

    /**
     * @return EntitySearchResult<EntityCollection<ProductEntity>>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->decoratedInstance->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->create($data, $context);
    }

    public function delete(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->delete($data, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->decoratedInstance->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->decoratedInstance->merge($versionId, $context);
    }
}
