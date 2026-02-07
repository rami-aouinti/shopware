<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'bin-location'])]
class BinLocationImporter implements Importer
{
    public const TECHNICAL_NAME = 'bin-location';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--bin-location-import',
        'type' => 'object',
        'properties' => [
            'code' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'position' => [
                'oneOf' => [
                    [
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                    ['type' => 'null'],
                ],
            ],
        ],
        // We do not require the `position` to support older exports and exports without the position feature
        'required' => ['code'],
    ];
    public const CONFIG_KEY_WAREHOUSE_ID = 'warehouseId';

    private Validator $validator;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BinLocationImportCsvRowNormalizer $normalizer,
        private readonly BinLocationUpsertService $binLocationUpsertService,
        private readonly ImportExportStateService $importExportStateService,
        #[Autowire('%pickware_erp.import_export.profiles.bin_location.batch_size%')]
        private readonly int $batchSize,
        JsonValidator $jsonValidator,
    ) {
        $this->validator = new Validator($normalizer, self::VALIDATION_SCHEMA, $jsonValidator);
    }

    public function canBeParallelized(): bool
    {
        return false;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->validator->validateHeaderRow($headerRow, $context);
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
        );
        $config = $import->getConfig();

        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $importId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $nextRowNumberToRead,
            RangeFilter::LT => $nextRowNumberToRead + $this->batchSize,
        ]));

        /** @var ImportExportElementCollection $importElements */
        $importElements = $this->entityManager->findBy(
            ImportExportElementDefinition::class,
            $criteria,
            $context,
        );

        if ($importElements->count() === 0) {
            return null;
        }

        $normalizedRows = $importElements->map(fn(ImportExportElementEntity $importElement) => $this->normalizer->normalizeRow($importElement->getRowData()));
        $originalColumnNamesByNormalizedColumnNames = $this->normalizer->mapNormalizedToOriginalColumnNames(array_keys(
            $importElements->first()->getRowData(),
        ));

        $importedBinLocations = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            $code = trim($normalizedRow['code']);
            if ($code === '') {
                continue;
            }

            $importedBinLocation = ['code' => $code];
            if (array_key_exists('position', $normalizedRow)) {
                $importedBinLocation['position'] = $normalizedRow['position'];
            }

            $importedBinLocations[] = $importedBinLocation;
        }

        try {
            $this->binLocationUpsertService->upsertBinLocations(
                $importedBinLocations,
                $config[self::CONFIG_KEY_WAREHOUSE_ID],
                $context,
            );
        } catch (Throwable $exception) {
            throw ImportException::batchImportError($exception, $nextRowNumberToRead, $this->batchSize);
        }

        if ($importElements->count() < $this->batchSize) {
            return null;
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    private function failOnErrors(string $importElementId, JsonApiErrors $errors, Context $context): bool
    {
        if (count($errors) > 0) {
            $this->importExportStateService->failImportExportElement($importElementId, $errors, $context);

            return true;
        }

        return false;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        if (!isset($config[self::CONFIG_KEY_WAREHOUSE_ID]) || $config[self::CONFIG_KEY_WAREHOUSE_ID] === '') {
            return new JsonApiErrors([
                ImportExportException::createConfigParameterNotSetError(self::CONFIG_KEY_WAREHOUSE_ID),
            ]);
        }

        return JsonApiErrors::noError();
    }
}
