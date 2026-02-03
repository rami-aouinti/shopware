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
use InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException as ShopwareDataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AutoconfigureTag('shopware.field_serializer')]
class NonUuidFkFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters,
    ): Generator {
        if (!$field instanceof NonUuidFkField) {
            throw ShopwareDataAbstractionLayerException::invalidSerializerField(NonUuidFkField::class, $field);
        }

        $value = $data->getValue();

        if ($this->shouldUseContext($field, $data)) {
            try {
                $value = $parameters->getContext()->get($field->getReferenceDefinition()->getClass(), $field->getReferenceField());
            } catch (InvalidArgumentException $exception) {
                $this->validate($this->getConstraints($field), $data, $parameters->getPath());
            }
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value;
    }

    protected function shouldUseContext(FkField $field, KeyValuePair $data): bool
    {
        return $data->isRaw() && $data->getValue() === null && $field->is(Required::class);
    }

    protected function getConstraints(Field $field): array
    {
        return [new NotBlank()];
    }
}
