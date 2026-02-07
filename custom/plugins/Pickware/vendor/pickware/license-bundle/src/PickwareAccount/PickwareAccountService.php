<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\PickwareAccount;

use DateInterval;
use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLeaseOptions;
use Pickware\PickwareAccountBundle\ApiClient\PickwareAccountApiClient;
use Pickware\PickwareAccountBundle\ApiClient\PickwareAccountApiClientException;
use Pickware\PickwareAccountBundle\ApiClient\PickwareAccountApiNotReachableError;
use Shopware\Core\Framework\Context;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PickwareAccountService
{
    private const PICKWARE_LICENSE_LEASE_REFRESH_THRESHOLD = '30 minutes';

    public function __construct(
        private readonly PickwareAccountApiClient $pickwareAccountApiClient,
        private readonly PluginInstallationRepository $pluginInstallationRepository,
        #[Autowire(env: 'APP_URL')]
        private readonly string $shopUrl,
        private readonly ClockInterface $timeProvider,
    ) {}

    private function refreshPickwareLicense(Context $context): PickwareLicenseRefreshResult
    {
        try {
            $pickwareLicense = $this->pickwareAccountApiClient->getPickwareLicense();

            $this->pluginInstallationRepository->update(
                [
                    'pickwareLicense' => $pickwareLicense,
                    'latestPickwareLicenseRefreshError' => null,
                ],
                $context,
            );

            return PickwareLicenseRefreshResult::Success;
        } catch (PickwareAccountApiClientException $error) {
            $this->pluginInstallationRepository->update(
                [
                    'latestPickwareLicenseRefreshError' => $error->serializeToJsonApiError(),
                ],
                $context,
            );

            return PickwareLicenseRefreshResult::Error;
        }
    }

    public function refreshPickwareLicenseLease(Context $context): PickwareLicenseLeaseRefreshResult
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);

        $pickwareLicense = $pluginInstallation->getPickwareLicense();
        if ($pickwareLicense === null) {
            throw PickwareAccountServiceException::createPickwareAccountNotConnectedError();
        }

        try {
            $pickwareLicenseLease = $this->pickwareAccountApiClient->getPickwareLicenseLease(new PickwareLicenseLeaseOptions(
                licenseUuid: $pickwareLicense->getLicenseUuid(),
                installationUuid: $pluginInstallation->getInstallationUuid(),
                shopUuid: $pickwareLicense->getShopUuid(),
                shopUrl: $this->shopUrl,
                serverTime: $this->timeProvider->now(),
            ));

            $this->pluginInstallationRepository->update(
                [
                    'pickwareLicenseLease' => $pickwareLicenseLease,
                    'lastPickwareLicenseLeaseRefreshedAt' => $this->timeProvider->now(),
                    'latestPickwareLicenseLeaseRefreshError' => null,
                ],
                $context,
            );

            return PickwareLicenseLeaseRefreshResult::Success;
        } catch (PickwareAccountApiNotReachableError $error) {
            $pickwareLicenseLease = $pluginInstallation->getPickwareLicenseLease();
            $pickwareLicenseLeaseValidUntil = $pickwareLicenseLease?->getValidUntil();
            $pluginInstallationUpdatePayload = [
                'lastPickwareLicenseLeaseRefreshedAt' => $this->timeProvider->now(),
                'latestPickwareLicenseLeaseRefreshError' => $error->serializeToJsonApiError(),
            ];
            // We don't want to immediately remove the feature flags if the API is not reachable for whatever reason.
            // To that end, we only invalidate the feature flags if the lease is already expired.
            // The license lease validity might be significantly shorter than the actual license validity, but it should
            // be sufficiently long to allow for a few hours of downtime.
            if (!$pickwareLicenseLeaseValidUntil || $this->timeProvider->now() > $pickwareLicenseLeaseValidUntil) {
                $pluginInstallationUpdatePayload['pickwareLicenseLease'] = null;
            }

            $this->pluginInstallationRepository->update($pluginInstallationUpdatePayload, $context);

            return PickwareLicenseLeaseRefreshResult::Error;
        } catch (PickwareAccountApiClientException $error) {
            $this->pluginInstallationRepository->update(
                [
                    'pickwareLicenseLease' => null,
                    'lastPickwareLicenseLeaseRefreshedAt' => $this->timeProvider->now(),
                    'latestPickwareLicenseLeaseRefreshError' => $error->serializeToJsonApiError(),
                ],
                $context,
            );

            return PickwareLicenseLeaseRefreshResult::Error;
        }
    }

    /**
     * Refreshing the Pickware license lease should happen automatically via a scheduled task. However, if the scheduled
     * task is not executed for some reason, we want to ensure that the Pickware license lease is up to date.
     */
    public function ensureUpToDatePickwareLicenseLease(Context $context): void
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
        $pickwareLicenseLeaseRefreshThreshold = $this->timeProvider->now()->sub(
            DateInterval::createFromDateString(self::PICKWARE_LICENSE_LEASE_REFRESH_THRESHOLD),
        );

        if (
            $pluginInstallation->getLastPickwareLicenseLeaseRefreshedAt() === null
            || $pluginInstallation->getLastPickwareLicenseLeaseRefreshedAt() < $pickwareLicenseLeaseRefreshThreshold
        ) {
            $this->refreshPickwareLicenseLease($context);
        }
    }

    public function connectToPickwareAccountViaOidcAccessToken(string $accessToken, Context $context): PickwareAccountConnectionResult
    {
        $this->pluginInstallationRepository->update(
            ['pickwareAccountAccessToken' => $accessToken],
            $context,
        );

        $licenseRefreshResult = $this->refreshPickwareLicense($context);
        if ($licenseRefreshResult === PickwareLicenseRefreshResult::Error) {
            return PickwareAccountConnectionResult::LicenseRefreshError;
        }

        $leaseRefreshResult = $this->refreshPickwareLicenseLease($context);
        if ($leaseRefreshResult === PickwareLicenseLeaseRefreshResult::Error) {
            return PickwareAccountConnectionResult::LicenseLeaseRefreshError;
        }

        return PickwareAccountConnectionResult::Success;
    }

    public function clearConnectionToPickwareAccount(Context $context): void
    {
        $this->pluginInstallationRepository->update(
            [
                'pickwareAccountAccessToken' => null,
                'pickwareLicense' => null,
                'pickwareLicenseLease' => null,
                'latestPickwareLicenseLeaseRefreshError' => null,
                'latestPickwareLicenseRefreshError' => null,
                'latestUsageReportError' => null,
                'lastPickwareLicenseLeaseRefreshedAt' => null,
            ],
            $context,
        );
    }

    public function disconnectPickwareAccount(Context $context): void
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
        $pickwareLicense = $pluginInstallation->getPickwareLicense();
        if ($pickwareLicense === null) {
            throw PickwareAccountServiceException::createPickwareAccountNotConnectedError();
        }

        try {
            $this->pickwareAccountApiClient->disconnectFromPickwareAccount(new PickwareLicenseLeaseOptions(
                licenseUuid: $pickwareLicense->getLicenseUuid(),
                installationUuid: $pluginInstallation->getInstallationUuid(),
                shopUuid: $pickwareLicense->getShopUuid(),
                shopUrl: $this->shopUrl,
                serverTime: $this->timeProvider->now(),
            ));
        } catch (PickwareAccountApiClientException $error) {
            throw PickwareAccountServiceException::createPickwareAccountApiClientException($error);
        }

        $this->pluginInstallationRepository->update(
            [
                'pickwareAccountAccessToken' => null,
                'pickwareLicense' => null,
                'pickwareLicenseLease' => null,
                'latestPickwareLicenseLeaseRefreshError' => null,
                'latestPickwareLicenseRefreshError' => null,
                'latestUsageReportError' => null,
                'lastPickwareLicenseLeaseRefreshedAt' => null,
            ],
            $context,
        );
    }

    public function isPickwareAccountConnected(Context $context): bool
    {
        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);

        return $pluginInstallation->getPickwareLicense() !== null;
    }
}
