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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierDefinition;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator generates suppliers.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class SupplierGenerator implements DemodataGeneratorInterface
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function getDefinition(): string
    {
        return SupplierDefinition::class;
    }

    public function generate(int $numberOfSuppliers, DemodataContext $demodataContext, array $options = []): void
    {
        $languageIds = $this->entityManager
            ->findAll(LanguageDefinition::class, $demodataContext->getContext())->getKeys();

        $demodataContext->getConsole()->progressStart($numberOfSuppliers);
        for ($i = 0; $i < $numberOfSuppliers; $i++) {
            do {
                $payload = [
                    'languageId' => $languageIds[array_rand($languageIds)],
                ];
                $supplierCreated = false;
                try {
                    $this->entityManager->create(
                        SupplierDefinition::class,
                        [
                            $this->getSupplierPayload($demodataContext, $payload),
                        ],
                        $demodataContext->getContext(),
                    );
                    $supplierCreated = true;
                } catch (UniqueConstraintViolationException $e) {
                    // One of the pre-defined/random supplier properties was already used - ignore and continue
                    continue;
                }
            } while (!$supplierCreated);

            $demodataContext->getConsole()->progressAdvance();
        }

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            '%s suppliers have been created.',
            $numberOfSuppliers,
        ));
    }

    private function getSupplierPayload(DemodataContext $demodataContext, array $payload = []): array
    {
        $faker = $demodataContext->getFaker();
        $city = $faker->city();
        $firstName = $faker->firstName();
        $lastName = $faker->lastName();
        $number = $this->numberRangeValueGenerator->getValue(
            'pickware_erp_supplier',
            $demodataContext->getContext(),
            null,
        );

        return array_merge(
            [
                'id' => Uuid::randomHex(),
                'name' => $lastName . ' ' . $city,
                'number' => $number,
                'customerNumber' => 'I-' . $number,
                'defaultDeliveryTime' => random_int(3, 21),
                'languageId' => null,

                'address' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'title' => null,
                    'email' => vsprintf(
                        '%s.%s@%s',
                        [
                            $firstName,
                            $lastName,
                            $faker->safeEmailDomain(),
                        ],
                    ),
                    'phone' => $faker->e164PhoneNumber(),
                    'fax' => $faker->e164PhoneNumber(),
                    'website' => $faker->url(),
                    'company' => $faker->company(),
                    'department' => null,
                    'position' => $faker->jobTitle(),

                    'street' => $faker->streetName(),
                    'houseNumber' => $faker->buildingNumber(),
                    'zipCode' => $faker->postcode(),
                    'city' => $faker->city(),
                    'state' => null,
                    'province' => null,
                    'addressAddition' => null,
                    'countryIso' => null,

                    'comment' => $faker->sentence(),
                    'vatId' => $faker->ean13(),
                ],
            ],
            $payload,
        );
    }
}
