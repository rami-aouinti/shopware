<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsellNudgingBundle\Modal\Controller;

use Pickware\UpsellNudgingBundle\Modal\UpsellNudgingModalException;
use Pickware\UpsellNudgingBundle\Modal\UpsellNudgingModalService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class UpsellNudgingModalController
{
    public function __construct(
        private readonly UpsellNudgingModalService $nudgingModalService,
    ) {}

    #[Route(
        path: '/api/_action/upsell-nudging/create-pickware-account-upgrade-link',
        methods: ['POST'],
    )]
    public function createPickwareAccountUpgradeLink(#[JsonParameter] string $featureName): Response
    {
        try {
            return new JsonResponse([
                'pickwareAccountUpgradeLink' => $this->nudgingModalService->createPickwareAccountUpgradeLink($featureName),
            ]);
        } catch (UpsellNudgingModalException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_PRECONDITION_FAILED);
        }
    }
}
