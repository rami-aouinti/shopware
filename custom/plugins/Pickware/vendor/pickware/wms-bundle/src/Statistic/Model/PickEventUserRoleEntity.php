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
class PickEventUserRoleEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickId;
    protected ?PickEventEntity $pick = null;
    protected string $userRoleReferenceId;

    /**
     * @var AclRoleSnapshot
     */
    protected array $userRoleSnapshot;

    public function getPickId(): string
    {
        return $this->pickId;
    }

    public function setPickId(string $pickId): void
    {
        if ($this->pick && $this->pick->getId() !== $pickId) {
            $this->pick = null;
        }
        $this->pickId = $pickId;
    }

    public function getPick(): PickEventEntity
    {
        if (!$this->pick) {
            throw new AssociationNotLoadedException('pick', $this);
        }

        return $this->pick;
    }

    public function setPick(PickEventEntity $pick): void
    {
        $this->pickId = $pick->getId();
        $this->pick = $pick;
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
