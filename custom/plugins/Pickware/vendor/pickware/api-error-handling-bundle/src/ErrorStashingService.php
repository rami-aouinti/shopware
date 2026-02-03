<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle;

use Throwable;

/**
 * Provides functionality to stash errors created during a request. Such stashed errors can be used to append debugging
 * information to a request when returning a response.
 */
class ErrorStashingService
{
    private const ERROR_LIMIT = 20;

    public function __construct(private array $errors = []) {}

    /**
     * @param Throwable[] $errors
     */
    public function stashErrors(array $errors): void
    {
        $callingInstance = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $callingFunctionIdentifier = sprintf('%s::%s', $callingInstance['file'], $callingInstance['function']);

        $this->errors[$callingFunctionIdentifier] ??= [];
        foreach ($errors as $error) {
            $this->errors[$callingFunctionIdentifier][] = $error;
        }
        if (count($this->errors[$callingFunctionIdentifier]) > self::ERROR_LIMIT) {
            array_splice(
                array: $this->errors[$callingFunctionIdentifier],
                offset: 0,
                length: count($this->errors[$callingFunctionIdentifier]) - self::ERROR_LIMIT,
            );
        }
    }

    public function getTotalStashedErrorCount(): int
    {
        return array_sum(array_map(
            fn(array $errorsOfFunctionIdentifier) => count($errorsOfFunctionIdentifier),
            $this->errors,
        ));
    }

    /**
     * @return array<string, Throwable[]>
     */
    public function getStashedErrorsAndClearStash(): array
    {
        $errors = $this->errors;

        $this->errors = [];

        return $errors;
    }
}
