<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Task;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionEntity;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenTaskEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $orderPositionId = null;

    protected ?string $packageId = null;

    protected ?string $type = null;

    protected ?string $status = null;

    protected ?string $assignedUserId = null;

    protected ?\DateTimeInterface $dueDate = null;

    protected ?string $createdById = null;

    protected ?\DateTimeInterface $completedAt = null;

    protected ?OrderEntity $order = null;

    protected ?LieferzeitenOrderPositionEntity $orderPosition = null;

    protected ?LieferzeitenPackageEntity $package = null;

    protected ?UserEntity $assignedUser = null;

    protected ?UserEntity $createdBy = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderPositionId(): ?string
    {
        return $this->orderPositionId;
    }

    public function setOrderPositionId(?string $orderPositionId): void
    {
        $this->orderPositionId = $orderPositionId;
    }

    public function getPackageId(): ?string
    {
        return $this->packageId;
    }

    public function setPackageId(?string $packageId): void
    {
        $this->packageId = $packageId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getAssignedUserId(): ?string
    {
        return $this->assignedUserId;
    }

    public function setAssignedUserId(?string $assignedUserId): void
    {
        $this->assignedUserId = $assignedUserId;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderPosition(): ?LieferzeitenOrderPositionEntity
    {
        return $this->orderPosition;
    }

    public function setOrderPosition(?LieferzeitenOrderPositionEntity $orderPosition): void
    {
        $this->orderPosition = $orderPosition;
    }

    public function getPackage(): ?LieferzeitenPackageEntity
    {
        return $this->package;
    }

    public function setPackage(?LieferzeitenPackageEntity $package): void
    {
        $this->package = $package;
    }

    public function getAssignedUser(): ?UserEntity
    {
        return $this->assignedUser;
    }

    public function setAssignedUser(?UserEntity $assignedUser): void
    {
        $this->assignedUser = $assignedUser;
    }

    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
