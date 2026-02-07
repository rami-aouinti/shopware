<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class DatevCustomerInformationCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_datev_customer_information';
    public const CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_DEBTOR_ACCOUNT = 'pickware_datev_customer_specific_debtor_account';
    public const CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_COMPANY_CODE = 'pickware_datev_customer_specific_company_code';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'de-DE' => 'DATEV',
                    'en-GB' => 'DATEV',
                ],
                'translated' => true,
            ],
            [CustomerDefinition::ENTITY_NAME],
            [
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_DEBTOR_ACCOUNT,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Kundenspezifisches Debitorenkonto',
                            'en-GB' => 'Customer-specific debtor account',
                        ],
                        'helpText' => [
                            'de-DE' => 'Hier kannst Du ein kundenspezifisches Debitorenkonto eintragen. ' .
                             'Dieses Konto wird dann für alle den Kunden betreffenden DATEV Buchungssätze ' .
                             'verwendet und überschreibt damit ermittelte Sammel- oder Einzeldebitorenkonten.',
                            'en-GB' => 'Here you can enter a customer-specific debtor account. ' .
                             'This account is then used for all DATEV posting records related to the customer and overwrites ' .
                             'determined collective or individual debtor accounts.',
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
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMER_SPECIFIC_COMPANY_CODE,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Kundenspezifischer Buchungskreis',
                            'en-GB' => 'Customer-specific company code',
                        ],
                        'helpText' => [
                            'de-DE' => 'Hier kannst Du einen kundenspezifischen Buchungskreis eintragen. Dieser Buchungskreis wird dann für alle den Kunden betreffenden DATEV Buchungssätze verwendet und überschreibt damit verkaufskanal- oder kundengruppenspezifische Buchungskreise.',
                            'en-GB' => 'Here you can enter a customer-specific company code. This company code is then used for all DATEV posting records related to the customer and overwrites determined sales-channel-specific or customer-group-specific company codes.',
                        ],
                        'placeholder' => [
                            'de-DE' => null,
                            'en-GB' => null,
                        ],
                        'componentName' => 'sw-field',
                        'customFieldType' => 'text',
                        'customFieldPosition' => 2,
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
