<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document;

use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * This whole class is there just to ensure that a documents deep link code is exactly 32 characters long.
 */
class DocumentEntityWriteValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            EntityPreWriteValidationEventDispatcher::getEventName(DocumentDefinition::ENTITY_NAME) => 'onPreWriteValidation',
        ];
    }

    public function onPreWriteValidation($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        $documentCommands = array_values(array_filter(
            $event->getCommands(),
            fn(WriteCommand $command) => !($command instanceof DeleteCommand),
        ));

        $violations = $this->getDeepLinkCodeViolations($documentCommands);

        if ($violations->count() > 0) {
            $event->addViolation(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * Checks every value of the deepLinkCode properties of the $documentCommands. If they do not have the expected
     * length, an appropriate violation is returned.
     *
     * @param WriteCommand[] $documentCommands
     */
    private function getDeepLinkCodeViolations(array $documentCommands): ConstraintViolationList
    {
        $violationMessageTemplate = 'The length of the property "deepLinkCode" must be exactly {{ length }} characters.';
        $parameters = ['{{ length }}' => DocumentDefinition::DEEP_LINK_CODE_LENGTH];
        $violationMessage = strtr($violationMessageTemplate, $parameters);

        $violations = new ConstraintViolationList();
        foreach ($documentCommands as $documentCommand) {
            $payload = $documentCommand->getPayload();
            if ($documentCommand->getEntityExistence()->exists() && !isset($payload['deep_link_code'])) {
                // Update without a change of the deepLinkCode
                continue;
            }
            if (mb_strlen($payload['deep_link_code']) !== DocumentDefinition::DEEP_LINK_CODE_LENGTH) {
                $violations[] = new ConstraintViolation(
                    $violationMessage,
                    $violationMessageTemplate,
                    $parameters,
                    null, // ???
                    $documentCommand->getPath() . '/deepLinkCode',
                    $payload['deep_link_code'],
                );
            }
        }

        return $violations;
    }
}
