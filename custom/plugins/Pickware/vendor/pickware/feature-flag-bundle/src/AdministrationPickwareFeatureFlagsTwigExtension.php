<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\FeatureFlagBundle;

use Exception;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdministrationPickwareFeatureFlagsTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly LoggerInterface $logger,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'pickwareFeatureGetFeatureFlags',
                [
                    $this,
                    'getFeatureFlags',
                ],
            ),
        ];
    }

    public function getFeatureFlags(): array
    {
        try {
            return $this->featureFlagService->getFeatureFlags()->jsonSerialize();
        } catch (Exception $e) {
            $this->logger->error('Exception occurred when evaluating Pickware feature flags', [
                'exception' => $e,
            ]);

            return [];
        }
    }
}
