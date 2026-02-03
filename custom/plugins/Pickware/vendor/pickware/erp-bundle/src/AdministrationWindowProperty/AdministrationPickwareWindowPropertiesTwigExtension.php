<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\AdministrationWindowProperty;

use Exception;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdministrationPickwareWindowPropertiesTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdministrationWindowPropertyService $administrationWindowPropertyService,
        private readonly LoggerInterface $logger,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'pickwareErpGetAdministrationWindowProperties',
                [
                    $this,
                    'getAdministrationWindowProperties',
                ],
            ),
        ];
    }

    public function getAdministrationWindowProperties(): array
    {
        try {
            return $this->administrationWindowPropertyService->getAdministrationWindowProperties();
        } catch (Exception $e) {
            $this->logger->error('Exception occurred when evaluating Pickware window properties', [
                'exception' => $e,
            ]);

            return [];
        }
    }
}
