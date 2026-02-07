<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class MovementReferenceNumberCustomField extends CustomField
{
    public const TECHNICAL_NAME = 'pickware_shipping_movement_reference_number';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            CustomFieldTypes::TEXT,
            [
                'type' => 'text',
                'label' => [
                    'de-DE' => 'Movement Reference Number (MRN)',
                    'en-GB' => 'Movement reference number (MRN)',
                ],
                'helpText' => [
                    'de-DE' => 'Die MRN erhältst du bei einer elektronischen Zollanmeldung dieser Bestellung, z.B. via ATLAS',
                    'en-GB' => 'You will receive the MRN when you electronically declare this order to customs, e.g. via ATLAS',
                ],
                'placeholder' => [
                    'de-DE' => null,
                    'en-GB' => null,
                ],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'customFieldPosition' => 1,
            ],
            allowCartExpose: true,
        );
    }
}
