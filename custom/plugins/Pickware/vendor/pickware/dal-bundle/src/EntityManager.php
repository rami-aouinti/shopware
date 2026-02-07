<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use BackedEnum;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use InvalidArgumentException;
use LogicException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * // The CriterionValue type corresponds to the values accepted by Shopware's @see EqualsFilter
 * @phpstan-type CriterionValue string|bool|float|int|null
 * //  Any value of type `object` has to be string-castable, however this can currently not be enforced using PHPStan
 * @phpstan-type CriterionValueEquivalent CriterionValue|object|BackedEnum
 * @phpstan-type CriteriaEquivalent Criteria|array<Filter>|array<string, CriterionValueEquivalent>|array<string, array<CriterionValueEquivalent>>
 */
class EntityManager
{
    private ContainerInterface $container;
    private Connection $db;
    private DefaultTranslationProvider $defaultTranslationProvider;
    private EntityDefinitionQueryHelper $entityDefinitionQueryHelper;
    private ?CriteriaQueryBuilder $criteriaQueryBuilder;

    public function __construct(
        #[Autowire(service: 'service_container')]
        ContainerInterface $container,
        Connection $db,
        DefaultTranslationProvider $defaultTranslationProvider,
        EntityDefinitionQueryHelper $entityDefinitionQueryHelper,
        ?CriteriaQueryBuilder $criteriaQueryBuilder = null,
    ) {
        $this->container = $container;
        $this->db = $db;
        $this->defaultTranslationProvider = $defaultTranslationProvider;
        $this->entityDefinitionQueryHelper = $entityDefinitionQueryHelper;
        $this->criteriaQueryBuilder = $criteriaQueryBuilder;
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     */
    public function findIdsBy(string $entityDefinitionClassName, Criteria|array $criteria, Context $context): array
    {
        $criteria = is_array($criteria) ? self::createCriteriaFromArray($criteria) : $criteria;

        $repository = $this->getRepository($entityDefinitionClassName);

        return $repository->searchIds($criteria, $context)->getIds();
    }

    public function findAllIds(string $entityDefinitionClassName, Context $context): array
    {
        return $this->findIdsBy($entityDefinitionClassName, [], $context);
    }

    /**
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     *
     * @return QueriedEntity|null
     */
    public function findByPrimaryKey(
        string $entityDefinitionClassName,
        array|string|int $primaryKey,
        Context $context,
        array $associations = [],
    ): ?Entity {
        $repository = $this->getRepository($entityDefinitionClassName);
        $criteria = new Criteria([$primaryKey]);
        if (count($associations) !== 0) {
            $criteria->addAssociations($associations);
        }

        $result = $repository->search($criteria, $context);

        if ($result->count() > 1) {
            throw DataAbstractionLayerException::moreThanOneEntityInResultSet(__METHOD__);
        }

        return $result->first();
    }

    /**
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @throws EntityManagerException when the entity was not found
     * @return QueriedEntity
     */
    public function getByPrimaryKey(
        string $entityDefinitionClassName,
        array|string|int $primaryKey,
        Context $context,
        array $associations = [],
    ): Entity {
        $entity = $this->findByPrimaryKey($entityDefinitionClassName, $primaryKey, $context, $associations);
        if (!$entity) {
            throw EntityManagerException::entityWithPrimaryKeyNotFound($entityDefinitionClassName, $primaryKey);
        }

        return $entity;
    }

    /**
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     *
     * @return EntityCollection<QueriedEntity>
     */
    public function findBy(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
        array $associations = [],
    ): EntityCollection {
        $criteria = is_array($criteria) ? self::createCriteriaFromArray($criteria) : $criteria;

        if (count($associations) !== 0) {
            $criteria->addAssociations($associations);
        }

        $repository = $this->getRepository($entityDefinitionClassName);
        $searchResult = $repository->search($criteria, $context);

        if (count($criteria->getFields()) !== 0) {
            $collectionClassName = PartialEntityCollection::class;
        } else {
            $collectionClassName = $this->getEntityDefinition($entityDefinitionClassName)->getCollectionClass();
        }

        return new $collectionClassName($searchResult->getElements());
    }

    /**
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     *
     * @return QueriedEntity|null
     */
    public function findOneBy(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
        array $associations = [],
    ): ?Entity {
        $result = $this->findBy($entityDefinitionClassName, $criteria, $context, $associations);

        if ($result->count() > 1) {
            throw DataAbstractionLayerException::moreThanOneEntityInResultSet(__METHOD__);
        }

        return $result->first();
    }

    /**
     * Same as findOneBy but throws an exception when no entity was returned.
     *
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     * @throws EntityManagerException when the entity was not found
     *
     * @return QueriedEntity
     */
    public function getOneBy(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
        array $associations = [],
    ): Entity {
        $entity = $this->findOneBy($entityDefinitionClassName, $criteria, $context, $associations);
        if (!$entity) {
            throw EntityManagerException::entityWithCriteriaNotFound($entityDefinitionClassName, $criteria);
        }

        return $entity;
    }

    /**
     * Returns the first entity of the result. Throws an exception if no entity was found at all. Be sure to use a
     * sorting in the criteria to get deterministic results.
     *
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     * @param FieldSorting[] $sorting
     *
     * @return QueriedEntity
     */
    public function getFirstBy(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        array $sorting,
        Context $context,
        array $associations = [],
    ): Entity {
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        }

        $criteria->setLimit(1);
        $criteria->addSorting(...$sorting);

        if ($criteria->getSorting() === []) {
            throw new InvalidArgumentException(
                'A sorting is required when using getFirstBy to ensure deterministic results.',
            );
        }

        return $this->getOneBy($entityDefinitionClassName, $criteria, $context, $associations);
    }

