<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\FeatureFlagBundle\Incompatibility;

use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityProvider;
use Pickware\IncompatibilityBundle\Incompatibility\PluginIncompatibility;

class PickwareFeatureFlagIncompatibilityProvider implements IncompatibilityProvider
{
    public function getIncompatibilities(): array
    {
        return [
            new PluginIncompatibility(
                conflictingPlugin: 'CobTwoFactor',
                translatedWarnings: [
                    'en-GB' => 'The plugin "Two-factor authentication (codeblick)" is not compatible with any Pickware plugin. ' .
                        'This plugin has to be deactivated to ensure the Pickware plugins work properly.',
                    'de-DE' => 'Das Plugin "Zwei-Faktor-Authentisierung (codeblick)" ist mit jeglichen Pickware Plugins nicht ' .
                        'kompatibel. Dieses Plugin sollte deaktiviert werden, um sicherzustellen dass die Pickware ' .
                        'Plugins korrekt funktionieren.',
                ],
            ),
            new PluginIncompatibility(
                conflictingPlugin: 'IwvTwoFactorAuthentication',
                translatedWarnings: [
                    'en-GB' => 'The plugin "Two-factor authentication (IWV)" is not compatible with any Pickware plugin. ' .
                        'This plugin has to be deactivated to ensure the Pickware plugins work properly.',
                    'de-DE' => 'Das Plugin "Zwei-Faktor-Authentisierung (IWV)" ist mit jeglichen Pickware Plugins nicht ' .
                        'kompatibel. Dieses Plugin sollte deaktiviert werden, um sicherzustellen dass die Pickware ' .
                        'Plugins korrekt funktionieren.',
                ],
            ),
        ];
    }
}
