<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\Country;

class Country
{
    public function __construct(
        private readonly array $translatedNames,
        private readonly string $iso2,
        private readonly string $iso3,
        private readonly int $position = 10,
    ) {}

    public function getTranslatesName(): array
    {
        return $this->translatedNames;
    }

    public function getIso2(): string
    {
        return $this->iso2;
    }

    public function getIso3(): string
    {
        return $this->iso3;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
