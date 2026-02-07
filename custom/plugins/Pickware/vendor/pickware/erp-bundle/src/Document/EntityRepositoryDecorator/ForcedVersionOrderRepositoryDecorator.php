<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Document\EntityRepositoryDecorator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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

/**
 * @template TEntityCollection of EntityCollection
 * @phpstan-ignore-next-line class.extendsFinalByPhpDoc
 */
#[AsDecorator('order.repository')]
class ForcedVersionOrderRepositoryDecorator extends EntityRepository
{
    /** @var array<string, string> $forcedOrderVersionIdByOrderIds */
    private array $forcedOrderVersionIdByOrderIds = [];

    /**
     * @param EntityRepository<TEntityCollection> $decoratedInstance
     */
    public function __construct(
        private readonly EntityRepository $decoratedInstance,
    ) {}

    /**
     * @return EntityDefinition<Entity>
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->decoratedInstance->getDefinition();
    }

    /**
     * The invoice renderer uses the order repository to retrieve order entities but uses their live version IDs
     * instead of the version IDs saved on the document entity. Therefore, if specific order version IDs
     * are provided for certain order IDs, this method creates scoped criteria and context to fetch the
     * version-specific order
     * @return EntitySearchResult<TEntityCollection>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if (count($this->forcedOrderVersionIdByOrderIds) === 0) {
            return $this->decoratedInstance->search($criteria, $context);
        }

        $orders = $this->decoratedInstance->search($criteria, $context);

        $orderIds = $orders->getIds();
        foreach ($orderIds as $orderId) {
            $versionId = $this->forcedOrderVersionIdByOrderIds[$orderId] ?? null;
            if ($versionId === null) {
                continue;
            }

            $newCriteria = Criteria::createFrom($criteria)->setIds([$orderId]);
            $orderInSpecificVersion = $this->decoratedInstance->search($newCriteria, $context->createWithVersionId($versionId))->getEntities()->first();
            if ($orderInSpecificVersion === null) {
                continue;
            }

            $orders->set($orderId, $orderInSpecificVersion);
            $orders->getEntities()->set($orderId, $orderInSpecificVersion);
        }

        return $orders;
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
     * @param array<array<string, mixed|null>> $data
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->update($data, $context);
    }

    /**
     * @param array<array<string, mixed|null>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->upsert($data, $context);
    }

    /**
     * @param array<array<string, mixed|null>> $data
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->create($data, $context);
    }

    /**
     * @param array<array<string, mixed|null>> $ids
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->delete($ids, $context);
    }

    /*
     * If this method is called with the forced order versions ids flag set the specified order version id is returned
     * instead of creating a new version.
     */
    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        if (isset($this->forcedOrderVersionIdByOrderIds[$id])) {
            return $this->forcedOrderVersionIdByOrderIds[$id];
        }

        return $this->decoratedInstance->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->decoratedInstance->merge($versionId, $context);
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->clone($id, $context, $newId, $behavior);
    }

    /**
     * @param array<string,string> $orderVersionIdByOrderIds
     * @param callable():void $callback
     */
    public function runWithForcedOrderVersions(array $orderVersionIdByOrderIds, callable $callback): void
    {
        $this->forcedOrderVersionIdByOrderIds = $orderVersionIdByOrderIds;
        try {
            $callback();
        } finally {
            $this->forcedOrderVersionIdByOrderIds = [];
        }
    }
}
