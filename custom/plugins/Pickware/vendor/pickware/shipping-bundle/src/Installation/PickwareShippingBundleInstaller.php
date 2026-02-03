<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Installation\DocumentUninstaller as PickwareDocumentUninstaller;
use Pickware\DocumentBundle\Installation\PickwareDocumentTypeInstaller;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSetInstaller;
use Pickware\PickwareErpStarter\Config\Subscriber\TopLevelNavigationFeatureFlag;
use Pickware\ShippingBundle\Config\CommonShippingConfig;
use Pickware\ShippingBundle\Config\ConfigService;
use Pickware\ShippingBundle\Installation\Documents\CommercialInvoiceDocumentType;
use Pickware\ShippingBundle\Installation\Documents\CustomsDeclarationDocumentType;
use Pickware\ShippingBundle\Installation\Documents\OtherDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\StampDocumentType;
use Pickware\ShippingBundle\Installation\Documents\WaybillDocumentType;
use Pickware\ShippingBundle\ParcelHydration\CustomsInformationCustomFieldSet;
use Pickware\ShippingBundle\ParcelHydration\OrderCustomsInformationCustomFieldSet;
use Pickware\ShippingBundle\Privacy\DataTransferAgreementCustomFieldSet;
use Pickware\ShippingBundle\Shipment\ReturnTrackingCodesCustomFieldSet;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareShippingBundleInstaller
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly PickwareDocumentUninstaller $pickwareDocumentUninstaller,
        private readonly CustomFieldSetInstaller $customFieldSetInstaller,
        private readonly PickwareDocumentTypeInstaller $pickwareDocumentTypeInstaller,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public static function createFromContainer(ContainerInterface $container): self
    {
        $connection = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $connection);
        $entityManager = new EntityManager(
            $container,
            $connection,
            $defaultTranslationProvider,
            new EntityDefinitionQueryHelper(),
        );

        $featureFlags = [];

        if (class_exists(TopLevelNavigationFeatureFlag::class)) {
            // Old ERP version expect this feature flag to be present
            $featureFlags[] = new TopLevelNavigationFeatureFlag();
        }

        return new self(
            $container->get(SystemConfigService::class),
            PickwareDocumentUninstaller::createForContainer($container),
            new CustomFieldSetInstaller($entityManager),
            new PickwareDocumentTypeInstaller($connection),
            new FeatureFlagService($featureFlags, $container->get('event_dispatcher')),
        );
    }

    public function install(InstallContext $installContext): void
    {
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new ShippingLabelDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new ReturnLabelDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new CustomsDeclarationDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new CommercialInvoiceDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new WaybillDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new StampDocumentType());
        $this->pickwareDocumentTypeInstaller->ensureDocumentType(new OtherDocumentType());
        $this->customFieldSetInstaller->installCustomFieldSet(
            new CustomsInformationCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->customFieldSetInstaller->installCustomFieldSet(
            new DataTransferAgreementCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->customFieldSetInstaller->installCustomFieldSet(
            new OrderCustomsInformationCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->customFieldSetInstaller->installCustomFieldSet(
            new ReturnTrackingCodesCustomFieldSet(),
            $installContext->getContext(),
        );
        $this->upsertDefaultConfiguration();
    }

    public function uninstall(): void
    {
        $this->pickwareDocumentUninstaller->removeDocumentType(ShippingLabelDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(ReturnLabelDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(CustomsDeclarationDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(CommercialInvoiceDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(WaybillDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(StampDocumentType::TECHNICAL_NAME);
        $this->pickwareDocumentUninstaller->removeDocumentType(OtherDocumentType::TECHNICAL_NAME);
    }

    private function upsertDefaultConfiguration(): void
    {
        $shippingConfigService = new ConfigService($this->systemConfigService);
        $currentConfig = $shippingConfigService->getConfigForSalesChannel(
            CommonShippingConfig::CONFIG_DOMAIN,
            null,
        );
        $defaultConfig = CommonShippingConfig::createDefault();
        $defaultConfig->apply(new CommonShippingConfig($currentConfig));
        $shippingConfigService->saveConfigForSalesChannel($defaultConfig->getConfig(), null);
    }
}
