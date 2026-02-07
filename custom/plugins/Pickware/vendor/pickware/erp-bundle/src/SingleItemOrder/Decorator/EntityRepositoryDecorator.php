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
use Pickware\PickwareErpStarter\SingleItemOrder\Model\SingleItemOrderEntity;
use Pickware\PickwareErpStarter\SingleItemOrder\OpenSingleItemOrderCriteriaFilterResolver;
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderCalculator;
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderDevFeatureFlag;
use Shopware\Core\Checkout\Order\OrderEntity;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @see DefinitionInstanceRegistryDecorator::getRepository()
 * @extends EntityRepository<EntityCollection<covariant Entity>>
 * @phpstan-ignore-next-line class.extendsFinalByPhpDoc
 */
#[Exclude]
class EntityRepositoryDecorator extends EntityRepository
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $decoratedInstance
     */
    public function __construct(
        private readonly EntityRepository $decoratedInstance,
        private readonly SingleItemOrderCalculator $singleItemOrderCalculator,
        private readonly OpenSingleItemOrderCriteriaFilterResolver $openSingleItemOrderCriteriaFilterResolver,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return EntityDefinition<Entity>
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->decoratedInstance->getDefinition();
    }

    /**
     * @return EntitySearchResult<EntityCollection<covariant Entity>>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if (!$this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            return $this->decoratedInstance->search($criteria, $context);
        }

        $originalCriteria = clone $criteria;

        $this->openSingleItemOrderCriteriaFilterResolver->resolveOpenSingleItemOrderFilter($criteria);
        $singleItemOrderAssociations = $this->removeSingleItemOrderAssociations($criteria);

        $searchResult = $this->decoratedInstance->search($criteria, $context);

        $orderIds = $this->collectOrderIdsFromEntitiesForSingleItemOrder(
            $searchResult->getElements(),
            $singleItemOrderAssociations,
        );

        if (count($orderIds) === 0) {
            return $this->replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
        }

        $singleItemOrderIds = $this->singleItemOrderCalculator->calculateSingleItemOrdersForOrderIds($orderIds);
        $this->injectSingleItemOrderIntoEntities(
            $searchResult->getElements(),
            $singleItemOrderIds,
            $singleItemOrderAssociations,
        );

        return $this->replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        if ($this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            $this->openSingleItemOrderCriteriaFilterResolver->resolveOpenSingleItemOrderFilter($criteria);
        }

        return $this->decoratedInstance->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        if ($this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            $this->openSingleItemOrderCriteriaFilterResolver->resolveOpenSingleItemOrderFilter($criteria);
        }

        return $this->decoratedInstance->searchIds($criteria, $context);
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

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->delete($ids, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->decoratedInstance->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->decoratedInstance->merge($versionId, $context);
    }

    public function clone(
        string $id,
        Context $context,
        ?string $newId = null,
        ?CloneBehavior $behavior = null,
    ): EntityWrittenContainerEvent {
        return $this->decoratedInstance->clone($id, $context, $newId, $behavior);
    }

    /**
     * @return array<string, mixed>
     */
    private function removeSingleItemOrderAssociations(Criteria $criteria): array
    {
        $associations = [];
        foreach ($criteria->getAssociations() as $associationKey => $associationCriteria) {
            if ($associationKey === 'pickwareErpSingleItemOrder') {
                $associations[$associationKey] = $associationCriteria;
                $criteria->removeAssociation($associationKey);
            } else {
                $nestedAssociations = $this->removeSingleItemOrderAssociations($associationCriteria);
                if (count($nestedAssociations) > 0) {
                    $associations[$associationKey] = $nestedAssociations;
                }
            }
        }

        return $associations;
    }

    /**
     * @param Entity[] $entities
     * @param array<string, mixed> $singleItemOrderAssociations
     * @return string[]
     */
    private function collectOrderIdsFromEntitiesForSingleItemOrder(array $entities, array $singleItemOrderAssociations): array
    {
        $orderIds = [];
        $nestedEntities = [];
        foreach ($entities as $entity) {
            foreach ($singleItemOrderAssociations as $associationKey => $nestedAssociations) {
                if ($entity instanceof OrderEntity && $associationKey === 'pickwareErpSingleItemOrder') {
                    $orderIds[] = $entity->get('id');
                } else {
                    $nestedEntities[$associationKey] ??= [];
                    $nestedEntities[$associationKey][] = $entity->get($associationKey);
                }
            }
        }

        $orderIds = [$orderIds];
        foreach ($nestedEntities as $associationKey => $nestedEntitiesForKey) {
            $orderIds[] = $this->collectOrderIdsFromEntitiesForSingleItemOrder(
                $nestedEntitiesForKey,
                $singleItemOrderAssociations[$associationKey],
            );
        }

        return array_unique(array_merge(...$orderIds));
    }

    /**
     * @param Entity[] $entities
     * @param string[] $singleItemOrderIds
     * @param array<string, mixed> $singleItemOrderAssociations
     */
    private function injectSingleItemOrderIntoEntities(
        array $entities,
        array $singleItemOrderIds,
        array $singleItemOrderAssociations,
    ): void {
        $nestedEntities = [];
        foreach ($entities as $entity) {
            foreach ($singleItemOrderAssociations as $associationKey => $nestedAssociations) {
                if ($entity instanceof OrderEntity && $associationKey === 'pickwareErpSingleItemOrder') {
                    $singleItemOrderEntity = new SingleItemOrderEntity();
                    $singleItemOrderEntity->setId(Uuid::randomHex());
                    $singleItemOrderEntity->setOrderId($entity->getId());
                    $singleItemOrderEntity->setOrderVersionId($entity->getVersionId());
                    $singleItemOrderEntity->setIsSingleItemOrder(in_array($entity->getId(), $singleItemOrderIds));

                    $entity->addExtension('pickwareErpSingleItemOrder', $singleItemOrderEntity);
                } else {
                    $child = $entity->get($associationKey);
                    if ($child === null) {
                        continue;
                    }
                    $nestedEntities[$associationKey] ??= [];
                    $nestedEntities[$associationKey][] = $child;
                }
            }
        }
        foreach ($nestedEntities as $associationKey => $nestedEntitiesForKey) {
            $this->injectSingleItemOrderIntoEntities(
                $nestedEntitiesForKey,
                $singleItemOrderIds,
                $singleItemOrderAssociations[$associationKey],
            );
        }
    }

    /**
     * @template Collection of EntityCollection<covariant Entity>
     * @param EntitySearchResult<Collection> $searchResult
     * @return EntitySearchResult<Collection>
     */
    private function replaceCriteriaInEntitySearchResult(
        EntitySearchResult $searchResult,
        Criteria $criteria,
    ): EntitySearchResult {
        return new EntitySearchResult(
            $searchResult->getEntity(),
            $searchResult->getTotal(),
            $searchResult->getEntities(),
            $searchResult->getAggregations(),
            $criteria,
            $searchResult->getContext(),
        );
    }
}
