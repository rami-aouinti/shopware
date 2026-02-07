<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Adapter;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostAdapterException extends CarrierAdapterException
{
    public static function senderCountryMissing(): self
    {
        return new self(new LocalizableJsonApiError([
            'title' => [
                'en' => 'Missing sender country',
                'de' => 'Fehlendes Absenderland',
            ],
            'detail' => [
                'en' => 'The country of the sender address is missing. The country must be specified for shipping with Austrian Post.',
                'de' => 'Das Land der Absenderadresse fehlt. Das Land muss für den Versand mit der Österreichischen Post angegeben werden.',
            ],
        ]));
    }
}
