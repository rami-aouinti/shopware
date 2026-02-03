<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Renderer;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;

class DomPdfRenderer
{
    public function render(RenderedDocument $document, string $html): string
    {
        $dompdf = new Dompdf();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->setPaper($document->getPageSize(), $document->getPageOrientation());

        $dompdf->loadHtml($html);

        $dompdf->render();

        $this->injectPageCount($dompdf);

        return (string) $dompdf->output();
    }

    /**
     * Replace a predefined placeholder with the total page count in the whole PDF document
     */
    private function injectPageCount(Dompdf $dompdf): void
    {
        /** @var CPDF $canvas */
        $canvas = $dompdf->getCanvas();
        $search = $this->insertNullByteBeforeEachCharacter('DOMPDF_PAGE_COUNT_PLACEHOLDER');
        $replace = $this->insertNullByteBeforeEachCharacter((string) $canvas->get_page_count());
        $pdf = $canvas->get_cpdf();

        foreach ($pdf->objects as &$o) {
            if ($o['t'] === 'contents') {
                $o['c'] = str_replace($search, $replace, (string) $o['c']);
            }
        }
    }

    private function insertNullByteBeforeEachCharacter(string $string): string
    {
        return "\u{0000}" . mb_substr(chunk_split($string, 1, "\u{0000}"), 0, -1);
    }
}
