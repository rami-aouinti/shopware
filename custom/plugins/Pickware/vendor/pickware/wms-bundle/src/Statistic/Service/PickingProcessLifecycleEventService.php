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
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Pickware\PickwareWms\Warehouse\WarehouseExtension;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Pickware\ShopwareExtensionsBundle\User\UserExtension;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Clock\ClockAwareTrait;

class PickingProcessLifecycleEventService
{
    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly PickingProfileIdProvider $pickPickingProfileIdProvider,
        private readonly StaticAdminRoleProvider $adminLogRoleProvider,
    ) {}

    public function writePickingProcessLifecycleEvent(
        PickingProcessLifecycleEventType $pickingProcessLifecycleEventType,
        string $pickingProcessId,
        ?string $pickingProfileId,
        Context $context,
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($pickingProcessLifecycleEventType, $pickingProcessId, $pickingProfileId): void {
            /** @var PickingProcessEntity $pickingProcess */
            $pickingProcess = $this->entityManager->getByPrimaryKey(
                PickingProcessDefinition::class,
                $pickingProcessId,
                $context,
                ['warehouse'],
            );

            if (!$pickingProfileId) {
                $pickingProfileId = $this->pickPickingProfileIdProvider->findLastUsedPickingProfileIdForPickingProcess($pickingProcessId, $context);
            }

            $device = Device::tryGetFromContext($context);
            $userId = ContextExtension::findUserId($context);
            $user = null;
            if ($userId) {
                /** @var UserEntity $user */
                $user = $this->entityManager->getByPrimaryKey(
                    UserDefinition::class,
                    $userId,
                    $context,
                    ['aclRoles'],
                );
            }
            $aclRoleIds = $user?->getAclRoles()?->map(fn(AclRoleEntity $aclRole) => $aclRole->getId());
            $snapshots = $this->entitySnapshotService->generateSnapshotsForDifferentEntities(
                [
                    ...(($user !== null) ? [UserDefinition::class => [$user->getId()]] : []),
                    WarehouseDefinition::class => [$pickingProcess->getWarehouseId()],
                    PickingProcessDefinition::class => [$pickingProcess->getId()],
                    ...(($pickingProfileId !== null) ? [PickingProfileDefinition::class => [$pickingProfileId]] : []),
                    ...(($aclRoleIds !== null) ? [AclRoleDefinition::class => $aclRoleIds] : []),
                    ...(($device !== null) ? [DeviceDefinition::class => [$device->getId()]] : []),
                ],
                $context,
            );
            if ($user && UserExtension::isAdmin($user)) {
                $userRoles = [$this->adminLogRoleProvider->getAdminLogRole()];
            } else {
                $userRoles = $user?->getAclRoles()?->map(
                    fn(AclRoleEntity $aclRole): array => [
                        'userRoleReferenceId' => $aclRole->getId(),
                        'userRoleSnapshot' => $snapshots[AclRoleDefinition::class][$aclRole->getId()],
                    ],
                );
            }

            $eventCreatedAt = $this->now();
            $eventCreatedAtLocaltime = $eventCreatedAt->setTimezone(
                WarehouseExtension::getTimezone($pickingProcess->getWarehouse()),
            );

            $this->entityManager->create(
                PickingProcessLifecycleEventDefinition::class,
                [
                    [
                        'eventTechnicalName' => $pickingProcessLifecycleEventType->value,
                        'pickingProcessReferenceId' => $pickingProcess->getId(),
                        'pickingProcessSnapshot' => $snapshots[PickingProcessDefinition::class][$pickingProcess->getId()],
                        'userReferenceId' => $user?->getId(),
                        'userSnapshot' => $snapshots[UserDefinition::class][$user?->getId()] ?? null,
                        'warehouseReferenceId' => $pickingProcess->getWarehouseId(),
                        'warehouseSnapshot' => $snapshots[WarehouseDefinition::class][$pickingProcess->getWarehouseId()],
                        'pickingMode' => $pickingProcess->getPickingMode(),
                        'pickingProfileReferenceId' => $pickingProfileId,
                        'pickingProfileSnapshot' => $snapshots[PickingProfileDefinition::class][$pickingProfileId] ?? null,
                        'deviceReferenceId' => $device?->getId(),
                        'deviceSnapshot' => $snapshots[DeviceDefinition::class][$device?->getId()] ?? null,
                        'eventCreatedAt' => $eventCreatedAt,
                        'eventCreatedAtLocaltime' => $eventCreatedAtLocaltime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'eventCreatedAtLocaltimeTimezone' => $eventCreatedAtLocaltime->getTimezone()->getName(),
                        'userRoles' => $userRoles,
                    ],
                ],
                $context,
            );
        });
    }
}
