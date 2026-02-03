<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Installation\Documents;

use Pickware\DalBundle\Translation;
use Pickware\DocumentBundle\Installation\DocumentType;

class CustomsDeclarationDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'customs_declaration_cn23';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            descriptionSingular: new Translation('Zollinhaltserklärung CN23', 'Customs declaration CN23'),
            descriptionPlural: new Translation('Zollinhaltserklärungen CN23', 'Customs declarations CN23'),
        );
    }
}
