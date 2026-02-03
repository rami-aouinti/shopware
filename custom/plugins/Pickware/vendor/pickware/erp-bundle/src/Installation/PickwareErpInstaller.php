<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Installation\DocumentUninstaller as PickwareDocumentUninstaller;
use Pickware\DocumentBundle\Installation\PickwareDocumentTypeInstaller;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSetInstaller;
use Pickware\InstallationLibrary\DocumentType\DocumentTypeInstaller;
use Pickware\InstallationLibrary\Elasticsearch\ElasticsearchIndexInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller;
use Pickware\InstallationLibrary\StateMachine\StateMachineInstaller;
use Pickware\InstallationLibrary\SystemConfig\SystemConfigDefaultValue;
use Pickware\InstallationLibrary\SystemConfig\SystemConfigInstaller;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\Customer\AlternativeEmailCustomFieldSet;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\DemandPlanningAnalyticsAggregation;
use Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\DemandPlanningAnalyticsReport;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptDocumentDocumentType;
use Pickware\PickwareErpStarter\GoodsReceipt\Document\GoodsReceiptPhotoDocumentType;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptNoteDocumentType;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptNumberRange;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStockingListDocumentType;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\ImportExport\ExportFileDocumentType;
use Pickware\PickwareErpStarter\ImportExport\ImportFileDocumentType;
use Pickware\PickwareErpStarter\Installation\Analytics\AnalyticsInstaller;
use Pickware\PickwareErpStarter\Installation\Installer\ImportExportProfileInstaller;
use Pickware\PickwareErpStarter\Installation\Steps\CreateCompletelyReturnedFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateCompletelyShippedFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateConfigInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateDefaultPickingPropertyInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateInitialWarehouseInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreatePartiallyReturnedFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreatePartiallyShippedFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateReorderNotificationFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\CreateTransitionOrderToDoneAfterShippingFlowInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\InitializeStockInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\SetExistingOrderFlowsToForceTransitionsInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertLocationTypesInstallationStep;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertSpecialStockLocationsInstallationStep;
use Pickware\PickwareErpStarter\Invoice\InvoiceCommentCustomFieldSet;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionMailTemplate;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionNumberRange;
use Pickware\PickwareErpStarter\Order\ExternalOrderNumberFieldSet;
use Pickware\PickwareErpStarter\Picking\PickingInstructionCustomFieldSet;
use Pickware\PickwareErpStarter\Picklist\PicklistDocumentType;
use Pickware\PickwareErpStarter\Picklist\PicklistNumberRange;
use Pickware\PickwareErpStarter\PurchaseList\ImportExportProfile\PurchaseListImporter;
use Pickware\PickwareErpStarter\Reorder\ReorderMailTemplate;
use Pickware\PickwareErpStarter\ReturnOrder\Document\ReturnOrderStockingListDocumentType;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderNumberRange;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\AbsoluteStock\AbsoluteStockImporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\RelativeStockChange\RelativeStockChangeImporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerProduct\StockPerProductExporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerStockLocation\StockPerStockLocationExporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockPerWarehouse\StockPerWarehouseExporter;
use Pickware\PickwareErpStarter\StockMovementProcess\AutomaticallyShippedStockMovementProcessType;
use Pickware\PickwareErpStarter\StockMovementProcess\StockMovementProcessTypeInstaller;
use Pickware\PickwareErpStarter\Stocktaking\ImportExportProfile\StocktakeExporter;
use Pickware\PickwareErpStarter\Stocktaking\StocktakeCountingProcessNumberRange;
use Pickware\PickwareErpStarter\Stocktaking\StocktakeNumberRange;
use Pickware\PickwareErpStarter\StockValuation\ImportExportProfile\StockValuationReportExporter;
use Pickware\PickwareErpStarter\Supplier\ImportExportProfile\ProductSupplierConfigurationListItemExporter;
use Pickware\PickwareErpStarter\Supplier\ImportExportProfile\SupplierImporter;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Pickware\PickwareErpStarter\Supplier\SupplierNumberRange;
use Pickware\PickwareErpStarter\SupplierOrder\ImportExportProfile\SupplierOrderExporter;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderDocumentType;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderMailTemplate;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderNumberRange;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderPaymentStateMachine;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use Pickware\PickwareErpStarter\Warehouse\Import\BinLocationImporter;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PickwareErpInstaller
{
    private Connection $db;
    private MailTemplateInstaller $mailTemplateInstaller;
    private MailTemplateUninstaller $mailTemplateUninstaller;
    private NumberRangeInstaller $numberRangeInstaller;
    private StateMachineInstaller $stateMachineInstaller;
    private DocumentTypeInstaller $documentTypeInstaller;
    private PickwareDocumentUninstaller $pickwareDocumentUninstaller;
    private CustomFieldSetInstaller $customFieldSetInstaller;
    private EntityManager $entityManager;
    private PickwareDocumentTypeInstaller $pickwareDocumentTypeInstaller;
    private ElasticsearchIndexInstaller $elasticsearchIndexInstaller;
    private EventDispatcherInterface $eventDispatcher;
    private FeatureFlagRegistry $featureFlagRegistry;
    private StockMovementProcessTypeInstaller $stockMovementProcessTypeInstaller;
    private ImportExportProfileInstaller $importExportProfileInstaller;
    private SystemConfigService $systemConfigService;
    private SystemConfigInstaller $systemConfigInstaller;
    private RegisteredIndexerSubscriber $registeredIndexerSuberscriber;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->db = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $self->db);
        $self->entityManager = new EntityManager($container, $self->db, $defaultTranslationProvider, new EntityDefinitionQueryHelper());
        $self->mailTemplateInstaller = new MailTemplateInstaller($self->entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($self->entityManager);
        $self->numberRangeInstaller = new NumberRangeInstaller($self->entityManager);
        $self->stateMachineInstaller = new StateMachineInstaller($self->entityManager);
        $self->documentTypeInstaller = new DocumentTypeInstaller($self->entityManager);
        $self->pickwareDocumentUninstaller = PickwareDocumentUninstaller::createForContainer($container);
        $self->customFieldSetInstaller = new CustomFieldSetInstaller($self->entityManager);
        $self->pickwareDocumentTypeInstaller = new PickwareDocumentTypeInstaller($self->db);
        $self->importExportProfileInstaller = new ImportExportProfileInstaller($self->db);
        $self->eventDispatcher = $container->get('event_dispatcher');
        $self->elasticsearchIndexInstaller = new ElasticsearchIndexInstaller($self->db, $self->eventDispatcher);
        $self->featureFlagRegistry = $container->get(FeatureFlagRegistry::class);
        $self->stockMovementProcessTypeInstaller = new StockMovementProcessTypeInstaller(db: $self->db);
        $self->systemConfigService = $container->get(SystemConfigService::class);
        $self->systemConfigInstaller = new SystemConfigInstaller($self->systemConfigService);
        $self->registeredIndexerSuberscriber = new RegisteredIndexerSubscriber(
            $container->get(IndexerQueuer::class),
            $container->get(EntityIndexerRegistry::class),
        );

        return $self;
    }

    public function install(InstallContext $installContext): void
    {
        (new UpsertLocationTypesInstallationStep($this->db))->install();
        $this->mailTemplateInstaller->installMailTemplate(new ReorderMailTemplate(), $installContext->getContext());
        (new CreateReorderNotificationFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        (new CreateTransitionOrderToDoneAfterShippingFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        (new UpsertSpecialStockLocationsInstallationStep($this->db))->install();
        (new CreateInitialWarehouseInstallationStep($this->db, $this->entityManager))->install($installContext->getContext());
        $this->documentTypeInstaller->installDocumentType(new PicklistDocumentType(), $installContext->getContext());
        $this->documentTypeInstaller->installDocumentType(new InvoiceCorrectionDocumentType(), $installContext->getContext());
        $this->mailTemplateInstaller->installMailTemplate(new InvoiceCorrectionMailTemplate(), $installContext->getContext());
        $this->documentTypeInstaller->installDocumentType(new SupplierOrderDocumentType(), $installContext->getContext());
        (new CreateCompletelyReturnedFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        (new CreatePartiallyReturnedFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        (new CreateCompletelyShippedFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        (new CreatePartiallyShippedFlowInstallationStep($this->entityManager))->install($installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new PicklistNumberRange(), $installContext->getContext());
        (new CreateConfigInstallationStep($this->db))->install();
        (new InitializeStockInstallationStep($this->db))->install();
        $this->numberRangeInstaller->ensureNumberRange(new SupplierNumberRange(), $installContext->getContext());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new ImportFileDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new ExportFileDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new GoodsReceiptStockingListDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new GoodsReceiptNoteDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new ReturnOrderStockingListDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new GoodsReceiptDocumentDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new GoodsReceiptPhotoDocumentType());
        $this->importExportProfileInstaller
            ->ensureImportExportProfile(RelativeStockChangeImporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(AbsoluteStockImporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(BinLocationImporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(StockPerProductExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(StockPerWarehouseExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(StockPerStockLocationExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(ProductSupplierConfigurationListItemExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(SupplierOrderExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(StockValuationReportExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(SupplierImporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(StocktakeExporter::TECHNICAL_NAME, logRetentionDays: 90)
            ->ensureImportExportProfile(PurchaseListImporter::TECHNICAL_NAME, logRetentionDays: 90);
        $this->numberRangeInstaller->ensureNumberRange(new SupplierOrderNumberRange(), $installContext->getContext());
        $this->stateMachineInstaller->ensureStateMachine(new SupplierOrderStateMachine(), $installContext->getContext());
        $this->stateMachineInstaller->ensureStateMachine(new SupplierOrderPaymentStateMachine(), $installContext->getContext());
        $this->stateMachineInstaller->ensureStateMachine(new ReturnOrderStateMachine(), $installContext->getContext());
        $this->stateMachineInstaller->ensureStateMachine(new ReturnOrderRefundStateMachine(), $installContext->getContext());
        $this->stateMachineInstaller->ensureStateMachine(new GoodsReceiptStateMachine(), $installContext->getContext());
        $this->mailTemplateInstaller->installMailTemplate(new SupplierOrderMailTemplate(), $installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new ReturnOrderNumberRange(), $installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new InvoiceCorrectionNumberRange(), $installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new StocktakeNumberRange(), $installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new StocktakeCountingProcessNumberRange(), $installContext->getContext());
        $this->numberRangeInstaller->ensureNumberRange(new GoodsReceiptNumberRange(), $installContext->getContext());
        (new AnalyticsInstaller($this->db))->installAggregations([new DemandPlanningAnalyticsAggregation()]);
        (new AnalyticsInstaller($this->db))->installReports([new DemandPlanningAnalyticsReport()]);
        $this->customFieldSetInstaller->installCustomFieldSet(new PickingInstructionCustomFieldSet(), $installContext->getContext());
        $this->customFieldSetInstaller->installCustomFieldSet(new InvoiceCommentCustomFieldSet(), $installContext->getContext());
        $this->customFieldSetInstaller->installCustomFieldSet(new AlternativeEmailCustomFieldSet(), $installContext->getContext());
        $this->customFieldSetInstaller->installCustomFieldSet(new ExternalOrderNumberFieldSet(), $installContext->getContext());
        (new CreateDefaultPickingPropertyInstallationStep($this->entityManager, $this->db))->install($installContext->getContext());
        (new SetExistingOrderFlowsToForceTransitionsInstallationStep($this->db))->install();
        $this->elasticsearchIndexInstaller->installElasticsearchIndices([
            WarehouseDefinition::ENTITY_NAME,
            GoodsReceiptDefinition::ENTITY_NAME,
            SupplierDefinition::ENTITY_NAME,
            SupplierOrderDefinition::ENTITY_NAME,
            ReturnOrderDefinition::ENTITY_NAME,
            BatchDefinition::ENTITY_NAME,
        ]);
        $this->stockMovementProcessTypeInstaller->installStockMovementProcessType(new AutomaticallyShippedStockMovementProcessType(), $installContext->getContext());
        $this->systemConfigInstaller->createSystemConfigValueIfNotExist(
            new SystemConfigDefaultValue(SupplierOrderExporter::SYSTEM_CONFIG_CSV_EXPORT_COLUMNS_KEY, SupplierOrderExporter::EMAIL_CSV_EXPORT_DEFAULT_COLUMNS),
        );
        $this->registeredIndexerSuberscriber->runRegisteredIndexers();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->mailTemplateUninstaller->uninstallMailTemplate(new ReorderMailTemplate(), $uninstallContext->getContext());
        (new CreateReorderNotificationFlowInstallationStep($this->entityManager))->uninstall($uninstallContext->getContext());
        (new CreateCompletelyReturnedFlowInstallationStep($this->entityManager))->uninstall($uninstallContext->getContext());
        (new CreatePartiallyReturnedFlowInstallationStep($this->entityManager))->uninstall($uninstallContext->getContext());
        (new CreateCompletelyShippedFlowInstallationStep($this->entityManager))->uninstall($uninstallContext->getContext());
        (new CreatePartiallyShippedFlowInstallationStep($this->entityManager))->uninstall($uninstallContext->getContext());
        $this->mailTemplateUninstaller->uninstallMailTemplate(new SupplierOrderMailTemplate(), $uninstallContext->getContext());
        $this->pickwareDocumentUninstaller->removeDocumentType(ImportFileDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(ExportFileDocumentType::TECHNICAL_NAME);
        if (Feature::has('MULTI_INVENTORY')) {
            $this->featureFlagRegistry->enable('MULTI_INVENTORY');
        }
        if (Feature::has('RETURNS_MANAGEMENT')) {
            $this->featureFlagRegistry->enable('RETURNS_MANAGEMENT');
        }
    }
}
