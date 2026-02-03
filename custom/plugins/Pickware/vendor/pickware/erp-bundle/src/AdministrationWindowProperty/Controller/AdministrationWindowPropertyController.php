<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\AdministrationWindowProperty\Controller;

use Pickware\PickwareErpStarter\AdministrationWindowProperty\AdministrationWindowPropertyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false])]
class AdministrationWindowPropertyController
{
    public function __construct(
        private readonly AdministrationWindowPropertyService $administrationWindowPropertyService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/administration-window-properties',
        name: 'api.action.pickware-erp.administration-window-properties',
        methods: ['GET'],
    )]
    public function getAdministrationWindowProperties(): Response
    {
        return new JsonResponse($this->administrationWindowPropertyService->getAdministrationWindowProperties());
    }
}
