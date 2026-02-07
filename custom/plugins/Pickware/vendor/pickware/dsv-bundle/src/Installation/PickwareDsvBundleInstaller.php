<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Installation;

use Doctrine\DBAL\Connection;
use Pickware\DsvBundle\Config\DsvConfig;
use Pickware\ShippingBundle\Installation\CarrierInstaller;
use Pickware\ShippingBundle\Installation\CarrierUninstaller;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PickwareDsvBundleInstaller
{
    private CarrierInstaller $carrierInstaller;
    private CarrierUninstaller $carrierUninstaller;
    private Connection $connection;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->connection = $container->get(Connection::class);
        $self->carrierInstaller = new CarrierInstaller($self->connection);
        $self->carrierUninstaller = CarrierUninstaller::createForContainer($container);

        return $self;
    }

    public function install(): void
    {
        $this->carrierInstaller->installCarrier(new DsvCarrier());
    }

    public function uninstall(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config
            WHERE configuration_key LIKE :domain',
            [
                'domain' => DsvConfig::CONFIG_DOMAIN . '.%',
            ],
        );

        $this->carrierUninstaller->uninstallCarrier(DsvCarrier::TECHNICAL_NAME);
    }
}
