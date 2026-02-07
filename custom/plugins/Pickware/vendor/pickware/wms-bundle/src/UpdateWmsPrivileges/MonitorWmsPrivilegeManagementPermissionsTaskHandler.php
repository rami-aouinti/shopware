<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\UpdateWmsPrivileges;

use Pickware\AclBundle\Acl\AclRoleFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareWms\Acl\PickwareWmsFeaturePermissionsProvider;
use Pickware\PickwareWms\FeatureFlags\PrivilegeManagementProdFeatureFlag;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: MonitorWmsPrivilegeManagementPermissionsTask::class)]
class MonitorWmsPrivilegeManagementPermissionsTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly PickwareWmsFeaturePermissionsProvider $featurePermissionsProvider,
        private readonly AclRoleFactory $aclRoleFactory,
        private readonly EntityManager $entityManager,
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if ($this->featureFlagService->isActive(PrivilegeManagementProdFeatureFlag::NAME)) {
            return;
        }

        $context = Context::createDefaultContext();

        // Find all ACL roles containing WMS privileges
        $criteria = new Criteria();
        $criteria->addFilter(
            new ContainsFilter('privileges', PickwareWmsFeaturePermissionsProvider::SPECIAL_BASIC_PRIVILEGE),
        );

        $aclRoles = $this->entityManager->findBy(AclRoleDefinition::class, $criteria, $context);

        if ($aclRoles->count() === 0) {
            return;
        }

        // Get all WMS privileges that should be added
        $wmsPrivileges = $this->getWmsPrivileges();
        $updates = [];
        /** @var AclRoleEntity $aclRole */
        foreach ($aclRoles as $aclRole) {
            $currentPrivileges = $aclRole->getPrivileges() ?? [];
            $privilegesToAdd = array_diff($wmsPrivileges, $currentPrivileges);
            if ($privilegesToAdd !== []) {
                $mergedPrivileges = array_values(array_unique(array_merge($currentPrivileges, $privilegesToAdd)));
                $updates[] = [
                    'id' => $aclRole->getId(),
                    'privileges' => $mergedPrivileges,
                ];
            }
        }

        if (!empty($updates)) {
            $this->entityManager->update(AclRoleDefinition::class, $updates, $context);
        }
    }

    /**
     * @return string[]
     */
    private function getWmsPrivileges(): array
    {
        $featurePermissions = [
            ...$this->featurePermissionsProvider->getDefaultFeaturePermissions(),
            ...$this->featurePermissionsProvider->getSpecialFeaturePermissions(),
        ];

        $privileges = array_map(
            fn($featurePermission) => $this->aclRoleFactory->createFeaturePermissionPrivileges($featurePermission),
            $featurePermissions,
        );

        if ($privileges === []) {
            return [];
        }

        return array_values(array_unique(array_merge(...$privileges)));
    }
}
