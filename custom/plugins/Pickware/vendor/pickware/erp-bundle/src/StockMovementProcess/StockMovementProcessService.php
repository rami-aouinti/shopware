<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcess;
use Pickware\PickwareErpStarter\StockMovementProcess\Model\StockMovementProcessDefinition;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;

class StockMovementProcessService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
    ) {}

    public function create(ImmutableCollection $stockMovementProcesses, Context $context): void
    {
        $userSnapshots = $this->entitySnapshotService->generateSnapshots(
            UserDefinition::class,
            $stockMovementProcesses
                ->map(fn(StockMovementProcess $stockMovementProcess) => $stockMovementProcess->getUserId())
                ->deduplicate()
                ->filter()
                ->asArray(),
            $context,
        );
        $referencedEntitySnapshots = $this->entitySnapshotService->generateSnapshotsForDifferentEntities(
            $stockMovementProcesses
                ->reduce([], function(array $carry, StockMovementProcess $stockMovementProcess) {
                    $carry[$stockMovementProcess->getType()->getReferencedEntityDefinitionClass()] ??= [];
                    $carry[$stockMovementProcess->getType()->getReferencedEntityDefinitionClass()][] = $stockMovementProcess->getReferencedEntityId();

                    return $carry;
                }),
            $context,
        );
        $stockMovementProcessesPayload = $stockMovementProcesses->map(fn(StockMovementProcess $stockMovementProcess) => [
            'id' => $stockMovementProcess->getId() ?? Uuid::randomHex(),
            'typeTechnicalName' => $stockMovementProcess->getType()->getTechnicalName(),
            'referencedEntitySnapshot' => $referencedEntitySnapshots[$stockMovementProcess->getType()->getReferencedEntityDefinitionClass()][$stockMovementProcess->getReferencedEntityId()] ?? null,
            'userId' => $stockMovementProcess->getUserId(),
            'userSnapshot' => $userSnapshots[$stockMovementProcess->getUserId()] ?? null,
            'stockMovements' => array_map(
                fn(string $id) => ['id' => $id],
                $stockMovementProcess->getStockMovementIds(),
            ),
            $stockMovementProcess->getType()->getReferencedEntityFieldName() => [
                ['id' => $stockMovementProcess->getReferencedEntityId()],
            ],
        ]);

        $context->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $context) => $this->entityManager->create(
                StockMovementProcessDefinition::class,
                $stockMovementProcessesPayload->asArray(),
                $context,
            ),
        );
    }
}
