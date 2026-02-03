<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Installation;

use Pickware\DalBundle\Translation;

class DocumentType
{
    public function __construct(
        private readonly string $technicalName,
        private readonly Translation $descriptionSingular,
        private readonly Translation $descriptionPlural,
    ) {}

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getDescriptionSingular(): Translation
    {
        return $this->descriptionSingular;
    }

    public function getDescriptionPlural(): Translation
    {
        return $this->descriptionPlural;
    }
}
