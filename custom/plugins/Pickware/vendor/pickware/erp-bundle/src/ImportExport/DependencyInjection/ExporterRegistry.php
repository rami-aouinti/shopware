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
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ExporterRegistry
{
    /**
     * @var Exporter[]
     */
    private readonly array $exporters;

    /**
     * @param iterable<string, Exporter> $exporters
     */
    public function __construct(
        #[AutowireIterator(
            tag: 'pickware_erp.import_export.exporter',
            // Make it an associative array
            indexAttribute: 'profileTechnicalName',
        )]
        iterable $exporters,
    ) {
        $this->exporters = iterator_to_array($exporters);
    }

    public function hasExporter(string $technicalName): bool
    {
        return array_key_exists($technicalName, $this->exporters);
    }

    public function getExporterByTechnicalName(string $technicalName): Exporter
    {
        if (!$this->hasExporter($technicalName)) {
            throw new OutOfBoundsException(sprintf(
                'Exporter with technical name "%s" is not installed.',
                $technicalName,
            ));
        }

        return $this->exporters[$technicalName];
    }
}
