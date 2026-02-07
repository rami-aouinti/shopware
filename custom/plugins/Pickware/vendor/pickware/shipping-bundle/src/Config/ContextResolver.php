<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Shipment\Address;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class ContextResolver
{
    public function __construct(private readonly EntityManager $entityManager) {}

    /**
     * @param array<string, mixed> $configDescription
     * @return array<string, mixed>
     */
    public function resolveDefaultConfigContext(
        array $configDescription,
        string $orderId,
        Context $context,
        Address $receiverAddress,
    ): array {
        $elements = $configDescription['elements'] ?? $configDescription;

        return $this->getDefaultValuesFromConfigElements($elements, $orderId, $context, $receiverAddress);
    }

    /**
     * @param array<array<string, mixed>> $configElements
     * @return array<string, mixed>
     */
    private function getDefaultValuesFromConfigElements(
        array $configElements,
        string $orderId,
        Context $context,
        Address $receiverAddress,
    ): array {
        $defaultValues = [];

        foreach ($configElements as $configElement) {
            if (isset($configElement['elements'])) {
                $nestedDefaultValues = $this->getDefaultValuesFromConfigElements(
                    $configElement['elements'],
                    $orderId,
                    $context,
                    $receiverAddress,
                );
                foreach ($nestedDefaultValues as $key => $value) {
                    $defaultValues[$key] = $value;
                }
            } elseif (isset($configElement['name'], $configElement['default'])) {
                $defaultValue = $configElement['default'];

                if (is_string($defaultValue) && str_starts_with($defaultValue, '$context.order.')) {
                    $resolvedDefaultValue = $this->resolveContextPlaceholder($defaultValue, $orderId, $context, $receiverAddress);
                    if ($resolvedDefaultValue !== null && $resolvedDefaultValue !== '') {
                        $defaultValues[$configElement['name']] = $resolvedDefaultValue;
                    }
                }
            }
        }

        return $defaultValues;
    }

    private function resolveContextPlaceholder(
        string $placeholder,
        string $orderId,
        Context $context,
        Address $receiverAddress,
    ): ?string {
        return match ($placeholder) {
            '$context.order.firstName' => $receiverAddress->getFirstName(),
            '$context.order.lastName' => $receiverAddress->getLastName(),
            '$context.order.birthday' => $this->getCustomerBirthday(
                $orderId,
                $context,
                $receiverAddress,
            ),
            default => null,
        };
    }

    private function getCustomerBirthday(
        string $orderId,
        Context $context,
        Address $receiverAddress,
    ): ?string {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'orderCustomer.customer',
            ],
        );

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            return null;
        }

        $relatedCustomer = $orderCustomer->getCustomer();
        if ($relatedCustomer === null) {
            return null;
        }

        // Only use customer birthday if names match the corrected address
        if (
            $receiverAddress->getFirstName() !== $relatedCustomer->getFirstName()
            || $receiverAddress->getLastName() !== $relatedCustomer->getLastName()
        ) {
            return null;
        }

        $birthday = $relatedCustomer->getBirthday();
        if ($birthday === null) {
            return null;
        }

        return $birthday->format('Y-m-d');
    }
}
