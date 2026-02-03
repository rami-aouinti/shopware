<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AclBundle\FeaturePermission;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Extend this class to register new feature permissions. All permissions are collected and processed automatically to
 * be displayed in the admin ACL configuration.
 * You must tag your service with `pickware_acl_bundle.feature_permissions_provider` to make it discoverable, either by
 * using the `AutoconfigureTag` attribute or by adding the tag to your service definition.
 */
#[Exclude]
class FeaturePermissionsProvider
{
    public const DI_CONTAINER_TAG = 'pickware_acl_bundle.feature_permissions_provider';

    /**
     * @param FeatureCategory[] $categories
     */
    public function __construct(private readonly array $categories) {}

    /**
     * @return FeatureCategory[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }
}
