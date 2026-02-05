<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Notification;

use LieferzeitenManagement\Core\Content\NotificationSettings\LieferzeitenNotificationSettingsDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NotificationSettingsResolver
{
    /**
     * @param EntityRepository<LieferzeitenNotificationSettingsDefinition> $notificationSettingsRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationSettingsRepository,
    ) {
    }

    public function isEnabled(?string $salesChannelId, string $notificationKey, Context $context): bool
    {
        if (!$salesChannelId) {
            return false;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('notificationKey', $notificationKey));
        $criteria->setLimit(1);

        $setting = $this->notificationSettingsRepository->search($criteria, $context)->first();

        return $setting?->isEnabled() ?? false;
    }
}
