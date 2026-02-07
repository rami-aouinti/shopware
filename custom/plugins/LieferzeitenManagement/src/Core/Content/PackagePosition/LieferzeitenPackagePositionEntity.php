<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\PackagePosition;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionEntity;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LieferzeitenPackagePositionEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $packageId = null;

    protected ?string $orderPositionId = null;

    protected ?int $quantity = null;

    protected ?string $splitType = null;

    protected ?LieferzeitenPackageEntity $package = null;

    protected ?LieferzeitenOrderPositionEntity $orderPosition = null;

    public function getPackageId(): ?string
    {
        return $this->packageId;
    }

    public function setPackageId(?string $packageId): void
    {
        $this->packageId = $packageId;
    }

    public function getOrderPositionId(): ?string
    {
        return $this->orderPositionId;
    }

    public function setOrderPositionId(?string $orderPositionId): void
    {
        $this->orderPositionId = $orderPositionId;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getSplitType(): ?string
    {
        return $this->splitType;
    }

    public function setSplitType(?string $splitType): void
    {
        $this->splitType = $splitType;
    }

    public function getPackage(): ?LieferzeitenPackageEntity
    {
        return $this->package;
    }

    public function setPackage(?LieferzeitenPackageEntity $package): void
    {
        $this->package = $package;
    }

    public function getOrderPosition(): ?LieferzeitenOrderPositionEntity
    {
        return $this->orderPosition;
    }

    public function setOrderPosition(?LieferzeitenOrderPositionEntity $orderPosition): void
    {
        $this->orderPosition = $orderPosition;
    }
}
