<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Service;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\Statistic\Model\PickEventDefinition;
use Pickware\PickwareWms\Warehouse\WarehouseExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Pickware\ShopwareExtensionsBundle\User\UserExtension;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;

class PickStatisticService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly PickingProfileIdProvider $pickPickingProfileIdProvider,
        private readonly StaticAdminRoleProvider $adminLogRoleProvider,
        private readonly ClockInterface $clock,
    ) {}

    public function logPickEvent(
        string $productId,
        ?string $binLocationId,
        string $pickingProcessId,
        int $quantity,
        Context $context,
    ): void {
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            [
                'user.aclRoles',
                'warehouse',
            ],
        );

        $pickingProfileId = $this->pickPickingProfileIdProvider->findLastUsedPickingProfileIdForPickingProcess(
            $pickingProcessId,
            $context,
        );

        /** @var ProductEntity $product */
        $product = $this->entityManager->getByPrimaryKey(
            ProductDefinition::class,
            $productId,
            $context,
        );

        $userRoleIds = $pickingProcess->getUser()->getAclRoles()->map(fn(AclRoleEntity $aclRole) => $aclRole->getId());

        $requiredSnapshots = [
            ProductDefinition::class => [$productId],
            UserDefinition::class => [$pickingProcess->getUser()->getId()],
            WarehouseDefinition::class => [$pickingProcess->getWarehouse()->getId()],
            PickingProcessDefinition::class => [$pickingProcess->getId()],
            AclRoleDefinition::class => $userRoleIds,
        ];

        if ($pickingProfileId) {
            $requiredSnapshots[PickingProfileDefinition::class] = [$pickingProfileId];
        }
        if ($binLocationId) {
            $requiredSnapshots[BinLocationDefinition::class] = [$binLocationId];
        }

        $snapshots = $this->entitySnapshotService->generateSnapshotsForDifferentEntities(
            $requiredSnapshots,
            $context,
        );

        if (UserExtension::isAdmin($pickingProcess->getUser())) {
            $userRoles = [$this->adminLogRoleProvider->getAdminLogRole()];
        } else {
            $userRoles = $pickingProcess->getUser()->getAclRoles()->map(
                fn(AclRoleEntity $aclRole): array => [
                    'userRoleReferenceId' => $aclRole->getId(),
                    'userRoleSnapshot' => $snapshots[AclRoleDefinition::class][$aclRole->getId()],
                ],
            );
        }

        $pickCreatedAt = $this->clock->now();
        $pickCreatedAtLocaltime = $pickCreatedAt->setTimezone(
            WarehouseExtension::getTimezone($pickingProcess->getWarehouse()),
        );

        $this->entityManager->create(
            PickEventDefinition::class,
            [
                [
                    'productReferenceId' => $productId,
                    'productSnapshot' => $snapshots[ProductDefinition::class][$productId],
                    'productWeight' => $product->getWeight(),
                    'userReferenceId' => $pickingProcess->getUser()->getId(),
                    'userSnapshot' => $snapshots[UserDefinition::class][$pickingProcess->getUser()->getId()],
                    'warehouseReferenceId' => $pickingProcess->getWarehouse()->getId(),
                    'warehouseSnapshot' => $snapshots[WarehouseDefinition::class][$pickingProcess->getWarehouse()->getId()],
                    'binLocationReferenceId' => $binLocationId,
                    'binLocationSnapshot' => $snapshots[BinLocationDefinition::class][$binLocationId] ?? null,
                    'pickingProcessReferenceId' => $pickingProcess->getId(),
                    'pickingProcessSnapshot' => $snapshots[PickingProcessDefinition::class][$pickingProcess->getId()],
                    'pickingMode' => $pickingProcess->getPickingMode(),
                    'pickedQuantity' => $quantity,
                    'pickingProfileReferenceId' => $pickingProfileId,
                    'pickingProfileSnapshot' => $snapshots[PickingProfileDefinition::class][$pickingProfileId] ?? null,
                    'pickCreatedAt' => $pickCreatedAt,
                    'pickCreatedAtLocaltime' => $pickCreatedAtLocaltime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'pickCreatedAtLocaltimeTimezone' => $pickCreatedAtLocaltime->getTimezone()->getName(),
                    'userRoles' => $userRoles,
                ],
            ],
            $context,
        );
    }
}
