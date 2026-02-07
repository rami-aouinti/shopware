<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment;

use Pickware\DatevBundle\PaymentCapture\ExternalOrderTransactionCapture as NewExternalOrderTransactionCapture;

class_alias(NewExternalOrderTransactionCapture::class, ExternalOrderTransactionCapture::class);

// phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found
if (false) {
    // By defining the class in an unreachable if-body we tell Composer that this file contains the class but avoid that
    // PHP actually loads it. The class is already defined above via `class_alias` and defining the class again would
    // crash PHP. This is necessary because there exist a rare case where the Symfony autowiring breaks when the
    // Composer autoloader is optimized with the option "authoritative".
    // See https://github.com/pickware/shopware-plugins/issues/7232#issuecomment-2374756939
    /**
     * @deprecated Removed with version 3.0.0 of the DATEV Bundle. Use NewExternalOrderTransactionCapture instead.
     */
    class ExternalOrderTransactionCapture {}
}
