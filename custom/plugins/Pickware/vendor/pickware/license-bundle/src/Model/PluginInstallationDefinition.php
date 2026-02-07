<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Model;

use Pickware\DalBundle\Field\JsonSerializableObjectField;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicense;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLease;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @extends EntityDefinition<PluginInstallationEntity>
 */
class PluginInstallationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'pickware_license_bundle_plugin_installation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PluginInstallationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PluginInstallationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            // A unique identifier for the current installation of the Pickware plugin.
            // This ID is for the Business Platform to identify and differentiate between individual installations of
            // the Pickware plugin.
            (new IdField('installation_id', 'installationId'))->addFlags(new Required()),
            // An actual, API serviceable UUID representation of the `installation_id` field, unhexed, lowercase and
            // with hyphens placed at the correct positions.
            (new StringField('installation_uuid', 'installationUuid'))->addFlags(new Computed(), new WriteProtected()),
            new StringField('pickware_account_access_token', 'pickwareAccountAccessToken'),
            new JsonSerializableObjectField(
                'pickware_license',
                'pickwareLicense',
                PickwareLicense::class,
            ),
            new JsonSerializableObjectField(
                'pickware_license_lease',
                'pickwareLicenseLease',
                PickwareLicenseLease::class,
            ),
            new JsonSerializableObjectField(
                'latest_pickware_license_lease_refresh_error',
                'latestPickwareLicenseLeaseRefreshError',
                JsonApiError::class,
            ),
            new JsonSerializableObjectField(
                'latest_pickware_license_refresh_error',
                'latestPickwareLicenseRefreshError',
                JsonApiError::class,
            ),
            new JsonSerializableObjectField(
                'latest_usage_report_error',
                'latestUsageReportError',
                JsonApiError::class,
            ),
            new DateTimeField('last_pickware_license_lease_refreshed_at', 'lastPickwareLicenseLeaseRefreshedAt'),
        ]);
    }
}
