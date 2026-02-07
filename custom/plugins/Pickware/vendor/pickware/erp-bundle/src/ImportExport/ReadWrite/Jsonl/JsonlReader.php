<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Jsonl;

use InvalidArgumentException;
use Pickware\PhpStandardLibrary\Json\Json;

class JsonlReader
{
    private int $offset = 0;

    public function read($resource, int $offset): iterable
    {
        if (!\is_resource($resource)) {
            throw new InvalidArgumentException('Argument $resource is not a resource');
        }

        $this->offset = $offset;

        while (!feof($resource)) {
            $record = $this->readSingleRecord($resource, $this->offset);
            $this->offset = ftell($resource);

            if ($record !== null) {
                yield $record;
            }
        }
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    private function readSingleRecord($resource, int $offset): mixed
    {
        $this->seek($resource, $offset);

        while (!feof($resource)) {
            $record = fgets($resource);
            if ($record === false || trim($record) === '') {
                // skip empty lines
                continue;
            }

            return Json::decodeToArray($record);
        }

        return null;
    }

    private function seek($resource, int $offset): void
    {
        $currentOffset = ftell($resource);
        if ($currentOffset !== $offset) {
            fseek($resource, $offset);
        }
    }
}
