<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;

class PicklistCustomProductGenerator
{
    private const OPTION_TYPES_WITH_VALUE_IN_PAYLOAD = [
        'checkbox',
        'datetime',
        'htmleditor',
        'numberfield',
        'textarea',
        'textfield',
        'timestamp',
    ];
    private const OPTION_TYPES_WITH_MEDIA_IN_PAYLOAD = [
        'fileupload',
        'imageupload',
    ];

    /**
     * This method generates a DocumentCustomProduct from the order line items. The order line items for custom products
     * currently have the following structure in an order:
     *
     * Example order with (5) order line items with (2) physical products where one is a custom product:
     * - custom product definition (no parent id)
     *     - physical product1 (referencing a physical product, parent id = custom product definition id)
     *     - selected custom product option (parent id = custom product definition id)
     *         - selected custom product value (parent id = selected custom product option id)
     * - physical product 2 (no parent id)
     *
     * (A physical product is defined here as a product in the `product` table in the database.)
     *
     * @return DocumentCustomProduct[]
     */
    public function generatorCustomProductDefinitions(OrderLineItemCollection $lineItems): array
    {
        $customOptions = [];
        $customValues = [];

        foreach ($lineItems->getIterator() as $lineItem) {
            if ($lineItem->getType() === 'customized-products-option') {
                $customOptions[$lineItem->getParentId()] = array_merge(
                    $customOptions[$lineItem->getParentId()] ?? [],
                    [$lineItem],
                );
            } elseif ($lineItem->getType() === 'option-values') {
                $customValues[$lineItem->getParentId()] = array_merge(
                    $customValues[$lineItem->getParentId()] ?? [],
                    [$lineItem],
                );
            }
        }

        $customProducts = [];
        foreach ($customOptions as $customProductDefinitionOrderLineItemId => $productOptions) {
            $customProductOptions = [];
            foreach ($productOptions as $productOption) {
                $productNumber = $productOption->getPayload()['productNumber'] ?? null;

                $payloadType = $productOption->getPayload()['type'];
                if (in_array($payloadType, self::OPTION_TYPES_WITH_VALUE_IN_PAYLOAD)) {
                    $value = $productOption->getPayload()['value'];
                } elseif (in_array($payloadType, self::OPTION_TYPES_WITH_MEDIA_IN_PAYLOAD)) {
                    // Custom products will not create order line items for options without values. Therefore, an upload
                    // field will always contain an item. Since there are also no options which allow to upload
                    // multiple items, there will always be exactly one media item.
                    $value = $productOption->getPayload()['media'][0]['filename'];
                } else {
                    $productNumbers = [];
                    $values = [];
                    foreach ($customValues[$productOption->getId()] ?? [] as $customValue) {
                        $values[] = $customValue->getLabel();

                        if (
                            isset($customValue->getPayload()['productNumber'])
                            && $customValue->getPayload()['productNumber']
                        ) {
                            // Product numbers can be stored either in the custom product option or in the custom
                            // product value. The custom product option might be a selection which could allow for
                            // multi-selection. If such a selection box has multiple items selected each with their
                            // own product number we would run into a scenario in which a custom product option is
                            // represented by multiple product numbers.
                            $productNumbers[] = $customValue->getPayload()['productNumber'];
                        }
                    }

                    // Product numbers are usually defined at the option level. However, in case of selections every
                    // item might have its own product number.
                    $productNumber = count($productNumbers) > 0 ? implode(', ', $productNumbers) : null;
                    $value = implode(', ', $values);
                }

                $customProductOptions[] = new DocumentCustomProduct(
                    $payloadType,
                    $productOption->getLabel(),
                    $value,
                    $productNumber,
                );
            }

            $customProducts[$customProductDefinitionOrderLineItemId] = $customProductOptions;
        }

        return $customProducts;
    }
}
