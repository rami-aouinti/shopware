<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility\Controller;

use Pickware\IncompatibilityBundle\Incompatibility\IncompatibilityService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class IncompatibilityController
{
    public function __construct(
        private readonly IncompatibilityService $incompatibilityService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-incompatibility-bundle/incompatibilities',
        name: 'api.action.pickware-incompatibility.get-incompatibilities',
        methods: ['GET'],
    )]
    public function getIncompatibilities(Context $context): JsonResponse
    {
        return new JsonResponse($this->incompatibilityService->getApplicableIncompatibilityWarnings($context));
    }
}
