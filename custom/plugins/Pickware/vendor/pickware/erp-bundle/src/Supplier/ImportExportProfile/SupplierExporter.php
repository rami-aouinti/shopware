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

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierCollection;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'supplier'])]
class SupplierExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'supplier';
    public const COLUMN_NUMBER = 'number';
    public const COLUMN_NAME = 'name';
    public const COLUMN_CUSTOMER_NUMBER = 'customerNumber';
    public const COLUMN_LANGUAGE = 'language.name';
    public const COLUMN_DEFAULT_DELIVERY_TIME = 'defaultDeliveryTime';
    public const COLUMN_ADDRESS_TITLE = 'address.title';
    public const COLUMN_ADDRESS_FIRST_NAME = 'address.firstName';
    public const COLUMN_ADDRESS_LAST_NAME = 'address.lastName';
    public const COLUMN_ADDRESS_EMAIL = 'address.email';
    public const COLUMN_ADDRESS_PHONE = 'address.phone';
    public const COLUMN_ADDRESS_FAX = 'address.fax';
    public const COLUMN_ADDRESS_WEBSITE = 'address.website';
    public const COLUMN_ADDRESS_DEPARTMENT = 'address.department';
    public const COLUMN_ADDRESS_COMPANY = 'address.company';
    public const COLUMN_ADDRESS_STREET = 'address.street';
    public const COLUMN_ADDRESS_HOUSE_NUMBER = 'address.houseNumber';
    public const COLUMN_ADDRESS_ADDRESS_ADDITION = 'address.addressAddition';
    public const COLUMN_ADDRESS_ZIP_CODE = 'address.zipCode';
    public const COLUMN_ADDRESS_CITY = 'address.city';
    public const COLUMN_ADDRESS_COUNTRY_ISO = 'address.countryIso';
    public const COLUMN_ADDRESS_VAT_ID = 'address.vatId';
    public const COLUMN_ADDRESS_COMMENT = 'address.comment';
    public const COLUMNS = [
        self::COLUMN_NUMBER,
        self::COLUMN_NAME,
        self::COLUMN_CUSTOMER_NUMBER,
        self::COLUMN_LANGUAGE,
        self::COLUMN_DEFAULT_DELIVERY_TIME,
        self::COLUMN_ADDRESS_TITLE,
        self::COLUMN_ADDRESS_FIRST_NAME,
        self::COLUMN_ADDRESS_LAST_NAME,
        self::COLUMN_ADDRESS_EMAIL,
        self::COLUMN_ADDRESS_PHONE,
        self::COLUMN_ADDRESS_FAX,
        self::COLUMN_ADDRESS_WEBSITE,
        self::COLUMN_ADDRESS_COMPANY,
        self::COLUMN_ADDRESS_DEPARTMENT,
        self::COLUMN_ADDRESS_STREET,
        self::COLUMN_ADDRESS_HOUSE_NUMBER,
        self::COLUMN_ADDRESS_ADDRESS_ADDITION,
        self::COLUMN_ADDRESS_ZIP_CODE,
        self::COLUMN_ADDRESS_CITY,
        self::COLUMN_ADDRESS_COUNTRY_ISO,
        self::COLUMN_ADDRESS_VAT_ID,
        self::COLUMN_ADDRESS_COMMENT,
    ];
    public const DEFAULT_COLUMNS = [
        self::COLUMN_NUMBER,
        self::COLUMN_NAME,
        self::COLUMN_ADDRESS_TITLE,
        self::COLUMN_ADDRESS_FIRST_NAME,
        self::COLUMN_ADDRESS_LAST_NAME,
        self::COLUMN_ADDRESS_EMAIL,
        self::COLUMN_ADDRESS_PHONE,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_NUMBER => 'pickware-erp-starter.supplier-export.columns.number',
        self::COLUMN_NAME => 'pickware-erp-starter.supplier-export.columns.name',
        self::COLUMN_CUSTOMER_NUMBER => 'pickware-erp-starter.supplier-export.columns.customer-number',
        self::COLUMN_LANGUAGE => 'pickware-erp-starter.supplier-export.columns.language',
        self::COLUMN_DEFAULT_DELIVERY_TIME => 'pickware-erp-starter.supplier-export.columns.default-delivery-time',
        self::COLUMN_ADDRESS_TITLE => 'pickware-erp-starter.supplier-export.columns.title',
        self::COLUMN_ADDRESS_FIRST_NAME => 'pickware-erp-starter.supplier-export.columns.first-name',
        self::COLUMN_ADDRESS_LAST_NAME => 'pickware-erp-starter.supplier-export.columns.last-name',
        self::COLUMN_ADDRESS_EMAIL => 'pickware-erp-starter.supplier-export.columns.email',
        self::COLUMN_ADDRESS_PHONE => 'pickware-erp-starter.supplier-export.columns.phone',
        self::COLUMN_ADDRESS_FAX => 'pickware-erp-starter.supplier-export.columns.fax',
        self::COLUMN_ADDRESS_WEBSITE => 'pickware-erp-starter.supplier-export.columns.website',
        self::COLUMN_ADDRESS_COMPANY => 'pickware-erp-starter.supplier-export.columns.company',
        self::COLUMN_ADDRESS_DEPARTMENT => 'pickware-erp-starter.supplier-export.columns.department',
        self::COLUMN_ADDRESS_STREET => 'pickware-erp-starter.supplier-export.columns.street',
        self::COLUMN_ADDRESS_HOUSE_NUMBER => 'pickware-erp-starter.supplier-export.columns.house-number',
        self::COLUMN_ADDRESS_ADDRESS_ADDITION => 'pickware-erp-starter.supplier-export.columns.address-addition',
        self::COLUMN_ADDRESS_ZIP_CODE => 'pickware-erp-starter.supplier-export.columns.zip-code',
        self::COLUMN_ADDRESS_CITY => 'pickware-erp-starter.supplier-export.columns.city',
        self::COLUMN_ADDRESS_COUNTRY_ISO => 'pickware-erp-starter.supplier-export.columns.country-iso',
        self::COLUMN_ADDRESS_VAT_ID => 'pickware-erp-starter.supplier-export.columns.vat-id',
        self::COLUMN_ADDRESS_COMMENT => 'pickware-erp-starter.supplier-export.columns.comment',
    ];

    private EntityManager $entityManager;
    private int $batchSize;
    private Translator $translator;
    private CriteriaJsonSerializer $criteriaJsonSerializer;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        Translator $translator,
        #[Autowire('%pickware_erp.import_export.profiles.supplier.batch_size%')]
        int $batchSize,
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->translator = $translator;
        $this->batchSize = $batchSize;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        $columns = $exportConfig['columns'] ?? self::DEFAULT_COLUMNS;

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getSupplierExportRows(
            $criteria,
            $exportConfig['locale'],
            $columns,
            $context,
        );

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $exportRow,
            ];
        }

        $this->entityManager->create(
            ImportExportElementDefinition::class,
            $exportElementPayloads,
            $context,
        );

        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return SupplierDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.supplier-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        $invalidColumns = array_diff($columns, self::COLUMNS);
        foreach ($invalidColumns as $invalidColumn) {
            $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
        }

        return $errors;
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $headerTranslations = $this->getCsvHeaderTranslations($export->getConfig()['locale'], $context);
        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            $export->getConfig()['columns'] ?? self::DEFAULT_COLUMNS,
        );

        return [$translatedColumns];
    }

    private function getSupplierExportRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        /** @var SupplierCollection $suppliers */
        $suppliers = $context->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->findBy(
            SupplierDefinition::class,
            $criteria,
            $inheritanceContext,
            [
                'address',
                'language',
            ],
        ));

        $rows = [];
        foreach ($suppliers as $supplier) {
            $currentRow = [];

            $columnValues = [
                self::COLUMN_NUMBER => $supplier->getNumber(),
                self::COLUMN_NAME => $supplier->getName(),
                self::COLUMN_CUSTOMER_NUMBER => $supplier->getCustomerNumber(),
                self::COLUMN_LANGUAGE => $supplier->getLanguage()->getName(),
                self::COLUMN_DEFAULT_DELIVERY_TIME => $supplier->getDefaultDeliveryTime(),
            ];

            $address = $supplier->getAddress();
            if ($address) {
                $columnValues = array_merge(
                    $columnValues,
                    [
                        self::COLUMN_ADDRESS_TITLE => $address->getTitle(),
                        self::COLUMN_ADDRESS_FIRST_NAME => $address->getFirstName(),
                        self::COLUMN_ADDRESS_LAST_NAME => $address->getLastName(),
                        self::COLUMN_ADDRESS_EMAIL => $address->getEmail(),
                        self::COLUMN_ADDRESS_PHONE => $address->getPhone(),
                        self::COLUMN_ADDRESS_FAX => $address->getFax(),
                        self::COLUMN_ADDRESS_WEBSITE => $address->getWebsite(),
                        self::COLUMN_ADDRESS_COMPANY => $address->getCompany(),
                        self::COLUMN_ADDRESS_DEPARTMENT => $address->getDepartment(),
                        self::COLUMN_ADDRESS_STREET => $address->getStreet(),
                        self::COLUMN_ADDRESS_HOUSE_NUMBER => $address->getHouseNumber(),
                        self::COLUMN_ADDRESS_ADDRESS_ADDITION => $address->getAddressAddition(),
                        self::COLUMN_ADDRESS_ZIP_CODE => $address->getZipCode(),
                        self::COLUMN_ADDRESS_CITY => $address->getCity(),
                        self::COLUMN_ADDRESS_COUNTRY_ISO => $address->getCountryIso(),
                        self::COLUMN_ADDRESS_VAT_ID => $address->getVatId(),
                        self::COLUMN_ADDRESS_COMMENT => $address->getComment(),
                    ],
                );
            }

            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column] ?? '';
            }

            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }
}
