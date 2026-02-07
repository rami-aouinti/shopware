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
class FeatureCategory
{
    /**
     * @param FeaturePermission[] $featurePermissions
     */
    public function __construct(
        private readonly string $technicalName,
        private readonly Translation $translatedName,
        private array $featurePermissions,
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
     * @return FeaturePermission[]
     */
    public function getFeaturePermissions(): array
    {
        return $this->featurePermissions;
    }

    /**
     * @param FeaturePermission[] $featurePermissions
     */
    public function addFeaturePermissions(array $featurePermissions): void
    {
        $this->featurePermissions = [
            ...$this->featurePermissions,
            ...$featurePermissions,
        ];
    }
}
