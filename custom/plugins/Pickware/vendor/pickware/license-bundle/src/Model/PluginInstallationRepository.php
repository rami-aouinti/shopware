<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Model;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\LicenseBundle\PickwareLicenseBundle;
use Shopware\Core\Framework\Context;

class PluginInstallationRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
    ) {}

    public function getPluginInstallation(Context $context): PluginInstallationEntity
    {
        $this->ensurePluginInstallationExists();

        /** @var PluginInstallationEntity $pluginInstallation */
        $pluginInstallation = $this->entityManager->getByPrimaryKey(
            PluginInstallationDefinition::class,
            PickwareLicenseBundle::PLUGIN_INSTALLATION_ID,
            $context,
        );

        return $pluginInstallation;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(array $payload, Context $context): void
    {
        $this->ensurePluginInstallationExists();

        $payload['id'] = PickwareLicenseBundle::PLUGIN_INSTALLATION_ID;
        $this->entityManager->update(
            PluginInstallationDefinition::class,
            [$payload],
            $context,
        );
    }

    public function ensurePluginInstallationExists(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_license_bundle_plugin_installation` (
                `id`,
                `installation_id`,
                `created_at`
            ) VALUES (
                :id,
                ' . SqlUuid::UUID_V4_GENERATION . ',
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            ['id' => hex2bin(mb_strtoupper(PickwareLicenseBundle::PLUGIN_INSTALLATION_ID))],
        );
    }
}
