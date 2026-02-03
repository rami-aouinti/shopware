<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DemodataGeneration\Generator;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.demodata_generator')]
class DeviceGenerator implements DemodataGeneratorInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function getDefinition(): string
    {
        return DeviceDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $demodataContext, array $options = []): void
    {
        $demodataContext->getConsole()->progressStart($numberOfItems);

        $createPayloads = [];

        for ($i = 0; $i < $numberOfItems; $i++) {
            $createPayloads[] = [
                'id' => Uuid::randomHex(),
                'name' => $this->generateDeviceName($demodataContext),
            ];

            if (count($createPayloads) >= 20) {
                $this->entityManager->create(
                    DeviceDefinition::class,
                    $createPayloads,
                    $demodataContext->getContext(),
                );
                $demodataContext->getConsole()->progressAdvance(count($createPayloads));
                $createPayloads = [];
            }
        }

        if (count($createPayloads) > 0) {
            $this->entityManager->create(
                DeviceDefinition::class,
                $createPayloads,
                $demodataContext->getContext(),
            );
            $demodataContext->getConsole()->progressAdvance(count($createPayloads));
        }

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            'Created %d devices',
            $numberOfItems,
        ));
    }

    private function generateDeviceName(DemodataContext $demodataContext): string
    {
        $faker = $demodataContext->getFaker();
        $firstName = $faker->firstName();

        return $firstName . "'s Device";
    }
}
