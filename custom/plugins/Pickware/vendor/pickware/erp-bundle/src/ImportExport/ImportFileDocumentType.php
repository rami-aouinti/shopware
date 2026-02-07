<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Pickware\DalBundle\Translation;
use Pickware\DocumentBundle\Installation\DocumentType;

class ImportFileDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_import';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            descriptionSingular: new Translation('Importierte Datei', 'Imported file'),
            descriptionPlural: new Translation('Importierte Dateien', 'Imported files'),
        );
    }
}
