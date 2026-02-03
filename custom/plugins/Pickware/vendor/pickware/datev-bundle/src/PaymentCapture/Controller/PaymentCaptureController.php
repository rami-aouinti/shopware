<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Pickware\DatevBundle\PaymentCapture\DependencyInjection\PaymentCaptureGeneratorRegistry;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PaymentCaptureController
{
    public function __construct(private readonly PaymentCaptureGeneratorRegistry $generatorRegistry) {}

    #[Route(path: '/api/_action/pickware-datev/get-projected-payment-capture-count', methods: ['POST'])]
    public function getProjectedPaymentCaptureCount(
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        Context $context,
    ): JsonResponse {
        $projectionDate = new DateTime('now', new DateTimeZone('UTC'));
        $count = $this->generatorRegistry
            ->getPaymentCaptureGeneratorForSalesChannel($salesChannelId, $context)
            ->getProjectedPaymentCaptureCount(
                $salesChannelId,
                new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
                new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
                $projectionDate,
                $context,
            );

        return new JsonResponse([
            'count' => $count,
            'projectionDate' => $projectionDate->format(DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '/api/_action/pickware-datev/create-next-payment-capture-batch', methods: ['POST'])]
    public function createNextPaymentCaptureBatch(
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        #[JsonParameter(validations: [new DateTimeConstraint(format: DateTimeInterface::ATOM)])] string $projectionDate,
        Context $context,
    ): JsonResponse {
        $count = $this->generatorRegistry
            ->getPaymentCaptureGeneratorForSalesChannel($salesChannelId, $context)
            ->createNextPaymentCaptureBatch(
                $salesChannelId,
                new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
                new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
                new DateTime($projectionDate, new DateTimeZone('UTC')),
                $context,
            );

        return new JsonResponse(['count' => $count]);
    }
}
