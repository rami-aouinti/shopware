<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Installation;

use Doctrine\DBAL\Connection;
use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\DalBundle\DefaultTranslationProvider;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\InstallationLibrary\AclRole\AclRoleInstaller;
use Pickware\InstallationLibrary\AclRole\AclRoleUninstaller;
use Pickware\InstallationLibrary\DefaultConfigValue\DefaultConfigValueInstaller;
use Pickware\InstallationLibrary\Elasticsearch\ElasticsearchIndexInstaller;
use Pickware\InstallationLibrary\NumberRange\NumberRangeInstaller;
use Pickware\InstallationLibrary\StateMachine\StateMachineInstaller;
use Pickware\PickwareErpStarter\Installation\Installer\ImportExportProfileInstaller;
use Pickware\PickwareErpStarter\Installation\Steps\UpsertImportExportProfilesInstallationStep;
use Pickware\PickwareWms\Acl\PickwareWmsFeaturePermissionsProvider;
use Pickware\PickwareWms\FeatureFlags\PrivilegeManagementProdFeatureFlag;
use Pickware\PickwareWms\Installation\Steps\InstallDefaultPickingProfileInstallationStep;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\PickingProcessNumberRange;
use Pickware\PickwareWms\PickingProcess\PickingProcessStateMachine;
use Pickware\PickwareWms\PickingProfile\DefaultPickingProfileFilterService;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\PickwareWmsBundle;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessNumberRange;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessStateMachine;
use Pickware\PickwareWms\Statistic\PickingStatisticsDeliveriesExporter;
use Pickware\PickwareWms\Statistic\PickingStatisticsPicksExporter;
use Pickware\PickwareWms\StockingProcess\StockingProcessNumberRange;
use Pickware\PickwareWms\StockingProcess\StockingProcessStateMachine;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PickwareWmsInstaller
{
    private StateMachineInstaller $stateMachineInstaller;
    private NumberRangeInstaller $numberRangeInstaller;
    private AclRoleInstaller $aclRoleInstaller;
    private AclRoleUninstaller $aclRoleUninstaller;
    private DefaultConfigValueInstaller $defaultConfigValueInstaller;
    private ElasticsearchIndexInstaller $elasticsearchIndexInstaller;
    private EntityManager $entityManager;
    private Connection $connection;
    private PickwareWmsAclRoleFactory $pickwareWmsAclRoleFactory;
    private EventDispatcherInterface $eventDispatcher;
    private DefaultPickingProfileFilterService $defaultPickingProfileService;

    private function __construct()
    {
        // Create an instance with ::initFromContainer()
    }

    public static function initFromContainer(ContainerInterface $container): self
    {
        $self = new self();

        $self->connection = $container->get(Connection::class);
        $defaultTranslationProvider = new DefaultTranslationProvider($container, $self->connection);
        $self->entityManager = new EntityManager($container, $self->connection, $defaultTranslationProvider, new EntityDefinitionQueryHelper());
        $self->stateMachineInstaller = new StateMachineInstaller($self->entityManager);
        $self->numberRangeInstaller = new NumberRangeInstaller($self->entityManager);
        $self->aclRoleInstaller = new AclRoleInstaller($self->entityManager);
        $self->aclRoleUninstaller = new AclRoleUninstaller($self->entityManager);
        $self->eventDispatcher = $container->get('event_dispatcher');
        $self->defaultPickingProfileService = new DefaultPickingProfileFilterService($self->entityManager);
        $self->pickwareWmsAclRoleFactory = new PickwareWmsAclRoleFactory(
            new PickwareWmsFeaturePermissionsProvider(
                new FeatureFlagService([new PrivilegeManagementProdFeatureFlag()], $self->eventDispatcher),
            ),
            new AclRoleFactory(),
        );

        $self->defaultConfigValueInstaller = new DefaultConfigValueInstaller(
            systemConfigService: $container->get(SystemConfigService::class),
        );
        $self->elasticsearchIndexInstaller = new ElasticsearchIndexInstaller(
            db: $self->connection,
            eventDispatcher: $self->eventDispatcher,
        );

        return $self;
    }

    public function install(InstallContext $installContext): void
    {
        $installContext->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context): void {
            $this->stateMachineInstaller->ensureStateMachine(new PickingProcessStateMachine(), $context);
            $this->stateMachineInstaller->ensureStateMachine(new DeliveryStateMachine(), $context);
            $this->numberRangeInstaller->ensureNumberRange(new PickingProcessNumberRange(), $context);

            $this->stateMachineInstaller->ensureStateMachine(new StockingProcessStateMachine(), $context);
            $this->numberRangeInstaller->ensureNumberRange(new StockingProcessNumberRange(), $context);

            $this->stateMachineInstaller->ensureStateMachine(new ShippingProcessStateMachine(), $context);
            $this->numberRangeInstaller->ensureNumberRange(new ShippingProcessNumberRange(), $context);

            $this->aclRoleInstaller->installAclRole(
                $this->pickwareWmsAclRoleFactory->createPickwareWmsAclRole(),
                $context,
            );
            $this->defaultConfigValueInstaller->writeDefaultConfiguration(new PickwareWmsBundle());

            (new InstallDefaultPickingProfileInstallationStep(
                defaultPickingProfileService: $this->defaultPickingProfileService,
                entityManager: $this->entityManager,
                db: $this->connection,
            ))->writeDefaultPickingProfile();

            $this->elasticsearchIndexInstaller->installElasticsearchIndices([
                PickingProfileDefinition::ENTITY_NAME,
                PickingProcessDefinition::ENTITY_NAME,
            ]);

            if (class_exists(ImportExportProfileInstaller::class)) {
                (new ImportExportProfileInstaller($this->connection))
                    ->ensureImportExportProfile(PickingStatisticsPicksExporter::TECHNICAL_NAME, logRetentionDays: 90)
                    ->ensureImportExportProfile(PickingStatisticsDeliveriesExporter::TECHNICAL_NAME, logRetentionDays: 90);
            } else {
                (new UpsertImportExportProfilesInstallationStep(
                    $this->connection,
                    [
                        PickingStatisticsPicksExporter::TECHNICAL_NAME,
                        PickingStatisticsDeliveriesExporter::TECHNICAL_NAME,
                    ],
                ))->install();
            }
        });
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $uninstallContext->getContext()->scope(Context::SYSTEM_SCOPE, function(Context $context): void {
            $this->aclRoleUninstaller->uninstallAclRole(
                $this->pickwareWmsAclRoleFactory->createPickwareWmsAclRole(),
                $context,
            );
            $this->stateMachineInstaller->removeStateMachine(new PickingProcessStateMachine(), $context);
            $this->stateMachineInstaller->removeStateMachine(new DeliveryStateMachine(), $context);
            $this->stateMachineInstaller->removeStateMachine(new StockingProcessStateMachine(), $context);
            $this->stateMachineInstaller->removeStateMachine(new ShippingProcessStateMachine(), $context);
        });
    }
}
