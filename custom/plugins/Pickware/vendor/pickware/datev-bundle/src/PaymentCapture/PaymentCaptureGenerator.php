<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture;

use DateTimeInterface;
use Pickware\DatevBundle\PaymentCapture\DependencyInjection\PaymentCaptureGeneratorRegistry;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: PaymentCaptureGeneratorRegistry::DI_CONTAINER_TAG)]
interface PaymentCaptureGenerator
{
    public function supportsSalesChannelType(string $salesChannelTypeId): bool;

    public function getProjectedPaymentCaptureCount(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        DateTimeInterface $projectionDate,
        Context $context,
    ): int;

    public function createNextPaymentCaptureBatch(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        DateTimeInterface $projectionDate,
        Context $context,
    ): int;
}
