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
class DeliveryLifecycleEventUserRoleEntity extends Entity
{
    use EntityIdTrait;

    protected string $deliveryLifecycleEventId;
    protected ?DeliveryLifecycleEventEntity $deliveryLifecycleEvent = null;
    protected string $userRoleReferenceId;

    /**
     * @var AclRoleSnapshot
     */
    protected array $userRoleSnapshot;

    public function getDeliveryLifecycleEventId(): string
    {
        return $this->deliveryLifecycleEventId;
    }

    public function setDeliveryLifecycleEventId(string $deliveryLifecycleEventId): void
    {
        if ($this->deliveryLifecycleEvent && $this->deliveryLifecycleEvent->getId() !== $deliveryLifecycleEventId) {
            $this->deliveryLifecycleEvent = null;
        }
        $this->deliveryLifecycleEventId = $deliveryLifecycleEventId;
    }

    public function getDeliveryLifecycleEvent(): DeliveryLifecycleEventEntity
    {
        if (!$this->deliveryLifecycleEvent) {
            throw new AssociationNotLoadedException('deliveryLifecycleEvent', $this);
        }

        return $this->deliveryLifecycleEvent;
    }

    public function setDeliveryLifecycleEvent(DeliveryLifecycleEventEntity $deliveryLifecycleEvent): void
    {
        $this->deliveryLifecycleEventId = $deliveryLifecycleEvent->getId();
        $this->deliveryLifecycleEvent = $deliveryLifecycleEvent;
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
