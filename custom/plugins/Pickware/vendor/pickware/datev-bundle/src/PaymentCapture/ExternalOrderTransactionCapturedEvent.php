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

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ExternalOrderTransactionCapturedEvent
{
    /**
     * @var ImmutableCollection<ExternalOrderTransactionCapture>
     */
    private ImmutableCollection $captures;

    public function __construct(
        ImmutableCollection $captures,
        private readonly Context $context,
    ) {
        $this->captures = $captures;
    }

    /**
     * @return ImmutableCollection<ExternalOrderTransactionCapture>
     */
    public function getCaptures(): ImmutableCollection
    {
        return $this->captures;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
