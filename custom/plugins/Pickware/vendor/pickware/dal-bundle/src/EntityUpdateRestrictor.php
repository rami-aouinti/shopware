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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Exclude]
class EntityUpdateRestrictor implements EventSubscriberInterface
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_DAL_BUNDLE__ENTITY_UPDATE_RESTRICTOR';
    private const IGNORED_UPDATE_FIELDS = [
        'updated_at',
    ];

    /**
     * @var string[]
     */
    private array $entityDefinitionEntityNames;

    /**
     * @var string[][]
     */
    private array $updateAllowedFieldsByEntityName;

    /**
     * @deprecated Passing class names in entityDefinitionEntityNames is deprecated. Pass entity names instead. Will be removed in 6.0.0.
     * @param string[] $entityDefinitionEntityNames
     * @param string[][] $updateAllowedFieldsByEntityName field names must be the storage name (i.e. database column name
     * in snake_case). Can be left empty per entity to restrict all updates on this entity.
     * @param string[] $allowedContextScopes
     */
    public function __construct(
        array $entityDefinitionEntityNames,
        array $updateAllowedFieldsByEntityName,
        private readonly array $allowedContextScopes,
    ) {
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

        // For backwards compatibility we still allow class names to be passed as Keys in updateAllowedFieldsByEntityName
        // and convert them to entity names.
        $this->updateAllowedFieldsByEntityName = [];
        foreach ($updateAllowedFieldsByEntityName as $entityName => $allowedFields) {
            if (class_exists($entityName)) {
                $entityName = $entityName::ENTITY_NAME;
            }
            $this->updateAllowedFieldsByEntityName[$entityName] = $allowedFields;
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
                !($command instanceof UpdateCommand)
                || !in_array($entityName, $this->entityDefinitionEntityNames, true)
            ) {
                continue;
            }

            $updatedAllowedFields = [];
            if (array_key_exists($entityName, $this->updateAllowedFieldsByEntityName)) {
                $updatedAllowedFields = $this->updateAllowedFieldsByEntityName[$entityName];
            }

            if (count($updatedAllowedFields) === 0) {
                // No fields are allowed. The entity is generally updated-restricted.
                $message = sprintf('A %s cannot be updated.', $entityName);
                $errorCode = sprintf(
                    '%s__UPDATE__%s',
                    self::ERROR_CODE_NAMESPACE,
                    mb_strtoupper($entityName),
                );
                $violations->add(new ConstraintViolation(
                    $message,
                    $message,
                    ['entity' => $entityName],
                    null,
                    '/',
                    null,
                    null,
                    $errorCode,
                ));

                continue;
            }

            $prohibitedUpdatedFields = [];
            foreach ($command->getPayload() as $key => $value) {
                if (
                    !in_array($key, self::IGNORED_UPDATE_FIELDS, true)
                    && !in_array($key, $updatedAllowedFields, true)
                ) {
                    $prohibitedUpdatedFields[] = $key;
                }
            }
            if (count($prohibitedUpdatedFields) === 0) {
                continue;
            }

            $message = sprintf(
                'Only the following fields of %s are allowed to be updated: %s. Update-prohibited fields found: %s.',
                $entityName,
                implode(', ', $updatedAllowedFields),
                implode(', ', array_unique($prohibitedUpdatedFields)),
            );
            $errorCode = sprintf(
                '%s__PARTIAL_UPDATE__%s',
                self::ERROR_CODE_NAMESPACE,
                mb_strtoupper($entityName),
            );
            $violations->add(new ConstraintViolation(
                $message,
                $message,
                [
                    'allowedFields' => $updatedAllowedFields,
                    'notAllowedFields' => $prohibitedUpdatedFields,
                ],
                null,
                '/',
                null,
                null,
                $errorCode,
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
