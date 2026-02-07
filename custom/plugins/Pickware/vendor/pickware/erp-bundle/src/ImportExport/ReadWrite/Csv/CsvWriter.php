<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CsvWriter
{
    private $handle;
    private string $delimiter;
    private string $enclosure;

    public function __construct(string $path, string $delimiter = ';', string $enclosure = '"')
    {
        $this->handle = fopen($path, 'ab');
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    public function append(array $data): void
    {
        $this->writeToHandle(array_values($data));
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    private function writeToHandle(array $data): void
    {
        $writeResult = fputcsv($this->handle, $data, $this->delimiter, $this->enclosure);

        if ($writeResult === false) {
            throw new RuntimeException('Could not write to handle');
        }
    }
}
