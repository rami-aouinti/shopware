<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product;

use Exception;
use Pickware\PickwareErpStarter\Template\TwigException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ProductNameTwigFilter extends AbstractExtension
{
    private ProductNameFormatterService $productNameFormatter;

    public function __construct(ProductNameFormatterService $productNameFormatter)
    {
        $this->productNameFormatter = $productNameFormatter;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('pickware_erp_product_name', [$this, 'renderProductName'], ['needs_context' => true]),
        ];
    }

    public function renderProductName($twigContext, $productId, $templateOptions = []): string
    {
        if (!array_key_exists('context', $twigContext)) {
            throw TwigException::filterContextIsMissing('pickware_erp_product_name');
        }

        // In SW versions <6.4.2.0 this was a Shopware\Core\Framework\Context. In >=6.4.2.0 this is a sales channel
        // context.
        // This fix should be removed once SW 6.5 is released.
        $context = $twigContext['context'];
        if ($context instanceof SalesChannelContext) {
            $context = $context->getContext();
        }

        try {
            $productName = $this->productNameFormatter->getFormattedProductName($productId, $templateOptions, $context);
        } catch (Exception $exception) {
            throw TwigException::filterProcessingError('pickware_erp_product_name', $exception->getMessage());
        }

        return $productName;
    }
}
