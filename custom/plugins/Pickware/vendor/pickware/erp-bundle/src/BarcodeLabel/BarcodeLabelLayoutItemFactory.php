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

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeLabelLayoutItemFactory
{
    public function createItemForLayoutA(
        string $code,
        string $field1Value,
        string $barcodeType = BarcodeGenerator::TYPE_CODE_128,
    ): array {
        return [
            'barcode' => $this->renderBarcode($code, $barcodeType),
            'field1Value' => $field1Value,
        ];
    }

    public function createItemForLayoutB(
        string $code,
        string $field1Value,
        string $field2Value,
        string $field3Value,
        string $barcodeType = BarcodeGenerator::TYPE_CODE_128,
    ): array {
        return [
            'barcode' => $this->renderBarcode($code, $barcodeType),
            'field1Value' => $field1Value,
            'field2Value' => $field2Value,
            'field3Value' => $field3Value,
        ];
    }

    public function createItemForLayoutC(
        string $code,
        string $field1Value,
        string $field2Value,
        string $barcodeType = BarcodeGenerator::TYPE_CODE_128,
    ): array {
        return [
            'barcode' => $this->renderBarcode($code, $barcodeType),
            'field1Value' => $field1Value,
            'field2Value' => $field2Value,
        ];
    }

    private function renderBarcode(string $code, string $type = BarcodeGenerator::TYPE_CODE_128): string
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($code, $type));

        return 'data:image/png;base64,' . $barcode;
    }
}
