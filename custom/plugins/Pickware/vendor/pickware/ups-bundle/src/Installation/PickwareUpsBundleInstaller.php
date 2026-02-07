<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\ShippingBundle\Config\ConfigService;
use Pickware\ShippingBundle\Installation\CarrierInstaller;
use Pickware\ShippingBundle\Installation\CarrierUninstaller;
use Pickware\UpsBundle\Config\UpsConfig;
use Pickware\UpsBundle\ReturnLabel\ReturnLabelMailTemplate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareUpsBundleInstaller
{
    private CarrierInstaller $carrierInstaller;
    private CarrierUninstaller $carrierUninstaller;
    private Connection $connection;
    private MailTemplateInstaller $mailTemplateInstaller;
    private MailTemplateUninstaller $mailTemplateUninstaller;
    private SystemConfigService $systemConfigService;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->connection = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $self->connection);
        $entityManager = new EntityManager($container, $self->connection, $defaultTranslationProvider, new EntityDefinitionQueryHelper());
        $self->carrierInstaller = new CarrierInstaller($self->connection);
        $self->carrierUninstaller = CarrierUninstaller::createForContainer($container);
        $self->mailTemplateInstaller = new MailTemplateInstaller($entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($entityManager);
        $self->systemConfigService = $container->get(SystemConfigService::class);

        return $self;
    }

    public function install(Context $context): void
    {
        $this->mailTemplateInstaller->installMailTemplate(new ReturnLabelMailTemplate(), $context);

        $this->carrierInstaller->installCarrier(new UpsCarrier());
        $this->upsertDefaultConfiguration();
    }

    public function uninstall(Context $context): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config
            WHERE configuration_key LIKE :domain',
            [
                'domain' => UpsConfig::CONFIG_DOMAIN . '.%',
            ],
        );

        $this->carrierUninstaller->uninstallCarrier(UpsCarrier::TECHNICAL_NAME);
        $this->mailTemplateUninstaller->uninstallMailTemplate(new ReturnLabelMailTemplate(), $context);
    }

    private function upsertDefaultConfiguration(): void
    {
        $shippingConfigService = new ConfigService($this->systemConfigService);
        $currentConfig = $shippingConfigService->getConfigForSalesChannel(UpsConfig::CONFIG_DOMAIN, null);
        $defaultConfig = UpsConfig::createDefault();
        $defaultConfig->apply(new UpsConfig($currentConfig));
        $shippingConfigService->saveConfigForSalesChannel($defaultConfig->getConfig(), null);
    }
}
