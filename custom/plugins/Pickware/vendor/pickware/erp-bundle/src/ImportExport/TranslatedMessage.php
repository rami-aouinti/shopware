<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

interface TranslatedMessage
{
    /**
     * Returns the content of the message indexed with the locale with which the value is translated. Example:
     * [
     *     'de-DE' => 'Meine Nachricht ist auf Deutsch',
     *     'en-GB' => 'My message is in english',
     * ]
     */
    public function getContent(): array;

    public function getMeta(): array;
}
