<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Shopware\Core\Framework\Context;

interface HeaderExporter
{
    /**
     * @return string[][] The header as an array of lines of header entries.
     *      I.e. a two line header:
     *              header1; header2; header3\n
     *              header4; header5; header6\n
     *      turns into:
     *              ["header1", "header2", "header3"],
     *              ["header4", "header5", "header6"],
     */
    public function getHeader(string $exportId, Context $context): array;
}
