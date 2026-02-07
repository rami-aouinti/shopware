<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AclBundle\FeaturePermission;

use Pickware\DalBundle\Translation;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class FeaturePermission
{
    public function __construct(
        private readonly string $technicalName,
        private readonly Translation $translatedName,
        private readonly array $privileges,
        private readonly array $dependencies = [],
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getTranslatedName(): Translation
    {
        return $this->translatedName;
    }

    /**
     * @return string[]
     */
    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    /**
     * @return FeaturePermission[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
