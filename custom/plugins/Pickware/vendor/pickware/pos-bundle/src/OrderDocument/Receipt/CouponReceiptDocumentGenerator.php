<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument\Receipt;

use LogicException;
use Pickware\PickwarePos\OrderDocument\CouponReceiptDocumentType;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class CouponReceiptDocumentGenerator extends AbstractDocumentRenderer
{
    public function supports(): string
    {
        return CouponReceiptDocumentType::TECHNICAL_NAME;
    }

    public function render(
        array $operations,
        Context $context,
        DocumentRendererConfig $rendererConfig,
    ): RendererResult {
        throw new LogicException('Generating pickware pos coupon receipts is unsupported');
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }
}
