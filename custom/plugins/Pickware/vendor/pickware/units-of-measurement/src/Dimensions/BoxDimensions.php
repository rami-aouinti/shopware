<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UnitsOfMeasurement\Dimensions;

use JsonSerializable;
use LogicException;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;

class BoxDimensions implements JsonSerializable
{
    private Length $height;
    private Length $width;
    private Length $length;

    public function __construct(Length $width, Length $height, Length $length)
    {
        self::validateLength($length, 'length');
        self::validateLength($width, 'width');
        self::validateLength($height, 'height');

        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
    }

    public function jsonSerialize(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
        ];
    }

    public static function fromArray(array $array): ?self
    {
        // This class used to support passing 0 as a dimension, but this is not allowed anymore. Anyway we still should
        // support deserializing old data.
        if (
            ($array['width']['value'] ?? 0) <= 0
            || ($array['length']['value'] ?? 0) <= 0
            || ($array['height']['value'] ?? 0) <= 0
        ) {
            trigger_error(
                'BoxDimensions with a dimension of 0 are not supported anymore and support will be removed with 3.0.0',
                E_USER_DEPRECATED,
            );

            return null;
        }

        return new self(
            Length::fromArray($array['width']),
            Length::fromArray($array['height']),
            Length::fromArray($array['length']),
        );
    }

    public function __clone()
    {
        $this->width = clone $this->width;
        $this->height = clone $this->height;
        $this->length = clone $this->length;
    }

    public function getHeight(): Length
    {
        return $this->height;
    }

    public function getWidth(): Length
    {
        return $this->width;
    }

    public function getLength(): Length
    {
        return $this->length;
    }

    private static function validateLength(Length $length, string $name): void
    {
        if (!$length->isGreaterThan(new Length(0, 'm'))) {
            throw new LogicException(sprintf('%s of box dimension must be greater than zero.', ucfirst($name)));
        }
    }
}
