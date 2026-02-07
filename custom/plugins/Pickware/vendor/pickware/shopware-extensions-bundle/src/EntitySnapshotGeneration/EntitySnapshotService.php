<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Traversable;

class EntitySnapshotService
{
    /**
     * @param iterable<EntitySnapshotGenerator<array<string, mixed>>> $entitySnapshotGenerators
     */
    public function __construct(
        #[TaggedIterator(tag: AsEntitySnapshotGenerator::TAG_NAME, indexAttribute: 'entityClass')]
        private iterable $entitySnapshotGenerators,
    ) {}

    /**
     * @template PassedEntity of Entity
     * @param class-string<EntityDefinition<PassedEntity>> $entityDefinitionClassName
     * @param array<string> $ids IDs of the entities to generate snapshots for
     * @return array<string, array<string, mixed>> key is the entity ID, value is the snapshot data
     */
    public function generateSnapshots(string $entityDefinitionClassName, array $ids, Context $context): array
    {
        /** @var array<class-string<EntityDefinition<PassedEntity>>, EntitySnapshotGenerator<array<string, mixed>>> $entitySnapshotGenerators */
        $entitySnapshotGenerators = $this->entitySnapshotGenerators instanceof Traversable ? iterator_to_array($this->entitySnapshotGenerators) : $this->entitySnapshotGenerators;
        if (!isset($entitySnapshotGenerators[$entityDefinitionClassName])) {
            throw new RuntimeException(sprintf('No snapshot generator found for entity "%s".', $entityDefinitionClassName));
        }

        return $entitySnapshotGenerators[$entityDefinitionClassName]->generateSnapshots($ids, $context);
    }

    /**
     * @param array<string, array<string>> $idsByEntityDefinitionClassName key is the entity definition class name, value is the list of entity IDs to generate snapshots for
     * @return array<string, array<string, array<string, mixed>>> snapshot data, keyed by entity definition class name and entity id
     */
    public function generateSnapshotsForDifferentEntities(array $idsByEntityDefinitionClassName, Context $context): array
    {
        $snapshots = [];
        foreach ($idsByEntityDefinitionClassName as $entityClassName => $entityIds) {
            $snapshots[$entityClassName] = $this->generateSnapshots(
                $entityClassName,
                $entityIds,
                $context,
            );
        }

        return $snapshots;
    }
}
