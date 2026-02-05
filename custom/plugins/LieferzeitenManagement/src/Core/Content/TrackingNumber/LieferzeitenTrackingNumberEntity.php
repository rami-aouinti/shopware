<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\TrackingNumber;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageEntity;
use LieferzeitenManagement\Core\Content\TrackingEvent\LieferzeitenTrackingEventCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LieferzeitenTrackingNumberEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $packageId = null;

    protected ?string $trackingNumber = null;

    protected ?string $trackingProvider = null;

    protected ?bool $isActive = null;

    protected ?LieferzeitenPackageEntity $package = null;

    protected ?LieferzeitenTrackingEventCollection $events = null;

    public function getPackageId(): ?string
    {
        return $this->packageId;
    }

    public function setPackageId(?string $packageId): void
    {
        $this->packageId = $packageId;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getPackage(): ?LieferzeitenPackageEntity
    {
        return $this->package;
    }

    public function setPackage(?LieferzeitenPackageEntity $package): void
    {
        $this->package = $package;
    }

    public function getEvents(): ?LieferzeitenTrackingEventCollection
    {
        return $this->events;
    }

    public function setEvents(?LieferzeitenTrackingEventCollection $events): void
    {
        $this->events = $events;
    }
}
