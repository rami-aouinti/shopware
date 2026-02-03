<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\AclRole;

class AclRole
{
    private string $name;
    private array $privileges;
    private ?string $description;

    public function __construct(string $name, array $privileges, ?string $description = null)
    {
        $this->name = $name;
        $this->privileges = $privileges;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
