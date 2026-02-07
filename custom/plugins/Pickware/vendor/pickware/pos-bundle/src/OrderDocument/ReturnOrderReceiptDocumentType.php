<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument;

use Pickware\InstallationLibrary\DocumentType\DocumentType;

class ReturnOrderReceiptDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_pos_return_order_receipt';
    public const FILENAME_PREFIX = 'return_order_receipt_';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Rückgabebeleg',
                'en-GB' => 'Return receipt',
            ],
            self::FILENAME_PREFIX,
        );
    }
}
