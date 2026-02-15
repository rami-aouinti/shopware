<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChannelPdmsThresholdValidationSubscriber implements EventSubscriberInterface
{
    /**
     * @var string[]
     */
    private const VALID_PDMS_KEYS = [
        'PDMS_1',
        'PDMS_2',
        'PDMS_3',
        'PDMS_4',
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
            if ($command->getDefinition()->getEntityName() !== 'lieferzeiten_channel_pdms_threshold') {
                continue;
            }

            $payload = $command->getPayload();

            $this->validatePdmsLieferzeit($payload);
            $this->validateNonNegativeInt($payload, 'shipping_overdue_working_days');
            $this->validateNonNegativeInt($payload, 'delivery_overdue_working_days');
        }
    }

    private function validatePdmsLieferzeit(array $payload): void
    {
        if (!array_key_exists('pdms_lieferzeit', $payload)) {
            return;
        }

        $value = $payload['pdms_lieferzeit'];
        if (!is_string($value) || !in_array($value, self::VALID_PDMS_KEYS, true)) {
            throw new \InvalidArgumentException('Invalid pdms_lieferzeit value. Allowed values: PDMS_1, PDMS_2, PDMS_3, PDMS_4.');
        }
    }

    private function validateNonNegativeInt(array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException(sprintf('Field "%s" must be an integer greater than or equal to 0.', $field));
        }
    }
}

