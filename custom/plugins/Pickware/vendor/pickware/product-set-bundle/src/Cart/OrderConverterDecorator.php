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

use Pickware\DalBundle\EntityManager;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderConverterDecorator extends OrderConverter
{
    public function __construct(
        private readonly OrderConverter $decoratedInstance,
        private readonly EntityManager $entityManager,
    ) {}

    public function convertToOrder(Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext): array
    {
        return $this->decoratedInstance->convertToOrder($cart, $context, $conversionContext);
    }

    public function convertToCart(OrderEntity $order, Context $context): Cart
    {
        return $this->decoratedInstance->convertToCart($order, $context);
    }

    public function assembleSalesChannelContext(OrderEntity $order, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        // After changing the quantity of an already created order the recalculation also triggers the renaming
        // of line items after their product names. With passing the ALLOW_PRODUCT_LABEL_OVERWRITES permission
        // we ensure that the product label is not overwritten. Moreover, we need to ensure that the default permissions
        // OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS (see https://github.com/shopware/shopware/blob/bfa59069acf24649ca600dba3cc57e972c3d69c5/src/Core/Checkout/Cart/Order/OrderConverter.php#L284-L292)
        // and already passed overrideOptions are not overwritten (as they are not deep merged).
        if ($this->hasProductSetLineItem($order, $context)) {
            $overrideOptions[SalesChannelContextService::PERMISSIONS] = [
                ...OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
                ...$overrideOptions[SalesChannelContextService::PERMISSIONS] ?? [],
                ProductCartProcessor::ALLOW_PRODUCT_LABEL_OVERWRITES => true,
            ];
        }

        return $this->decoratedInstance->assembleSalesChannelContext($order, $context, $overrideOptions);
    }

    private function hasProductSetLineItem(OrderEntity $order, Context $context): bool
    {
        $lineItems = $order->getLineItems();
        if ($order->getLineItems() === null) {
            /** @var OrderLineItemCollection $lineItems */
            $lineItems = $this->entityManager->findBy(
                OrderLineItemDefinition::class,
                ['orderId' => $order->getId()],
                $context,
            );
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === ProductSetDefinition::LINE_ITEM_TYPE) {
                return true;
            }
        }

        return false;
    }
}
