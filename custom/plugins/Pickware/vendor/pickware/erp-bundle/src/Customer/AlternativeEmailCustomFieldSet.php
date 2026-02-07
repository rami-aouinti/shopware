<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Customer;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class AlternativeEmailCustomFieldSet extends CustomFieldSet
{
    public const CUSTOM_FIELD_ALTERNATIVE_EMAIL = 'pickware_erp_alternative__email';

    public function __construct()
    {
        parent::__construct(
            self::CUSTOM_FIELD_ALTERNATIVE_EMAIL,
            [
                'label' => [
                    'en-GB' => 'Alternative e-mail address for invoice sending',
                    'de-DE' => 'Abweichende E-Mail-Adresse für den Rechnungsversand',
                ],
                'translated' => true,
            ],
            [
                CustomerDefinition::ENTITY_NAME,
            ],
            [
                new CustomField(
                    self::CUSTOM_FIELD_ALTERNATIVE_EMAIL,
                    CustomFieldTypes::TEXT,
                    [
                        'label' => [
                            'en-GB' => 'E-mail address',
                            'de-DE' => 'E-Mail-Adresse',
                        ],
                        'componentName' => 'sw-email-field',
                        'type' => 'text',
                        'customFieldPosition' => 1,
                        'helpText' => [
                            'de-DE' => 'Trage hier eine alternative E-Mail-Adresse für den Rechnungsversand ein – Bei Geschäftskunden beispielsweise die Adresse der Buchhaltung. Wenn das Feld leer bleibt, wird standardmäßig die im Kundenkonto hinterlegte E-Mail-Adresse verwendet.',
                            'en-GB' => 'Enter an alternative e-mail address for sending invoices here - for business customers, for example, the address of the accounting department. If the field is left blank, the e-mail address stored in the customer account will be used by default.',
                        ],
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
