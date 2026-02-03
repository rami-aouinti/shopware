<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PickwarePos\CashRegister\CashRegisterCreation;
use Pickware\PickwarePos\CashRegister\CashRegisterException;
use Pickware\PickwarePos\CashRegister\Model\CashRegisterDefinition;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class CashRegisterController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntityResponseService $entityResponseService,
        private readonly CashRegisterCreation $cashRegisterCreation,
    ) {}

    #[JsonValidation(schemaFilePath: 'payload-create-cash-register.schema.json')]
    #[Route(path: '/api/_action/pickware-pos/create-cash-register', methods: ['PUT'])]
    public function createCashRegister(Request $request, Context $context): Response
    {
        $cashRegisterPayload = $request->get('cashRegister');

        $cashRegister = $this->entityManager->findByPrimaryKey(
            CashRegisterDefinition::class,
            $cashRegisterPayload['id'],
            $context,
        );
        // This is an idempotency check. If a cash register with the same ID already exist we assume this action has
        // been executed already and just return the cash register.
        if (!$cashRegister) {
            try {
                $this->cashRegisterCreation->createCashRegister($cashRegisterPayload, $context);
            } catch (CashRegisterException $cashRegisterError) {
                return $cashRegisterError
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            CashRegisterDefinition::class,
            $cashRegisterPayload['id'],
            $context,
            associations: [],
        );
    }
}
