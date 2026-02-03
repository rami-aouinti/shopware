<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\Elasticsearch;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

abstract class AbstractIndexer extends AbstractAdminIndexer
{
    public function __construct(
        protected readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize,
        private readonly string $entityName,
    ) {}

    public function getEntity(): string
    {
        return $this->entityName;
    }

    public function getName(): string
    {
        return str_replace('_', '-', $this->entityName) . '-listing';
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    private function encodeForElasticsearch(array $data): array
    {
        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = implode(' ', array_filter($row));
            $mapped[$id] = [
                'id' => $id,
                'text' => mb_strtolower($text),
            ];
        }

        return $mapped;
    }

    public function fetch(array $ids): array
    {
        $data = $this->getQuery($ids);

        return $this->encodeForElasticsearch($data);
    }

    abstract protected function getQuery(array $ids): array;
}
