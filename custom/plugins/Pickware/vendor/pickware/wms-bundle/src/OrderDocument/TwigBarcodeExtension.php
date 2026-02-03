<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderDocument;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigBarcodeExtension extends AbstractExtension
{
    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pw_wms_barcode_data_uri', [$this, 'generateBarcodeDataUri']),
            new TwigFunction('pw_wms_invoice_delivery_note_barcode_prefix', [$this, 'getInvoiceDeliveryNoteBarcodePrefix']),
        ];
    }

    public function generateBarcodeDataUri(string $barcode): string
    {
        $generator = new BarcodeGeneratorPNG();
        $uriPrefix = 'data:image/png;base64,';

        return sprintf(
            '%s%s',
            $uriPrefix,
            base64_encode($generator->getBarcode($barcode, $generator::TYPE_CODE_128, 1,)),
        );
    }

    public function getInvoiceDeliveryNoteBarcodePrefix(): string
    {
        if ($this->featureFlagService->isActive(RemoveActionPrefixFromBarcodeOnInvoiceAndDeliveryNoteFeatureFlag::NAME)) {
            return '';
        }

        return '^9';
    }
}
