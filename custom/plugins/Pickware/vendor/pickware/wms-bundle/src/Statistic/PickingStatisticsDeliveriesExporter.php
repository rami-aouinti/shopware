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
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'picking-statistics-deliveries'])]
class PickingStatisticsDeliveriesExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'picking-statistics-deliveries';
    public const COLUMN_DELIVERY_ID = 'delivery_id';
    public const COLUMN_DELIVERY_COMPLETED_AT = 'delivery_completed_at';
    public const COLUMN_USER_NAME = 'user_name';
    public const COLUMN_USER_ROLES = 'user_roles';
    public const COLUMN_ORDER_NUMBER = 'order_number';
    public const COLUMN_ORDER_SALES_CHANNEL_NAME = 'order_sales_channel_name';
    public const COLUMN_WAREHOUSE_NAME = 'warehouse_name';
    public const COLUMN_PICKING_PROCESS_NUMBER = 'picking_process_number';
    public const COLUMN_PICKING_MODE = 'picking_mode';
    public const COLUMN_PICKING_PROFILE_NAME = 'picking_profile_name';
    public const COLUMNS = [
        self::COLUMN_DELIVERY_ID,
        self::COLUMN_DELIVERY_COMPLETED_AT,
        self::COLUMN_USER_NAME,
        self::COLUMN_USER_ROLES,
        self::COLUMN_ORDER_NUMBER,
        self::COLUMN_ORDER_SALES_CHANNEL_NAME,
        self::COLUMN_WAREHOUSE_NAME,
        self::COLUMN_PICKING_PROCESS_NUMBER,
        self::COLUMN_PICKING_MODE,
        self::COLUMN_PICKING_PROFILE_NAME,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_DELIVERY_ID => 'pickware-wms.picking-statistics-deliveries-export.columns.delivery-id',
        self::COLUMN_DELIVERY_COMPLETED_AT => 'pickware-wms.picking-statistics-deliveries-export.columns.delivery-completed-at',
        self::COLUMN_USER_NAME => 'pickware-wms.picking-statistics-deliveries-export.columns.user-name',
        self::COLUMN_USER_ROLES => 'pickware-wms.picking-statistics-deliveries-export.columns.user-roles',
        self::COLUMN_ORDER_NUMBER => 'pickware-wms.picking-statistics-deliveries-export.columns.order-number',
        self::COLUMN_ORDER_SALES_CHANNEL_NAME => 'pickware-wms.picking-statistics-deliveries-export.columns.order-sales-channel-name',
        self::COLUMN_WAREHOUSE_NAME => 'pickware-wms.picking-statistics-deliveries-export.columns.warehouse-name',
        self::COLUMN_PICKING_PROCESS_NUMBER => 'pickware-wms.picking-statistics-deliveries-export.columns.picking-process-number',
        self::COLUMN_PICKING_MODE => 'pickware-wms.picking-statistics-deliveries-export.columns.picking-mode',
        self::COLUMN_PICKING_PROFILE_NAME => 'pickware-wms.picking-statistics-deliveries-export.columns.picking-profile-name',
    ];
    public const PICKING_MODE_TRANSLATION_PREFIX = 'pickware-wms.picking-modes.';

    private int $batchSize;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        #[Autowire('%pickware_wms.import_export.profiles.picking_statistics_deliveries.batch_size%')]
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

        /** @var list<DeliveryLifecycleEventEntity> $exportRows */
        $exportRows = array_values($this->entityManager->findBy(
            DeliveryLifecycleEventDefinition::class,
            $criteria,
            $context,
        )->getElements());

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $deliveryEvent) {
            $userRoleNames = $deliveryEvent->getUserRoles()->map(fn($userRole) => $userRole->getUserRoleSnapshot()['name'] ?? '');
            sort($userRoleNames);
            $userTimezone = $exportConfig['timezone'] ?? 'UTC';
            $createdAtInUserTimezone = DateTime::createFromInterface($deliveryEvent->getEventCreatedAt())->setTimezone(new DateTimeZone($userTimezone));
            $rowData = [
                self::COLUMN_DELIVERY_ID => $deliveryEvent->getDeliveryReferenceId(),
                self::COLUMN_DELIVERY_COMPLETED_AT => $createdAtInUserTimezone->format('Y-m-d H:i:s'),
                self::COLUMN_USER_NAME => $deliveryEvent->getUserSnapshot()['username'] ?? '',
                self::COLUMN_USER_ROLES => implode(', ', $userRoleNames),
                self::COLUMN_ORDER_NUMBER => $deliveryEvent->getOrderSnapshot()['orderNumber'] ?? '',
                self::COLUMN_ORDER_SALES_CHANNEL_NAME => $deliveryEvent->getSalesChannelSnapshot()['name'] ?? '',
                self::COLUMN_WAREHOUSE_NAME => $deliveryEvent->getWarehouseSnapshot()['name'] ?? '',
                self::COLUMN_PICKING_PROCESS_NUMBER => $deliveryEvent->getPickingProcessSnapshot()['number'] ?? '',
                self::COLUMN_PICKING_MODE => $this->translator->translate(self::PICKING_MODE_TRANSLATION_PREFIX . $deliveryEvent->getPickingMode()),
                self::COLUMN_PICKING_PROFILE_NAME => $deliveryEvent->getPickingProfileSnapshot()['name'] ?? '',
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
        return DeliveryLifecycleEventDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-wms.picking-statistics-deliveries-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();

        if (isset($config['timezone'])) {
            $timezone = $config['timezone'];
            $validTimezones = DateTimeZone::listIdentifiers();
            if (!in_array($timezone, $validTimezones, true)) {
                $errors->addErrors(
                    PickingStatisticsExportException::createInvalidTimezoneError($timezone)
                        ->serializeToJsonApiError(),
                );
            }
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
        $dateColumnIndex = array_search(self::COLUMN_DELIVERY_COMPLETED_AT, self::COLUMNS);
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
