<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel;

use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Twig\Environment as TwigEnvironment;

class BarcodeLabelRenderer
{
    private BarcodeLabelLayouts $barcodeLabelLayouts;
    private TwigEnvironment $twig;

    public function __construct(BarcodeLabelLayouts $barcodeLabelLayouts, TwigEnvironment $twig)
    {
        $this->barcodeLabelLayouts = $barcodeLabelLayouts;
        $this->twig = $twig;
    }

    public function render(BarcodeLabelConfiguration $labelConfiguration, array $data): RenderedDocument
    {
        $template = $this->barcodeLabelLayouts->getTemplate($labelConfiguration->getLayout());
        $renderedHtml = $this->twig->render(
            $template,
            [
                'marginsInMillimeter' => [
                    'top' => $labelConfiguration->getMarginTopInMillimeter(),
                    'left' => $labelConfiguration->getMarginLeftInMillimeter(),
                    'right' => $labelConfiguration->getMarginRightInMillimeter(),
                    'bottom' => $labelConfiguration->getMarginBottomInMillimeter(),
                ],
                'contentDimensions' => [
                    'width' => $labelConfiguration->getWidthInMillimeter() - $labelConfiguration->getMarginLeftInMillimeter() - $labelConfiguration->getMarginRightInMillimeter(),
                    'height' => $labelConfiguration->getHeightInMillimeter() - $labelConfiguration->getMarginTopInMillimeter() - $labelConfiguration->getMarginBottomInMillimeter(),
                ],
                'items' => $data,
            ],
        );

        $dompdf = new Dompdf();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);

        $dompdf->setPaper($this->getPaperSizeInPt(
            $labelConfiguration->getWidthInMillimeter(),
            $labelConfiguration->getHeightInMillimeter(),
        ));

        $dompdf->loadHtml($renderedHtml);

        // Dompdf is currently not capable of handling SVG header information like title or desc and hence outputs
        // related warnings to the output stream. To prevent pollution of the output stream dompdf outputs are captured
        // and the output stream is cleaned after the rendering.
        ob_start();
        $dompdf->render();
        ob_get_clean();

        $renderedDocument = new RenderedDocument(
            name: sprintf(
                '%s_barcode_labels - %s.pdf',
                $labelConfiguration->getBarcodeLabelType(),
                (new DateTimeImmutable())->format('Y-m-d H_i_s'),
            ),
        );
        $renderedDocument->setContent($dompdf->output());

        return $renderedDocument;
    }

    /**
     * Converts a paper size from millimeters to pt (= 1/72 inch).
     */
    private function getPaperSizeInPt(int $widthInMillimeter, int $heightMillimeter): array
    {
        $mmPerInch = 25.4;

        return [
            0,
            0,
            $widthInMillimeter / $mmPerInch * 72,
            $heightMillimeter / $mmPerInch * 72,
        ];
    }
}
