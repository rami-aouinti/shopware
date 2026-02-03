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

use Pickware\InstallationLibrary\MailTemplate\MailTemplate;
use Pickware\InstallationLibrary\MailTemplate\MailTemplateTranslation;

class CouponReceiptMailTemplate extends MailTemplate
{
    public const TECHNICAL_NAME = 'pickware_pos_coupon_receipt';

    /**
     * The associations that have to be loaded for variable "order" so the template is working.
     */
    public const ORDER_ASSOCIATIONS = ['orderCustomer.salutation'];

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            MailTemplateTranslation::loadFromDir(__DIR__ . '/../Resources/mail-templates/coupon-receipt'),
            [
                'order' => 'order',
                'shopName' => null,
            ],
        );
    }
}
