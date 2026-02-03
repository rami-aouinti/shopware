<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\DpdBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\ShippingBundle\Config\ConfigService;
use Pickware\ShippingBundle\Installation\CarrierInstaller;
use Pickware\ShippingBundle\Installation\CarrierUninstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareDpdBundleInstaller
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

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $self->connection = $connection;
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $connection);
        $entityManager = new EntityManager($container, $connection, $defaultTranslationProvider, new EntityDefinitionQueryHelper());
        $self->carrierInstaller = new CarrierInstaller($connection);
        $self->carrierUninstaller = CarrierUninstaller::createForContainer($container);
        $self->mailTemplateInstaller = new MailTemplateInstaller($entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($entityManager);
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $container->get(SystemConfigService::class);
        $self->systemConfigService = $systemConfigService;

        return $self;
    }

    public function install(Context $context): void
    {
        $this->mailTemplateInstaller->installMailTemplate(new ReturnLabelMailTemplate(), $context);
        $this->carrierInstaller->installCarrier(new DpdCarrier());
        $this->upsertDefaultConfiguration();
    }

    public function uninstall(Context $context): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config
            WHERE configuration_key LIKE :domain',
            [
                'domain' => DpdConfig::CONFIG_DOMAIN . '.%',
            ],
        );

        $this->carrierUninstaller->uninstallCarrier(DpdCarrier::TECHNICAL_NAME);
        $this->mailTemplateUninstaller->uninstallMailTemplate(new ReturnLabelMailTemplate(), $context);
    }

    private function upsertDefaultConfiguration(): void
    {
        $shippingConfigService = new ConfigService($this->systemConfigService);
        $currentConfig = $shippingConfigService->getConfigForSalesChannel(DpdConfig::CONFIG_DOMAIN, null);
        $defaultConfig = DpdConfig::createDefault();
        $defaultConfig->apply(new DpdConfig($currentConfig));
        $shippingConfigService->saveConfigForSalesChannel($defaultConfig->getConfig(), null);
    }
}
