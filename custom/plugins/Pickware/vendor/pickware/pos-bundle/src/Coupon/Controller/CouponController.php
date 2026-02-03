<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Coupon\Controller;

use Pickware\PickwarePos\Coupon\CouponException;
use Pickware\PickwarePos\Coupon\CouponService;
use Pickware\PickwarePos\Coupon\NetiNextEasyCouponAdapter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class CouponController
{
    private CouponService $couponService;
    private NetiNextEasyCouponAdapter $netiNextEasyCouponAdapter;

    public function __construct(
        CouponService $couponService,
        NetiNextEasyCouponAdapter $netiNextEasyCouponAdapter,
    ) {
        $this->couponService = $couponService;
        $this->netiNextEasyCouponAdapter = $netiNextEasyCouponAdapter;
    }

    #[JsonValidation(schemaFilePath: 'payload-find-coupon.schema.json')]
    #[Route(path: '/api/_action/pickware-pos/find-coupon', methods: ['POST'])]
    public function findCoupon(Context $context, Request $request): Response
    {
        $code = $request->get('code');
        $currencyId = $request->get('currencyId');

        try {
            $shopwarePromotion = $this->couponService->findPickwarePosCoupon($code, $currencyId, $context);
            $easyCoupon = $this->netiNextEasyCouponAdapter->findPickwarePosCoupon($code, $currencyId, $context);
        } catch (CouponException $couponException) {
            return $couponException->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        if ($shopwarePromotion && $easyCoupon) {
            return CouponException::ambiguousCouponCode($code, [$shopwarePromotion, $easyCoupon])
                ->serializeToJsonApiError()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($shopwarePromotion ?? $easyCoupon);
    }

    #[Route(path: '/api/_action/pickware-pos/get-coupons-for-automatic-redemption', methods: ['POST'])]
    public function getCouponsForAutomaticRedemption(
        #[JsonParameterAsUuid] string $currencyId,
        Context $context,
    ): Response {
        return new JsonResponse(
            $this->couponService->getPosCouponsForAutomaticRedemption($currencyId, $context),
        );
    }
}
