<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Invoice;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class InvoiceCommentCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_erp_starter_invoice_comment';
    public const CUSTOM_FIELD_INVOICE_COMMENT = 'pickware_erp_starter_invoice_comment';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'en-GB' => 'Invoice comment',
                    'de-DE' => 'Rechnungskommentar',
                ],
                'translated' => true,
            ],
            [
                OrderDefinition::ENTITY_NAME,
            ],
            [
                new CustomField(
                    self::CUSTOM_FIELD_INVOICE_COMMENT,
                    CustomFieldTypes::TEXT,
                    [
                        'label' => [
                            'en-GB' => 'Invoice comment',
                            'de-DE' => 'Rechnungskommentar',
                        ],
                        'customFieldPosition' => 2,
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
