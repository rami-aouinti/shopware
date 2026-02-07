<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\DhlExpressBundle\Config\DhlExpressConfig;
use Pickware\DhlExpressBundle\ReturnLabel\ReturnLabelMailTemplate;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateInstaller;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUninstaller;
use Pickware\ShippingBundle\Installation\CarrierInstaller;
use Pickware\ShippingBundle\Installation\CarrierUninstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareDhlExpressBundleInstaller
{
    private CarrierInstaller $carrierInstaller;
    private CarrierUninstaller $carrierUninstaller;
    private Connection $connection;
    private MailTemplateInstaller $mailTemplateInstaller;
    private MailTemplateUninstaller $mailTemplateUninstaller;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->connection = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $self->connection);
        $entityManager = new EntityManager(
            $container,
            $self->connection,
            $defaultTranslationProvider,
            new EntityDefinitionQueryHelper(),
        );
        $self->carrierInstaller = new CarrierInstaller($self->connection);
        $self->carrierUninstaller = CarrierUninstaller::createForContainer($container);
        $self->mailTemplateInstaller = new MailTemplateInstaller($entityManager);
        $self->mailTemplateUninstaller = new MailTemplateUninstaller($entityManager);

        return $self;
    }

    public function install(Context $context): void
    {
        $this->mailTemplateInstaller->installMailTemplate(new ReturnLabelMailTemplate(), $context);
        $this->carrierInstaller->installCarrier(new DhlExpressCarrier());
    }

    public function uninstall(Context $context): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config
            WHERE configuration_key LIKE :domain',
            [
                'domain' => DhlExpressConfig::CONFIG_DOMAIN . '.%',
            ],
        );

        $this->carrierUninstaller->uninstallCarrier(DhlExpressCarrier::TECHNICAL_NAME);
        $this->mailTemplateUninstaller->uninstallMailTemplate(new ReturnLabelMailTemplate(), $context);
    }
}
