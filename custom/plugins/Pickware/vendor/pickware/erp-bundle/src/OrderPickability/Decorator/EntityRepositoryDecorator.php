<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Decorator;

use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityCollection;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCalculator;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCriteriaFilterResolver;
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
     * @var EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    private EntityRepository $decoratedInstance;

    private OrderPickabilityCalculator $orderPickabilityCalculator;
    private OrderPickabilityCriteriaFilterResolver $orderPickabilityCriteriaFilterResolver;

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $decoratedInstance
     */
    public function __construct(
        EntityRepository $decoratedInstance,
        OrderPickabilityCalculator $orderPickabilityCalculator,
        OrderPickabilityCriteriaFilterResolver $orderPickabilityCriteriaFilterResolver,
    ) {
        $this->decoratedInstance = $decoratedInstance;
        $this->orderPickabilityCalculator = $orderPickabilityCalculator;
        $this->orderPickabilityCriteriaFilterResolver = $orderPickabilityCriteriaFilterResolver;
    }

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
        // Keep a copy of the original criteria to restore them in the search result returned by this method
        $originalCriteria = clone $criteria;

        $this->orderPickabilityCriteriaFilterResolver->resolveOrderPickabilityFilter($criteria);
        $orderPickabilityAssociations = self::removeOrderPickabilitiesAssociations($criteria);

        $searchResult = $this->decoratedInstance->search($criteria, $context);

        $orderIds = self::collectOrderIdsFromEntities($searchResult->getElements(), $orderPickabilityAssociations);
        if (count($orderIds) === 0) {
            return self::replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
        }

        $orderPickabilities = $this->orderPickabilityCalculator->calculateOrderPickabilitiesForOrders($orderIds);
        $pickabilitiesByOrderId = [];
        foreach ($orderPickabilities as $pickabilityEntity) {
            $pickabilitiesByOrderId[$pickabilityEntity->getOrderId()] ??= new OrderPickabilityCollection();
            $pickabilitiesByOrderId[$pickabilityEntity->getOrderId()]->add($pickabilityEntity);
        }
        self::injectOrderPickabilitiesIntoEntities(
            $searchResult->getElements(),
            $pickabilitiesByOrderId,
            $orderPickabilityAssociations,
        );

        return self::replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        $this->orderPickabilityCriteriaFilterResolver->resolveOrderPickabilityFilter($criteria);

        return $this->decoratedInstance->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $this->orderPickabilityCriteriaFilterResolver->resolveOrderPickabilityFilter($criteria);

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
    private static function removeOrderPickabilitiesAssociations(Criteria $criteria): array
    {
        $associations = [];
        foreach ($criteria->getAssociations() as $associationKey => $associationCriteria) {
            if ($associationKey === 'pickwareErpOrderPickabilities') {
                $associations[$associationKey] = $associationCriteria;
                $criteria->removeAssociation($associationKey);
            } else {
                $nestedAssociations = self::removeOrderPickabilitiesAssociations($associationCriteria);
                if (count($nestedAssociations) > 0) {
                    $associations[$associationKey] = $nestedAssociations;
                }
            }
        }

        return $associations;
    }

    /**
     * @param Entity[] $entities
     * @param array<string, mixed> $orderPickabilityAssociations
     * @return string[]
     */
    private static function collectOrderIdsFromEntities(array $entities, array $orderPickabilityAssociations): array
    {
        $orderIds = [];
        $nestedEntities = [];
        foreach ($entities as $entity) {
            foreach ($orderPickabilityAssociations as $associationKey => $nestedAssociations) {
                if ($entity instanceof OrderEntity && $associationKey === 'pickwareErpOrderPickabilities') {
                    $orderIds[] = $entity->get('id');
                } else {
                    $nestedEntities[$associationKey] ??= [];
                    $nestedEntities[$associationKey][] = $entity->get($associationKey);
                }
            }
        }

        $orderIds = [$orderIds];
        foreach ($nestedEntities as $associationKey => $nested) {
            $orderIds[] = self::collectOrderIdsFromEntities($nested, $orderPickabilityAssociations[$associationKey]);
        }

        return array_unique(array_merge(...$orderIds));
    }

    /**
     * @param Entity[] $entities
     * @param OrderPickabilityCollection[] $groupedOrderPickabilities
     * @param array<string, mixed> $orderPickabilityAssociations
     */
    private static function injectOrderPickabilitiesIntoEntities(
        array $entities,
        array $groupedOrderPickabilities,
        array $orderPickabilityAssociations,
    ): void {
        $nestedEntities = [];
        foreach ($entities as $entity) {
            foreach ($orderPickabilityAssociations as $associationKey => $nestedAssociations) {
                if ($entity instanceof OrderEntity && $associationKey === 'pickwareErpOrderPickabilities') {
                    $entity->addExtension(
                        'pickwareErpOrderPickabilities',
                        $groupedOrderPickabilities[$entity->get('id')] ?? new OrderPickabilityCollection(),
                    );
                } else {
                    $nestedEntities[$associationKey] ??= [];
                    $nestedEntities[$associationKey][] = $entity->get($associationKey);
                }
            }
        }
        foreach ($nestedEntities as $associationKey => $nested) {
            self::injectOrderPickabilitiesIntoEntities(
                $nested,
                $groupedOrderPickabilities,
                $orderPickabilityAssociations[$associationKey],
            );
        }
    }

    /**
     * Creates and returns a new instance of `EntitySearchResult` that matches the given instance except for the
     * criteria, which are replaced by the given criteria. This is necessary because `EntitySearchResult` does not have
     * a setter for the criteria.
     *
     * @template Collection of EntityCollection
     * @param EntitySearchResult<Collection> $searchResult
     * @return EntitySearchResult<Collection>
     */
    private static function replaceCriteriaInEntitySearchResult(
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
