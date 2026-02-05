<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TaskAssignment;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenTaskAssignmentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?string $area = null;

    protected ?string $taskType = null;

    protected ?string $assignedUserId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?UserEntity $assignedUser = null;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): void
    {
        $this->area = $area;
    }

    public function getTaskType(): ?string
    {
        return $this->taskType;
    }

    public function setTaskType(?string $taskType): void
    {
        $this->taskType = $taskType;
    }

    public function getAssignedUserId(): ?string
    {
        return $this->assignedUserId;
    }

    public function setAssignedUserId(?string $assignedUserId): void
    {
        $this->assignedUserId = $assignedUserId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getAssignedUser(): ?UserEntity
    {
        return $this->assignedUser;
    }

    public function setAssignedUser(?UserEntity $assignedUser): void
    {
        $this->assignedUser = $assignedUser;
    }
}
