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
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogEntryMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @phpstan-type ImportExportLogEntryPayload array{
 *     importExportId: string,
 *     rowNumber: ?int,
 *     logLevel: ImportExportLogLevel,
 *     message: ImportExportLogEntryMessage
 * }
 */
class ImportExportLogEntryEntity extends Entity
{
    use EntityIdTrait;

    protected string $importExportId;
    protected ?ImportExportEntity $importExport = null;
    protected ?int $rowNumber = null;
    protected ImportExportLogLevel $logLevel;
    protected ImportExportLogEntryMessage $message;

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function setImportExportId(string $importExportId): void
    {
        if ($this->importExport && $this->importExport->getId() !== $importExportId) {
            $this->importExport = null;
        }
        $this->importExportId = $importExportId;
    }

    public function getImportExport(): ImportExportEntity
    {
        if (!$this->importExport) {
            throw new AssociationNotLoadedException('importExport', $this);
        }

        return $this->importExport;
    }

    public function setImportExport(ImportExportEntity $importExport): void
    {
        $this->importExport = $importExport;
        $this->importExportId = $importExport->getId();
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(?int $rowNumber): void
    {
        $this->rowNumber = $rowNumber;
    }

    public function getLogLevel(): ImportExportLogLevel
    {
        return $this->logLevel;
    }

    public function setLogLevel(ImportExportLogLevel $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    public function getMessage(): ImportExportLogEntryMessage
    {
        return $this->message;
    }

    public function setMessage(ImportExportLogEntryMessage $message): void
    {
        $this->message = $message;
    }
}
