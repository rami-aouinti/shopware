<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\PaketEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class VorkassePaymentReminderService
{
    public function __construct(
        private readonly EntityRepository $paketRepository,
        private readonly NotificationEventService $notificationEventService,
    ) {
    }

    public function run(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentDate', null));
        $criteria->addFilter(new EqualsAnyFilter('paymentMethod', ['vorkasse', 'prepayment']));

        /** @var iterable<PaketEntity> $orders */
        $orders = $this->paketRepository->search($criteria, $context)->getEntities();

        $now = new \DateTimeImmutable();

        foreach ($orders as $order) {
            $status = mb_strtolower((string) $order->getStatus());
            if (str_contains($status, 'cancel')) {
                continue;
            }

            $baseDate = $order->getOrderDate() ?? $order->getCreatedAt();
            if ($baseDate === null) {
                continue;
            }

            $elapsedDays = (int) floor(($now->getTimestamp() - $baseDate->getTimestamp()) / 86400);
            if ($elapsedDays < 5) {
                continue;
            }

            if ($elapsedDays % 5 !== 0) {
                continue;
            }

            $reminderNo = (int) floor($elapsedDays / 5);
            $eventKey = sprintf('vorkasse:%s:%d', $order->getExternalOrderId() ?? $order->getPaketNumber(), $reminderNo);

            $payload = [
                'reminderNo' => $reminderNo,
                'elapsedDays' => $elapsedDays,
                'externalOrderId' => $order->getExternalOrderId(),
                'paketNumber' => $order->getPaketNumber(),
                'customerEmail' => $order->getCustomerEmail(),
            ];

            foreach (NotificationTriggerCatalog::channels() as $channel) {
                $this->notificationEventService->dispatch(
                    $eventKey . ':' . $channel,
                    NotificationTriggerCatalog::PAYMENT_REMINDER_VORKASSE,
                    $channel,
                    $payload,
                    $context,
                    $order->getExternalOrderId(),
                    $order->getSourceSystem(),
                );
            }
        }
    }
}
