<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NotificationToggleResolver
{
    public function __construct(private readonly EntityRepository $notificationToggleRepository)
    {
    }

    public function isEnabled(string $triggerKey, string $channel, Context $context, ?string $salesChannelId = null): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('triggerKey', $triggerKey));
        $criteria->addFilter(new EqualsFilter('channel', $channel));

        if ($salesChannelId !== null && $salesChannelId !== '') {
            $criteriaForSales = clone $criteria;
            $criteriaForSales->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            $match = $this->notificationToggleRepository->search($criteriaForSales, $context)->first();
            if ($match !== null) {
                return (bool) $match->get('enabled');
            }
        }

        $criteria->addFilter(new EqualsFilter('salesChannelId', null));
        $match = $this->notificationToggleRepository->search($criteria, $context)->first();

        return $match !== null ? (bool) $match->get('enabled') : true;
    }
}
