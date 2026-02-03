<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\NumberRange;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class NumberRangeInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function ensureNumberRange(NumberRange $numberRange, Context $context): void
    {
        /** @var NumberRangeTypeEntity|null $existingNumberRangeType */
        $existingNumberRangeType = $this->entityManager->findOneBy(
            NumberRangeTypeDefinition::class,
            ['technicalName' => $numberRange->getTechnicalName()],
            $context,
        );
        if (!$existingNumberRangeType) {
            $numberRangeTypeId = Uuid::randomHex();
            $this->entityManager->upsert(
                NumberRangeTypeDefinition::class,
                [
                    [
                        'id' => $numberRangeTypeId,
                        'technicalName' => $numberRange->getTechnicalName(),
                        'typeName' => $numberRange->getTypeNameTranslations(),
                        'global' => true,
                    ],
                ],
                $context,
            );
        } else {
            $numberRangeTypeId = $existingNumberRangeType->getId();
        }

        /** @var NumberRangeEntity|null $existingNumberRange */
        $existingNumberRange = $this->entityManager->findBy(
            NumberRangeDefinition::class,
            ['typeId' => $numberRangeTypeId],
            $context,
        )->first();
        if (!$existingNumberRange) {
            $this->entityManager->upsert(
                NumberRangeDefinition::class,
                [
                    [
                        'id' => Uuid::randomHex(),
                        'typeId' => $numberRangeTypeId,
                        'name' => $numberRange->getTypeNameTranslations(),
                        'pattern' => $numberRange->getPattern(),
                        'start' => $numberRange->getStart(),
                        'global' => true,
                    ],
                ],
                $context,
            );
        }
    }

    public function removeNumberRange(NumberRange $numberRange, Context $context): void
    {
        /** @var NumberRangeTypeEntity|null $numberRangeType */
        $numberRangeType = $this->entityManager->findOneBy(
            NumberRangeTypeDefinition::class,
            ['technicalName' => $numberRange->getTechnicalName()],
            $context,
        );

        if (!$numberRangeType) {
            return;
        }

        $this->entityManager->deleteByCriteria(
            NumberRangeDefinition::class,
            ['typeId' => $numberRangeType->getId()],
            $context,
        );

        $this->entityManager->delete(
            NumberRangeTypeDefinition::class,
            [['id' => $numberRangeType->getId()]],
            $context,
        );
    }
}
