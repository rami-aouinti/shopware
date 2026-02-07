<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Incompatibility;

use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityProvider;
use Pickware\IncompatibilityBundle\Incompatibility\PluginIncompatibility;

class PickwarePosIncompatibilityProvider implements IncompatibilityProvider
{
    public function getIncompatibilities(): array
    {
        return [
            new PluginIncompatibility(
                conflictingPlugin: 'PostLabelCenter',
                translatedWarnings: [
                    'en-GB' => 'The plugin "Post-Labelcenter" is incompatible with Pickware POS. Please deactivate it to allow' .
                        'checkout with Pickware POS.',
                    'Post-Labelcenter',
                    'de-DE' => 'Das Plugin "Post-Labelcenter" ist mit Pickware POS nicht kompatibel. Bitte deaktiviere es,' .
                        ' um den Checkout mit Pickware POS zu ermöglichen.',
                ],
            ),
        ];
    }
}
