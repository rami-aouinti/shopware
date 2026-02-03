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

use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportWriter;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ImportExportWriterRegistry extends AbstractRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_erp.import_export.import_export_writer';

    public function __construct(
        #[TaggedIterator('pickware_erp.import_export.import_export_writer')]
        iterable $importExportWriters,
    ) {
        parent::__construct(
            $importExportWriters,
            [ImportExportWriter::class],
            self::DI_CONTAINER_TAG,
        );
    }

    /**
     * @param ImportExportWriter $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getTechnicalName();
    }

    public function getImportExportWriterByTechnicalName(string $technicalName): ImportExportWriter
    {
        return $this->getRegisteredInstanceByKey($technicalName);
    }
}
