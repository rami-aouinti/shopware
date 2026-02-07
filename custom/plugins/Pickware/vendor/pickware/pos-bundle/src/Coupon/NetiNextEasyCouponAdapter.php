<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Coupon;

use NetInventors\NetiNextEasyCoupon\Core\Content\EasyCoupon\EasyCouponEntity;
use NetInventors\NetiNextEasyCoupon\Core\Content\Product\Aggregate\EasyCouponProductDefinition;
use NetInventors\NetiNextEasyCoupon\Core\Content\Product\Aggregate\EasyCouponProductEntity;
use NetInventors\NetiNextEasyCoupon\Core\Content\Transaction\TransactionDefinition;
use NetInventors\NetiNextEasyCoupon\Core\Content\Transaction\TransactionEntity;
use NetInventors\NetiNextEasyCoupon\Service\PluginConfigFactory;
use NetInventors\NetiNextEasyCoupon\Service\Repository\VoucherRepository;
use NetInventors\NetiNextEasyCoupon\Service\VoucherRedemption\Validator\PaymentActivationStateValidator;
use NetInventors\NetiNextEasyCoupon\Service\VoucherRedemptionStatusService;
use NetInventors\NetiNextEasyCoupon\Struct\LineItemStruct;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;

class NetiNextEasyCouponAdapter
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CashRounding $cashRounding,
        private readonly PluginService $pluginService,
        private readonly ?VoucherRepository $voucherRepository,
        private readonly ?VoucherRedemptionStatusService $redemptionStatusService,
        private readonly ?PaymentActivationStateValidator $paymentActivationStateValidator,
        private readonly ?PluginConfigFactory $pluginConfigFactory,
    ) {}

    public function findPickwarePosCoupon(string $code, string $currencyId, Context $context): ?PickwarePosCoupon
    {
        if (!$this->isEasyCouponPluginAvailable($context)) {
            return null;
        }

        $easyCoupon = $this->voucherRepository->getVoucherByCodeWithAssociations($code, $context);
        if (!$easyCoupon || $easyCoupon->isDeleted()) {
            return null;
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $currencyId,
            $context,
        );

        return $this->convertNetiNextEasyCouponToPickwarePosCoupon($easyCoupon, $currency, $context);
    }

    public function prepareOrderPayloadForPurchasableCoupons(array &$orderPayload, Context $context): void
    {
        // Plugin version 5.2.0 has an important fix that ensures the voucher has the correct value in any currency.
        if (!$this->isEasyCouponPluginAvailable(context: $context, minVersion: '5.2.0')) {
            return;
        }

        $productIds = array_filter(array_map(
            fn(array $lineItem) => ($lineItem['productId'] ?? null),
            $orderPayload['lineItems'],
        ));

        /** @var ImmutableCollection<EasyCouponProductEntity> $couponProducts */
        /** @phpstan-ignore argument.templateType (Since we do not install the dependency, phpstan cannot determine the template type. This cannot be fixed with a stub file.) */
        $couponProducts = ImmutableCollection::create($this->entityManager->findBy(
            EasyCouponProductDefinition::class,
            [
                'productId' => $productIds,
            ],
            $context,
        ));

        if ($couponProducts->count() === 0) {
            return;
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $orderPayload['currencyId'],
            $context,
        );

        foreach ($orderPayload['lineItems'] as &$lineItem) {
            $couponProduct = $couponProducts->first(
                fn(EasyCouponProductEntity $couponProduct) => (
                    $couponProduct->getProductId() === ($lineItem['productId'] ?? null)
                ),
            );
            if ($couponProduct === null) {
                continue;
            }

            // For now, only coupons with fixed value are supported. Other coupons are ignored.
            if ($couponProduct->getValueType() !== EasyCouponProductEntity::VALUE_TYPE_FIXED) {
                continue;
            }
            $couponValue = $couponProduct
                ->getValue()
                ?->getCurrencyPrice($orderPayload['currencyId'], false)
                ?->getGross();

            if ($couponValue === null) {
                $couponValue = $couponProduct->getValue()?->getCurrencyPrice(Defaults::CURRENCY)?->getGross() ?? 0.0;
                $couponValue *= $currency->getFactor();
                $couponValue = $this->cashRounding->cashRound($couponValue, $currency->getItemRounding());
            }

            $lineItem['payload'][LineItemStruct::PAYLOAD_NAME] = [
                'voucherValue' => $couponValue,
            ];
        }
    }

    public function addCreatedCouponsToOrderLineItems(string $orderId, Context $context): void
    {
        if (!$this->isEasyCouponPluginAvailable($context)) {
            return;
        }

        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            ['lineItems'],
        );

        /** @var OrderLineItemEntity[] $lineItems */
        $lineItems = $order->getLineItems()?->getElements();
        if ($lineItems === null) {
            return;
        }

        $updatePayload = [];
        foreach ($lineItems as $lineItem) {
            $payload = $lineItem->getPayload();
            $couponValue = $payload[LineItemStruct::PAYLOAD_NAME]['voucherValue'] ?? null;
            $coupons = $payload[LineItemStruct::PAYLOAD_NAME]['vouchers'] ?? null;
            if ($couponValue === null || $coupons === null) {
                continue;
            }

            $payload['pickwarePosCoupons'] = array_map(
                fn(array $coupon) => [
                    'value' => $couponValue,
                    'code' => $coupon['code'] ?? null,
                ],
                $coupons,
            );
            $updatePayload[] = [
                'id' => $lineItem->getId(),
                'payload' => $payload,
                // Required for updating an order line item
                'productId' => $lineItem->getProductId(),
                'referencedId' => $lineItem->getReferencedId(),
            ];
        }

        $this->entityManager->update(
            OrderLineItemDefinition::class,
            $updatePayload,
            $context,
        );
    }

    private function convertNetiNextEasyCouponToPickwarePosCoupon(
        EasyCouponEntity $easyCoupon,
        CurrencyEntity $currency,
        Context $context,
    ): PickwarePosCoupon {
        // We do not support all conditions of the easy coupon plugin because the main use case at POS is for gift cards
        // only. We rather accept a gift card with unknown conditions instead of denying it. However, we want to
        // support the global redemption limit of a coupon even if we don't understand other combined conditions.
        $maxRedemptions = $easyCoupon
            ->getConditions()
            ?->filterByProperty('type', 'netiEasyCouponTotalUses')
            ->first()
            ?->getValue()['value'];

        $taxRate = null;
        $discountMaxValue = null;
        $easyCouponStatus = $this->redemptionStatusService->getStatus($easyCoupon->getId(), $context);
        switch ($easyCoupon->getValueType()) {
            case EasyCouponEntity::VALUE_TYPE_ABSOLUTE:
                $discountType = PromotionDiscountEntity::TYPE_ABSOLUTE;

                // The redemption status service returns the coupon in the currency of the coupon that's why we need
                // to translate it into the base currency and then into the requested currency.
                $discountValue = $easyCouponStatus['value']['remaining'] / $easyCoupon->getCurrencyFactor();
                $discountValue *= $currency->getFactor();
                $discountValue = max($discountValue, 0);
                $isRedeemed = (
                    $easyCouponStatus['value']['isExpired']
                    || $discountValue <= 0
                    || ($maxRedemptions && ($easyCouponStatus['redemptionCount'] >= $maxRedemptions))
                );
                break;
            case EasyCouponEntity::VALUE_TYPE_PERCENTAGE:
                $discountType = PromotionDiscountEntity::TYPE_PERCENTAGE;
                $discountValue = $easyCoupon->getValue();
                $isRedeemed = $maxRedemptions && ($easyCouponStatus['redemptionCount'] >= $maxRedemptions);

                // The max discount is always in base currency of the shop and is only valid for 'percentage' type
                $discountMaxValue = $easyCoupon
                    ->getMaxRedemptionValue()
                    ?->getCurrencyPrice(Defaults::CURRENCY, false)
                    ?->getGross();
                $discountMaxValue *= $currency->getFactor();
                break;
            default:
                throw CouponException::unsupportedCouponType($easyCoupon->getCode(), $easyCoupon->getValueType());
        }

        // A coupon with a productId means that it was purchased. It is safe to do this check here because this value
        // persists even when the corresponding product is deleted. (No FK-constraint on database)
        if ($easyCoupon->getProductId() !== null) {
            /** @var TransactionEntity $purchaseTransaction */
            /** @phpstan-ignore argument.templateType (Since we do not install the dependency, phpstan cannot determine the template type. This cannot be fixed with a stub file.) */
            $purchaseTransaction = $this->entityManager->getOneBy(
                /** @phpstan-ignore argument.type (Since we do not install the dependency, phpstan cannot determine the type. This cannot be fixed with a stub file.) */
                TransactionDefinition::class,
                [
                    'easyCouponId' => $easyCoupon->getId(),
                    'transactionType' => TransactionEntity::TYPE_CREATED_BY_PURCHASE,
                ],
                $context,
                [
                    'orderLineItem',
                    'order.transactions',
                ],
            );

            $pluginConfig = $this->pluginConfigFactory->create($purchaseTransaction->getOrder()?->getSalesChannelId());
            $isCouponPaid = $this->paymentActivationStateValidator->matchesPaymentActivationState(
                order: $purchaseTransaction->getOrder(),
                paymentActivationStates: $pluginConfig->getVoucherActivatePaymentStatus(),
                context: $context,
            );
            if (!$isCouponPaid) {
                throw CouponException::notPaid($easyCoupon->getCode(), $purchaseTransaction->getOrderId());
            }

            // In case the coupon was created by a purchase, the tax configuration of the purchase must override any other
            // tax configuration to ensure correct taxation.
            $taxRules = $purchaseTransaction->getOrderLineItem()->getPrice()->getCalculatedTaxes();
            if ($taxRules->count() !== 1) {
                throw CouponException::unsupportedTaxes($easyCoupon->getCode(), $taxRules);
            }
            $taxRate = $taxRules->first()->getTaxRate();
        }

        return PickwarePosCoupon::makeFromEasyCoupon(
            easyCoupon: $easyCoupon,
            taxRate: $taxRate,
            isRedeemed: $isRedeemed,
            discountType: $discountType,
            discountValue: $discountValue,
            discountMaxValue: $discountMaxValue,
        );
    }

    private function isEasyCouponPluginAvailable(Context $context, ?string $minVersion = null): bool
    {
        $isPluginAvailable = (
            $this->voucherRepository !== null
            && $this->redemptionStatusService !== null
            && $this->paymentActivationStateValidator !== null
            && $this->pluginConfigFactory !== null
        );
        if ($minVersion === null || $isPluginAvailable === false) {
            return $isPluginAvailable;
        }

        try {
            $pluginVersion = $this->pluginService
                ->getPluginByName('NetiNextEasyCoupon', $context)
                ->getVersion();
        } catch (PluginNotFoundException) {
            // This method must not throw if the plugin is not found.
            return false;
        }

        return version_compare($pluginVersion, '5.2.0', '>=');
    }
}
