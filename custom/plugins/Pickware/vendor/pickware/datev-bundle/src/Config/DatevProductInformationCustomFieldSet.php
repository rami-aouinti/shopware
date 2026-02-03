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
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class DatevProductInformationCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_datev_product_information';
    public const CUSTOM_FIELD_NAME_PRODUCT_COST_CENTER = 'pickware_datev_product_cost_center';

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
            [ProductDefinition::ENTITY_NAME],
            [
                new CustomField(
                    self::CUSTOM_FIELD_NAME_PRODUCT_COST_CENTER,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Kostenstelle des Produkts',
                            'en-GB' => 'Cost center of the product',
                        ],
                        'helpText' => [
                            'de-DE' => 'Hier kannst Du optional ein Kostenstelle für das Produkt eintragen. Die ' .
                            'Umsätze werden dann nach Kostenstellen gruppiert nach DATEV übertragen.',
                            'en-GB' => 'Here you can optionally enter a cost center for the product. ' .
                            'The revenue is then transferred to DATEV grouped by cost center.',
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
