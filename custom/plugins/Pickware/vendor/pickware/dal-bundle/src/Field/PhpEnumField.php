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

use BackedEnum;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;

class PhpEnumField extends Field implements StorageAware
{
    /**
     * @param class-string<BackedEnum> $enumName
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly string $enumName,
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return class-string<BackedEnum>
     */
    public function getEnumName(): string
    {
        return $this->enumName;
    }

    protected function getSerializerClass(): string
    {
        return PhpEnumFieldSerializer::class;
    }
}
