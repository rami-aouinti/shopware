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

use DateTimeInterface;
use JsonSerializable;
use NetInventors\NetiNextEasyCoupon\Core\Checkout\Cart\AbstractCartProcessor;
use NetInventors\NetiNextEasyCoupon\Core\Content\EasyCoupon\EasyCouponEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PickwarePosCoupon implements JsonSerializable
{
    private array $discount;
    private ?string $validFrom;
    private ?string $validUntil;
    private array $rules;

    private function __construct(
        private readonly string $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly int|string $number,
        private readonly bool $isActive,
        private readonly ?string $taxRateId,
        private readonly ?float $taxRate,
        private readonly bool $isRedeemed,
        private readonly string $type,
        private readonly bool $isValueCoupon,
        private readonly bool $isPartialRedeemingAllowed,
        private readonly bool $isAutomaticallyRedeemable,
        string $discountType,
        ?float $discountValue,
        ?float $discountMaxValue,
        ?string $discountCurrencyId,
        ?DateTimeInterface $validFrom,
        ?DateTimeInterface $validUntil,
        RuleCollection $rules,
    ) {
        $this->discount = [
            'type' => $discountType,
            'value' => $discountValue,
            'maxValue' => $discountMaxValue,
            'currencyId' => $discountCurrencyId,
        ];
        $this->validFrom = $validFrom?->format(DateTimeInterface::RFC3339_EXTENDED);
        $this->validUntil = $validUntil?->format(DateTimeInterface::RFC3339_EXTENDED);
        $this->rules = array_values($rules->getElements());
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function isRedeemed(): bool
    {
        return $this->isRedeemed;
    }

    public function getDiscount(): array
    {
        return $this->discount;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function isValueCoupon(): bool
    {
        return $this->isValueCoupon;
    }

    public static function makeFromPromotion(
        ?string $code,
        bool $isRedeemed,
        PromotionEntity $promotion,
        PromotionDiscountEntity $discount,
        float $discountValue,
        RuleCollection $rules,
    ): self {
        return new PickwarePosCoupon(
            id: $promotion->getId(),
            code: $code,
            name: $promotion->getName(),
            // SW6 promotions don't have any number, but a coupon requires it, so we just use the ID here as well
            number: $promotion->getId(),
            isActive: $promotion->isActive(),
            taxRateId: null,
            taxRate: null,
            isRedeemed: $isRedeemed,
            type: 'promotion',
            isValueCoupon: false,
            isPartialRedeemingAllowed: false,
            isAutomaticallyRedeemable: !$promotion->isUseCodes(),
            discountType: $discount->getType(),
            discountValue: $discountValue,
            discountMaxValue: $discount->getMaxValue(),
            discountCurrencyId: null,
            validFrom: $promotion->getValidFrom(),
            validUntil: $promotion->getValidUntil(),
            rules: $rules,
        );
    }

    public static function makeFromEasyCoupon(
        EasyCouponEntity $easyCoupon,
        ?float $taxRate,
        bool $isRedeemed,
        string $discountType,
        float $discountValue,
        ?float $discountMaxValue,
    ): self {
        $easyCouponRule = new RuleEntity();
        $easyCouponRule->setUniqueIdentifier(Uuid::randomHex());
        $easyCouponRule->setPriority(1);
        $easyCouponRule->setConditions($easyCoupon->getConditions());

        $isValueCoupon = (
            $easyCoupon->getVoucherType() === EasyCouponEntity::VOUCHER_TYPE_INDIVIDUAL
            && $easyCoupon->getValueType() === EasyCouponEntity::VALUE_TYPE_ABSOLUTE
            && !$easyCoupon->isDiscardRemaining()
        );

        return new PickwarePosCoupon(
            id: $easyCoupon->getId(),
            code: $easyCoupon->getCode(),
            name: $easyCoupon->getTitle(),
            number: $easyCoupon->getNumber(),
            isActive: $easyCoupon->isActive(),
            taxRateId: $easyCoupon->getTaxId(),
            // If the taxRate is set, it has a higher priority than the taxRateId.
            taxRate: $taxRate,
            isRedeemed: $isRedeemed,
            type: AbstractCartProcessor::EASY_COUPON_LINE_ITEM_TYPE,
            isValueCoupon: $isValueCoupon,
            isPartialRedeemingAllowed: true,
            isAutomaticallyRedeemable: false,
            discountType: $discountType,
            discountValue: $discountValue,
            discountMaxValue: $discountMaxValue,
            discountCurrencyId: $easyCoupon->getCurrencyId(),
            validFrom: null,
            validUntil: $easyCoupon->getValidUntil(),
            rules: new RuleCollection([$easyCouponRule]),
        );
    }
}
