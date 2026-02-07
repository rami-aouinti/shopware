<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\DependencyInjection;

use InvalidArgumentException;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportReader;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ImportExportReaderRegistry
{
    /**
     * @var ImportExportReader[]
     */
    private array $importExportReaders = [];

    public function __construct(
        #[TaggedIterator('pickware_erp.import_export.import_export_reader')]
        iterable $importExportReaders,
    ) {
        foreach ($importExportReaders as $importExportReader) {
            if (!($importExportReader instanceof ImportExportReader)) {
                throw new InvalidArgumentException(
                    'Tagged argument for ImportExportReaderRegistry must implement the ImportExportReader'
                    . ' interface.',
                );
            }

            $this->importExportReaders[$importExportReader->getTechnicalName()] = $importExportReader;
        }
    }

    public function getImportExportReaderByTechnicalName(string $technicalName): ImportExportReader
    {
        if (!array_key_exists($technicalName, $this->importExportReaders)) {
            throw new InvalidArgumentException(sprintf(
                'ImportExportReader with technical name "%s" is not installed.',
                $technicalName,
            ));
        }

        return $this->importExportReaders[$technicalName];
    }
}
