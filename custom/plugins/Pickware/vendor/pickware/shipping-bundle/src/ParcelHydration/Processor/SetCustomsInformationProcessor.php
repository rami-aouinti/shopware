<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration\Processor;

use InvalidArgumentException;
use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\ParcelHydration\CustomsInformationCustomFieldSet;
use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrationNotification;
use Pickware\ShippingBundle\Shipment\Country;

/**
 * This processor sets customs-related information on each parcel item based on the product's custom fields.
 */
class SetCustomsInformationProcessor implements ParcelItemsProcessor
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        foreach ($items as $item) {
            $orderLineItem = $item->getOrderLineItem();
            $parcelItem = $item->getParcelItem();

            if (!$parcelItem) {
                continue;
            }

            $product = $orderLineItem->getProduct();

            if (!$product) {
                // If the product was deleted, we cannot set any customs information.
                continue;
            }

            $customFields = $product->getTranslation('customFields');
            $productName = $product->getName() ?: $product->getTranslation('name');

            $description = $customFields[CustomsInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_DESCRIPTION] ?? '';
            if (!$description) {
                // If no explicit description for this product was provided, use the product name as fallback
                $description = $productName;
            }

            $parcelItem->setCustomsDescription($description);
            $parcelItem->setTariffNumber(
                $customFields[CustomsInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_TARIFF_NUMBER] ?? null,
            );

            try {
                if ($customFields[CustomsInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_COUNTRY_OF_ORIGIN] ?? null) {
                    $country = new Country(
                        $customFields[CustomsInformationCustomFieldSet::CUSTOM_FIELD_NAME_CUSTOMS_INFORMATION_COUNTRY_OF_ORIGIN],
                    );
                } else {
                    $country = null;
                }
                $parcelItem->setCountryOfOrigin($country);
            } catch (InvalidArgumentException $exception) {
                // Since the customs information are optional, we can ignore this error, add a notification and continue
                // with the remaining parcel items (without throwing an Exception).
                $this->notificationService->emit(
                    ParcelHydrationNotification::parcelItemCustomsInformationInvalid(
                        $orderLineItem->getLabel(),
                        $exception,
                    ),
                );
            }
        }

        return $items;
    }
}
