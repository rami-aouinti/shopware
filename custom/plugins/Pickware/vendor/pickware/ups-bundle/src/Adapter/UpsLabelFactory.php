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

use Dompdf\Dompdf;
use GdImage;
use UnexpectedValueException;

class UpsLabelFactory
{
    public function convertUpsLabelImageToPdf(string $imageString, UpsLabelSize $labelSize): string
    {
        $image = imagecreatefromstring($imageString);
        if ($image === false) {
            throw new UnexpectedValueException('GdImage could not be created from stringified version of UPS label!');
        }

        // The label image from UPS is always in landscape format and rotated by 90 degrees anti clockwise. It would
        // perfectly fill a landscape orientated a5. But we want a portrait orientated a5, so we need to rotate the
        // label image for that format.
        switch ($labelSize) {
            case UpsLabelSize::A5:
                $image = self::rotateImageClockwise($image);
                break;
            case UpsLabelSize::Inch4x6:
                $image = self::rotateImageClockwise($image);
                $image = self::reduceImageHeight($image, 1201);
                break;
            case UpsLabelSize::Inch4x7:
                $image = self::rotateImageClockwise($image);
                break;
        }

        return self::renderImageAsPdf($image, $labelSize);
    }

    /**
     * Rotates the label image by 90 degrees clockwise
     */
    private static function rotateImageClockwise(GdImage $image): GdImage
    {
        $rotatedImage = imagerotate(
            $image,
            angle: -90,
            background_color: imagecolorallocate($image, 255, 255, 255),
        );

        if ($rotatedImage === false) {
            throw new UnexpectedValueException('Failed to rotate image of UPS label using GdImage!');
        }

        return $rotatedImage;
    }

    /**
     * Reduces the height of the image to $heightInPixels by cutting the content away in the bottom.
     */
    private static function reduceImageHeight(GdImage $image, int $heightInPixels): GdImage
    {
        $croppedImage = imagecrop($image, [
            'x' => 0,
            'y' => 0,
            'width' => imagesx($image),
            'height' => min($heightInPixels, imagesy($image)),
        ]);

        if ($croppedImage === false) {
            throw new UnexpectedValueException('Failed to crop image of UPS label using GdImage!');
        }

        return $croppedImage;
    }

    private static function renderImageAsPdf(GdImage $image, UpsLabelSize $labelSize): string
    {
        ob_start();
        imagepng($image);
        $png = ob_get_contents();
        ob_end_clean();

        $dompdf = new Dompdf();
        $dompdf = $dompdf->setPaper(
            size: $labelSize->getDomPdfSize(),
            orientation: 'portrait',
        );

        $encodedImage = sprintf('data:image/png;base64,%s', base64_encode($png));
        $html = $labelSize->getHtml($encodedImage);
        $dompdf->loadHtml($html);

        $dompdf->render();

        $output = $dompdf->output();
        if ($output === null) {
            throw new UnexpectedValueException('Failed to render PDF as dompdf didn\'t return a string!');
        }

        return $output;
    }
}
