<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\DateHistory;

use LieferzeitenManagement\Core\Content\OrderPosition\LieferzeitenOrderPositionEntity;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenDateHistoryEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderPositionId = null;

    protected ?string $packageId = null;

    protected ?string $type = null;

    protected ?\DateTimeInterface $rangeStart = null;

    protected ?\DateTimeInterface $rangeEnd = null;

    protected ?string $comment = null;

    protected ?string $createdById = null;

    protected ?LieferzeitenOrderPositionEntity $orderPosition = null;

    protected ?LieferzeitenPackageEntity $package = null;

    protected ?UserEntity $createdBy = null;

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

    public function getRangeStart(): ?\DateTimeInterface
    {
        return $this->rangeStart;
    }

    public function setRangeStart(?\DateTimeInterface $rangeStart): void
    {
        $this->rangeStart = $rangeStart;
    }

    public function getRangeEnd(): ?\DateTimeInterface
    {
        return $this->rangeEnd;
    }

    public function setRangeEnd(?\DateTimeInterface $rangeEnd): void
    {
        $this->rangeEnd = $rangeEnd;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
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
