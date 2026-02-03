<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document\Model;

use Pickware\DalBundle\Translation;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class DocumentTypeEntity extends Entity
{
    protected string $technicalName;
    protected Translation $singularDescription;
    protected Translation $pluralDescription;
    protected string $description;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
        $this->_uniqueIdentifier = $technicalName;
    }

    public function getSingularDescription(): Translation
    {
        return $this->singularDescription;
    }

    public function setSingularDescription(Translation $singularDescription): void
    {
        $this->singularDescription = $singularDescription;
        $this->description = $singularDescription->getGerman();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPluralDescription(): Translation
    {
        return $this->pluralDescription;
    }

    public function setPluralDescription(Translation $pluralDescription): void
    {
        $this->pluralDescription = $pluralDescription;
    }
}
