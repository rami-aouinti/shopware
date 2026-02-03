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
use Pickware\PhpStandardLibrary\DateTime\CalendarDate;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException as ShopwareDataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.field_serializer')]
class CalendarDateFieldSerializer implements FieldSerializerInterface
{
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
        if (!$field instanceof CalendarDateField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(CalendarDateField::class, $field);
        }

        $value = $data->getValue();

        // If the value is not a CalendarDate instance and not null, try to decode it first
        if (!($value instanceof CalendarDate) && $value !== null) {
            $value = $this->decode($field, $value);
        }

        // Store the ISO string representation in the database
        $isoString = $value?->toIsoString();

        yield $field->getStorageName() => $isoString;
    }

    public function decode(Field $field, mixed $value): mixed
    {
        if (!$field instanceof CalendarDateField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(CalendarDateField::class, $field);
        }

        if ($value === null) {
            return null;
        }

        // If the value is already a CalendarDate instance, return it as-is
        if ($value instanceof CalendarDate) {
            return $value;
        }

        // Convert from ISO string to CalendarDate
        // Let CalendarDate::fromIsoString() handle invalid values and throw its own exception
        return CalendarDate::fromIsoString($value);
    }
}
