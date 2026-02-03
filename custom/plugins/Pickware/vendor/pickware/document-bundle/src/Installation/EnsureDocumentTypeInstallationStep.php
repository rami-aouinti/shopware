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
use Pickware\PhpStandardLibrary\Json\Json;

/**
 * @deprecated Use PickwareDocumentTypeInstaller::class instead.
 */
class EnsureDocumentTypeInstallationStep
{
    private Connection $db;
    private array $documentTypeDescriptionMapping;

    public function __construct(Connection $db, array $documentTypeDescriptionMapping)
    {
        $this->db = $db;
        $this->documentTypeDescriptionMapping = $documentTypeDescriptionMapping;
    }

    /**
     * @deprecated Use installDocumentType from PickwareDocumentTypeInstaller::class instead.
     */
    public function install(): void
    {
        $sql = '
            INSERT INTO `pickware_document_type`
                (`technical_name`, `singular_description`, `plural_description`, `created_at`)
            VALUES
                (:technicalName, :singularDescription, :pluralDescription, UTC_TIMESTAMP(3))
            ON DUPLICATE KEY UPDATE
                `singular_description` = VALUES(`singular_description`),
                `plural_description` = VALUES(`plural_description`),
                `updated_at` = UTC_TIMESTAMP(3)';

        foreach ($this->documentTypeDescriptionMapping as $technicalName => $description) {
            $description = Json::stringify(['de' => $description, 'en' => $description]);
            $this->db->executeStatement($sql, [
                'technicalName' => $technicalName,
                'singularDescription' => $description,
                'pluralDescription' => $description,
            ]);
        }
    }
}
