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
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;

class RectangleDimensions implements JsonSerializable
{
    private Length $height;
    private Length $width;

    public function __construct(Length $width, Length $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function jsonSerialize(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            Length::fromArray($array['width']),
            Length::fromArray($array['height']),
        );
    }

    public function __clone()
    {
        $this->width = clone $this->width;
        $this->height = clone $this->height;
    }

    public function getHeight(): Length
    {
        return $this->height;
    }

    public function getWidth(): Length
    {
        return $this->width;
    }

    public function isEqualTo(RectangleDimensions $size, Length $epsilon): bool
    {
        return (
            $size->width->isEqualTo($this->width, $epsilon)
            && $size->height->isEqualTo($this->height, $epsilon)
        ) || (
            $size->width->isEqualTo($this->height, $epsilon)
            && $size->height->isEqualTo($this->width, $epsilon)
        );
    }
}
