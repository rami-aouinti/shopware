<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessSourceCollection;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessSourceDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessSourceEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

class StockingProcessCreation
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {}

    public function createStockingProcess(array $stockingProcessPayload, Context $context): void
    {
        $stockingProcessPayload['number'] = $this->numberRangeValueGenerator->getValue(
            StockingProcessNumberRange::TECHNICAL_NAME,
            $context,
            null,
        );
        $stockingProcessPayload['stateId'] = $this->initialStateIdLoader->get(
            StockingProcessStateMachine::TECHNICAL_NAME,
        );
        $this->validateSourcesAreNotAlreadyPartOfOtherStockProcess($stockingProcessPayload['sources'], $context);
        $stockingProcessPayload['sources'] = array_map(
            fn(array $sourcePayload) => StockLocationReference::create($sourcePayload)->toPayload(),
            $stockingProcessPayload['sources'],
        );

        $this->entityManager->create(
            StockingProcessDefinition::class,
            [$stockingProcessPayload],
            $context,
        );
    }

    private function validateSourcesAreNotAlreadyPartOfOtherStockProcess(array $sourcesPayload, Context $context): void
    {
        $filter = new MultiFilter(MultiFilter::CONNECTION_OR);
        foreach ($sourcesPayload as $sourcePayload) {
            $filter->addQuery(StockLocationReference::create($sourcePayload)->getFilterForStockDefinition());
        }
        $criteria = new Criteria();
        $criteria->addFilter($filter);

        /** @var StockingProcessSourceCollection $stockingProcessSources */
        $stockingProcessSources = $this->entityManager->findBy(
            StockingProcessSourceDefinition::class,
            $criteria,
            $context,
            [
                'goodsReceipt',
                'stockContainer',
            ],
        );

        if (count($stockingProcessSources) > 0) {
            // Improve error message for the most common cases.
            if ($stockingProcessSources->first()?->getGoodsReceipt()) {
                throw StockingProcessException::goodsReceiptAlreadyInUse(
                    $stockingProcessSources->first()->getGoodsReceipt()->getNumber(),
                );
            }
            if ($stockingProcessSources->first()?->getStockContainer()) {
                throw StockingProcessException::stockContainerAlreadyInUse(
                    $stockingProcessSources->first()->getStockContainer()->getNumber() ?? $stockingProcessSources->first()->getStockContainer()->getId(),
                );
            }

            throw StockingProcessException::sourceAlreadyInUse(
                $stockingProcessSources->reduce(
                    function(array $sourceIds, StockingProcessSourceEntity $source) {
                        $sourceIds[] = $source->getId();

                        return $sourceIds;
                    },
                    [],
                ),
            );
        }
    }
}
