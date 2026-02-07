<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwarePos\BranchStore\BranchStoreConfigService;
use Pickware\PickwarePos\BranchStore\BranchStoreException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class BranchStoreController
{
    private BranchStoreConfigService $branchStoreConfigService;

    public function __construct(BranchStoreConfigService $branchStoreConfigService)
    {
        $this->branchStoreConfigService = $branchStoreConfigService;
    }

    #[Route(path: '/api/_action/pickware-pos/branch-store/{branchStoreId}/config', methods: ['GET'])]
    public function config(string $branchStoreId, Context $context): Response
    {
        if (!$branchStoreId || !Uuid::isValid($branchStoreId)) {
            return ResponseFactory::createUuidParameterMissingResponse('branchStoreId');
        }

        try {
            $config = $this->branchStoreConfigService->getBranchStoreConfig($branchStoreId, $context);
        } catch (BranchStoreException $e) {
            return $e->serializeToJsonApiError()->setStatus(Response::HTTP_BAD_REQUEST)->toJsonApiErrorResponse();
        }

        return new JsonResponse($config, Response::HTTP_OK);
    }
}
