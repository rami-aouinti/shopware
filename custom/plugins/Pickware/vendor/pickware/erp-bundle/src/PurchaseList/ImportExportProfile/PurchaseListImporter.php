<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogEntryMessage;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * @phpstan-import-type NormalizedPurchaseListRow from PurchaseListImportCsvRowNormalizer
 */
#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'purchase-list'])]
class PurchaseListImporter implements Importer
{
    public const TECHNICAL_NAME = 'purchase-list';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--purchase-list-import',
        'type' => 'object',
        'additionalProperties' => true,
        'properties' => [
            'productNumber' => [
                'type' => 'string',
            ],
            'supplier' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'quantity' => [
                'type' => 'integer',
                'minimum' => 0,
            ],
            'purchasePriceNet' => [
                'type' => 'number',
                'minimum' => 0,
                'multipleOf' => 0.01,
            ],
        ],
        'required' => [
            'productNumber',
        ],
    ];

    private readonly Validator $validator;

    public function __construct(
        private readonly PurchaseListItemBatchMappingService $listItemBatchMappingService,
        private readonly ImportExportStateService $importExportStateService,
        private readonly PurchaseListImportCsvRowNormalizer $normalizer,
        private readonly EntityManager $entityManager,
        #[Autowire('%pickware_erp.import_export.profiles.purchase-list.batch_size%')]
        private readonly int $batchSize,
    ) {
        $this->validator = new Validator($normalizer, self::VALIDATION_SCHEMA);
    }

    /**
     * @param array<string> $headerRow
     */
    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->validator->validateHeaderRow($headerRow);
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
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

        $normalizedRows = ImmutableCollection::create($importElements)
            ->map(fn(ImportExportElementEntity $importElement) => $this->normalizer->normalizeRow($importElement->getRowData()))
            ->zip(ImmutableCollection::create($importElements)->map(fn(ImportExportElementEntity $importElement) => $importElement->getId()))
            ->filter(fn(array $tuple) => $this->validateRow($tuple[0], $tuple[1], $context))
            ->mapTuple(fn(array $normalizedRow, string $importElementId) => PurchaseListItemImportRow::fromRow(
                $normalizedRow,
                $importElements->get($importElementId)?->getRowNumber(),
            ));

        $upsertResult = $this->listItemBatchMappingService->mapRows($normalizedRows, $context);

        try {
            $this->entityManager->upsert(
                PurchaseListItemDefinition::class,
                array_map(
                    fn(PurchaseListImportListItem $importListItem) => array_filter([
                        'id' => $importListItem->getId(),
                        'productId' => $importListItem->getProductId(),
                        'quantity' => $importListItem->getQuantity(),
                        'productSupplierConfiguration' => array_filter([
                            'id' => $importListItem->getProductSupplierConfigurationId(),
                            'purchasePrices' => $importListItem->getPurchasePrices(),
                        ]),
                    ]),
                    $upsertResult->getPurchaseListImportListItems(),
                ),
                $context,
            );

            $this->entityManager->create(
                ImportExportLogEntryDefinition::class,
                array_map(
                    fn(PurchaseListImportMessage $importExportLogEntry) => [
                        'importExportId' => $importId,
                        'rowNumber' => $importExportLogEntry->getRowNumber(),
                        'logLevel' => $importExportLogEntry->getLevel(),
                        'message' => ImportExportLogEntryMessage::fromTranslatedMessage($importExportLogEntry),
                    ],
                    $upsertResult->getPurchaseListImportMessages(),
                ),
                $context,
            );
        } catch (Throwable $exception) {
            throw ImportException::batchImportError($exception, $nextRowNumberToRead, $this->batchSize);
        }

        $nextRowNumberToRead += $this->batchSize;

        return $nextRowNumberToRead;
    }

    /**
     * @param NormalizedPurchaseListRow $normalizedRow
     */
    private function validateRow(array $normalizedRow, string $importElementId, Context $context): bool
    {
        $errors = $this->validator->validateRow($normalizedRow);
        if (count($errors) > 0) {
            $this->importExportStateService->failImportExportElement($importElementId, $errors, $context);

            return false;
        }

        return true;
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return JsonApiErrors::noError();
    }
}
