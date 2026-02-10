<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Subscriber;

use LieferzeitenAdmin\Entity\PaketDefinition;
use LieferzeitenAdmin\Service\PaketDeliveryDateRecalculationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryDateRecalculationSubscriber implements EventSubscriberInterface
{
    private bool $isRunning = false;

    public function __construct(private readonly PaketDeliveryDateRecalculationService $recalculationService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaketDefinition::ENTITY_NAME . '.written' => 'onPaketWritten',
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onPaketWritten(EntityWrittenEvent $event): void
    {
        if ($this->isRunning) {
            return;
        }

        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            if (
                !array_key_exists('order_date', $payload)
                && !array_key_exists('payment_date', $payload)
                && !array_key_exists('payment_method', $payload)
                && !array_key_exists('source_system', $payload)
            ) {
                continue;
            }

            $ids[] = $result->getPrimaryKey()['id'] ?? null;
        }

        $ids = array_values(array_filter($ids, static fn ($id): bool => is_string($id) && $id !== ''));
        if ($ids === []) {
            return;
        }

        $this->runGuarded(fn () => $this->recalculationService->recalculateByIds($ids, $event->getContext()));
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($this->isRunning) {
            return;
        }

        $key = (string) $event->getKey();
        $context = Context::createDefaultContext();

        if ($key === 'LieferzeitenAdmin.config.shopwareDateSettings') {
            $this->runGuarded(fn () => $this->recalculationService->recalculateForSourceSystem('shopware', $context));
        }

        if ($key === 'LieferzeitenAdmin.config.gambioDateSettings') {
            $this->runGuarded(fn () => $this->recalculationService->recalculateForSourceSystem('gambio', $context));
        }
    }

    private function runGuarded(callable $callback): void
    {
        $this->isRunning = true;
        try {
            $callback();
        } finally {
            $this->isRunning = false;
        }
    }
}
