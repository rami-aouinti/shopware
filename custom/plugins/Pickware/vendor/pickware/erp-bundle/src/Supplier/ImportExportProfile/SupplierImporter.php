<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'supplier'])]
class SupplierImporter implements Importer
{
    public const TECHNICAL_NAME = 'supplier';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--supplier-import',
        'type' => 'object',
        'properties' => [
            'number' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'name' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'customerNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'language' => [
                'type' => 'string',
                'maxLength' => 50,
            ],
            'defaultDeliveryTime' => [
                '$ref' => '#/definitions/integerMinZeroOrEmpty',
            ],
            'title' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'firstName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'lastName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'email' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'phone' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'fax' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'website' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'company' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'department' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'street' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'houseNumber' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'addressAddition' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'zipCode' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'city' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'countryIso' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'vatId' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'commentary' => [
                'type' => 'string',
            ],
        ],
        'required' => ['name'],
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
            ],
            'integerMinZeroOrEmpty' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                ],
            ],
        ],
    ];

    private Validator $validator;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SupplierImportCsvRowNormalizer $normalizer,
        private readonly ImportExportStateService $importExportStateService,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        #[Autowire('%pickware_erp.import_export.profiles.supplier.batch_size%')]
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

        $languagesByName = [];
        $countriesByIso = [];
        $supplierUpsertPayloads = [];
        foreach ($importElements->getElements() as $index => $importElement) {
            $normalizedRow = $normalizedRows[$index];

            $errors = $this->validator->validateRow($normalizedRow, $originalColumnNamesByNormalizedColumnNames);
            if ($this->failOnErrors($importElement->getId(), $errors, $context)) {
                continue;
            }

            try {
                $supplierUpsertPayload = $this->getSupplierUpsertPayload(
                    $importElement->getId(),
                    $normalizedRow,
                    $languagesByName,
                    $errors,
                    $context,
                );

                if ($supplierUpsertPayload === null) {
                    continue;
                }

                $addressUpsertPayload = $this->getAddressUpsertPayload(
                    $importElement->getId(),
                    $normalizedRow,
                    $countriesByIso,
                    $errors,
                    $context,
                );

                if ($addressUpsertPayload === null) {
                    $supplierUpsertPayloads[] = $supplierUpsertPayload;

                    continue;
                }

                $supplierUpsertPayload['address'] = array_merge(
                    $supplierUpsertPayload['address'],
                    $addressUpsertPayload,
                );
                $supplierUpsertPayloads[] = $supplierUpsertPayload;
            } catch (Throwable $exception) {
                throw ImportException::rowImportError($exception, $importElement->getRowNumber());
            }
        }

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($supplierUpsertPayloads, $context): void {
                    if (count($supplierUpsertPayloads) > 0) {
                        $this->entityManager->upsert(
                            SupplierDefinition::class,
                            $supplierUpsertPayloads,
                            $context,
                        );
                    }
                },
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

    private function findCountryByIso(array &$countriesByIso, string $countryIso, Context $context): ?CountryEntity
    {
        if (!array_key_exists($countryIso, $countriesByIso)) {
            $countriesByIso[$countryIso] = $this->entityManager->findOneBy(
                CountryDefinition::class,
                ['iso' => $countryIso],
                $context,
            );
        }

        return $countriesByIso[$countryIso];
    }

    private function findLanguageByName(array &$languagesByName, string $languageName, Context $context): ?LanguageEntity
    {
        if (!array_key_exists($languageName, $languagesByName)) {
            $languagesByName[$languageName] = $this->entityManager->findOneBy(
                LanguageDefinition::class,
                ['name' => $languageName],
                $context,
            );
        }

        return $languagesByName[$languageName];
    }

    private function getAddressUpsertPayload(
        string $importElementId,
        array $normalizedRow,
        array $countriesByIso,
        JsonApiErrors $errors,
        Context $context,
    ): ?array {
        $payload = [];

        if (isset($normalizedRow['countryIso'])) {
            $countryIso = $normalizedRow['countryIso'];

            if ($countryIso === '') {
                // Country iso was cleared
                $payload['countryIso'] = $countryIso;
            } else {
                // Country iso was set. Check if it exists.
                $country = $this->findCountryByIso($countriesByIso, $countryIso, $context);
                if (!$country) {
                    $errors->addError(SupplierImportException::createCountryIsoNotFoundError($countryIso));
                } else {
                    $payload['countryIso'] = $countryIso;
                }
            }
        }

        if ($this->failOnErrors($importElementId, $errors, $context)) {
            return null;
        }

        $remainingColumns = [
            'title',
            'firstName',
            'lastName',
            'email',
            'phone',
            'fax',
            'website',
            'company',
            'department',
            'street',
            'houseNumber',
            'addressAddition',
            'zipCode',
            'city',
            'vatId',
            'comment',
        ];

        foreach ($remainingColumns as $colName) {
            if (isset($normalizedRow[$colName])) {
                $payload[$colName] = $normalizedRow[$colName];
            }
        }

        if (count($payload) === 0) {
            // If no row data regarding the address was provided (i.e. the import does not contain any address
            // information), do not return any update payload.
            return null;
        }

        return $payload;
    }

    private function getSupplierUpsertPayload(
        string $importElementId,
        array $normalizedRow,
        array $languagesByName,
        JsonApiErrors $errors,
        Context $context,
    ): ?array {
        /** @var SupplierEntity|null $supplier */
        $supplier = null;

        /** @var int|null $supplierNumber */
        $supplierNumber = null;

        // we want to match a supplier primarily by number, and use a name as fallback - and otherwise create a new one.
        $supplierNumberIsGiven = array_key_exists('number', $normalizedRow) && $normalizedRow['number'] !== '';
        if ($supplierNumberIsGiven) {
            $supplierNumber = $normalizedRow['number'];
            $supplier = $this->entityManager->findOneBy(
                SupplierDefinition::class,
                ['number' => $supplierNumber],
                $context,
            );
        }

        if (!$supplier) {
            $supplier = $this->entityManager->findOneBy(
                SupplierDefinition::class,
                ['name' => $normalizedRow['name']],
                $context,
            );

            if ($supplier) {
                $supplierNumber = $supplier->getNumber();
            }
        }

        $supplierNumber ??= $this->numberRangeValueGenerator->getValue('pickware_erp_supplier', $context, null);
        if ($supplier) {
            // Use existing supplier
            $payload = [
                'id' => $supplier->getId(),
                'name' => $normalizedRow['name'],
                'number' => $supplierNumber,
                'address' => [
                    'id' => $supplier->getAddressId() ?? Uuid::randomHex(),
                ],
            ];
        } else {
            // Use new supplier. Create a new address entity without any information.
            $payload = [
                'id' => Uuid::randomHex(),
                'name' => $normalizedRow['name'],
                'number' => $supplierNumber,
                'address' => [
                    'id' => Uuid::randomHex(),
                ],
            ];
        }

        if (isset($normalizedRow['language'])) {
            $languageName = $normalizedRow['language'];
            $language = $this->findLanguageByName($languagesByName, $languageName, $context);

            if (!$language) {
                $errors->addError(SupplierImportException::createLanguageNotFoundError(
                    $normalizedRow['language'],
                ));
            } else {
                $payload['languageId'] = $language->getId();
            }
        }

        if (isset($normalizedRow['defaultDeliveryTime']) && $normalizedRow['defaultDeliveryTime'] !== '') {
            $payload['defaultDeliveryTime'] = (int) $normalizedRow['defaultDeliveryTime'];
        } else {
            // Reminder: supplier default delivery time is optional
            $payload['defaultDeliveryTime'] = null;
        }

        if (isset($normalizedRow['customerNumber'])) {
            $payload['customerNumber'] = $normalizedRow['customerNumber'];
        }

        if ($this->failOnErrors($importElementId, $errors, $context)) {
            return null;
        }

        return $payload;
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
        return JsonApiErrors::noError();
    }
}
