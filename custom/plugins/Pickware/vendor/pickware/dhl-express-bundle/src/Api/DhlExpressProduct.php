<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DhlExpressProduct implements JsonSerializable
{
    public const CODE_DHL_DOMESTIC_EXPRESS = 'N';
    public const CODE_DHL_DOMESTIC_EXPRESS_09_00 = 'I';
    public const CODE_DHL_DOMESTIC_EXPRESS_10_00 = 'O';
    public const CODE_DHL_DOMESTIC_EXPRESS_12_00 = '1';
    public const CODE_DHL_EXPRESS_ENVELOPE = 'X';
    public const CODE_DHL_EXPRESS_WORLDWIDE_EU = 'U';
    public const CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC = 'P';
    public const CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_09_00 = 'E';
    public const CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_10_30 = 'M';
    public const CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_12_00 = 'Y';
    public const CODE_DHL_EXPRESS_WORLDWIDE_DOC = 'D';
    public const CODE_DHL_EXPRESS_WORLDWIDE_DOC_09_00 = 'K';
    public const CODE_DHL_EXPRESS_WORLDWIDE_DOC_10_30 = 'L';
    public const CODE_DHL_EXPRESS_WORLDWIDE_DOC_12_00 = 'T';
    public const CODE_DHL_ECONOMY_SELECT_EU = 'W';
    public const CODE_DHL_ECONOMY_SELECT_NON_EU = 'H';
    private const DOMESTIC_EXPRESS_MAPPING = [
        '24' => self::CODE_DHL_DOMESTIC_EXPRESS,
        '09' => self::CODE_DHL_DOMESTIC_EXPRESS_09_00,
        '10' => self::CODE_DHL_DOMESTIC_EXPRESS_10_00,
        '12' => self::CODE_DHL_DOMESTIC_EXPRESS_12_00,
    ];
    private const EXPRESS_WORLDWIDE_MAPPING = [
        'doc' => [
            '24' => self::CODE_DHL_EXPRESS_WORLDWIDE_DOC,
            '09' => self::CODE_DHL_EXPRESS_WORLDWIDE_DOC_09_00,
            '10' => self::CODE_DHL_EXPRESS_WORLDWIDE_DOC_10_30,
            '12' => self::CODE_DHL_EXPRESS_WORLDWIDE_DOC_12_00,
        ],
        'non-doc' => [
            '24' => self::CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC,
            '09' => self::CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_09_00,
            '10' => self::CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_10_30,
            '12' => self::CODE_DHL_EXPRESS_WORLDWIDE_NON_DOC_12_00,
        ],
    ];
    private const PRODUCT_CONFIGURATION_MAPPING = [
        'DE' => self::DOMESTIC_EXPRESS_MAPPING,
        'WW' => self::EXPRESS_WORLDWIDE_MAPPING,
        'WWE' => self::CODE_DHL_EXPRESS_WORLDWIDE_EU,
        'EE' => self::CODE_DHL_EXPRESS_ENVELOPE,
        'ESE' => self::CODE_DHL_ECONOMY_SELECT_EU,
        'ES' => self::CODE_DHL_ECONOMY_SELECT_NON_EU,
    ];

    public static function getExpressProductByConfiguration(
        string $productKey,
        ?string $contentType = null,
        ?string $deliveryTime = null,
    ): DhlExpressProduct {
        $product = self::PRODUCT_CONFIGURATION_MAPPING[$productKey];

        if (!$contentType) {
            $contentType = 'doc';
        }

        if (!is_array($product)) {
            return new self($product, $contentType);
        }

        if (!$deliveryTime) {
            $deliveryTime = '24';
        }

        if (array_key_exists('doc', $product)) {
            return new self($product[$contentType][$deliveryTime], $contentType);
        }

        return new self($product[$deliveryTime], $contentType);
    }

    private function __construct(
        private readonly string $code,
        private readonly string $contentType,
    ) {}

    public function jsonSerialize(): string
    {
        return $this->code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isCustomsDeclarable(): bool
    {
        if ($this->code === self::CODE_DHL_ECONOMY_SELECT_NON_EU) {
            return true; // Economy select (Non-EU) is a non documents product.
        }

        return $this->contentType !== 'doc';
    }
}
