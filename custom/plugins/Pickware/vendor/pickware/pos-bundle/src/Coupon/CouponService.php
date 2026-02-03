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

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;

class CouponService
{
    private EntityManager $entityManager;

    public function __construct(
        EntityManager $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    public function findPickwarePosCoupon(string $code, string $currencyId, Context $context): ?PickwarePosCoupon
    {
        $searchCriteria = new Criteria();
        $searchCriteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsFilter('code', $code),
                    new EqualsFilter('individualCodes.code', $code),
                ],
            ),
        );
        // The association needs to be filtered to only return individual codes matching the query, otherwise all
        // individual codes would be returned.
        $searchCriteria->getAssociation('individualCodes')->addFilter(new EqualsFilter('code', $code));

        /** @var PromotionCollection $promotions */
        $shopwarePromotions = $this->entityManager->findBy(
            PromotionDefinition::class,
            $searchCriteria,
            $context,
            [
                'salesChannels',
                'discounts.promotionDiscountPrices',
                'cartRules.conditions',
                'personaRules.conditions',
                'orderRules.conditions',
            ],
        );
        if (count($shopwarePromotions) > 1) {
            throw CouponException::ambiguousCouponCode($code, array_values($shopwarePromotions->getElements()));
        }
        $shopwarePromotion = $shopwarePromotions->first();
        if ($shopwarePromotion == null) {
            return null;
        }
        $discounts = $shopwarePromotion->getDiscounts();
        if ($discounts->count() == 0) {
            throw CouponException::discountMissing($code);
        }
        if ($discounts->count() > 1) {
            throw CouponException::onlyOneDiscountAllowed($code);
        }
        if ($discounts->first()->getScope() !== PromotionDiscountEntity::SCOPE_CART) {
            throw CouponException::invalidDiscountScope($code, $discounts->first()->getScope());
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $currencyId,
            $context,
        );

        return self::convertShopwarePromotionToPickwarePosCoupon($shopwarePromotion, $currency);
    }

    /**
     * @return PickwarePosCoupon[]
     */
    public function getPosCouponsForAutomaticRedemption(string $currencyId, Context $context): array
    {
        /** @var PromotionCollection $promotions */
        $shopwarePromotions = $this->entityManager->findBy(
            PromotionDefinition::class,
            ['useCodes' => false],
            $context,
            [
                'salesChannels',
                'discounts.promotionDiscountPrices',
                'cartRules.conditions',
                'personaRules.conditions',
                'orderRules.conditions',
            ],
        );

        if ($shopwarePromotions->count() === 0) {
            return [];
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            $currencyId,
            $context,
        );

        return ImmutableCollection::create($shopwarePromotions)
            ->filter(function($shopwarePromotion) {
                $discounts = $shopwarePromotion->getDiscounts();

                return $discounts->count() === 1 && $discounts->first()->getScope() === PromotionDiscountEntity::SCOPE_CART;
            })
            ->map(fn($shopwarePromotion): PickwarePosCoupon => self::convertShopwarePromotionToPickwarePosCoupon($shopwarePromotion, $currency))
            ->asArray();
    }

    private static function convertShopwarePromotionToPickwarePosCoupon(
        PromotionEntity $promotion,
        CurrencyEntity $currency,
    ): PickwarePosCoupon {
        if ($promotion->isUseIndividualCodes()) {
            /** @var PromotionIndividualCodeEntity $individualCode */
            $individualCode = $promotion->getIndividualCodes()->first();
            $code = $individualCode->getCode();
            $isRedeemed = $individualCode->getPayload() !== null;
        } else {
            $code = $promotion->getCode();
            $maxRedemptions = $promotion->getMaxRedemptionsGlobal();
            $isRedeemed = $maxRedemptions && ($promotion->getOrderCount() >= $maxRedemptions);
        }
        $discount = $promotion->getDiscounts()->first();

        // Check if a custom price for the requested currency is available otherwise take the default value.
        $currencyPrice = $discount
            ->getPromotionDiscountPrices()
            ->filterByProperty('currencyId', $currency->getId())
            ->first()
            ?->getPrice();
        $discountValue = $currencyPrice ?: ($discount->getValue() * $currency->getFactor());

        $rules = new RuleCollection();
        /** @var RuleCollection $cartRules */
        $cartRules = $promotion->getCartRules();
        if ($cartRules !== null) {
            $rules->merge($cartRules);
        }
        /** @var RuleCollection $customerRules */
        $customerRules = $promotion->getPersonaRules();
        if ($customerRules !== null) {
            $rules->merge($customerRules);
        }
        /** @var RuleCollection $cartRules */
        $orderRules = $promotion->getOrderRules();
        if ($orderRules !== null) {
            $rules->merge($orderRules);
        }

        $salesChannelIds = array_values(array_map(
            fn($element) => $element->getSalesChannelId(),
            $promotion->getSalesChannels()->getElements(),
        ));
        if (count($salesChannelIds) > 0) {
            // Build a simple shopware 6 rule with conditions for all valid sales channels.
            $salesChannelsCondition = new RuleConditionEntity();
            $salesChannelsCondition->setUniqueIdentifier(Uuid::randomHex());
            $salesChannelsCondition->setType('salesChannel');
            $salesChannelsCondition->setValue([
                'operator' => '=',
                'salesChannelIds' => $salesChannelIds,
            ]);
            $salesChannelsRule = new RuleEntity();
            $salesChannelsRule->setPriority(1);
            $salesChannelsRule->setUniqueIdentifier(Uuid::randomHex());
            $salesChannelsRule->setConditions(new RuleConditionCollection([$salesChannelsCondition]));

            $rules->add($salesChannelsRule);
        }

        return PickwarePosCoupon::makeFromPromotion(
            code: $code,
            isRedeemed: $isRedeemed,
            promotion: $promotion,
            discount: $discount,
            discountValue: $discountValue,
            rules: $rules,
        );
    }
}
