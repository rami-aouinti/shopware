<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Adapter;

use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\UnitsOfMeasurement\Dimensions\RectangleDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;

enum UpsLabelSize: string
{
    case A5 = 'A5';
    case Inch4x6 = '4x6-inch';
    case Inch4x7 = '4x7-inch';

    public function getPageFormat(): PageFormat
    {
        return match ($this) {
            self::A5 => new PageFormat(
                'UPS Label A5 (DHL 910-300-700 Format)',
                PageFormat::createDinPageFormat('A5')->getSize(),
                'ups_a5',
            ),
            self::Inch4x6 => new PageFormat(
                'UPS Label 4" x 6"',
                new RectangleDimensions(
                    new Length(4, 'in'),
                    new Length(6, 'in'),
                ),
                'ups_4_x_6_inch',
            ),
            self::Inch4x7 => new PageFormat(
                'UPS Label 4" x 7"',
                new RectangleDimensions(
                    new Length(4, 'in'),
                    new Length(7, 'in'),
                ),
                'ups_4_x_7_inch',
            ),
        };
    }

    public static function getSupportedPageFormats(): array
    {
        $pageFormats = [];

        foreach (self::cases() as $case) {
            $pageFormats[] = $case->getPageFormat();
        }

        return $pageFormats;
    }

    /**
     * Returns a Dompdf compatible size array for the given label size.
     */
    public function getDomPdfSize(): array
    {
        $dimensions = $this->getPageFormat()->getSize();

        // The conversion factor from millimeter to Desktop Publishing Points (DTP).
        // Reference: https://en.wikipedia.org/wiki/Point_(typography)#Desktop_publishing_point
        $mmToPoints = 72 / 25.4;
        $width = round($dimensions->getWidth()->convertTo('mm') * $mmToPoints);
        $height = round($dimensions->getHeight()->convertTo('mm') * $mmToPoints);

        return [
            0,
            0,
            $width,
            $height,
        ];
    }

    public function getHtml(string $imageSrc): string
    {
        $css = match ($this) {
            self::A5 => $this->getCssForA5(),
            self::Inch4x6, self::Inch4x7 => $this->getCssForInchSized(),
        };

        return <<<HTML
            <html>
                <style type="text/css">
            {$css}
                </style>
                <body>
                    <img src="{$imageSrc}" alt="UPS Shipping Label" />
                </body>
            </html>
            HTML;
    }

    private function getCssForA5(): string
    {
        // For A5 PDFs we want to maintain the original size of the label, which is 4x7 inch for UPS labels.
        $width = (new Length(4, 'in'))->convertTo('mm');
        $height = (new Length(7, 'in'))->convertTo('mm');

        return <<<CSS
            img {
                width: {$width}mm;
                height: {$height}mm;
            }
            body {
                text-align: center;
            }
            CSS;
    }

    private function getCssForInchSized(): string
    {
        $dimensions = $this->getPageFormat()->getSize();
        $width = $dimensions->getWidth()->convertTo('mm');
        $height = $dimensions->getHeight()->convertTo('mm');

        return <<<CSS
            @page {
                margin: 0;
            }
            img {
                width: {$width}mm;
                height: {$height}mm;
            }
            body {
                margin: 0;
            }
            CSS;
    }
}
