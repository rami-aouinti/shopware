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

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use function Pickware\PhpStandardLibrary\Range\safeRange;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ImporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Framework\Context;

class ImportExportSchedulerMessageGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
        private readonly ImporterRegistry $importerRegistry,
    ) {}

    /**
     * @return ImportExportSchedulerMessage[]
     */
    public function createExecuteImportMessagesForImportExport(string $importId, Context $context): array
    {
        /**
         * @var ImportExportEntity $import
         */
        $import = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
        );
        $rowCount = $this->getImportExportRowCount($importId);

        $importer = $this->importerRegistry->getImporterByTechnicalName($import->getProfileTechnicalName());

        if (
            /** @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions) */
            method_exists($importer, 'canBeParallelized')
            /** @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions) */
            && method_exists($importer, 'getBatchSize')
            && $importer->canBeParallelized()
        ) {
            return array_map(
                fn($nextRowNumberToRead) => new ImportExportSchedulerMessage(
                    $import->getId(),
                    ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT,
                    $context,
                    $nextRowNumberToRead,
                    spawnNextMessage: false,
                ),
                safeRange(1, $rowCount, $importer->getBatchSize()),
            );
        }

        return [
            new ImportExportSchedulerMessage(
                $import->getId(),
                ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT,
                $context,
                1,
                spawnNextMessage: true,
            ),
        ];
    }

    private function getImportExportRowCount(string $importExportId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(`id`)
            FROM `pickware_erp_import_export_element`
            WHERE `import_export_id` = :importExportId',
            ['importExportId' => hex2bin($importExportId)],
        );
    }
}
