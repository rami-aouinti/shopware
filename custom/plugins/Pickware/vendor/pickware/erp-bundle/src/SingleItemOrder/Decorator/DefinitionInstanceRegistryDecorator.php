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
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderCalculator;
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
    public function __construct(
        #[AutowireDecorated]
        private readonly DefinitionInstanceRegistry $decoratedInstance,
        private readonly SingleItemOrderCalculator $openSingleItemOrderCalculator,
        private readonly OpenSingleItemOrderCriteriaFilterResolver $openSingleItemOrderCriteriaFilterResolver,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function getRepository(string $entityName): EntityRepository
    {
        return new EntityRepositoryDecorator(
            $this->decoratedInstance->getRepository($entityName),
            $this->openSingleItemOrderCalculator,
            $this->openSingleItemOrderCriteriaFilterResolver,
            $this->featureFlagService,
        );
    }

    /**
     * @return EntityDefinition<Entity>
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
     * @return ?EntityDefinition<Entity>
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
