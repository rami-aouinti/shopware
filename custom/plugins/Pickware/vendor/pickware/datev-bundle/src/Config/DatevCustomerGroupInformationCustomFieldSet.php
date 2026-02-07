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
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class DatevCustomerGroupInformationCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_datev_customer_group_information';
    public const CUSTOM_FIELD_NAME_CUSTOMER_GROUP_SPECIFIC_COMPANY_CODE = 'pickware_datev_customer_group_specific_company_code';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'de-DE' => 'DATEV',
                    'en-GB' => 'DATEV',
                ],
                'translated' => false,
            ],
            [CustomerGroupDefinition::ENTITY_NAME],
            [
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMER_GROUP_SPECIFIC_COMPANY_CODE,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Kundengruppenspezifischer Buchungskreis',
                            'en-GB' => 'Customer-group-specific company code',
                        ],
                        'helpText' => [
                            'de-DE' => 'Hier kannst Du einen kundengruppenspezifischen Buchungskreis eintragen. Dieser Buchungskreis wird dann für alle Kunden dieser Gruppe verwendet, für die kein kundenspezifischer Buchungskreis hinterleget wurde und überschreibt damit verkaufskanalspezifische Buchungskreise.',
                            'en-GB' => 'Here you can enter a customer-group-specific company code. This company code is then used for all customers in this group for whom no customer-specific company code has been defined and thus overwrites sales-channel-specific company codes.',
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
