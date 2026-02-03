<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\CustomFieldSet;

class CustomFieldSet
{
    public function __construct(
        private readonly string $technicalName,
        private readonly array $config,
        private readonly array $relations,
        private array $fields = [],
        private readonly int $position = 1,
        private readonly bool $global = false,
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function addField(CustomField $customField): void
    {
        $this->fields[] = $customField;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }
}
