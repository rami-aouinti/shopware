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
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException as ShopwareDataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.field_serializer')]
class PhpEnumFieldSerializer implements FieldSerializerInterface
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
        if (!$field instanceof PhpEnumField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(PhpEnumField::class, $field);
        }

        $value = $data->getValue();
        if (!is_a($data->getValue(), $field->getEnumName()) && $data->getValue() !== null) {
            $value = $this->decode($field, $data->getValue());
        }

        yield $field->getStorageName() => $value->value;
    }

    public function decode(Field $field, mixed $value): mixed
    {
        if (!$field instanceof PhpEnumField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(PhpEnumField::class, $field);
        }

        return ($field->getEnumName())::from($value);
    }
}
