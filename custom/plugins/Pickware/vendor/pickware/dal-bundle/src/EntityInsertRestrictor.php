<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Exclude]
class EntityInsertRestrictor implements EventSubscriberInterface
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_INSERT_RESTRICTOR';

    /**
     * @var string[]
     */
    private array $entityDefinitionEntityNames;

    /**
     * @deprecated Passing class names in entityDefinitionEntityNames is deprecated. Pass entity names instead. Will be removed in 6.0.0.
     */
    public function __construct(array $entityDefinitionEntityNames, private readonly array $allowedContextScopes)
    {
        // For backwards compatibility we still allow class names to be passed in the entityDefinitionEntityNames array
        // and convert them to entity names.
        $classNameCount = count(array_filter($entityDefinitionEntityNames, 'class_exists'));
        if ($classNameCount === count($entityDefinitionEntityNames)) {
            $this->entityDefinitionEntityNames = array_map(
                fn(string $entityName) => ($entityName::ENTITY_NAME),
                $entityDefinitionEntityNames,
            );
            trigger_error(
                'Passing class names in entityDefinitionEntityNames is deprecated. Pass entity names instead. Will be removed in 6.0.0.',
                E_USER_DEPRECATED,
            );
        } elseif ($classNameCount > 1) {
            throw new InvalidArgumentException(
                'Only class names or entity names are allowed in $entityDefinitionEntityNames, but a mix of both was ' .
                'provided.',
            );
        } else {
            $this->entityDefinitionEntityNames = $entityDefinitionEntityNames;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if (in_array($event->getContext()->getScope(), $this->allowedContextScopes, true)) {
            return;
        }

        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            $entityName = $command->getEntityName();
            if (
                !($command instanceof InsertCommand)
                || !in_array($entityName, $this->entityDefinitionEntityNames, true)
            ) {
                continue;
            }

            $message = sprintf('A %s cannot be inserted.', $entityName);
            $violations->add(new ConstraintViolation(
                $message,
                $message,
                [],
                null,
                '/',
                null,
                null,
                sprintf(
                    '%s__%s',
                    self::ERROR_CODE_NAMESPACE,
                    mb_strtoupper($entityName),
                ),
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
