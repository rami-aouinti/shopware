<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdditionalDeliveryAssigneeConfigValidationSubscriber implements EventSubscriberInterface
{
    private const CONFIG_KEYS = [
        'LieferzeitenAdmin.config.defaultAssigneeLieferterminAnfrageZusaetzlich',
        'LieferzeitenAdmin.config.defaultAdditionalDeliveryAssignee',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getDefinition()->getEntityName() !== 'system_config') {
                continue;
            }

            $payload = $command->getPayload();
            $configurationKey = $payload['configurationKey'] ?? $payload['configuration_key'] ?? null;
            if (!is_string($configurationKey) || !in_array($configurationKey, self::CONFIG_KEYS, true)) {
                continue;
            }

            $configurationValue = $payload['configurationValue'] ?? $payload['configuration_value'] ?? null;
            if (!is_array($configurationValue)) {
                throw new \InvalidArgumentException('The default additional delivery assignee must be provided as a non-empty string.');
            }

            $assignee = trim((string) ($configurationValue['_value'] ?? ''));
            if ($assignee === '') {
                throw new \InvalidArgumentException('The default additional delivery assignee must not be empty.');
            }
        }
    }
}
