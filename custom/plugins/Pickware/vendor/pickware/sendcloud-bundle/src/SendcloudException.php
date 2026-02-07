<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle;

use function Pickware\PhpStandardLibrary\Language\makeSentence;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;

class SendcloudException extends CarrierAdapterException
{
    protected static function flatErrors(array $errors, ?string $keys = null): array
    {
        $errorMessages = [];
        foreach ($errors as $key => $error) {
            if (is_array($error)) {
                $messages = self::flatErrors($error, sprintf('%s[%s]', $keys, $key));

                array_push($errorMessages, ...$messages);

                continue;
            }
            $errorMessages[] = makeSentence(sprintf('%s: %s', $keys ?? $key, $error));
        }

        return $errorMessages;
    }
}
