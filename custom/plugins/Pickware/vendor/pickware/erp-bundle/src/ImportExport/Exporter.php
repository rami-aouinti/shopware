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

use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware_erp.import_export.exporter')]
interface Exporter
{
    /**
     * Exports a chunk of CSV file data from table pickware_erp_import_export_element.
     *
     * The chunk size can be chosen by the implementation of the method.
     *
     * Notes for implementation:
     *  * You should choose a chunk size so that the process time takes about 1 second as that allows the Message Queue
     *    to export multiple chunks per iteration even in case of system slowdown. A notably smaller chunk size would
     *    instead cause many database reads and writes per second which shouldn't happen in a production system if it
     *    can be avoided.
     *
     * @param int $nextRowNumberToWrite next row number that will be written to the db. Starts with 1 for each export.
     * @return int|null the index of the next unprocessed element, null if there are no elements left to export
     */
    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int;

    /**
     * Returns the EntityDefinition the Exporter is based on.
     *
     * @return class-string<EntityDefinition<Entity>> The EntityDefinition class name the Exporter is based on
     */
    public function getEntityDefinitionClassName(): string;

    /**
     * @param array<string, mixed> $config
     */
    public function validateConfig(array $config): JsonApiErrors;
}
