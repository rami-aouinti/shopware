<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Sql;

class SqlUuid
{
    // MySQL's UUID() method neither generates UUIDv4 nor works reliably, so we use this instead.
    public const UUID_V4_GENERATION = '
        UNHEX(CONCAT(
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0"),
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0"),
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0"),
            "4",
            LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, "0"),
            HEX(FLOOR(RAND() * 4 + 8)),
            LPAD(HEX(FLOOR(RAND() * 0x0fff)), 3, "0"),
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0"),
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0"),
            LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, "0")
        ))';
}
