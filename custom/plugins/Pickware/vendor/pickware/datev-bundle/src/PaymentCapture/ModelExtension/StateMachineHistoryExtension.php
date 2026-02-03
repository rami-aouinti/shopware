<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\ModelExtension;

use Pickware\DalBundle\AbstractCompatibilityEntityExtension;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

class StateMachineHistoryExtension extends AbstractCompatibilityEntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pickwareDatevPaymentCaptures',
                PaymentCaptureDefinition::class,
                'state_machine_history_id',
                'id',
            ))->addFlags(new CascadeDelete(false /* isCloneRelevant */)),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return StateMachineHistoryDefinition::class;
    }
}
