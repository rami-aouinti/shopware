<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PaperTrail;

use Monolog\Attribute\WithMonologChannel;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Psr\Log\LoggerInterface;

#[WithMonologChannel(channel: 'pickware_erp_paper_trail')]
class PaperTrailUriProvider
{
    /** @var list<AbstractPaperTrailUri> */
    private array $uriStack = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function registerUri(AbstractPaperTrailUri $uri): void
    {
        // Feature flag is checked here instead of at callsites to reduce overhead when logging.
        if (!$this->featureFlagService->isActive(PaperTrailLoggingProdFeatureFlag::NAME)) {
            return;
        }

        $this->uriStack[] = $uri;
    }

    public function getCurrentUri(): AbstractPaperTrailUri
    {
        if (count($this->uriStack) === 0) {
            $fallbackUri = AbstractPaperTrailUri::getFallbackUri();
            $this->logger->error(
                sprintf('No uri registered, using fallback uri "%s"', $fallbackUri->getUri()),
                [
                    'fallbackUri' => $fallbackUri,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                ],
            );

            return $fallbackUri;
        }

        return end($this->uriStack);
    }

    /**
     * @return list<AbstractPaperTrailUri>
     */
    public function getUriStack(): array
    {
        return $this->uriStack;
    }

    public function reset(): void
    {
        array_pop($this->uriStack);
    }
}
