<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Customer\Controller;

use Exception;
use Pickware\DalBundle\CriteriaFactory;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityWrittenContainerEventExtension;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;
use Pickware\PickwarePos\Customer\CustomerCreation;
use Pickware\PickwarePos\Customer\CustomerCreationException;
use Pickware\PickwarePos\Customer\CustomerNotification;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class CustomerController
{
    private EntityManager $entityManager;
    private CriteriaFactory $criteriaFactory;
    private CustomerCreation $customerCreation;
    private CustomerNotification $customerNotification;

    public function __construct(
        EntityManager $entityManager,
        CriteriaFactory $criteriaFactory,
        CustomerCreation $customerCreation,
        CustomerNotification $customerNotification,
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaFactory = $criteriaFactory;
        $this->customerCreation = $customerCreation;
        $this->customerNotification = $customerNotification;
    }

    #[Route(path: '/api/_action/pickware-pos/create-customer', methods: ['POST'])]
    public function createCustomerAction(Context $context, Request $request): Response
    {
        $customerPayload = $request->get('customer');
        if (!$customerPayload) {
            return ResponseFactory::createParameterMissingResponse('customer');
        }
        if (!isset($customerPayload['id'])) {
            return ResponseFactory::createIdMissingForIdempotentCreationResponse('customer');
        }
        $branchStoreId = $request->get('branchStoreId');
        if (!$branchStoreId || !Uuid::isValid($branchStoreId)) {
            return ResponseFactory::createUuidParameterMissingResponse('branchStoreId');
        }
        /** @var BranchStoreEntity $branchStore */
        $branchStore = $this->entityManager->getByPrimaryKey(
            BranchStoreDefinition::class,
            $branchStoreId,
            $context,
        );
        if ($branchStore->getSalesChannelId() === null) {
            return (new JsonApiError([
                'status' => Response::HTTP_PRECONDITION_FAILED,
                'title' => Response::$statusTexts[Response::HTTP_PRECONDITION_FAILED],
                'detail' => sprintf(
                    'The provided branch store "%s" is not associated with a sales channel, which is required for '
                    . 'customer creation.',
                    $branchStore->getName(),
                ),
            ]))->toJsonApiErrorResponse();
        }

        $customerPayload['salesChannelId'] = $branchStore->getSalesChannelId();
        unset($customerPayload['salesChannel']);
        $customerId = $customerPayload['id'];
        $customerAssociations = $request->get('customerAssociations', []);
        $customerSearchCriteria = $this->criteriaFactory->makeCriteriaForEntitiesIdentifiedByIdWithAssociations(
            CustomerDefinition::class,
            [$customerId],
            $customerAssociations,
        );
        $requestNewsletterSubscription = $request->get('requestNewsletterSubscription', false);

        $customerWasCreated = false;
        $newsletterSubscriptionWasCreated = false;
        $this->entityManager->runInTransactionWithRetry(
            function() use (
                $context,
                $customerId,
                $customerPayload,
                $requestNewsletterSubscription,
                &$customerWasCreated,
                &$newsletterSubscriptionWasCreated
            ): void {
                $customerWrittenContainerEvent = $this->customerCreation->createPosCustomerIfNotExists(
                    $customerPayload,
                    $context,
                );
                $customerWasCreated = EntityWrittenContainerEventExtension::hasWrittenEntities(
                    $customerWrittenContainerEvent,
                );
                if ($requestNewsletterSubscription) {
                    $subscriptionWrittenContainerEvent = $this->customerCreation->createNewsletterSubscriptionIfNotExists(
                        $customerId,
                        $context,
                    );
                    $newsletterSubscriptionWasCreated = EntityWrittenContainerEventExtension::hasWrittenEntities(
                        $subscriptionWrittenContainerEvent,
                    );
                }
            },
        );

        /** @var JsonApiErrorSerializable[] $collectedErrors */
        $collectedErrors = [];
        if ($customerWasCreated) {
            /**
             * The CustomerFlowEventsSubscriber was added in 6.4.8.0 and dispatches the customer registration event
             * itself which in turn sends an email to the customer. Only the event in this controller in case that
             * subscriber is not present.
             * https://github.com/shopware/shopware/commit/38e67c1c095a9cca3aaee934eb251c7d6849ac38#diff-d56e8829b946695f80124eae51b440a8544abb9ab322c388a259ab681bbf496aR43-R62
             */
            if (!class_exists(CustomerFlowEventsSubscriber::class)) {
                try {
                    $this->customerNotification->notifyCustomerRegistration(
                        $customerId,
                        $context,
                    );
                } catch (Exception $e) {
                    $collectedErrors[] = CustomerCreationException::notifyCustomerRegistrationFailed($e);
                }
            }
            try {
                $this->customerNotification->notifyCustomerRecovery(
                    $customerId,
                    $context,
                );
            } catch (Exception $e) {
                $collectedErrors[] = CustomerCreationException::notifyCustomerRecoveryFailed($e);
            }
        }
        if ($requestNewsletterSubscription && $newsletterSubscriptionWasCreated) {
            try {
                $this->customerNotification->notifyNewsletterSubscription($customerId, $context);
            } catch (Exception $e) {
                $collectedErrors[] = CustomerCreationException::notifyCustomerNewsletterSubscriptionFailed($e);
            }
        }

        $customer = $this->entityManager->getOneBy(CustomerDefinition::class, $customerSearchCriteria, $context);

        return new JsonResponse([
            'customer' => $customer,
            'errors' => array_map(fn($error) => $error->serializeToJsonApiError(), $collectedErrors),
        ], Response::HTTP_CREATED);
    }
}
