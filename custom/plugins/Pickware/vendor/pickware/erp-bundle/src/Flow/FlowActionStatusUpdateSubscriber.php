<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Flow;

use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FlowActionStatusUpdateSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [EntityWriteValidationEventType::Pre->getEventName(FlowSequenceDefinition::ENTITY_NAME) => 'validateStatusUpdateFlowAction'];
    }

    public function validateStatusUpdateFlowAction(EntityPreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== FlowSequenceDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            $flowSequenceConfig = Json::decodeToArray($payload['config'] ?? '{}');
            if (isset($flowSequenceConfig['force_transition']) && $flowSequenceConfig['force_transition'] !== true) {
                throw FlowWriteException::nonForceStatusUpdateInFlowActionNotAllowed($payload['id']);
            }
        }
    }
}
