<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\OAuth\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(MobileAppCredentialEntity $entity)
 * @method void                     set(string $key, MobileAppCredentialEntity $entity)
 * @method MobileAppCredentialEntity[]    getIterator()
 * @method MobileAppCredentialEntity[]    getElements()
 * @method MobileAppCredentialEntity|null get(string $key)
 * @method MobileAppCredentialEntity|null first()
 * @method MobileAppCredentialEntity|null last()
 */
class MobileAppCredentialCollection extends EntityCollection {}
