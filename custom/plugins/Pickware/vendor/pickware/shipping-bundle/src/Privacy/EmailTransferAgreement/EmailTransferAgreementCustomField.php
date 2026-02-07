<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy\EmailTransferAgreement;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class EmailTransferAgreementCustomField extends CustomField
{
    public const TECHNICAL_NAME = 'pickware_shipping_allow_email_transfer';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            CustomFieldTypes::CHECKBOX,
            [
                'type' => 'checkbox',
                'label' => [
                    'en-GB' => 'Allow transfer of email address to the shipping provider.',
                    'de-DE' => 'Weiterleitung der E-Mail-Adresse an den Versanddienstleister erlauben.',
                ],
                'customFieldPosition' => 1,
            ],
            allowCartExpose: true,
        );
    }
}
