<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AclBundle\Acl\Controller;

use Pickware\AclBundle\Acl\AdminAclConfigurationFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class AclController
{
    public function __construct(
        private readonly AdminAclConfigurationFactory $adminAclConfigurationFactory,
    ) {}

    #[Route(
        path: '/api/_action/pickware-acl/acl/feature-permissions',
        defaults: ['_acl' => ['acl_role:read']],
        methods: ['GET'],
    )]
    public function getFeaturePermissions(): JsonResponse
    {
        return new JsonResponse($this->adminAclConfigurationFactory->createConfiguration());
    }
}
