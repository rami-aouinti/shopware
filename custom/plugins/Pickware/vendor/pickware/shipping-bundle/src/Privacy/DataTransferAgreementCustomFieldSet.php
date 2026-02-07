<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Pickware\ShippingBundle\Privacy\EmailTransferAgreement\EmailTransferAgreementCustomField;
use Shopware\Core\Checkout\Order\OrderDefinition;

class DataTransferAgreementCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_shipping_data_transfer_agreement';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'en-GB' => 'Data transfer',
                    'de-DE' => 'Datenübertragung',
                ],
                'translated' => true,
            ],
            [OrderDefinition::ENTITY_NAME],
            [new EmailTransferAgreementCustomField()],
        );
    }
}
