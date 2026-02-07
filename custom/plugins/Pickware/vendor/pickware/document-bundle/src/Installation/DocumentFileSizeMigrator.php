<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Installation;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;

class DocumentFileSizeMigrator
{
    private Connection $connection;
    private FilesystemOperator $privateFileSystem;

    public function __construct(Connection $connection, FilesystemOperator $privateFileSystem)
    {
        $this->connection = $connection;
        $this->privateFileSystem = $privateFileSystem;
    }

    /**
     * Refresh the file size of all documents that currently have a file size of -1
     */
    public function migrateFileSize(): void
    {
        $filesWithNoFileSize = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`id`)) AS `id`,
                `path_in_private_file_system` AS `pathInPrivateFileSystem`
            FROM `pickware_document`
            WHERE `file_size_in_bytes` = -1',
        );

        foreach ($filesWithNoFileSize as $fileWithNoFileSize) {
            $size = 0;
            if ($this->privateFileSystem->fileExists($fileWithNoFileSize['pathInPrivateFileSystem'])) {
                $size = $this->privateFileSystem->fileSize($fileWithNoFileSize['pathInPrivateFileSystem']);
            }

            $this->connection->executeStatement(
                'UPDATE `pickware_document`
                SET `file_size_in_bytes` = :size
                WHERE `id` = :id',
                [
                    'id' => hex2bin($fileWithNoFileSize['id']),
                    'size' => $size,
                ],
            );
        }
    }
}
