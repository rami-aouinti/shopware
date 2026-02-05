<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\ActivityLog;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionEntity;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenActivityLogEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $orderPositionId = null;

    protected ?string $packageId = null;

    protected ?string $action = null;

    protected ?array $payload = null;

    protected ?string $createdById = null;

    protected ?OrderEntity $order = null;

    protected ?LieferzeitenOrderPositionEntity $orderPosition = null;

    protected ?LieferzeitenPackageEntity $package = null;

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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
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

    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
