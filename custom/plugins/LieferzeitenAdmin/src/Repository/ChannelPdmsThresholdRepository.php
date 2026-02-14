<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ChannelPdmsThresholdRepository
{
    public function __construct(
        private readonly EntityRepository $repository,
    ) {
    }

    public function upsert(array $payload, Context $context): void
    {
        $this->repository->upsert($payload, $context);
    }

    public function search(Criteria $criteria, Context $context)
    {
        return $this->repository->search($criteria, $context);
    }

    public function delete(array $ids, Context $context): void
    {
        $this->repository->delete($ids, $context);
    }
}
