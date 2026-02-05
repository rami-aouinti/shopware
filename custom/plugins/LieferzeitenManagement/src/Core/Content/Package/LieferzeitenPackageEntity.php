<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Package;

use LieferzeitenManagement\Core\Content\DateHistory\LieferzeitenDateHistoryCollection;
use LieferzeitenManagement\Core\Content\PackagePosition\LieferzeitenPackagePositionCollection;
use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class LieferzeitenPackageEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $san6PackageNumber = null;

    protected ?string $packageStatus = null;

    protected ?\DateTimeInterface $latestShippingAt = null;

    protected ?\DateTimeInterface $latestDeliveryAt = null;

    protected ?\DateTimeInterface $shippedAt = null;

    protected ?\DateTimeInterface $deliveredAt = null;

    protected ?string $trackingNumber = null;

    protected ?string $trackingProvider = null;

    protected ?string $trackingStatus = null;

    protected ?\DateTimeInterface $newDeliveryStart = null;

    protected ?\DateTimeInterface $newDeliveryEnd = null;

    protected ?string $newDeliveryComment = null;

    protected ?string $newDeliveryUpdatedById = null;

    protected ?\DateTimeInterface $newDeliveryUpdatedAt = null;

    protected ?OrderEntity $order = null;

    protected ?UserEntity $newDeliveryUpdatedBy = null;

    protected ?LieferzeitenPackagePositionCollection $packagePositions = null;

    protected ?LieferzeitenDateHistoryCollection $dateHistories = null;

    protected ?LieferzeitenTrackingNumberCollection $trackingNumbers = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getSan6PackageNumber(): ?string
    {
        return $this->san6PackageNumber;
    }

    public function setSan6PackageNumber(?string $san6PackageNumber): void
    {
        $this->san6PackageNumber = $san6PackageNumber;
    }

    public function getPackageStatus(): ?string
    {
        return $this->packageStatus;
    }

    public function setPackageStatus(?string $packageStatus): void
    {
        $this->packageStatus = $packageStatus;
    }

    public function getLatestShippingAt(): ?\DateTimeInterface
    {
        return $this->latestShippingAt;
    }

    public function setLatestShippingAt(?\DateTimeInterface $latestShippingAt): void
    {
        $this->latestShippingAt = $latestShippingAt;
    }

    public function getLatestDeliveryAt(): ?\DateTimeInterface
    {
        return $this->latestDeliveryAt;
    }

    public function setLatestDeliveryAt(?\DateTimeInterface $latestDeliveryAt): void
    {
        $this->latestDeliveryAt = $latestDeliveryAt;
    }

    public function getShippedAt(): ?\DateTimeInterface
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeInterface $shippedAt): void
    {
        $this->shippedAt = $shippedAt;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): void
    {
        $this->deliveredAt = $deliveredAt;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getTrackingProvider(): ?string
    {
        return $this->trackingProvider;
    }

    public function setTrackingProvider(?string $trackingProvider): void
    {
        $this->trackingProvider = $trackingProvider;
    }

    public function getTrackingStatus(): ?string
    {
        return $this->trackingStatus;
    }

    public function setTrackingStatus(?string $trackingStatus): void
    {
        $this->trackingStatus = $trackingStatus;
    }

    public function getNewDeliveryStart(): ?\DateTimeInterface
    {
        return $this->newDeliveryStart;
    }

    public function setNewDeliveryStart(?\DateTimeInterface $newDeliveryStart): void
    {
        $this->newDeliveryStart = $newDeliveryStart;
    }

    public function getNewDeliveryEnd(): ?\DateTimeInterface
    {
        return $this->newDeliveryEnd;
    }

    public function setNewDeliveryEnd(?\DateTimeInterface $newDeliveryEnd): void
    {
        $this->newDeliveryEnd = $newDeliveryEnd;
    }

    public function getNewDeliveryComment(): ?string
    {
        return $this->newDeliveryComment;
    }

    public function setNewDeliveryComment(?string $newDeliveryComment): void
    {
        $this->newDeliveryComment = $newDeliveryComment;
    }

    public function getNewDeliveryUpdatedById(): ?string
    {
        return $this->newDeliveryUpdatedById;
    }

    public function setNewDeliveryUpdatedById(?string $newDeliveryUpdatedById): void
    {
        $this->newDeliveryUpdatedById = $newDeliveryUpdatedById;
    }

    public function getNewDeliveryUpdatedAt(): ?\DateTimeInterface
    {
        return $this->newDeliveryUpdatedAt;
    }

    public function setNewDeliveryUpdatedAt(?\DateTimeInterface $newDeliveryUpdatedAt): void
    {
        $this->newDeliveryUpdatedAt = $newDeliveryUpdatedAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getNewDeliveryUpdatedBy(): ?UserEntity
    {
        return $this->newDeliveryUpdatedBy;
    }

    public function setNewDeliveryUpdatedBy(?UserEntity $newDeliveryUpdatedBy): void
    {
        $this->newDeliveryUpdatedBy = $newDeliveryUpdatedBy;
    }

    public function getPackagePositions(): ?LieferzeitenPackagePositionCollection
    {
        return $this->packagePositions;
    }

    public function setPackagePositions(?LieferzeitenPackagePositionCollection $packagePositions): void
    {
        $this->packagePositions = $packagePositions;
    }

    public function getDateHistories(): ?LieferzeitenDateHistoryCollection
    {
        return $this->dateHistories;
    }

    public function setDateHistories(?LieferzeitenDateHistoryCollection $dateHistories): void
    {
        $this->dateHistories = $dateHistories;
    }

    public function getTrackingNumbers(): ?LieferzeitenTrackingNumberCollection
    {
        return $this->trackingNumbers;
    }

    public function setTrackingNumbers(?LieferzeitenTrackingNumberCollection $trackingNumbers): void
    {
        $this->trackingNumbers = $trackingNumbers;
    }
}
