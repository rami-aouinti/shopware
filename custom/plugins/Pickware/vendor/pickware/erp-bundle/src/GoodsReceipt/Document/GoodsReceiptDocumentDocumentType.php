<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Document;

use Pickware\DalBundle\Translation;
use Pickware\DocumentBundle\Installation\DocumentType;

class GoodsReceiptDocumentDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_goods_receipt_document';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            Translation::fromArray([
                'de' => 'Wareneingangsdokument',
                'en' => 'Goods receipt document',
            ]),
            Translation::fromArray([
                'de' => 'Wareneingangsdokumente',
                'en' => 'Goods receipt documents',
            ]),
        );
    }
}
