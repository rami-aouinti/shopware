<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentUtils\Pdf;

use InvalidArgumentException;
use setasign\Fpdi\Fpdi;

class PdfMerger
{
    private array $fileContents = [];

    /**
     * @param resource $stream
     */
    public function add(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('The stream must be a valid resource.');
        }
        $this->fileContents[] = $stream;
    }

    public function merge(): string
    {
        $fpdi = new Fpdi();

        foreach ($this->fileContents as $fileContent) {
            $pageCount = $fpdi->setSourceFile($fileContent);

            for ($pageNr = 1; $pageNr <= $pageCount; $pageNr++) {
                $template = $fpdi->importPage($pageNr);
                $size = $fpdi->getTemplateSize($template);
                $fpdi->AddPage(
                    $size['orientation'],
                    [
                        $size[0], // with
                        $size[1], // height
                    ],
                );
                $fpdi->useTemplate($template);
            }
        }

        return $fpdi->Output('', 'S');
    }
}
