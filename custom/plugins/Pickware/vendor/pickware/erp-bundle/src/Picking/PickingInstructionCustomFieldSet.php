<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class PickingInstructionCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_erp_starter_picking_instruction';
    public const CUSTOM_FIELD_PICKING_INSTRUCTION = 'pickware_erp_starter_picking_instruction';

    public function __construct()
    {
        parent::__construct(
            self::TECHNICAL_NAME,
            [
                'label' => [
                    'en-GB' => 'Picking instruction',
                    'de-DE' => 'Pickanweisung',
                ],
                'translated' => true,
            ],
            [
                ProductDefinition::ENTITY_NAME,
                OrderDefinition::ENTITY_NAME,
            ],
            [
                new CustomField(
                    self::CUSTOM_FIELD_PICKING_INSTRUCTION,
                    CustomFieldTypes::TEXT,
                    [
                        'label' => [
                            'en-GB' => 'Picking instruction',
                            'de-DE' => 'Pickanweisung',
                        ],
                        'customFieldPosition' => 1,
                    ],
                    allowCartExpose: true,
                ),
            ],
        );
    }
}
