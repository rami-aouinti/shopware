<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @method void add(IndividualDebtorAccountInformationEntity $entity)
 * @method void set(string $key, IndividualDebtorAccountInformationEntity $entity)
 * @method IndividualDebtorAccountInformationEntity[] getIterator()
 * @method IndividualDebtorAccountInformationEntity[] getElements()
 * @method IndividualDebtorAccountInformationEntity|null get(string $key)
 * @method IndividualDebtorAccountInformationEntity|null first()
 * @method IndividualDebtorAccountInformationEntity|null last()
 *
 * @extends EntityCollection<IndividualDebtorAccountInformationEntity>
 */
#[Exclude]
class IndividualDebtorAccountInformationCollection extends EntityCollection {}
