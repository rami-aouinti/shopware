<?php declare(strict_types=1);

namespace LieferzeitenManagement\Core\Content\NotificationSettings;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class LieferzeitenNotificationSettingsEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?string $notificationKey = null;

    protected ?bool $enabled = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getNotificationKey(): ?string
    {
        return $this->notificationKey;
    }

    public function setNotificationKey(?string $notificationKey): void
    {
        $this->notificationKey = $notificationKey;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): void
    {
        $this->enabled = $enabled;
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
