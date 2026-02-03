<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\DependencyInjection;

use OutOfBoundsException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ImporterRegistry
{
    /**
     * @var Importer[]
     */
    private readonly array $importers;

    /**
     * @param iterable<string, Importer> $importers
     */
    public function __construct(
        #[AutowireIterator(
            tag: 'pickware_erp.import_export.importer',
            // Make it an associative array
            indexAttribute: 'profileTechnicalName',
        )]
        iterable $importers,
    ) {
        $this->importers = iterator_to_array($importers);
    }

    public function hasImporter(string $technicalName): bool
    {
        return array_key_exists($technicalName, $this->importers);
    }

    public function getImporterByTechnicalName(string $technicalName): Importer
    {
        if (!$this->hasImporter($technicalName)) {
            throw new OutOfBoundsException(sprintf(
                'Importer with technical name "%s" is not installed.',
                $technicalName,
            ));
        }

        return $this->importers[$technicalName];
    }
}
