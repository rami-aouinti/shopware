<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Field;

use Generator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException as ShopwareDataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AutoconfigureTag('shopware.field_serializer')]
class EnumFieldSerializer implements FieldSerializerInterface
{
    private ValidatorInterface $validator;
    private DefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var Constraint[][]
     */
    private array $cachedConstraints = [];

    public function __construct(ValidatorInterface $validator, DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->validator = $validator;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters,
    ): Generator {
        if (!$field instanceof EnumField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(EnumField::class, $field);
        }

        if ($data->getValue() === '' && !$field->is(AllowEmptyString::class)) {
            $data->setValue(null);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $data->getValue() !== null ? (string) $data->getValue() : null;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value;
    }

    protected function validate(
        array $constraints,
        KeyValuePair $data,
        string $path,
    ): void {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator->validate($data->getValue(), $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $fieldName = $data->getKey();

                if ($violation->getPropertyPath()) {
                    $property = str_replace('][', '/', $violation->getPropertyPath());
                    $property = trim($property, '][');
                    $fieldName .= '/' . $property;
                }

                $fieldName = '/' . $fieldName;

                $violationList->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $fieldName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause(),
                    ),
                );
            }
        }

        if (\count($violationList)) {
            throw new WriteConstraintViolationException($violationList, $path);
        }
    }

    protected function requiresValidation(
        Field $field,
        EntityExistence $existence,
        $value,
        WriteParameterBag $parameters,
    ): bool {
        if ($value !== null) {
            return true;
        }

        if ($existence->isChild() && $this->isInherited($field, $parameters)) {
            return false;
        }

        if (
            $existence->hasEntityName()
            && $parameters->getCurrentWriteLanguageId() !== Defaults::LANGUAGE_SYSTEM
            && $this->definitionRegistry->getByEntityName($existence->getEntityName()) instanceof EntityTranslationDefinition
        ) {
            return false;
        }

        return $field->is(Required::class);
    }

    protected function isInherited(Field $field, WriteParameterBag $parameters): bool
    {
        if ($parameters->getDefinition()->isInheritanceAware()) {
            return $field->is(Inherited::class);
        }

        if (!$parameters->getDefinition() instanceof EntityTranslationDefinition) {
            return false;
        }

        $parent = $parameters->getDefinition()->getParentDefinition();

        $field = $parent->getFields()->get($field->getPropertyName());

        return $field->is(Inherited::class);
    }

    protected function validateIfNeeded(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): void
    {
        if (!$this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            return;
        }

        $constraints = $this->getCachedConstraints($field);

        $this->validate($constraints, $data, $parameters->getPath());
    }

    /**
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        if (!($field instanceof EnumField)) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(EnumField::class, $field);
        }

        $constraints = [
            new Type('string'),
            new Length(['max' => $field->getMaxLength()]),
        ];

        if (!$field->is(AllowEmptyString::class)) {
            $constraints[] = new NotBlank();
        }

        $constraints[] = new Choice([
            'multiple' => false,
            'choices' => $field->getAllowedValues(),
        ]);

        return $constraints;
    }

    /**
     * @return Constraint[]
     */
    protected function getCachedConstraints(Field $field): array
    {
        $key = $field->getPropertyName() . spl_object_id($field);

        if (!array_key_exists($key, $this->cachedConstraints)) {
            $this->cachedConstraints[$key] = $this->getConstraints($field);
        }

        return $this->cachedConstraints[$key];
    }
}
