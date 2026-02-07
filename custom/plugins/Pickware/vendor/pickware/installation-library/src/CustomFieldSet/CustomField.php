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

class CustomField
{
    public function __construct(
        private readonly string $technicalName,
        private readonly string $type,
        private readonly array $config,
        private readonly bool $active = true,
        private readonly bool $allowCustomerWrite = false,
        private readonly bool $allowCartExpose = false,
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function allowsCustomerWrite(): bool
    {
        return $this->allowCustomerWrite;
    }

    public function allowsCartExpose(): bool
    {
        return $this->allowCartExpose;
    }
}
