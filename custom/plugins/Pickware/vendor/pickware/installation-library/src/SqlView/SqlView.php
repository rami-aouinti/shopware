<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\SqlView;

class SqlView
{
    private string $name;
    private string $selectStatement;
    private array $selectStatementParameters;
    private array $selectStatementParameterTypes;

    public function __construct(
        string $name,
        string $selectStatement,
        array $parameters = [],
        array $parameterTypes = [],
    ) {
        $this->name = $name;
        $this->selectStatement = $selectStatement;
        $this->selectStatementParameters = $parameters;
        $this->selectStatementParameterTypes = $parameterTypes;
    }

    public function getMySQLInstallQuery(): string
    {
        return sprintf('CREATE OR REPLACE VIEW `%s` AS %s', $this->name, $this->selectStatement);
    }

    public function getSelectStatementParameters(): array
    {
        return $this->selectStatementParameters;
    }

    public function getSelectStatementParameterTypes(): array
    {
        return $this->selectStatementParameterTypes;
    }
}
