<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Mail;

use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Mime\Email;

class MailServiceDecorator extends AbstractMailService
{
    public function __construct(private readonly AbstractMailService $decoratedService) {}

    public function getDecorated(): AbstractMailService
    {
        return $this->decoratedService;
    }

    // Filters all the nested line items of a product set line item, so that only the product set line item
    // is shown in mails.
    // order should always represent an OrderEntity, when called serverside this is generally the case, however when called from
    // Frontend/Administration code the Argument gets JSON-encoded for http delivery and is therefore transformed into an associative array
    // we have to handle this case and adjust our filtering logic, we also have to assume anything can be passed
    // -> we ignore cases that are neither an OrderEntity nor an Array representing an order
    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $filterLineItemAsArray = fn(array $lineItem) => !array_key_exists(
            OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY,
            $lineItem['payload'] ?? [],
        );
        $filterLineItemAsEntity = fn(OrderLineItemEntity $lineItem) => !array_key_exists(
            OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY,
            $lineItem->getPayload(),
        );

        /** @var null|OrderEntity|array|mixed $order */
        $order = $templateData['order'] ?? null;
        if ($order && is_array($order)) {
            $lineItems = $order['lineItems'] ?? [];
            // Plugins may do unexpected things such as adding line item collections to orders that are arrays. We make
            // this decorator extra robust.
            if (is_array($lineItems)) {
                $order['lineItems'] = array_filter($lineItems, $filterLineItemAsArray);
            } elseif ($lineItems instanceof OrderLineItemCollection) {
                $order['lineItems'] = $lineItems->filter($filterLineItemAsEntity)->getElements();
            }
            $templateData['order'] = $order;
        } elseif ($order instanceof OrderEntity && $order->getLineItems()) {
            $order->setLineItems($order->getLineItems()->filter($filterLineItemAsEntity));
        }

        return $this->decoratedService->send($data, $context, $templateData);
    }
}
