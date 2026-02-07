<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config;

use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentCustomer;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMessage;
use Pickware\DatevBundle\Config\AccountAssignment\AccountAssignmentMetadata;
use Pickware\DatevBundle\Config\AccountAssignment\AccountDetermination;
use Pickware\DatevBundle\Config\AccountAssignment\AccountRule;
use Pickware\DatevBundle\Config\AccountAssignment\AccountRuleStack;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\CountryCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\HasNoVatIdCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\IntraCommunityCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\IntraCommunityFallbackCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\PaymentMethodCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\TaxFreeCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\TaxRateCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Conditions\ThirdCountryCondition;
use Pickware\DatevBundle\Config\AccountAssignment\Item\CashMovementRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\ClearingAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\DebtorAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Pickware\DatevBundle\Config\AccountAssignment\MessageRule;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

class AccountRuleStackCreationService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return AccountRuleStack<RevenueAccountRequestItem>
     */
    public function createRevenueAccountRuleStack(ConfigValues $configValues): AccountRuleStack
    {
        return new AccountRuleStack([
            // Intra community delivery account rules
            ...$this->getRulesForRegularIntraCommunityDeliveries($configValues),
            ...$this->getRulesForIrregularIntraCommunityDeliveries($configValues),

            // Third country delivery account rules
            ...$this->getRulesForThirdCountryDeliveries($configValues),

            // Revenue accounts by country and tax rate
            ...$this->getRulesForRevenueAccountsByCountryAndTaxRate($configValues),

            // Default revenue accounts
            ...$this->getRulesForDefaultRevenueAccounts($configValues),
            ...$this->getRulesForTaxFreeFallbackRevenueAccounts($configValues),
        ]);
    }

    /**
     * @return AccountRuleStack<DebtorAccountRequestItem>
     */
    public function createDebtorAccountsRuleStack(
        ConfigValues $configValues,
        AccountAssignmentCustomer $accountAssignmentCustomer,
    ): AccountRuleStack {
        $config = $configValues->getCollectiveDebtorAccounts();
        /** @var array<AccountRule<ClearingAccountRequestItem>> $accountRules */
        $accountRules = [];
        $individualDebtorFeatureActive = $this->featureFlagService->isActive(IndividualDebtorAccountsFeatureFlag::NAME);

        if ($individualDebtorFeatureActive) {
            $customerSpecificAccount = $accountAssignmentCustomer->getCustomerSpecificDebtorAccount();
            if ($customerSpecificAccount !== null) {
                if ($this->accountFormatCorrect($customerSpecificAccount)) {
                    $accountRules[] = new AccountRule(
                        AccountDetermination::createForCustomerSpecificDebtorAccount((int) $customerSpecificAccount),
                        ImmutableCollection::create(),
                    );
                } else {
                    $accountRules[] = new MessageRule(
                        fn() => ImmutableCollection::create([
                            AccountAssignmentMessage::createCustomerSpecificDebtorAccountDoesNotMatchIndividualDebtorFormatMessage(
                                $customerSpecificAccount,
                                $accountAssignmentCustomer->getCustomerNumber() ?? '',
                            ),
                        ]),
                        ImmutableCollection::create(),
                    );
                }

                // return early to avoid matching any other accounts if the customer specific debtor account is invalid
                // in this case the account should always be the fallback account
                if ($config->getDefaultAccount() !== null) {
                    $accountRules[] = new AccountRule(AccountDetermination::createForStaticAccount($config->getDefaultAccount()), ImmutableCollection::create());
                }

                return new AccountRuleStack(sortedRules: $accountRules);
            }
        }

        if ($individualDebtorFeatureActive && $configValues->isIndividualDebtorDetermination()) {
            $customerNumber = $accountAssignmentCustomer->getCustomerNumber();
            if ($customerNumber !== null && $this->accountFormatCorrect($customerNumber)) {
                $accountRules[] = new AccountRule(
                    AccountDetermination::createForIndividualDebtorAccount((int) $customerNumber),
                    ImmutableCollection::create(),
                );
            }

            $accountRules[] = new MessageRule(
                fn() => ImmutableCollection::create([
                    AccountAssignmentMessage::createCustomerNumberDoesNotMatchIndividualDebtorFormatMessage($customerNumber ?? ''),
                ]),
                ImmutableCollection::create(),
            );
        } else {
            foreach ($config->getAccountsByPaymentMethodId() as $paymentMethodId => $account) {
                $accountRules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                    new PaymentMethodCondition($paymentMethodId),
                ]));
            }
        }

        if ($config->getDefaultAccount() !== null) {
            $accountRules[] = new AccountRule(AccountDetermination::createForStaticAccount($config->getDefaultAccount()), ImmutableCollection::create());
        }

        return new AccountRuleStack(sortedRules: $accountRules);
    }

    private function accountFormatCorrect(string $account): bool
    {
        return preg_match('/^\\d{1,8}$/', $account) === 1;
    }

    /**
     * @return AccountRuleStack<ClearingAccountRequestItem>
     */
    public function createClearingAccountRuleStack(ConfigValues $configValues): AccountRuleStack
    {
        $config = $configValues->getClearingAccounts();

        /** @var array<AccountRule<ClearingAccountRequestItem>> $accountRules */
        $accountRules = [];
        foreach ($config->getAccountsByPaymentMethodId() as $paymentMethodId => $account) {
            $accountRules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                new PaymentMethodCondition($paymentMethodId),
            ]));
        }

        if ($config->getDefaultAccount() !== null) {
            $accountRules[] = new AccountRule(AccountDetermination::createForStaticAccount($config->getDefaultAccount()), ImmutableCollection::create());
        }

        return new AccountRuleStack(sortedRules: $accountRules);
    }

    /**
     * @return AccountRuleStack<CashMovementRequestItem>
     */
    public function createCashMovementAccountRuleStack(ConfigValues $configValues): AccountRuleStack
    {
        $account = $configValues->getCashMovementAccounts()->getAccount();
        if ($account === null) {
            return new AccountRuleStack(sortedRules: []);
        }

        return new AccountRuleStack(sortedRules: [
            new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create()),
        ]);
    }

    /**
     * @return AccountRuleStack<CashMovementRequestItem>
     */
    public function createCashMovementContraAccountRuleStack(ConfigValues $configValues): AccountRuleStack
    {
        $contraAccount = $configValues->getCashMovementAccounts()->getContraAccount();
        if ($contraAccount === null) {
            return new AccountRuleStack(sortedRules: []);
        }

        return new AccountRuleStack(sortedRules: [
            new AccountRule(AccountDetermination::createForStaticAccount($contraAccount), ImmutableCollection::create()),
        ]);
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>>
     */
    private function getRulesForRevenueAccountsByCountryAndTaxRate(ConfigValues $configValues): array
    {
        $rules = [];
        foreach ($configValues->getRevenueAccounts()['accountsByCountryIsoCode'] ?? [] as $countryIsoCode => $countryConfig) {
            foreach ($countryConfig['accountsByTaxRate'] ?? [] as $taxRate => $account) {
                $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                    new CountryCondition($countryIsoCode),
                    new TaxRateCondition((string) $taxRate),
                ]));
            }
        }

        $rules[] = new MessageRule(
            fn(RevenueAccountRequestItem $item, AccountAssignmentMetadata $metadata) => ImmutableCollection::create([
                AccountAssignmentMessage::createUnknownShippingAddressCountryFormatMessage(
                    $metadata->getOrderNumber(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                ),
            ]),
            ImmutableCollection::create([
                new CountryCondition(PickwareDatevBundle::PICKWARE_SHOPIFY_UNKNOWN_COUNTRY_ISO_CODE),
            ]),
        );

        return $rules;
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>>
     */
    private function getRulesForDefaultRevenueAccounts(ConfigValues $configValues): array
    {
        $rules = [];
        foreach ($configValues->getRevenueAccounts()['defaultAccountsByTaxRate'] ?? [] as $taxRate => $account) {
            $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                new TaxRateCondition((string) $taxRate),
            ]));
        }

        return $rules;
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>|MessageRule<RevenueAccountRequestItem>>
     */
    private function getRulesForTaxFreeFallbackRevenueAccounts(ConfigValues $configValues): array
    {
        $fallbackAccount = ($configValues->getRevenueAccounts()['defaultAccountsByTaxRate'] ?? [])['0'] ?? null;
        if ($fallbackAccount === null) {
            return [];
        }

        return [
            new MessageRule(
                fn(RevenueAccountRequestItem $item) => ImmutableCollection::create([
                    AccountAssignmentMessage::createTaxFreeGermanyFallbackMessage($item->getOrderNumber()),
                ]),
                ImmutableCollection::create([
                    new TaxFreeCondition(),
                    new CountryCondition(countryIsoCode: 'DE'),
                ]),
            ),
            new AccountRule(AccountDetermination::createForStaticAccount($fallbackAccount), ImmutableCollection::create([
                new TaxFreeCondition(),
            ])),
        ];
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>|MessageRule<RevenueAccountRequestItem>>
     */
    private function getRulesForRegularIntraCommunityDeliveries(ConfigValues $configValues): array
    {
        $accountConfig = $configValues->getRevenueAccountsForIntraCommunityDeliveries();

        $rules = [
            new MessageRule(
                fn(RevenueAccountRequestItem $item) => ImmutableCollection::create([
                    AccountAssignmentMessage::createTaxFreeIntraCommunityFallbackMessage($item->getOrderNumber()),
                ]),
                ImmutableCollection::create([
                    new IntraCommunityCondition(),
                    new HasNoVatIdCondition(),
                ]),
            ),
        ];
        foreach ($accountConfig['accountsByCountryIsoCode'] ?? [] as $isoCode => $account) {
            $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                new IntraCommunityCondition(),
                new CountryCondition($isoCode),
            ]));
        }

        if (($accountConfig['defaultAccount'] ?? null) !== null) {
            $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($accountConfig['defaultAccount']), ImmutableCollection::create([
                new IntraCommunityCondition(),
            ]));
        }

        return $rules;
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>>
     */
    private function getRulesForIrregularIntraCommunityDeliveries(ConfigValues $configValues): array
    {
        $accountConfig = $configValues->getRevenueAccounts();

        $rules = [];
        foreach ($accountConfig['accountsByCountryIsoCode'] ?? [] as $isoCode => $countryConfig) {
            $taxRateZeroAccount = ($countryConfig['accountsByTaxRate'] ?? [])['0'] ?? null;
            if ($taxRateZeroAccount !== null) {
                $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($taxRateZeroAccount), ImmutableCollection::create([
                    new IntraCommunityFallbackCondition(),
                    new CountryCondition($isoCode),
                ]));
            }
        }

        return $rules;
    }

    /**
     * @return array<AccountRule<RevenueAccountRequestItem>>
     */
    private function getRulesForThirdCountryDeliveries(ConfigValues $configValues): array
    {
        $accountConfig = $configValues->getRevenueAccountsForThirdCountryDeliveries();

        $rules = [];
        foreach ($accountConfig['accountsByCountryIsoCode'] ?? [] as $isoCode => $account) {
            $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($account), ImmutableCollection::create([
                new ThirdCountryCondition(),

                new CountryCondition(
                    // Convert into string as array keys may be integers
                    (string) $isoCode,
                ),
            ]));
        }

        if ($accountConfig['defaultAccount'] !== null) {
            $rules[] = new AccountRule(AccountDetermination::createForStaticAccount($accountConfig['defaultAccount']), ImmutableCollection::create([
                new ThirdCountryCondition(),
            ]));
        }

        return $rules;
    }
}
