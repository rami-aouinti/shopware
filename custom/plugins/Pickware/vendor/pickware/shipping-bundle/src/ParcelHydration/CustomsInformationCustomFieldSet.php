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
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomsInformationCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_shipping_customs_information';
    public const CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_DESCRIPTION = 'pickware_shipping_customs_information_description';
    public const CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_TARIFF_NUMBER = 'pickware_shipping_customs_information_tariff_number';
    public const CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_COUNTRY_OF_ORIGIN = 'pickware_shipping_customs_information_country_of_origin';
    public const CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_CUSTOMS_VALUE = 'pickware_shipping_customs_information_customs_value';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'de-DE' => 'Zollinformationen',
                    'en-GB' => 'Customs information',
                ],
                'translated' => true,
            ],
            [ProductDefinition::ENTITY_NAME],
            [
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_DESCRIPTION,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Beschreibung',
                            'en-GB' => 'Description',
                        ],
                        'helpText' => [
                            'de-DE' => 'Eine detaillierte Beschreibung des Artikels, z.B. "Herren-Baumwollhemden". ' .
                                'Allgemeine Beschreibungen wie z.B. "Ersatzteile", "Muster" oder "Lebensmittel" sind ' .
                                'nicht erlaubt. Wenn du das Feld freilässt, wird der Produktname verwendet.',
                            'en-GB' => 'A detailed description of the item, e.g. "men\'s cotton shirts". General ' .
                                'descriptions e.g. "spare parts", "samples" or "food products" are not permitted. If ' .
                                'you leave this field blank the product name will be used.',
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
                    self::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_TARIFF_NUMBER,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Zolltarifnummer (nach HS)',
                            'en-GB' => 'HS customs tariff number',
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
                        'customFieldPosition' => 4,
                    ],
                    allowCartExpose: true,
                ),
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_COUNTRY_OF_ORIGIN,
                    CustomFieldTypes::TEXT,
                    [
                        'type' => 'text',
                        'label' => [
                            'de-DE' => 'Herkunftsland',
                            'en-GB' => 'Country of origin',
                        ],
                        'placeholder' => [
                            'de-DE' => null,
                            'en-GB' => null,
                        ],
                        'componentName' => 'pw-shipping-country-select-by-iso-code',
                        'customFieldType' => 'text',
                        'customFieldPosition' => 5,
                    ],
                    allowCartExpose: true,
                ),
                new CustomField(
                    self::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_CUSTOMS_VALUE,
                    CustomFieldTypes::FLOAT,
                    [
                        'type' => 'number',
                        'label' => [
                            'de-DE' => 'Fallback-Zollwert in Standardwährung',
                            'en-GB' => 'Fallback customs value in default currency',
                        ],
                        'placeholder' => [
                            'de-DE' => null,
                            'en-GB' => null,
                        ],
                        'helpText' => [
                            'de-DE' => 'Der Fallback-Zollwert wird auf Zolldokumenten für Bestellpostionen ohne ' .
                                'Preis genutzt. Sollte der Fallback-Zollwert ebenfalls 0 sein wird der Preis des ' .
                                'Produkts als Zollwert ausgefüllt.',
                            'en-GB' => 'The fallback customs value is used on customs documents for order line items ' .
                                'without a price. If the fallback customs value is also 0, the price of the product ' .
                                'is filled in as the customs value.',
                        ],
                        'numberType' => 'float',
                        'customFieldType' => 'number',
                        'customFieldPosition' => 6,
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
