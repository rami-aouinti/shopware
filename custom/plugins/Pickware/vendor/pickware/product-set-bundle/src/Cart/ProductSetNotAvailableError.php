<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;

class ProductSetNotAvailableError extends Error
{
    public const CHECKOUT_ERROR_TRANSLATION_KEY = 'pw-product-set-bundle-product-set-not-available';

    private string $productId;
    private string $orderLineItemLabel;
    private int $allowedQuantity;

    public function __construct(string $productId, string $orderLineItemLabel, int $allowedQuantity)
    {
        $this->productId = $productId;
        $this->orderLineItemLabel = $orderLineItemLabel;
        $this->allowedQuantity = $allowedQuantity;
        parent::__construct();
    }

    public function getId(): string
    {
        return $this->productId;
    }

    public function getAllowedQuantity(): int
    {
        return $this->allowedQuantity;
    }

    public function getMessageKey(): string
    {
        return self::CHECKOUT_ERROR_TRANSLATION_KEY;
    }

    public function getLevel(): int
    {
        return Error::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function blockResubmit(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return [
            'orderLineItemLabel' => $this->orderLineItemLabel,
            'allowQuantity' => $this->allowedQuantity,
        ];
    }
}
