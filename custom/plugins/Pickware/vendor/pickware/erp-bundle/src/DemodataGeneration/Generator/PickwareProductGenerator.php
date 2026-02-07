<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Generator;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductDefinition;
use Pickware\PickwareErpStarter\Product\PickwareProductInitializer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator creates pickware products with demo data.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class PickwareProductGenerator implements DemodataGeneratorInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly PickwareProductInitializer $pickwareProductInitializer,
    ) {}

    public function getDefinition(): string
    {
        return PickwareProductDefinition::class;
    }

    public function generate(int $number, DemodataContext $demodataContext, array $options = []): void
    {
        // First ensure that all pickware products have been initialized. The indexer possibly hasn't run yet.
        $productIds = $this->entityManager->findIdsBy(ProductDefinition::class, [], $demodataContext->getContext());
        $this->pickwareProductInitializer->ensurePickwareProductsExist($productIds);

        $pickwareProducts = $this->entityManager->findAll(
            PickwareProductDefinition::class,
            $demodataContext->getContext(),
        );
        $demodataContext->getConsole()->progressStart($pickwareProducts->count());
        $payloads = [];
        $numberOfWrittenItems = 0;
        foreach ($pickwareProducts as $pickwareProduct) {
            $payloads[] = array_merge(
                $this->getPickwareProductPayload(),
                [
                    'id' => $pickwareProduct->getId(),
                ],
            );

            if (count($payloads) >= 50) {
                $this->entityManager->update(
                    PickwareProductDefinition::class,
                    $payloads,
                    $demodataContext->getContext(),
                );
                $numberOfWrittenItems += count($payloads);
                $demodataContext->getConsole()->progressAdvance($numberOfWrittenItems);
                $payloads = [];
            }
        }
        $this->entityManager->update(
            PickwareProductDefinition::class,
            $payloads,
            $demodataContext->getContext(),
        );

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            '%s pickware products have been updated.',
            $pickwareProducts->count(),
        ));
    }

    private function getPickwareProductPayload(): array
    {
        // 25er steps in [0..400]
        $reorderPoint = random_int(0, 16) * 25;

        return ['reorderPoint' => $reorderPoint];
    }
}
