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
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventEntity;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventEntity;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Pickware\PickwareWms\Warehouse\WarehouseExtension;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Pickware\ShopwareExtensionsBundle\User\UserExtension;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class DeliveryLifecycleEventService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly PickingProfileIdProvider $pickingProfileIdProvider,
        private readonly StaticAdminRoleProvider $adminLogRoleProvider,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @param array<string> $deliveryIds
     */
    public function writeDeliveryLifecycleEvents(
        DeliveryLifecycleEventType $deliveryLifecycleEventType,
        array $deliveryIds,
        Context $context,
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($deliveryLifecycleEventType, $deliveryIds): void {
            /** @var EntityCollection<DeliveryEntity> $deliveries */
            $deliveries = $this->entityManager->findBy(
                DeliveryDefinition::class,
                ['id' => $deliveryIds],
                $context,
                [
                    'order.salesChannel',
                    'pickingProcess.warehouse',
                ],
            );

            $pickingProcessIds = ImmutableCollection::create($deliveries)
                ->map(fn(DeliveryEntity $delivery) => $delivery->getPickingProcessId())
                ->deduplicate();
            /** @var array<string, string> $pickingProfileIdsByPickingProcessId */
            $pickingProfileIdsByPickingProcessId = array_combine(
                $pickingProcessIds->asArray(),
                $pickingProcessIds
                    ->map(fn(string $pickingProcessId) => $this->pickingProfileIdProvider->findLastUsedPickingProfileIdForPickingProcess($pickingProcessId, $context))
                    ->asArray(),
            );

            $device = Device::tryGetFromContext($context);
            $userId = ContextExtension::getUserId($context);
            /** @var UserEntity $user */
            $user = $this->entityManager->getByPrimaryKey(
                UserDefinition::class,
                $userId,
                $context,
                ['aclRoles'],
            );
            $aclRoleIds = $user->getAclRoles()->map(fn(AclRoleEntity $aclRole) => $aclRole->getId());

            $snapshotEntityIds = [
                UserDefinition::class => [$user->getId()],
                AclRoleDefinition::class => $aclRoleIds,
                DeviceDefinition::class => ($device !== null) ? [$device->getId()] : [],
                PickingProfileDefinition::class => array_filter(array_unique(array_values($pickingProfileIdsByPickingProcessId))),
                SalesChannelDefinition::class => [],
            ];
            foreach ($deliveries as $delivery) {
                $pickingProcess = $delivery->getPickingProcess();
                $snapshotEntityIds[WarehouseDefinition::class][] = $pickingProcess->getWarehouseId();
                $snapshotEntityIds[PickingProcessDefinition::class][] = $pickingProcess->getId();
                $snapshotEntityIds[OrderDefinition::class][] = $delivery->getOrderId();
                $snapshotEntityIds[SalesChannelDefinition::class][] = $delivery->getOrder()->getSalesChannelId();
            }
            $snapshots = $this->entitySnapshotService->generateSnapshotsForDifferentEntities(
                $snapshotEntityIds,
                $context,
            );

            if (UserExtension::isAdmin($user)) {
                $userRoles = [$this->adminLogRoleProvider->getAdminLogRole()];
            } else {
                $userRoles = $user->getAclRoles()->map(
                    fn(AclRoleEntity $aclRole): array => [
                        'userRoleReferenceId' => $aclRole->getId(),
                        'userRoleSnapshot' => $snapshots[AclRoleDefinition::class][$aclRole->getId()],
                    ],
                );
            }

            $eventsData = [];
            $eventCreatedAt = $this->clock->now();
            foreach ($deliveries as $delivery) {
                $pickingProcess = $delivery->getPickingProcess();
                $pickingProfileId = $pickingProfileIdsByPickingProcessId[$pickingProcess->getId()] ?? null;
                $salesChannelId = $delivery->getOrder()->getSalesChannelId();

                $eventCreatedAtLocaltime = $eventCreatedAt->setTimezone(
                    WarehouseExtension::getTimezone($pickingProcess->getWarehouse()),
                );

                $eventsData[] = [
                    'eventTechnicalName' => $deliveryLifecycleEventType->value,
                    'deliveryReferenceId' => $delivery->getId(),
                    'orderReferenceId' => $delivery->getOrder()->getId(),
                    'orderVersionId' => $delivery->getOrder()->getVersionId(),
                    'orderSnapshot' => $snapshots[OrderDefinition::class][$delivery->getOrderId()],
                    'salesChannelReferenceId' => $salesChannelId,
                    'salesChannelSnapshot' => $snapshots[SalesChannelDefinition::class][$salesChannelId],
                    'pickingProcessReferenceId' => $pickingProcess->getId(),
                    'pickingProcessSnapshot' => $snapshots[PickingProcessDefinition::class][$pickingProcess->getId()],
                    'userReferenceId' => $user->getId(),
                    'userSnapshot' => $snapshots[UserDefinition::class][$user->getId()],
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
                ];
            }

            $this->entityManager->create(
                DeliveryLifecycleEventDefinition::class,
                $eventsData,
                $context,
            );
        });
    }

    public function patchDeliveryEventWithPickingProcessCompleteEvent(
        string $pickingProcessId,
        string $deliveryId,
        DeliveryLifecycleEventType $eventType,
        Context $context,
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function(Context $systemContext) use ($pickingProcessId, $deliveryId, $eventType): void {
            /** @var ?PickingProcessLifecycleEventEntity $completeEventForPickingProcess */
            $completeEventForPickingProcess = $this->entityManager->findOneBy(
                PickingProcessLifecycleEventDefinition::class,
                [
                    'pickingProcessReferenceId' => $pickingProcessId,
                    'eventTechnicalName' => PickingProcessLifecycleEventType::Complete,
                ],
                $systemContext,
            );
            if (!$completeEventForPickingProcess) {
                return;
            }

            /** @var DeliveryLifecycleEventEntity $deliveryLifecycleEvent */
            $deliveryLifecycleEvent = $this->entityManager->getOneBy(
                DeliveryLifecycleEventDefinition::class,
                [
                    'deliveryReferenceId' => $deliveryId,
                    'eventTechnicalName' => $eventType,
                ],
                $systemContext,
            );

            $this->entityManager->update(
                DeliveryLifecycleEventDefinition::class,
                [
                    [
                        'id' => $deliveryLifecycleEvent->getId(),
                        'eventCreatedAt' => $completeEventForPickingProcess->getEventCreatedAt(),
                        'eventCreatedAtLocaltime' => $completeEventForPickingProcess->getEventCreatedAtLocaltime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'eventCreatedAtLocaltimeTimezone' => $completeEventForPickingProcess->getEventCreatedAtLocaltime()->getTimezone()->getName(),
                        'userReferenceId' => $completeEventForPickingProcess->getUserReferenceId(),
                        'userSnapshot' => $completeEventForPickingProcess->getUserSnapshot(),
                        'deviceReferenceId' => $completeEventForPickingProcess->getDeviceReferenceId(),
                        'deviceSnapshot' => $completeEventForPickingProcess->getDeviceSnapshot(),
                    ],
                ],
                $systemContext,
            );
        });
    }
}
