<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class ExternalOrderNumberFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_erp_starter_external_order_number';
    public const CUSTOM_FIELD_EXTERNAL_ORDER_NUMBER = 'pickware_erp_starter_external_order_number';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'en-GB' => 'External order number',
                    'de-DE' => 'Externe Auftragsnummer',
                ],
            ],
            [
                OrderDefinition::ENTITY_NAME,
            ],
            [
                new CustomField(
                    self::CUSTOM_FIELD_EXTERNAL_ORDER_NUMBER,
                    CustomFieldTypes::TEXT,
                    [
                        'label' => [
                            'en-GB' => 'External order number',
                            'de-DE' => 'Externe Auftragsnummer',
                        ],
                        'helpText' => [
                            'en-GB' => 'Enter the external order number of imported orders (e.g., from marketplaces) here, e.g., for export to DATEV. This number must be entered before the invoice is created in order to be included as the order number in the DATEV export.',
                            'de-DE' => 'Hinterlege hier die externe Auftragsnummer von importierten Bestellungen (z. B. von Marktplätzen), etwa für den Export zu DATEV. Diese Nummer muss vor der Rechnungserstellung eingetragen sein, um als Auftragsnummer im DATEV Export enthalten zu sein.',
                        ],
                        'customFieldPosition' => 1,
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
