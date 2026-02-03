<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MoneyBundle;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable class to represent a currency.
 *
 * Keep this object immutable!
 */
class Currency implements JsonSerializable
{
    /**
     * XXX is the official code for no currency.
     */
    public const ISO_CODE_NO_CURRENCY = 'XXX';

    /**
     * 3-character ISO 4217 currency code (like EUR, USD, ...)
     */
    private string $isoCode;

    /**
     * @param string $isoCode 3-character ISO 4217 currency code (like EUR, USD, ...)
     */
    public function __construct(string $isoCode)
    {
        if (mb_strlen($isoCode) !== 3) {
            throw new InvalidArgumentException(sprintf(
                'Passed code "%s" is not a valid ISO 4217 currency code (like EUR, USD, ...).',
                $isoCode,
            ));
        }
        $this->isoCode = mb_strtoupper($isoCode);
    }

    public function jsonSerialize(): array
    {
        return [
            'isoCode' => $this->isoCode,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self($array['isoCode'] ?? '');
    }

    /**
     * @return string 3-character ISO 4217 currency code (like EUR, USD, ...)
     */
    public function getIsoCode(): string
    {
        return $this->isoCode;
    }

    public function equals(self $currency): bool
    {
        return $currency->isoCode === $this->isoCode;
    }
}
