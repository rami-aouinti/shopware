<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Customer;

use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerAlternativeEmailValidator implements EventSubscriberInterface
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [EntityWriteValidationEventType::Pre->getEventName(CustomerDefinition::ENTITY_NAME) => 'validateAlternativeEmail'];
    }

    public function validateAlternativeEmail(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            $payload = $command->getPayload();
            if (
                isset($payload[AlternativeEmailCustomFieldSet::CUSTOM_FIELD_ALTERNATIVE_EMAIL])
                && !empty($payload[AlternativeEmailCustomFieldSet::CUSTOM_FIELD_ALTERNATIVE_EMAIL])
            ) {
                /** @var string $alternativeEmail */
                $alternativeEmail = $payload[AlternativeEmailCustomFieldSet::CUSTOM_FIELD_ALTERNATIVE_EMAIL];
                if (!filter_var($alternativeEmail, FILTER_VALIDATE_EMAIL)) {
                    throw CustomerAlternativeEmailValidationException::alternativeEmailIsNotValid($alternativeEmail);
                }
            }
        }
    }
}
