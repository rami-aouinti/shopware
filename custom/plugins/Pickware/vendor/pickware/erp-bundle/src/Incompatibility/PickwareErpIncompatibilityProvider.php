<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Incompatibility;

use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityProvider;
use Pickware\IncompatibilityBundle\Incompatibility\PluginIncompatibility;
use Pickware\IncompatibilityBundle\Incompatibility\SalesChannelIncompatibility;

class PickwareErpIncompatibilityProvider implements IncompatibilityProvider
{
    public const ZETTLE_POS_SALES_CHANNEL_TYPE_ID = '1ce0868f406d47d98cfe4b281e62f099';

    public function getIncompatibilities(): array
    {
        return [
            new SalesChannelIncompatibility(
                self::ZETTLE_POS_SALES_CHANNEL_TYPE_ID,
                [
                    'en-GB' => 'The Zettle POS Sales Channel is incompatible with Pickware ERP. Please deactivate the ' .
                        'sales channel to prevent unknown stock movements of your products.',
                    'de-DE' => 'Der Zettle POS Verkaufskanal is nicht mit Pickware ERP kompatibel. Bitte deaktiviere ' .
                        'den Verkaufskanal um unbekannte Bestandsbewegungen deiner Produkte zu vermeiden.',
                ],
            ),
            new PluginIncompatibility(
                conflictingPlugin: 'SwagCustomizedProducts',
                translatedWarnings: [
                    'en-GB' => 'The "Custom Products" plugin is not fully compatible with Pickware ERP, as ' .
                        'option-dependent surcharges cannot be automatically taken into account in returns.',
                    'de-DE' => 'Das Plugin "Custom Products" ist nicht vollständig mit Pickware ERP kompatibel, da ' .
                        'optionsabhängige Zuschläge nicht automatisch in Retouren berücksichtigt werden können.',
                ],
            ),
        ];
    }
}
