<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class ReturnTrackingCodesCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_shipping_return_tracking_codes';
    public const CUSTOM_FIELD_NAME_RETURN_TRACKING_CODES = 'pickware_shipping_return_tracking_codes';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'de-DE' => 'Retouren-Tracking-Codes',
                    'en-GB' => 'Return tracking codes',
                ],
                'translated' => false,
            ],
            [OrderDefinition::ENTITY_NAME],
            [
                new CustomField(
                    self::CUSTOM_FIELD_NAME_RETURN_TRACKING_CODES,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Retouren-Tracking-Codes',
                            'en-GB' => 'Return tracking codes',
                        ],
                        'helpText' => [
                            'de-DE' => null,
                            'en-GB' => null,
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
                ),
            ],
        );
    }
}
