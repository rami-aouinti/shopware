<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\Settings;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class LieferzeitenSettingsEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?string $area = null;

    protected ?int $latestShippingOffsetDays = null;

    protected ?int $latestDeliveryOffsetDays = null;

    protected ?string $cutoffTime = null;

    protected ?SalesChannelEntity $salesChannel = null;

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

    public function getLatestShippingOffsetDays(): ?int
    {
        return $this->latestShippingOffsetDays;
    }

    public function setLatestShippingOffsetDays(?int $latestShippingOffsetDays): void
    {
        $this->latestShippingOffsetDays = $latestShippingOffsetDays;
    }

    public function getLatestDeliveryOffsetDays(): ?int
    {
        return $this->latestDeliveryOffsetDays;
    }

    public function setLatestDeliveryOffsetDays(?int $latestDeliveryOffsetDays): void
    {
        $this->latestDeliveryOffsetDays = $latestDeliveryOffsetDays;
    }

    public function getCutoffTime(): ?string
    {
        return $this->cutoffTime;
    }

    public function setCutoffTime(?string $cutoffTime): void
    {
        $this->cutoffTime = $cutoffTime;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
