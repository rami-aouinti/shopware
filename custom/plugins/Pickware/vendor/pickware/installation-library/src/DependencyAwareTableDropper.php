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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DependencyAwareTableDropper
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function createForContainer(ContainerInterface $container): self
    {
        return new self($container->get(Connection::class));
    }

    public function dropTables(array $tableNames): self
    {
        $dependenciesByTableName = $this->resolveDependencies($tableNames);

        if (count($dependenciesByTableName) === 0) {
            return $this;
        }

        $dropTableStatements = [];
        $droppedTableNames = [];
        $iterations = 0;
        while (count($dependenciesByTableName) > 0 && $iterations < 50) {
            foreach ($dependenciesByTableName as $referencedTableName => $referencingTableNames) {
                if (
                    count(array_diff($referencingTableNames, $droppedTableNames)) === 0
                    // If a table only references itself, it can be dropped
                    || (count($referencingTableNames) === 1 && $referencingTableNames[0] === $referencedTableName)
                ) {
                    $dropTableStatements[] = sprintf('DROP TABLE IF EXISTS `%s`;', $referencedTableName);
                    $droppedTableNames[] = $referencedTableName;
                    unset($dependenciesByTableName[$referencedTableName]);
                }
            }

            $iterations += 1;
        }

        if (count($dependenciesByTableName) > 0) {
            throw new LogicException(sprintf(
                'Table dependencies remain unresolved after 50 iterations: %s',
                implode(', ', array_keys($dependenciesByTableName)),
            ));
        }

        $this->connection->executeStatement(implode('', $dropTableStatements));

        return $this;
    }

    private function resolveDependencies(array $tableNames): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT
                TABLE_NAME as referencing_table_name,
                COLUMN_NAME as referencing_column_name,
                CONSTRAINT_NAME as constraint_name,
                REFERENCED_TABLE_NAME as referenced_table_name,
                REFERENCED_COLUMN_NAME as referenced_column_name
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = :databaseName AND
                REFERENCED_TABLE_NAME IN (:tableNames)',
            [
                'databaseName' => $this->connection->getDatabase(),
                'tableNames' => $tableNames,
            ],
            ['tableNames' => ArrayParameterType::STRING],
        );

        $dependencies = [];
        foreach ($result as $foreignKeyRelationship) {
            $referencingTableName = $foreignKeyRelationship['referencing_table_name'];
            $referencedTableName = $foreignKeyRelationship['referenced_table_name'];

            if (!in_array($referencingTableName, $tableNames)) {
                throw new LogicException(sprintf(
                    'Table "%s" has to be dropped to drop "%s" but dropping this table was not requested!',
                    $referencingTableName,
                    $referencedTableName,
                ));
            }

            if (!array_key_exists($referencedTableName, $dependencies)) {
                $dependencies[$referencedTableName] = [];
            }
            $dependencies[$referencedTableName][] = $referencingTableName;
        }

        // All remaining tables have no incoming foreign key relations and are thus not detected by the previous loop.
        // Adding them here ensures all tables requested to be dropped will also be dropped.
        foreach (array_diff($tableNames, array_keys($dependencies)) as $noDependencyTableName) {
            $dependencies[$noDependencyTableName] = [];
        }

        return $dependencies;
    }
}
