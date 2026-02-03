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
use Pickware\PhpStandardLibrary\Json\Json;
use Psr\Log\LoggerInterface;
use Throwable;

#[WithMonologChannel(channel: 'pickware_erp_paper_trail')]
class PaperTrailLoggingService
{
    // Max payload size is 256 kb on GCloud, we underestimate a bit to account for message and fixed payload size.
    public const MAX_PAYLOAD_SIZE = 260_000;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PaperTrailUriProvider $paperTrailUriProvider,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function logPaperTrailEvent(string $eventName, array $payload = []): void
    {
        // Feature flag is checked here instead of at callsites to reduce overhead when logging.
        if (!$this->featureFlagService->isActive(PaperTrailLoggingProdFeatureFlag::NAME)) {
            return;
        }

        // We want to be conservative both in regard to performance and error handling, as logging should never
        // adversely affect the business logic. Thus, all error cases have to be handled gracefully. Also, truncation is
        // very aggressive, so we cap execution time.
        try {
            $serialized = Json::stringify($payload);
            if (mb_strlen($serialized, '8bit') > self::MAX_PAYLOAD_SIZE) {
                $count = 0;
                while (mb_strlen($serialized, '8bit') > self::MAX_PAYLOAD_SIZE && $count < 5) {
                    // Half the payload on each iteration to ensure fast execution.
                    $payload = array_slice($payload, 0, (int) ceil(count($payload) / 2));
                    $serialized = Json::stringify($payload);
                    ++$count;
                }
                $payload[] = sprintf('truncated "%s" times', $count);

                if ($count === 5) {
                    $payload = [];
                    $payload[] = 'payload truncated too many times';
                }
            }
        } catch (Throwable) {
            $payload = [];
            $payload[] = 'payload could not be serialized';
        }

        $currentUri = $this->paperTrailUriProvider->getCurrentUri();
        $payload = [
            'eventName' => $eventName,
            'uri' => $currentUri->getUri(),
            'payload' => $payload,
        ];
        $uriStack = $this->paperTrailUriProvider->getUriStack();
        if (count($uriStack) > 1) {
            $payload['uriStack'] = $uriStack;
        }
        $this->logger->info(
            sprintf('PaperTrail event "%s" happened for uri "%s"', $eventName, $currentUri->getUri()),
            $payload,
        );
    }

    /**
     * @param list<AbstractPaperTrailUri> $composedUris
     */
    public function logPaperTrailComposition(array $composedUris): void
    {
        // Feature flag is checked here instead of at callsites to reduce overhead when logging.
        if (!$this->featureFlagService->isActive(PaperTrailLoggingProdFeatureFlag::NAME)) {
            return;
        }

        $compositeUri = $this->paperTrailUriProvider->getCurrentUri()->getUri();
        $payload = [
            'eventName' => 'compositePaperTrailUriCreated',
            'compositeUri' => $compositeUri,
            'composedUris' => $composedUris,
        ];

        $uriStack = $this->paperTrailUriProvider->getUriStack();
        if (count($uriStack) > 1) {
            $payload['uriStack'] = $uriStack;
        }

        $this->logger->info(
            sprintf('Composite paper trail uri "%s" created', $compositeUri),
            $payload,
        );
    }
}
