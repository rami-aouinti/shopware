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

use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCalculator;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCriteriaFilterResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: DefinitionInstanceRegistry::class)]
class DefinitionInstanceRegistryDecorator extends DefinitionInstanceRegistry
{
    private DefinitionInstanceRegistry $decoratedInstance;
    private OrderPickabilityCalculator $orderPickabilityCalculator;
    private OrderPickabilityCriteriaFilterResolver $orderPickabilityCriteriaFilterResolver;

    public function __construct(
        #[AutowireDecorated]
        DefinitionInstanceRegistry $decoratedInstance,
        OrderPickabilityCalculator $orderPickabilityCalculator,
        OrderPickabilityCriteriaFilterResolver $orderPickabilityCriteriaFilterResolver,
    ) {
        $this->decoratedInstance = $decoratedInstance;
        $this->orderPickabilityCalculator = $orderPickabilityCalculator;
        $this->orderPickabilityCriteriaFilterResolver = $orderPickabilityCriteriaFilterResolver;
    }

    public function getRepository(string $entityName): EntityRepository
    {
        return new EntityRepositoryDecorator(
            $this->decoratedInstance->getRepository($entityName),
            $this->orderPickabilityCalculator,
            $this->orderPickabilityCriteriaFilterResolver,
        );
    }

    /**
     * @template PassedEntity of Entity
     * @param class-string<EntityDefinition<PassedEntity>> $class
     * @return EntityDefinition<PassedEntity>
     */
    public function get(string $class): EntityDefinition
    {
        return $this->decoratedInstance->get($class);
    }

    /**
     * @return EntityDefinition<Entity>
     */
    public function getByClassOrEntityName(string $key): EntityDefinition
    {
        return $this->decoratedInstance->getByClassOrEntityName($key);
    }

    public function has(string $name): bool
    {
        return $this->decoratedInstance->has($name);
    }

    /**
     * @return EntityDefinition<Entity>
     */
    public function getByEntityName(string $entityName): EntityDefinition
    {
        return $this->decoratedInstance->getByEntityName($entityName);
    }

    /**
     * @return array<string, EntityDefinition<Entity>>
     */
    public function getDefinitions(): array
    {
        return $this->decoratedInstance->getDefinitions();
    }

    public function getSerializer(string $serializerClass): FieldSerializerInterface
    {
        return $this->decoratedInstance->getSerializer($serializerClass);
    }

    public function getResolver(string $resolverClass)
    {
        return $this->decoratedInstance->getResolver($resolverClass);
    }

    public function getAccessorBuilder(string $accessorBuilderClass): FieldAccessorBuilderInterface
    {
        return $this->decoratedInstance->getAccessorBuilder($accessorBuilderClass);
    }

    /**
     * @template PassedEntity of Entity
     * @param PassedEntity $entity
     * @return ?EntityDefinition<PassedEntity>
     */
    public function getByEntityClass(Entity $entity): ?EntityDefinition
    {
        return $this->decoratedInstance->getByEntityClass($entity);
    }

    /**
     * @param EntityDefinition<Entity> $definition
     */
    public function register(EntityDefinition $definition, ?string $serviceId = null): void
    {
        $this->decoratedInstance->register($definition, $serviceId);
    }
}
