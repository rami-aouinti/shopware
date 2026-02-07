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

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;

class BarcodeLabelLayouts
{
    public const LAYOUT_A = 'layout_a';
    public const LAYOUT_B = 'layout_b';
    public const LAYOUT_C = 'layout_c';
    private const LAYOUT_TEMPLATE_FILES_DIRECTORY = '@PickwareErpBundle/documents/barcode_label_layouts/';

    private TemplateFinder $templateFinder;

    public function __construct(TemplateFinder $templateFinder)
    {
        $this->templateFinder = $templateFinder;
    }

    public function getTemplate(string $layout): string
    {
        return $this->templateFinder->find(self::LAYOUT_TEMPLATE_FILES_DIRECTORY . $layout . '.html.twig');
    }
}
