<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CompanyCodes;

use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountAssignment;
use Pickware\DatevBundle\Config\AccountAssignment\Item\AccountRequestItem;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\FeatureFlagBundle\FeatureFlagService;

class CompanyCodesService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @template Item of AccountRequestItem
     * @param AccountAssignment<Item> $accountAssignment
     * @return AccountAssignment<Item>
     */
    public function applyCompanyCode(
        ConfigValues $configValues,
        AccountAssignment $accountAssignment,
        SpecificCompanyCodes $companyCodes,
        CompanyCodeMessageMetadata $companyCodeMessageMetadata,
    ): AccountAssignment {
        $config = $configValues->getCompanyCodes();
        $featureActive = $this->featureFlagService->isActive(CompanyCodesFeatureFlag::NAME);

        if ($featureActive && $config->isCompanyCodesActive()) {
            if ($companyCodes->getCustomerSpecificCompanyCode() !== null) {
                if ($this->isValidCompanyCode($companyCodes->getCustomerSpecificCompanyCode())) {
                    $accountAssignment->getAccountDetermination()
                        ->addCompanyCode((int) $companyCodes->getCustomerSpecificCompanyCode());

                    return $accountAssignment;
                }

                $accountAssignment->addMessage(CompanyCodeMessage::createCustomerSpecificCompanyCodeDoesNotMatchFormatMessage(
                    $companyCodes->getCustomerSpecificCompanyCode(),
                    $companyCodeMessageMetadata,
                ));
            } elseif ($companyCodes->getCustomerGroupSpecificCompanyCode() !== null) {
                if ($this->isValidCompanyCode($companyCodes->getCustomerGroupSpecificCompanyCode())) {
                    $accountAssignment->getAccountDetermination()
                        ->addCompanyCode((int) $companyCodes->getCustomerGroupSpecificCompanyCode());

                    return $accountAssignment;
                }

                $accountAssignment->addMessage(CompanyCodeMessage::createCustomerGroupSpecificCompanyCodeDoesNotMatchFormatMessage(
                    $companyCodes->getCustomerGroupSpecificCompanyCode(),
                    $companyCodeMessageMetadata,
                ));
            }

            if ($this->isValidCompanyCode($config->getDefaultCompanyCode())) {
                $accountAssignment->getAccountDetermination()->addCompanyCode($config->getDefaultCompanyCode());

                return $accountAssignment;
            }

            $accountAssignment->addMessage(CompanyCodeMessage::createCompanyCodeDoesNotMatchFormatMessage(
                $config->getDefaultCompanyCode(),
                $companyCodeMessageMetadata,
            ));

            return $accountAssignment;
        }

        return $accountAssignment;
    }

    private function isValidCompanyCode(int|string $companyCode): bool
    {
        if (is_string($companyCode)) {
            return preg_match('/^\\d{2}$/', $companyCode) === 1;
        }

        return $companyCode >= 0 && $companyCode <= 99;
    }
}
