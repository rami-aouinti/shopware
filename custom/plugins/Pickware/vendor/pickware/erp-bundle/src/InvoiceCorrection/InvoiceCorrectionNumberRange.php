<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\InvoiceCorrection;

use Pickware\InstallationLibrary\NumberRange\NumberRange;

class InvoiceCorrectionNumberRange extends NumberRange
{
    public const TECHNICAL_NAME = 'document_' . InvoiceCorrectionDocumentType::TECHNICAL_NAME;

    public function __construct()
    {
        parent::__construct(self::TECHNICAL_NAME, '{n}', 1000, [
            'de-DE' => 'Rechnungskorrekturen',
            'en-GB' => 'Invoice corrections',
        ]);
    }
}
