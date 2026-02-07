<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document;

use InvalidArgumentException;
use JsonSerializable;
use Pickware\UnitsOfMeasurement\Dimensions\RectangleDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;

class PageFormat implements JsonSerializable
{
    /**
     * This values are defined by the DIN norm
     */
    private const DIN_SERIES_AREA_EXPONENTS = [
        'A' => 0,
        'B' => 0.5,
        'C' => 0.25,
        'D' => -0.25,
    ];

    /**
     * This values is defined by the DIN norm
     */
    private const DIN_HEIGHT_WIDTH_RATIO = 0.707106781186; // = 1/sqrt(2)

    public function __construct(
        private readonly string $description,
        private readonly RectangleDimensions $size,
        private readonly ?string $id = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'size' => $this->size,
            'id' => $this->id,
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            $array['description'],
            RectangleDimensions::fromArray($array['size']),
            $array['id'] ?? null,
        );
    }

    public static function createDinPageFormat(string $dinName): self
    {
        $dinName = mb_strtoupper($dinName);
        if (!preg_match('/^([A-D])(\\d|10)$/', $dinName, $matches)) {
            throw new InvalidArgumentException(sprintf(
                '%s is either not a valid DIN format or is currently not supported.',
                $dinName,
            ));
        }

        $series = $matches[1]; // The letter of the DIN name, e.g. A for A4
        $class = (int) ($matches[2]); // The number of the DIN name, e.g. 4 for A4

        // The series of a DIN format tells us the basic area in QM of a paper from this series with class 0.
        // E.g. for A it is 2^0 QM = 1QM (because A => 0) and for B it is 2^(0.5) QM = 1.41QM (because B => 0.5)
        // The class tells us how often to split this area in half
        // E.g. for A0 the area of the paper is just 2/(2^0) QM = 2 QM, for A2 it is 2/(2^2) QM = 0.5 QM
        // This is how to calculate the area of a paper in DIN format:
        $seriesExponent = self::DIN_SERIES_AREA_EXPONENTS[$series];
        $areaInQm = (2 ** $seriesExponent) / (2 ** $class);
        // Since all DIN format have a height/width ratio of 1/SQRT(2) you can easily calculate width and height from
        // the area of a page format like this:
        $heightInM = sqrt($areaInQm / self::DIN_HEIGHT_WIDTH_RATIO);
        $widthInM = sqrt($areaInQm * self::DIN_HEIGHT_WIDTH_RATIO);
        $size = new RectangleDimensions(
            new Length(round($widthInM * 1000), 'mm'),
            new Length(round($heightInM * 1000), 'mm'),
        );
        $description = 'DIN ' . $series . $class;

        return new self($description, $size);
    }

    public static function findMatchingDinFormat(RectangleDimensions $size): ?self
    {
        foreach (array_keys(self::DIN_SERIES_AREA_EXPONENTS) as $series) {
            for ($class = 0; $class <= 10; $class++) {
                $pageFormat = self::createDinPageFormat($series . $class);
                if ($pageFormat->getSize()->isEqualTo($size, new Length(1, 'mm'))) {
                    return $pageFormat;
                }
            }
        }

        return null;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSize(): RectangleDimensions
    {
        return $this->size;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
