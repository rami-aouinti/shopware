<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderDocument;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated Will be removed in 5.0.0.
 */
class TwigBarcodeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pw_erp_barcode_data_uri', [$this, 'generateBarcodeDataUri']),
        ];
    }

    /**
     * @deprecated Will be removed in 5.0.0.
     */
    public function generateBarcodeDataUri($data): string
    {
        $generator = new BarcodeGeneratorPNG();
        $uriPrefix = 'data:image/png;base64,';

        return sprintf(
            '%s%s',
            $uriPrefix,
            base64_encode($generator->getBarcode($data, $generator::TYPE_CODE_128, 1)),
        );
    }
}
