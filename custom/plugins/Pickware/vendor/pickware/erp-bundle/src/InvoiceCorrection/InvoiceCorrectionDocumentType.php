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

use Pickware\InstallationLibrary\DocumentType\DocumentType;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;

class InvoiceCorrectionDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_invoice_correction';
    public const FILENAME_PREFIX = 'invoice_correction_';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Rechnungskorrektur',
                'en-GB' => 'Invoice correction',
            ],
            self::FILENAME_PREFIX,
            InvoiceRenderer::TYPE,
        );
    }
}
