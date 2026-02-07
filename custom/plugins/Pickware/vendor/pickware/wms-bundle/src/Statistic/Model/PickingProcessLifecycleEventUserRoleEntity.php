<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\AclRoleSnapshotGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @phpstan-import-type AclRoleSnapshot from AclRoleSnapshotGenerator
 */
class PickingProcessLifecycleEventUserRoleEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickingProcessLifecycleEventId;
    protected ?PickingProcessLifecycleEventEntity $pickingProcessLifecycleEvent = null;
    protected string $userRoleReferenceId;

    /**
     * @var AclRoleSnapshot
     */
    protected array $userRoleSnapshot;

    public function getPickingProcessLifecycleEventId(): string
    {
        return $this->pickingProcessLifecycleEventId;
    }

    public function setPickingProcessLifecycleEventId(string $pickingProcessLifecycleEventId): void
    {
        if ($this->pickingProcessLifecycleEvent && $this->pickingProcessLifecycleEvent->getId() !== $pickingProcessLifecycleEventId) {
            $this->pickingProcessLifecycleEvent = null;
        }
        $this->pickingProcessLifecycleEventId = $pickingProcessLifecycleEventId;
    }

    public function getPickingProcessLifecycleEvent(): PickingProcessLifecycleEventEntity
    {
        if (!$this->pickingProcessLifecycleEvent) {
            throw new AssociationNotLoadedException('pickingProcessLifecycleEvent', $this);
        }

        return $this->pickingProcessLifecycleEvent;
    }

    public function setPickingProcessLifecycleEvent(PickingProcessLifecycleEventEntity $pickingProcessLifecycleEvent): void
    {
        $this->pickingProcessLifecycleEventId = $pickingProcessLifecycleEvent->getId();
        $this->pickingProcessLifecycleEvent = $pickingProcessLifecycleEvent;
    }

    public function getUserRoleReferenceId(): string
    {
        return $this->userRoleReferenceId;
    }

    public function setUserRoleReferenceId(string $userRoleReferenceId): void
    {
        $this->userRoleReferenceId = $userRoleReferenceId;
    }

    /**
     * @return AclRoleSnapshot
     */
    public function getUserRoleSnapshot(): array
    {
        return $this->userRoleSnapshot;
    }

    /**
     * @param AclRoleSnapshot $userRoleSnapshot
     */
    public function setUserRoleSnapshot(array $userRoleSnapshot): void
    {
        $this->userRoleSnapshot = $userRoleSnapshot;
    }
}
