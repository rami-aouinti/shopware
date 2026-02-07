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

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FeaturePermissionsService
{
    public function __construct(
        #[TaggedIterator(FeaturePermissionsProvider::DI_CONTAINER_TAG)]
        private readonly iterable $featurePermissionsProviders,
    ) {
        foreach ($this->featurePermissionsProviders as $featurePermissionsProvider) {
            if (!($featurePermissionsProvider instanceof FeaturePermissionsProvider)) {
                throw new InvalidArgumentException(sprintf(
                    "Service %s tagged with 'pickware_acl_bundle.feature_permissions_provider' needs to be an instance of %s.",
                    $featurePermissionsProvider::class,
                    FeaturePermissionsProvider::class,
                ));
            }
        }
    }

    /**
     * @return FeatureCategory[]
     */
    public function getFeatureCategories(): array
    {
        $categories = [];
        foreach ($this->featurePermissionsProviders as $featurePermissionsProvider) {
            foreach ($featurePermissionsProvider->getCategories() as $category) {
                if (isset($categories[$category->getTechnicalName()])) {
                    $categories[$category->getTechnicalName()]->addFeaturePermissions(
                        $category->getFeaturePermissions(),
                    );
                } else {
                    $categories[$category->getTechnicalName()] = $category;
                }
            }
        }

        return array_values($categories);
    }
}
