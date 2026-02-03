<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ReadingOffset implements JsonSerializable
{
    private int $nextRowNumberToWrite;
    private int $nextByteToRead;

    public function __construct(int $nextRowNumberToWrite, int $nextByteToRead)
    {
        $this->nextRowNumberToWrite = $nextRowNumberToWrite;
        $this->nextByteToRead = $nextByteToRead;
    }

    public function getNextRowNumberToWrite(): int
    {
        return $this->nextRowNumberToWrite;
    }

    public function setNextRowNumberToWrite(int $nextRowNumberToWrite): void
    {
        $this->nextRowNumberToWrite = $nextRowNumberToWrite;
    }

    public function getNextByteToRead(): int
    {
        return $this->nextByteToRead;
    }

    public function setNextByteToRead(int $nextByteToRead): void
    {
        $this->nextByteToRead = $nextByteToRead;
    }

    /**
     * @return array{nextRowNumberToWrite: int, nextByteToRead: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'nextRowNumberToWrite' => $this->nextRowNumberToWrite,
            'nextByteToRead' => $this->nextByteToRead,
        ];
    }

    /**
     * @param array{nextRowNumberToWrite: int, nextByteToRead: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['nextRowNumberToWrite'], $data['nextByteToRead']);
    }
}
