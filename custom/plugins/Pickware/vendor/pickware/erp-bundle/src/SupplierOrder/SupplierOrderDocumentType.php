<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder;

use Pickware\InstallationLibrary\DocumentType\DocumentType;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;

class SupplierOrderDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_supplier_order';
    public const FILENAME_PREFIX = 'supplier_order_';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'de-DE' => 'Lieferantenbestellung',
                'en-GB' => 'Supplier order',
            ],
            self::FILENAME_PREFIX,
            InvoiceRenderer::TYPE,
            [],
        );
    }
}
