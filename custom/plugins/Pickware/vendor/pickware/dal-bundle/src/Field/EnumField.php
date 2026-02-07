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

use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class EnumField extends StringField
{
    /**
     * @var string[]
     */
    private array $allowedValues;

    /**
     * @param string[] $allowedValues
     */
    public function __construct(string $storageName, string $propertyName, array $allowedValues)
    {
        parent::__construct($storageName, $propertyName);
        $this->allowedValues = $allowedValues;
    }

    /**
     * @return string[]
     */
    public function getAllowedValues(): array
    {
        return $this->allowedValues;
    }

    protected function getSerializerClass(): string
    {
        return EnumFieldSerializer::class;
    }
}
