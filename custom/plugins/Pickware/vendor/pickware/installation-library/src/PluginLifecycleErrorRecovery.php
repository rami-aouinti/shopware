<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Shopware's `postInstall` and `postUpdate` are called after plugins are updated in the database to be "installed" or
 * "updated". This means that any errors occurring in these post-handlers do not reset the plugin information to a state
 * before the installation or before the update, thus appearing like the update succeeded even though it did not.
 *
 * This error recovery should be instantiated and used in every plugin's `postInstall` and `postUpdate` methods as it
 * provides the missing plugin information reset handling described above.
 *
 * For more information see https://github.com/pickware/shopware-plugins/issues/3209.
 */
class PluginLifecycleErrorRecovery
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public static function createForContainer(ContainerInterface $container): self
    {
        $connection = $container->get(Connection::class);

        return new self(
            new EntityManager(
                $container,
                $connection,
                new DefaultTranslationProvider($container, $connection),
                new EntityDefinitionQueryHelper(),
            ),
        );
    }

    public function recoverFromErrorsIn(callable $in, InstallContext $installContext): void
    {
        try {
            $in($installContext);
        } catch (Throwable $throwable) {
            /** @var PluginEntity $plugin */
            $plugin = $this->entityManager->findOneBy(
                PluginDefinition::class,
                ['name' => $installContext->getPlugin()->getName()],
                $installContext->getContext(),
            );

            $payload = [
                'id' => $plugin->getId(),
                'version' => $installContext->getCurrentPluginVersion(),
            ];
            if ($installContext instanceof UpdateContext) {
                $payload['upgradeVersion'] = $installContext->getUpdatePluginVersion();
            } else {
                $payload['upgradeVersion'] = $plugin->getVersion();
                $payload['installedAt'] = null;
            }

            $this->entityManager->update(
                PluginDefinition::class,
                [$payload],
                $installContext->getContext(),
            );

            throw $throwable;
        }
    }
}
