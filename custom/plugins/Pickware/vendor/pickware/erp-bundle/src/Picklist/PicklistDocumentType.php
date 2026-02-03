<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Pickware\InstallationLibrary\DocumentType\DocumentType;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;

class PicklistDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_picklist';
    public const FILENAME_PREFIX = 'picklist_';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Pickliste',
                'en-GB' => 'Picklist',
            ],
            self::FILENAME_PREFIX,
            InvoiceRenderer::TYPE,
            [
                'displayLineItems' => false,
                'displayLineItemPosition' => false,
                'displayPrices' => false,
            ],
        );
    }
}
