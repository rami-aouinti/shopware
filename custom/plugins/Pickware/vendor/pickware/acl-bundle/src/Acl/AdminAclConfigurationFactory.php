<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AclBundle\Acl;

use Pickware\AclBundle\FeaturePermission\FeaturePermission;
use Pickware\AclBundle\FeaturePermission\FeaturePermissionsService;
use Pickware\DalBundle\Translation;

class AdminAclConfigurationFactory
{
    public function __construct(
        private readonly FeaturePermissionsService $featurePermissionsService,
        private readonly AclRoleFactory $aclRoleFactory,
    ) {}

    public function createConfiguration(): array
    {
        $translations = [];
        $permissions = [];
        foreach ($this->featurePermissionsService->getFeatureCategories() as $category) {
            // Prefix the category name with 'pickware_acl_' to allow identifying the category in the admin
            $parentName = 'pickware_acl_' . $category->getTechnicalName();
            foreach (Translation::REQUIRED_LOCALES as $locale) {
                $translations[$locale] ??= [
                    'parents' => [],
                ];
                $translations[$locale]['parents'][$parentName] = $category
                    ->getTranslatedName()
                    ->getTranslation($locale);
            }

            foreach ($category->getFeaturePermissions() as $featurePermission) {
                foreach (Translation::REQUIRED_LOCALES as $locale) {
                    $translations[$locale][$featurePermission->getTechnicalName()] = [
                        'label' => $featurePermission->getTranslatedName()->getTranslation($locale),
                    ];
                }

                $permissions[] = [
                    'key' => $featurePermission->getTechnicalName(),
                    'parent' => $parentName,
                    'roles' => $this->createFeatureRoles($featurePermission),
                    'category' => 'permissions',
                    'pickwareAclIsFeaturePermission' => true,
                ];
            }
        }

        return [
            'translations' => $translations,
            'permissions' => $permissions,
        ];
    }

    private function createFeatureRoles(FeaturePermission $featurePermission): array
    {
        $dependencies = [
            ...$this->aclRoleFactory->createShopwarePermissionRolePrivileges($featurePermission),
            ...array_merge(
                ...array_map(
                    fn(FeaturePermission $dependency) => $this->aclRoleFactory->createShopwarePermissionRolePrivileges($dependency),
                    $featurePermission->getDependencies(),
                ),
            ),
        ];

        return array_combine(
            AclRoleFactory::SHOPWARE_PERMISSION_ROLES,
            array_map(
                fn(string $role) => [
                    'privileges' => $this->aclRoleFactory->createFeaturePermissionPrivileges($featurePermission),
                    'dependencies' => $dependencies,
                ],
                AclRoleFactory::SHOPWARE_PERMISSION_ROLES,
            ),
        );
    }
}
