<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\DependencyInjection;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\PaymentCapture\PaymentCaptureGenerator;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class PaymentCaptureGeneratorRegistry extends AbstractRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_datev.payment_capture.payment_capture_generator';

    public function __construct(
        #[AutowireIterator(self::DI_CONTAINER_TAG)]
        iterable $paymentCaptureGenerators,
        private readonly EntityManager $entityManager,
    ) {
        parent::__construct(
            $paymentCaptureGenerators,
            [PaymentCaptureGenerator::class],
            self::DI_CONTAINER_TAG,
        );
    }

    /**
     * @param PaymentCaptureGenerator $instance
     */
    protected function getKey($instance): string
    {
        return get_class($instance);
    }

    public function getPaymentCaptureGeneratorForSalesChannel(string $salesChannelId, Context $context): PaymentCaptureGenerator
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(SalesChannelDefinition::class, $salesChannelId, $context);

        $foundGenerators = [];
        /** @var PaymentCaptureGenerator $instance */
        foreach ($this->registeredInstances as $instance) {
            if ($instance->supportsSalesChannelType($salesChannel->getTypeId())) {
                $foundGenerators[] = $instance;
            }
        }

        switch (count($foundGenerators)) {
            case 0:
                throw new InvalidArgumentException(sprintf(
                    'No payment capture generator found that supports the given sales channel type: %s',
                    $salesChannel->getTypeId(),
                ));
            case 1:
                return $foundGenerators[0];
            default:
                throw new InvalidArgumentException(sprintf(
                    'More than one payment capture generator found that supports the given sales channel type: %s',
                    $salesChannel->getTypeId(),
                ));
        }
    }
}
