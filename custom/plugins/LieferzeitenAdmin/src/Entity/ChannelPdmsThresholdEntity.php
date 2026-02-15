<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ChannelPdmsThresholdEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?string $pdmsLieferzeit = null;

    protected int $shippingOverdueWorkingDays = 0;

    protected int $deliveryOverdueWorkingDays = 0;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getPdmsLieferzeit(): ?string
    {
        return $this->pdmsLieferzeit;
    }

    public function setPdmsLieferzeit(?string $pdmsLieferzeit): void
    {
        $this->pdmsLieferzeit = $pdmsLieferzeit;
    }

    public function getShippingOverdueWorkingDays(): int
    {
        return $this->shippingOverdueWorkingDays;
    }

    public function setShippingOverdueWorkingDays(int $shippingOverdueWorkingDays): void
    {
        $this->shippingOverdueWorkingDays = $shippingOverdueWorkingDays;
    }

    public function getDeliveryOverdueWorkingDays(): int
    {
        return $this->deliveryOverdueWorkingDays;
    }

    public function setDeliveryOverdueWorkingDays(int $deliveryOverdueWorkingDays): void
    {
        $this->deliveryOverdueWorkingDays = $deliveryOverdueWorkingDays;
    }
}
