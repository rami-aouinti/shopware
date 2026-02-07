<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use InvalidArgumentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ImportExportSchedulerMessage implements AsyncMessageInterface
{
    public const STATE_EXECUTE_IMPORT = 'execute-import';
    public const STATE_READ_FILE_TO_DATABASE = 'read-file-to-database';
    public const STATE_FILE_VALIDATION = 'file-validation';
    public const STATE_EXECUTE_EXPORT = 'execute-export';
    public const STATE_WRITE_DATABASE_TO_FILE = 'write-database-to-file';

    private string $importExportId;
    private string $state;
    private Context $context;
    private ?int $nextRowNumberToRead;
    private ?bool $spawnNextMessage;

    public function __construct(string $importExportId, string $state, Context $context, ?int $nextRowNumberToRead = null, ?bool $spawnNextMessage = null)
    {
        if ($state === self::STATE_EXECUTE_IMPORT && ($nextRowNumberToRead === null || $spawnNextMessage === null)) {
            throw new InvalidArgumentException('execute-import messages must contain $nextRowNumberToRead and $spawnNextMessage');
        }
        if ($state !== self::STATE_EXECUTE_IMPORT && ($nextRowNumberToRead !== null || $spawnNextMessage !== null)) {
            throw new InvalidArgumentException('only execute-import messages may contain $nextRowNumberToRead and $spawnNextMessage');
        }

        $this->importExportId = $importExportId;
        $this->state = $state;
        $this->context = $context;
        $this->nextRowNumberToRead = $nextRowNumberToRead;
        $this->spawnNextMessage = $spawnNextMessage;
    }

    public function getImportExportId(): string
    {
        return $this->importExportId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getNextRowNumberToRead(): ?int
    {
        return $this->nextRowNumberToRead;
    }

    public function getSpawnNextMessage(): ?bool
    {
        return $this->spawnNextMessage;
    }
}
