<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic;

use DateTime;
use DateTimeZone;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareWms\Statistic\Model\PickEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickEventEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'picking-statistics-picks'])]
class PickingStatisticsPicksExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'picking-statistics-picks';
    public const COLUMN_PICK_ID = 'pick_id';
    public const COLUMN_PICK_CREATED_AT = 'pick_created_at';
    public const COLUMN_PICKED_QUANTITY = 'picked_quantity';
    public const COLUMN_PRODUCT_NUMBER = 'product_number';
    public const COLUMN_PRODUCT_EAN = 'product_ean';
    public const COLUMN_PRODUCT_NAME = 'product_name';
    public const COLUMN_PRODUCT_WEIGHT = 'product_weight';
    public const COLUMN_USER_NAME = 'user_name';
    public const COLUMN_USER_ROLES = 'user_roles';
    public const COLUMN_WAREHOUSE_NAME = 'warehouse_name';
    public const COLUMN_BIN_LOCATION_CODE = 'bin_location_code';
    public const COLUMN_PICKING_PROCESS_NUMBER = 'picking_process_number';
    public const COLUMN_PICKING_MODE = 'picking_mode';
    public const COLUMN_PICKING_PROFILE_NAME = 'picking_profile_name';
    public const COLUMNS = [
        self::COLUMN_PICK_ID,
        self::COLUMN_PICK_CREATED_AT,
        self::COLUMN_PICKED_QUANTITY,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_EAN,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_PRODUCT_WEIGHT,
        self::COLUMN_USER_NAME,
        self::COLUMN_USER_ROLES,
        self::COLUMN_WAREHOUSE_NAME,
        self::COLUMN_BIN_LOCATION_CODE,
        self::COLUMN_PICKING_PROCESS_NUMBER,
        self::COLUMN_PICKING_MODE,
        self::COLUMN_PICKING_PROFILE_NAME,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PICK_ID => 'pickware-wms.picking-statistics-picks-export.columns.pick-id',
        self::COLUMN_PICK_CREATED_AT => 'pickware-wms.picking-statistics-picks-export.columns.pick-created-at',
        self::COLUMN_PICKED_QUANTITY => 'pickware-wms.picking-statistics-picks-export.columns.picked-quantity',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-wms.picking-statistics-picks-export.columns.product-number',
        self::COLUMN_PRODUCT_EAN => 'pickware-wms.picking-statistics-picks-export.columns.product-ean',
        self::COLUMN_PRODUCT_NAME => 'pickware-wms.picking-statistics-picks-export.columns.product-name',
        self::COLUMN_PRODUCT_WEIGHT => 'pickware-wms.picking-statistics-picks-export.columns.product-weight',
        self::COLUMN_USER_NAME => 'pickware-wms.picking-statistics-picks-export.columns.user-name',
        self::COLUMN_USER_ROLES => 'pickware-wms.picking-statistics-picks-export.columns.user-roles',
        self::COLUMN_WAREHOUSE_NAME => 'pickware-wms.picking-statistics-picks-export.columns.warehouse-name',
        self::COLUMN_BIN_LOCATION_CODE => 'pickware-wms.picking-statistics-picks-export.columns.bin-location-code',
        self::COLUMN_PICKING_PROCESS_NUMBER => 'pickware-wms.picking-statistics-picks-export.columns.picking-process-number',
        self::COLUMN_PICKING_MODE => 'pickware-wms.picking-statistics-picks-export.columns.picking-mode',
        self::COLUMN_PICKING_PROFILE_NAME => 'pickware-wms.picking-statistics-picks-export.columns.picking-profile-name',
    ];
    public const PICKING_MODE_TRANSLATION_PREFIX = 'pickware-wms.picking-modes.';

    private int $batchSize;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        #[Autowire('%pickware_wms.import_export.profiles.picking_statistics_picks.batch_size%')]
        int $batchSize,
    ) {
        $this->batchSize = $batchSize;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $this->translator->setTranslationLocale($exportConfig['locale'], $context);

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);
        $criteria->addAssociation('userRoles');

        /** @var list<PickEventEntity> $exportRows */
        $exportRows = array_values($this->entityManager->findBy(
            PickEventDefinition::class,
            $criteria,
            $context,
        )->getElements());

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $pickEvent) {
            $productSnapshot = $pickEvent->getProductSnapshot();
            $userRoleNames = $pickEvent->getUserRoles()->map(fn($userRole) => $userRole->getUserRoleSnapshot()['name'] ?? '');
            sort($userRoleNames);
            $userTimezone = $exportConfig['timezone'] ?? 'UTC';
            $createdAtInUserTimezone = DateTime::createFromInterface($pickEvent->getPickCreatedAt())->setTimezone(new DateTimeZone($userTimezone));

            $rowData = [
                self::COLUMN_PICK_ID => $pickEvent->getId(),
                self::COLUMN_PICK_CREATED_AT => $createdAtInUserTimezone->format('Y-m-d H:i:s'),
                self::COLUMN_PICKED_QUANTITY => $pickEvent->getPickedQuantity(),
                self::COLUMN_PRODUCT_NUMBER => $productSnapshot['productNumber'] ?? '',
                self::COLUMN_PRODUCT_EAN => $productSnapshot['ean'] ?? '',
                self::COLUMN_PRODUCT_NAME => $productSnapshot['name'] ?? '',
                self::COLUMN_PRODUCT_WEIGHT => $pickEvent->getProductWeight(),
                self::COLUMN_USER_NAME => $pickEvent->getUserSnapshot()['username'] ?? '',
                self::COLUMN_USER_ROLES => implode(', ', $userRoleNames),
                self::COLUMN_WAREHOUSE_NAME => $pickEvent->getWarehouseSnapshot()['name'] ?? '',
                self::COLUMN_BIN_LOCATION_CODE => $pickEvent->getBinLocationSnapshot()['code'] ?? $this->translator->translate('pickware-wms.picking-statistics-picks-export.unknown-stock-location'),
                self::COLUMN_PICKING_PROCESS_NUMBER => $pickEvent->getPickingProcessSnapshot()['number'] ?? '',
                self::COLUMN_PICKING_MODE => $this->translator->translate(self::PICKING_MODE_TRANSLATION_PREFIX . $pickEvent->getPickingMode()),
                self::COLUMN_PICKING_PROFILE_NAME => $pickEvent->getPickingProfileSnapshot()['name'] ?? '',
            ];

            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $rowData,
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
        return PickEventDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-wms.picking-statistics-picks-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();

        if (isset($config['timezone']) && !in_array($config['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors->addErrors(
                PickingStatisticsExportException::createInvalidTimezoneError($config['timezone'])->serializeToJsonApiError(),
            );
        }

        return $errors;
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        $headerTranslations = $this->getCsvHeaderTranslations($exportConfig['locale'], $context);
        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            self::COLUMNS,
        );

        $timezone = $exportConfig['timezone'] ?? 'UTC';
        $dateColumnIndex = array_search(self::COLUMN_PICK_CREATED_AT, self::COLUMNS);
        $translatedColumns[$dateColumnIndex] .= sprintf(' (%s)', $timezone);

        return [$translatedColumns];
    }

    /**
     * @return array<string, string>
     */
    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(
            fn($snippetId) => $this->translator->translate($snippetId),
            self::COLUMN_TRANSLATIONS,
        );
    }
}