    /**
     * Returns the first entity of the result.
     *
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     * @param FieldSorting[]|FieldSorting $sorting
     *
     * @return QueriedEntity|null
     */
    public function findFirstBy(
        string $entityDefinitionClassName,
        array|FieldSorting $sorting,
        Context $context,
        array|Criteria|null $criteria = null,
        array $associations = [],
    ): ?Entity {
        if (is_array($criteria)) {
            $criteria = self::createCriteriaFromArray($criteria);
        } elseif (!$criteria) {
            $criteria = new Criteria();
        }

        if ($sorting && is_array($sorting)) {
            $criteria->addSorting(...$sorting);
        } elseif ($sorting instanceof FieldSorting) {
            $criteria->addSorting($sorting);
        } else {
            throw new InvalidArgumentException(sprintf(
                'Parameter $sorting must be %s or array of %s.',
                FieldSorting::class,
                FieldSorting::class,
            ));
        }

        $criteria->setOffset(0);
        $criteria->setLimit(1);

        return $this->findBy($entityDefinitionClassName, $criteria, $context, $associations)->first();
    }

    /**
     * @template QueriedEntity of Entity
     *
     * @param class-string<EntityDefinition<QueriedEntity>> $entityDefinitionClassName
     *
     * @return EntityCollection<QueriedEntity>
     */
    public function findAll(
        string $entityDefinitionClassName,
        Context $context,
        array $associations = [],
    ): EntityCollection {
        return $this->findBy($entityDefinitionClassName, [], $context, $associations);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     * @param array<array<string,mixed>> $payload
     */
    public function create(
        string $entityDefinitionClassName,
        array $payload,
        Context $context,
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->create($payload, $context);
    }

    /**
     * Creates all entities from $payload that do not exist yet.
     *
     * If the entities exist already, they stay untouched. The primary key is used to decide whether the entities already
     * exist.
     *
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     */
    public function createIfNotExists(
        string $entityDefinitionClassName,
        array $payload,
        Context $context,
    ): EntityWrittenContainerEvent {
        $primaryKeyFields = $this->getEntityDefinition($entityDefinitionClassName)->getPrimaryKeys();
        if ($primaryKeyFields->count() !== 1) {
            throw new LogicException('Entities with multiple primary key fields are not supported yet');
        }
        $primaryKeyName = $primaryKeyFields->first()->getPropertyName();

        $existingEntities = $this->findBy(
            $entityDefinitionClassName,
            [$primaryKeyName => array_map(fn(array $entity) => $entity[$primaryKeyName], $payload)],
            $context,
        );
        $existingEntityIds = EntityCollectionExtension::getField($existingEntities, $primaryKeyName);
        $entitiesToCreate = array_values(array_filter(
            $payload,
            fn(array $entity) => !in_array($entity[$primaryKeyName], $existingEntityIds, true),
        ));

        return $this->create($entityDefinitionClassName, $entitiesToCreate, $context);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     */
    public function upsert(
        string $entityDefinitionClassName,
        array $payload,
        Context $context,
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->upsert($payload, $context);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     * @param array<array<string,mixed>> $payload
     */
    public function update(
        string $entityDefinitionClassName,
        array $payload,
        Context $context,
    ): EntityWrittenContainerEvent {
        if (count($payload) === 0) {
            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        }
        $this->defaultTranslationProvider->ensureSystemDefaultTranslationInEntityWritePayload(
            $entityDefinitionClassName,
            $payload,
        );

        return $this->getRepository($entityDefinitionClassName)->update($payload, $context);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     */
    public function delete(string $entityDefinitionClassName, array $ids, Context $context): EntityWrittenContainerEvent
    {
        if (count($ids) === 0) {
            return EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
        }

        $ids = array_values($ids);

        // Convert the $ids to an array of associative arrays if not passed as such
        if (!is_array($ids[0])) {
            $entityDefinition = $this->getEntityDefinition($entityDefinitionClassName);
            $primaryKeyFields = $entityDefinition->getPrimaryKeys()->filter(fn(Field $field) => !($field instanceof VersionField));
            $primaryKey = $primaryKeyFields->first();
            $ids = array_map(fn($id) => [
                $primaryKey->getPropertyName() => $id,
            ], $ids);
        }

        return $this->getRepository($entityDefinitionClassName)->delete($ids, $context);
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     */
    public function deleteByCriteria(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
    ): EntityWrittenContainerEvent {
        $entitiesToDelete = $this->findBy($entityDefinitionClassName, $criteria, $context);
        $primaryKeyFields = $this->getEntityDefinition($entityDefinitionClassName)->getPrimaryKeys();
        $deletePayload = [];
        foreach ($entitiesToDelete as $entityToDelete) {
            $payload = [];
            foreach ($primaryKeyFields as $primaryKeyField) {
                $propertyName = $primaryKeyField->getPropertyName();
                $payload[$propertyName] = $entityToDelete->get($propertyName);
            }
            $deletePayload[] = $payload;
        }

        return $this->delete($entityDefinitionClassName, $deletePayload, $context);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     * @return string the UUID of the newly created version
     */
    public function createVersion(string $entityDefinitionClassName, string $primaryKey, Context $context, ?string $name = null): string
    {
        return $this->getRepository($entityDefinitionClassName)->createVersion($primaryKey, $context, $name);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     */
    public function merge(string $entityDefinitionClassName, string $versionId, Context $context): void
    {
        $this->getRepository($entityDefinitionClassName)->merge($versionId, $context);
    }

    /**
     * @template RelatedEntity of Entity
     *
     * @param class-string<EntityDefinition<RelatedEntity>> $entityDefinitionClassName
     */
    public function getRepository(string $entityDefinitionClassName): EntityRepository
    {
        $entityName = $this->getEntityDefinition($entityDefinitionClassName)->getEntityName();

        return $this->container->get(sprintf('%s.repository', $entityName));
    }

    /**
     * @template QueriedEntityDefinition of EntityDefinition<Entity>
     * @param class-string<QueriedEntityDefinition> $entityDefinitionClassName
     * @return QueriedEntityDefinition
     */
    public function getEntityDefinition(string $entityDefinitionClassName): EntityDefinition
    {
        /** @var EntityDefinition<Entity> $entityDefinition */
        $entityDefinition = $this->container->get($entityDefinitionClassName);

        return $entityDefinition;
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     * @return list<string> The locked entity IDs
     */
    public function lockPessimistically(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
    ): array {
        $queryBuilder = $this->createQueryBuilder(
            $entityDefinitionClassName,
            $criteria,
            $context,
        );

        return $this->lockPessimisticallyWithQueryBuilder($queryBuilder);
    }

    /**
     * @throws DataAbstractionLayerException when not in transaction
     * @return list<string> The locked entity IDs
     */
    public function lockPessimisticallyWithQueryBuilder(
        QueryBuilder $queryBuilder,
    ): array {
        if (!$this->db->isTransactionActive()) {
            // Pessimistic locking can happen in transactions exclusively
            throw DataAbstractionLayerException::transactionNecessaryForPessimisticLocking();
        }

        $queryBuilder->forUpdate(ConflictResolutionMode::ORDINARY);

        // Execute locking SQL
        return $this->db->fetchFirstColumn(
            $queryBuilder->getSQL(),
            $queryBuilder->getParameters(),
            $queryBuilder->getParameterTypes(),
        );
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     */
    public function createQueryBuilder(
        string $entityDefinitionClassName,
        Criteria|array $criteria,
        Context $context,
    ): QueryBuilder {
        if (!$this->criteriaQueryBuilder) {
            throw new LogicException(sprintf(
                'This instance of %s was created without an %s therefore this method is unavailable.',
                self::class,
                CriteriaQueryBuilder::class,
            ));
        }

        // Convert criteria array to Criteria object
        $criteria = is_array($criteria) ? self::createCriteriaFromArray($criteria) : $criteria;

        // Create queryBuilder for Criteria object
        $entityDefinition = $this->getEntityDefinition($entityDefinitionClassName);
        $queryBuilder = $this->criteriaQueryBuilder->build(
            new QueryBuilder($this->db),
            $entityDefinition,
            $criteria,
            $context,
        );
        // Queries with a to-many join (like a filter with "order.lineItems") will have this state when using a LIMIT > 1.
        // Such queries will generate ORDER BY statements using aggregate functions like MIN and MAX. This necessitates
        // presence of a GROUP BY statement as otherwise no aggregations are allowed by MySQL. This is added as a fallback
        // that a GROUP BY always exists such that those queries can be executed and is directly copied from some preprocessing
        // Shopware does when searching for entities over the repository. As we use a lower level API here, we need to
        // do this ourselves.
        // See https://github.com/shopware/shopware/blob/2bf9456550ea99b4ecb4a9a19969dec635b20da7/src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php#L210-L214.
        if ($queryBuilder->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN)) {
            $queryBuilder->addGroupBy(
                EntityDefinitionQueryHelper::escape($entityDefinition->getEntityName()) . '.' . EntityDefinitionQueryHelper::escape('id'),
            );
        }
        // Add pagination and ID support like Shopware does in preprocessing for searching for entities.
        // See https://github.com/shopware/shopware/blob/2bf9456550ea99b4ecb4a9a19969dec635b20da7/src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php#L86-L98.
        if (!empty($criteria->getIds())) {
            $this->entityDefinitionQueryHelper->addIdCondition($criteria, $entityDefinition, $queryBuilder);
        }
        if ($criteria->getOffset() !== null) {
            $queryBuilder->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() !== null) {
            $queryBuilder->setMaxResults($criteria->getLimit());
        }
        $queryBuilder->addSelect(
            'LOWER(HEX(' . $this->db->quoteIdentifier(sprintf('%s.id', $entityDefinition->getEntityName())) . '))',
        );

        return $queryBuilder;
    }

    /**
     * @param class-string<EntityDefinition<Entity>> $entityDefinitionClassName
     * @param CriteriaEquivalent $criteria
     */
    public function count(
        string $entityDefinitionClassName,
        string $aggregatedFieldName,
        Criteria|array $criteria,
        Context $context,
    ): int {
        $criteria = is_array($criteria) ? self::createCriteriaFromArray($criteria) : $criteria;

        $countAggregationCriteria = $criteria->addAggregation(
            new CountAggregation('count', $aggregatedFieldName),
        );

        return $this->getRepository(
            $entityDefinitionClassName,
        )->aggregate(
            $countAggregationCriteria,
            $context,
        )->get('count')->getCount();
    }

    /**
     * @template ReturnValue
     * @param callable():ReturnValue $callback
     * @return ReturnValue
     */
    public function runInTransactionWithRetry(callable $callback): mixed
    {
        return RetryableTransaction::retryable($this->db, $callback);
    }

    /**
     * @param list<Filter>|array<string, CriterionValueEquivalent>|array<string, array<CriterionValueEquivalent>> $array
     */
    public static function createCriteriaFromArray(array $array): Criteria
    {
        $criteria = new Criteria();
        foreach ($array as $field => $criterion) {
            if ($criterion instanceof Filter) {
                $criteria->addFilter($criterion);
            } elseif (is_array($criterion)) {
                $criteria->addFilter(new EqualsAnyFilter($field, array_map(self::getCriterionValue(...), $criterion)));
            } else {
                $criteria->addFilter(new EqualsFilter($field, self::getCriterionValue($criterion)));
            }
        }

        return $criteria;
    }

    /**
     * @param CriterionValueEquivalent $criterion
     * @return CriterionValue
     */
    private static function getCriterionValue(mixed $criterion): mixed
    {
        if ($criterion instanceof BackedEnum) {
            return $criterion->value;
        }

        if (is_object($criterion) && method_exists($criterion, '__toString')) {
            return (string) $criterion;
        }

        return $criterion;
    }

    /**
     * Creates a new Criteria object with filter, sorting, limit and offset of the given criteria (i.e. associations and
     * other settings are ignored).
     */
    public static function sanitizeCriteria(Criteria $criteria): Criteria
    {
        $sanitizedCriteria = new Criteria();
        $sanitizedCriteria->addFilter(...$criteria->getFilters());
        $sanitizedCriteria->addSorting(...$criteria->getSorting());
        $sanitizedCriteria->setLimit($criteria->getLimit());
        $sanitizedCriteria->setOffset($criteria->getOffset());

        return $sanitizedCriteria;
    }
}
