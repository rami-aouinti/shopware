<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Incompatibility;

use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityProvider;

class ProductSetIncompatibilityProvider implements IncompatibilityProvider
{
    public function getIncompatibilities(): array
    {
        return [
            new MagnalisterIncompatibility(
                configKey: 'general.shopware6flowskipped',
                configValue: '1',
                translatedWarnings: [
                    'en-GB' => 'If the Magnalister setting “Skip Flow Builder during order import” is enabled, set products cannot be split correctly during order import. Make sure that this setting is disabled in Magnalister if you are using Pickware product sets.',
                    'de-DE' => 'Mit der Magnalister Einstellung "Flow Builder beim Bestellimport überspringen" können Stücklisten beim Bestellimport nicht korrekt aufgeteilt werden. Stelle sicher, dass diese Einstellung in Magnalister ausgeschaltet ist, sofern du Pickware Stücklisten verwendest.',
                ],
            ),
        ];
    }
}
