<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Privacy;

/**
 * Can be used to determine weather customer data (e.g. email addresses or phone numbers) will be transferred to the
 * shipping provider.
 */
enum DataTransferPolicy: string
{
    case Always = 'always';
    case Never = 'never';
    /** Let the customer decide via a checkbox during checkout */
    case AskCustomer = 'ask_customer';
}
