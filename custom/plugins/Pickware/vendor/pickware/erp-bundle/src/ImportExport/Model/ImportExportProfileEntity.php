<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ImportExportProfileEntity extends Entity
{
    protected string $technicalName;
    protected int $logRetentionDays;
    protected ?ImportExportCollection $importExports = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLogRetentionDays(): int
    {
        return $this->logRetentionDays;
    }

    public function setLogRetentionDays(int $logRetentionDays): void
    {
        $this->logRetentionDays = $logRetentionDays;
    }

    public function getImportExports(): ImportExportCollection
    {
        if (!$this->importExports) {
            throw new AssociationNotLoadedException('importExports', $this);
        }

        return $this->importExports;
    }

    public function setImportExports(?ImportExportCollection $importExports): void
    {
        $this->importExports = $importExports;
    }
}
