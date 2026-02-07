<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BundleMigrationDropper
{
    private function __construct(private readonly Connection $connection) {}

    public static function createForContainer(ContainerInterface $container): self
    {
        return new self($container->get(Connection::class));
    }

    public function dropMigrationsForBundle(string $migrationNamespacePrefix): self
    {
        $this->connection->executeStatement(
            'DELETE FROM `migration` WHERE `class` LIKE :migrationNamespace',
            // We need to match a single backslash for each namespace separation given in the migration namespace.
            // Since the MySQL parser needs its own set of escaping backslashes, we double them here.
            ['migrationNamespace' => sprintf('%s%%', str_replace('\\', '\\\\', $migrationNamespacePrefix))],
        );

        return $this;
    }
}
