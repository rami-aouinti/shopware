<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ChannelSettingsEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?string $defaultStatus = null;

    protected bool $enableNotifications = false;

    protected ?int $shippingWorkingDays = null;

    protected ?string $shippingCutoff = null;

    protected ?int $deliveryWorkingDays = null;

    protected ?string $deliveryCutoff = null;

    protected ?string $lastChangedBy = null;

    protected ?\DateTimeInterface $lastChangedAt = null;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getDefaultStatus(): ?string
    {
        return $this->defaultStatus;
    }

    public function setDefaultStatus(?string $defaultStatus): void
    {
        $this->defaultStatus = $defaultStatus;
    }

    public function isEnableNotifications(): bool
    {
        return $this->enableNotifications;
    }

    public function setEnableNotifications(bool $enableNotifications): void
    {
        $this->enableNotifications = $enableNotifications;
    }

    public function getLastChangedBy(): ?string
    {
        return $this->lastChangedBy;
    }

    public function setLastChangedBy(?string $lastChangedBy): void
    {
        $this->lastChangedBy = $lastChangedBy;
    }

    public function getLastChangedAt(): ?\DateTimeInterface
    {
        return $this->lastChangedAt;
    }

    public function setLastChangedAt(?\DateTimeInterface $lastChangedAt): void
    {
        $this->lastChangedAt = $lastChangedAt;
    }

    public function getShippingWorkingDays(): ?int
    {
        return $this->shippingWorkingDays;
    }

    public function setShippingWorkingDays(?int $shippingWorkingDays): void
    {
        $this->shippingWorkingDays = $shippingWorkingDays;
    }

    public function getShippingCutoff(): ?string
    {
        return $this->shippingCutoff;
    }

    public function setShippingCutoff(?string $shippingCutoff): void
    {
        $this->shippingCutoff = $shippingCutoff;
    }

    public function getDeliveryWorkingDays(): ?int
    {
        return $this->deliveryWorkingDays;
    }

    public function setDeliveryWorkingDays(?int $deliveryWorkingDays): void
    {
        $this->deliveryWorkingDays = $deliveryWorkingDays;
    }

    public function getDeliveryCutoff(): ?string
    {
        return $this->deliveryCutoff;
    }

    public function setDeliveryCutoff(?string $deliveryCutoff): void
    {
        $this->deliveryCutoff = $deliveryCutoff;
    }
}
