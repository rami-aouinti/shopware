<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\DalBundle\Translation;
use Pickware\DocumentBundle\Installation\DocumentType;

class GoodsReceiptNoteDocumentType extends DocumentType
{
    public const TECHNICAL_NAME = 'pickware_erp_goods_receipt_note';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            new Translation(
                german: 'Wareneingangsbeleg',
                english: 'Goods receipt note',
            ),
            new Translation(
                german: 'Wareneingangsbelege',
                english: 'Goods receipt notes',
            ),
        );
    }
}
