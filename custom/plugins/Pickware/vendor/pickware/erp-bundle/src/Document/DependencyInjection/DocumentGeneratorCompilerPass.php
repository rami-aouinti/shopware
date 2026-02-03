<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Document\DependencyInjection;

use Pickware\PickwareErpStarter\Document\DocumentGeneratorDecorator;
use Pickware\PickwareErpStarter\Document\EntityRepositoryDecorator\ForcedVersionOrderRepositoryDecorator;
use Pickware\PickwareErpStarter\Document\ExistingDocumentRerenderService;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

// This is intentionally a compiler pass instead of a symfony decorator.
// See `Pickware\PickwareErpStarter\Document\DocumentGeneratorDecorator`
class DocumentGeneratorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(DocumentGenerator::class);
        $originalArguments = $definition->getArguments();

        $definition->setClass(DocumentGeneratorDecorator::class);
        $definition->setArguments([
            new Reference(ForcedVersionOrderRepositoryDecorator::class),
            new Reference(ExistingDocumentRerenderService::class),
            ...$originalArguments,
        ]);
    }
}
