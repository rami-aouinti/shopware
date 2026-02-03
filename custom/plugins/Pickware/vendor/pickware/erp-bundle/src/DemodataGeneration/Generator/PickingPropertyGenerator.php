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
use Pickware\PickwareErpStarter\PickingProperty\Model\PickingPropertyDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.demodata_generator')]
class PickingPropertyGenerator implements DemodataGeneratorInterface
{
    private const PICKING_PROPERTY_MHD = 'MHD';
    private const PICKING_PROPERTY_CONDITION = 'Zustand';
    private const PICKING_PROPERTIES = [
        self::PICKING_PROPERTY_MHD,
        self::PICKING_PROPERTY_CONDITION,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function getDefinition(): string
    {
        return PickingPropertyDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $demodataContext, array $options = []): void
    {
        $pickingPropertyPayloads = array_map(
            fn(string $name) => [
                'name' => $name,
            ],
            self::PICKING_PROPERTIES,
        );

        $this->entityManager->create(
            PickingPropertyDefinition::class,
            $pickingPropertyPayloads,
            $demodataContext->getContext(),
        );
    }
}
