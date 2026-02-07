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

use DateTimeInterface;
use Generator;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException as ShopwareDataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\DataStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AutoconfigureTag('shopware.field_serializer')]
class JsonSerializableObjectFieldSerializer implements FieldSerializerInterface
{
    private array $cachedConstraints = [];

    public function __construct(
        protected ValidatorInterface $validator,
        protected DefinitionInstanceRegistry $definitionRegistry,
    ) {}

    public function decode(Field $field, mixed $value): mixed
    {
        if (!($field instanceof JsonSerializableObjectField)) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(JsonSerializableObjectField::class, $field);
        }

        if ($value === null) {
            return null;
        }

        $value = $this->jsonFieldSerializerDecode($field, $value);

        return $field->getDeserializer()($value);
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): Generator
    {
        if (!$field instanceof JsonSerializableObjectField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(JsonField::class, $field);
        }

        if (!is_a($data->getValue(), $field->getClass())) {
            // In case the passed value isn't an instance of the expected class, it is assumed that the field value was
            // passed as encoded value (e.g. when it comes from the API). The decoding from encoded value to
            // object is then done on the fly here.
            $value = $data->getValue() ?? $field->getDefault();
            $data = new KeyValuePair(
                key: $data->getKey(),
                value: doIf($value, $field->getDeserializer()(...)),
                isRaw: $data->isRaw(),
            );
        }

        yield from $this->jsonFieldSerializerEncode($field, $existence, $data, $parameters);
    }

    private function getConstraints(Field $field): array
    {
        if (!($field instanceof JsonSerializableObjectField)) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(JsonSerializableObjectField::class, $field);
        }

        return [
            new NotNull(),
        ];
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public static function encodeJson($value): string
    {
        return Json::stringify($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * To avoid using another class extension, the following methods are copied from Shopware's JsonFieldSerializer:
     * https://github.com/shopware/shopware/blob/v6.4.15.0/src/Core/Framework/DataAbstractionLayer/FieldSerializer/JsonFieldSerializer.php
     */
    public function jsonFieldSerializerEncode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters,
    ): Generator {
        if (!$field instanceof JsonField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(JsonField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue() ?? $field->getDefault();

        if ($value !== null && !empty($field->getPropertyMapping())) {
            $value = $this->validateMapping($field, $value, $parameters);
        }

        if ($value !== null) {
            $value = self::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function jsonFieldSerializerDecode(Field $field, $value)
    {
        if (!$field instanceof JsonField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(JsonField::class, $field);
        }

        if ($value === null) {
            return $field->getDefault();
        }

        $raw = Json::decodeToArray($value);
        $decoded = $raw;
        if (empty($field->getPropertyMapping())) {
            return $raw;
        }

        foreach ($field->getPropertyMapping() as $embedded) {
            $key = $embedded->getPropertyName();
            if (!isset($raw[$key])) {
                continue;
            }
            $value = $embedded instanceof JsonField ? self::encodeJson($raw[$key]) : $raw[$key];

            $embedded->compile($this->definitionRegistry);
            $decodedValue = $embedded->getSerializer()->decode($embedded, $value);
            if ($decodedValue instanceof DateTimeInterface) {
                $format = $embedded instanceof DateField ? Defaults::STORAGE_DATE_FORMAT : \DATE_ATOM;
                $decodedValue = $decodedValue->format($format);
            }

            $decoded[$key] = $decodedValue;
        }

        return $decoded;
    }

    private function validateMapping(JsonField $field, array $data, WriteParameterBag $parameters): array
    {
        if (\array_key_exists('_class', $data)) {
            unset($data['_class']);
        }

        $stack = new DataStack($data);
        $existence = new EntityExistence(null, [], false, false, false, []);
        $fieldPath = $parameters->getPath() . '/' . $field->getPropertyName();

        $propertyKeys = array_map(fn(Field $field) => $field->getPropertyName(), $field->getPropertyMapping());

        // If a mapping is defined, you should not send properties that are undefined.
        // Sending undefined fields will throw an UnexpectedFieldException
        $keyDiff = array_diff(array_keys($data), $propertyKeys);
        if (\count($keyDiff)) {
            foreach ($keyDiff as $fieldName) {
                $parameters->getContext()->getExceptions()->add(
                    new UnexpectedFieldException($fieldPath . '/' . $fieldName, (string) $fieldName),
                );
            }
        }

        foreach ($field->getPropertyMapping() as $nestedField) {
            $kvPair = $stack->pop($nestedField->getPropertyName());

            if ($kvPair === null) {
                // The writer updates the whole field, so there is no possibility to update
                // "some" fields. To enable a merge, we have to respect the $existence state
                // for correct constraint validation. In addition the writer has to be rewritten
                // in order to handle merges.
                if (!$nestedField->is(Required::class)) {
                    continue;
                }

                $kvPair = new KeyValuePair($nestedField->getPropertyName(), null, true);
            }

            $nestedParams = new WriteParameterBag(
                $parameters->getDefinition(),
                $parameters->getContext(),
                $parameters->getPath() . '/' . $field->getPropertyName(),
                $parameters->getCommandQueue(),
            );

            /*
             * Don't call `encode()` or `validateIfNeeded()` on nested JsonFields if they are not typed.
             * This also allows directly storing non-array values like strings.
             */
            if ($nestedField instanceof JsonField && empty($nestedField->getPropertyMapping())) {
                // Validate required flag manually
                if ($nestedField->is(Required::class)) {
                    $this->validate([new NotNull()], $kvPair, $nestedParams->getPath());
                }
                $stack->update($kvPair->getKey(), $kvPair->getValue());

                continue;
            }

            try {
                $nestedField->compile($this->definitionRegistry);
                $encoded = $nestedField->getSerializer()->encode($nestedField, $existence, $kvPair, $nestedParams);

                foreach ($encoded as $fieldKey => $fieldValue) {
                    if ($nestedField instanceof JsonField && $fieldValue !== null) {
                        $fieldValue = Json::decodeToArray($fieldValue);
                    }

                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (WriteFieldException $exception) {
                $parameters->getContext()->getExceptions()->add($exception);
            }
        }

        return $stack->getResultAsArray();
    }

    private function validateIfNeeded(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): void
    {
        if (!$this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            return;
        }

        $constraints = $this->getCachedConstraints($field);

        $this->validate($constraints, $data, $parameters->getPath());
    }

    private function validate(
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

                // correct pointer for json fields with pre-defined structure
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

    private function requiresValidation(
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
            && $this->definitionRegistry->getByEntityName($existence->getEntityName()) instanceof EntityTranslationDefinition
            && $parameters->getCurrentWriteLanguageId() !== Defaults::LANGUAGE_SYSTEM
        ) {
            return false;
        }

        return $field->is(Required::class);
    }

    private function isInherited(Field $field, WriteParameterBag $parameters): bool
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

    private function getCachedConstraints(Field $field): array
    {
        $key = $field->getPropertyName() . spl_object_id($field);

        if (\array_key_exists($key, $this->cachedConstraints)) {
            return $this->cachedConstraints[$key];
        }
        $this->cachedConstraints[$key] = $this->getConstraints($field);

        return $this->cachedConstraints[$key];
    }
}
