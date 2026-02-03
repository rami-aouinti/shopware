<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MailDraft;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailDraftValidator
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function validate(MailDraft $mailDraft): void
    {
        $violationList = new ConstraintViolationList();
        $this->validateNonEmptyString(
            $violationList,
            [
                'senderName' => $mailDraft->getSenderName(),
                'subject' => $mailDraft->getSubject(),
            ],
        );
        $this->validateNonEmptyArray(
            $violationList,
            ['recipients' => $mailDraft->getRecipients()],
        );

        $mailAddresses = ['senderMail' => $mailDraft->getSenderEmailAddress()];
        foreach ($mailDraft->getRecipients() as $index => $recipient) {
            $mailAddresses[sprintf('recipient%s', $index + 1)] = $recipient;
        }
        $this->validateEmailAddresses($violationList, $mailAddresses);

        if ($violationList->count() > 0) {
            throw new ConstraintViolationException($violationList, $mailDraft->jsonSerialize());
        }
    }

    private function validateNonEmptyArray(ConstraintViolationList $violationList, array $values): void
    {
        $constraints = (new ConstraintBuilder())
            ->isArray()
            ->addConstraint(new Count(['min' => 1]))
            ->getConstraints();
        $this->validateValues($violationList, $values, $constraints);
    }

    private function validateNonEmptyString(ConstraintViolationList $violationList, array $values): void
    {
        $constraints = (new ConstraintBuilder())
            ->isNotBlank()
            ->getConstraints();
        $this->validateValues($violationList, $values, $constraints);
    }

    private function validateEmailAddresses(ConstraintViolationList $violationList, array $addresses): void
    {
        $constraints = (new ConstraintBuilder())
            ->isNotBlank()
            ->isEmail()
            ->getConstraints();
        $this->validateValues($violationList, $addresses, $constraints);
    }

    private function validateValues(ConstraintViolationList $violationList, array $values, array $constraints): void
    {
        foreach ($values as $path => $value) {
            $violationList->addAll(
                $this->validator->startContext()
                ->atPath($path)
                ->validate($value, $constraints)
                ->getViolations(),
            );
        }
    }
}
