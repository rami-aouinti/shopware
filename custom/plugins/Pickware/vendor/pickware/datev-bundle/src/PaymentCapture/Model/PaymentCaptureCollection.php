<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @method void add(PaymentCaptureEntity $entity)
 * @method void set(string $key, PaymentCaptureEntity $entity)
 * @method PaymentCaptureEntity[] getIterator()
 * @method PaymentCaptureEntity[] getElements()
 * @method PaymentCaptureEntity|null get(string $key)
 * @method PaymentCaptureEntity|null first()
 * @method PaymentCaptureEntity|null last()
 *
 * @extends EntityCollection<PaymentCaptureEntity>
 */
#[Exclude]
class PaymentCaptureCollection extends EntityCollection {}
