<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DemodataGeneration\Command;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareWms\Delivery\DeliveryService;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\Device\Model\DeviceDefinition;
use Pickware\PickwareWms\Device\Model\DeviceEntity;
use Pickware\PickwareWms\Installation\PickwareWmsAclRoleFactory;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\PickingItem;
use Pickware\PickwareWms\PickingProcess\PickingProcessCreation;
use Pickware\PickwareWms\PickingProcess\PickingProcessService;
use Pickware\PickwareWms\PickingProcess\StockReversionAction;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\ShopwareExtensionsBundle\User\UserExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-type DataPool array{
 *     userIds: array<string>,
 *     orderIds: array<string>,
 *     orders: EntityCollection<OrderEntity>,
 *     warehouseIds: array<string>,
 *     productIds: array<string>,
 *     pickingProfileIds: array<string>,
 *     deviceIds: array<string>,
 * }
 * @phpstan-type PickingProcessProperties array{
 *     pickingProcessId: string,
 *     deliveryId: string,
 *     preCollectingStockContainerId: string,
 *     warehouseId: string,
 *     orderId: string,
 *     productId: string,
 *     pickingMode: string,
 *     pickedQuantity: int,
 * }
 * @phpstan-type EntityToGenerate array{
 *     identifier: string,
 *     class: class-string<EntityDefinition<Entity>>,
 *     quantity: int,
 *     alwaysCreateNewEntities: bool,
 *     existingEntityFilter?: string,
 *     generateFirst?: bool,
 * }
 */
#[AsCommand(
    name: 'pickware-wms:demodata:generate-picking-process-and-delivery-demodata',
    description: 'Generates picking process and delivery demodata',
)]
class GeneratePickingProcessAndDeliveryDemodataCommand extends Command
{
    private DateTime $startDate;
    private DateTime $endDate;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly PickingProcessService $pickingProcessService,
        private readonly PickingProcessCreation $pickingProcessCreation,
        private readonly DemodataService $demodataService,
        private readonly StockMovementService $stockMovementService,
        private readonly DeliveryService $deliveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Total number of users to use', 5)
            ->addOption('picking-profiles', null, InputOption::VALUE_REQUIRED, 'Number of picking profiles to use', 5)
            ->addOption('warehouses', null, InputOption::VALUE_REQUIRED, 'Number of warehouses to use', 3)
            ->addOption('devices', null, InputOption::VALUE_REQUIRED, 'Number of devices to use', 3)
            ->addOption(
                'sequences',
                's',
                InputOption::VALUE_REQUIRED,
                'Distribution of lifecycle sequences per picking mode (comma-separated, 6 values): ' .
                'standard,cancelled_before_completion,cancelled_after_completion,taken_over,deferred_continued,deferred_only. ' .
                'Example: "17,2,1,3,1,1" creates 17 standard + 2 cancelled before + 1 cancelled after + 3 taken over + 1 deferred continued + 1 deferred only per mode (25 total per mode = 100 total)',
                '17,2,1,3,1,1',
            )
            ->addOption(
                'start-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Start date for random timestamp generation (e.g., "1 month ago", "2024-01-01")',
                '1 month ago',
            )
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_REQUIRED,
                'End date for random timestamp generation (e.g., "now", "2024-12-31")',
                'now',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usersCount = $this->validatePositiveInteger($input->getOption('users'));
        if ($usersCount === null) {
            $io->error('Invalid value for --users option. Must be a strictly positive integer.');

            return Command::FAILURE;
        }

        $pickingProfilesCount = $this->validatePositiveInteger($input->getOption('picking-profiles'));
        if ($pickingProfilesCount === null) {
            $io->error('Invalid value for --picking-profiles option. Must be a strictly positive integer.');

            return Command::FAILURE;
        }

        $warehouseCount = $this->validatePositiveInteger($input->getOption('warehouses'));
        if ($warehouseCount === null) {
            $io->error('Invalid value for --warehouses option. Must be a strictly positive integer.');

            return Command::FAILURE;
        }

        $deviceCount = $this->validatePositiveInteger($input->getOption('devices'));
        if ($deviceCount === null) {
            $io->error('Invalid value for --devices option. Must be a strictly positive integer.');

            return Command::FAILURE;
        }

        $lifecycleSequenceDistribution = $this->parseSequencesOption($input->getOption('sequences'));
        if ($lifecycleSequenceDistribution === null) {
            $io->error('Invalid sequences option. Must be 6 comma-separated integers (e.g., "17,2,1,3,1,1").');

            return Command::FAILURE;
        }

        $this->startDate = new DateTime($input->getOption('start-date'));
        $this->endDate = new DateTime($input->getOption('end-date'));

        $totalProcessesPerMode = array_sum(array_values($lifecycleSequenceDistribution));
        $totalProcesses = $totalProcessesPerMode * count(PickingProcessDefinition::PICKING_MODES);

        $io->title('Picking Statistic Demodata Generator');
        $io->table(
            [
                'Configuration',
                'Value',
            ],
            [
                [
                    'Total users',
                    $usersCount,
                ],
                [
                    'Picking profiles',
                    $pickingProfilesCount,
                ],
                [
                    'Warehouses',
                    $warehouseCount,
                ],
                [
                    'Devices',
                    $deviceCount,
                ],
                [
                    'Processes per mode',
                    $totalProcessesPerMode,
                ],
                [
                    'Total processes',
                    $totalProcesses,
                ],
                [
                    'Start date',
                    $this->startDate->format(DateTimeInterface::RFC3339),
                ],
                [
                    'End date',
                    $this->endDate->format(DateTimeInterface::RFC3339),
                ],
                [
                    'Sequence distribution',
                    implode(', ', array_map(
                        fn($key, $value) => "{$key}: {$value}",
                        array_keys($lifecycleSequenceDistribution),
                        $lifecycleSequenceDistribution,
                    )),
                ],
            ],
        );

        $io->warning('WARNING: This command will create demodata in your database. Do NOT run this on production systems!');
        if ($input->isInteractive() && !$io->confirm('Do you want to continue?', false)) {
            $io->info('Command cancelled by user.');

            return Command::SUCCESS;
        }
        $entitiesToGenerate = [
            // We need to make sure that some Customers and Products exist before generating orders, because Shopware's
            // OrderGenerator will crash otherwise.
            [
                'identifier' => 'customer',
                'class' => CustomerDefinition::class,
                'quantity' => 1,
                'alwaysCreateNewEntities' => true,
                'generateFirst' => true,
            ],
            [
                'identifier' => 'product',
                'class' => ProductDefinition::class,
                // Shopware's OrderGenerator requires at least 5 products to exist as it will create 3-5 product line
                // items per order.
                'quantity' => 5,
                'alwaysCreateNewEntities' => true,
                'generateFirst' => true,
            ],
            [
                'identifier' => 'user',
                'class' => UserDefinition::class,
                'quantity' => $usersCount,
                'alwaysCreateNewEntities' => false,
            ],
            [
                'identifier' => 'order',
                'class' => OrderDefinition::class,
                'quantity' => $totalProcesses,
                'alwaysCreateNewEntities' => true,
            ],
            [
                'identifier' => 'warehouse',
                'class' => WarehouseDefinition::class,
                'quantity' => $warehouseCount,
                'alwaysCreateNewEntities' => false,
            ],
            [
                'identifier' => 'pickingProfile',
                'class' => PickingProfileDefinition::class,
                'quantity' => $pickingProfilesCount,
                // Only use existing picking profiles if they don't have any restrictions so that we can pick any order
                // with them.
                'existingEntityFilter' => 'filter IS NULL',
                'alwaysCreateNewEntities' => false,
            ],
            [
                'identifier' => 'device',
                'class' => DeviceDefinition::class,
                'quantity' => $deviceCount,
                'alwaysCreateNewEntities' => false,
            ],
        ];
        $dataPool = $this->prepareDemodata($io, $entitiesToGenerate);
        // Now that we have both productIds (from Phase 1) and orderIds (from Phase 2), ensure orders have product line items
        $this->ensureOrdersHaveProductLineItems($dataPool['orderIds'], $dataPool['productIds']);
        $dataPool['orders'] = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $dataPool['orderIds']],
            Context::createDefaultContext(),
            ['lineItems'],
        );

        $this->ensureUsersHaveAclRole($dataPool['userIds']);
        $this->setAllOrderLineItemQuantitiesToSameQuantity($dataPool['orderIds']);
        $this->ensureOrdersArePickable($dataPool['orderIds'], $dataPool['warehouseIds']);

        $io->section('Simulating picking process lifecycles');
        $this->simulatePickingProcessLifecycles($io, $dataPool, $lifecycleSequenceDistribution);

        $io->success('Demodata generation completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * @param array<EntityToGenerate> $entitiesToGenerate
     * @return DataPool
     */
    private function prepareDemodata(SymfonyStyle $io, array $entitiesToGenerate): array
    {
        $dataPool = [
            ...$this->generateDemodata(
                $io,
                array_filter($entitiesToGenerate, fn($entityToGenerate) => $entityToGenerate['generateFirst'] ?? false),
            ),
            ...$this->generateDemodata(
                $io,
                array_filter($entitiesToGenerate, fn($entityToGenerate) => !($entityToGenerate['generateFirst'] ?? false)),
            ),
        ];
        $this->ensureOrdersHaveProductLineItems($dataPool['orderIds'], $dataPool['productIds']);
        $dataPool['orders'] = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $dataPool['orderIds']],
            Context::createDefaultContext(),
            ['lineItems'],
        );

        return $dataPool;
    }

    /**
     * @param array<EntityToGenerate> $entitiesToGenerate
     * @return array<string, mixed>
     */
    private function generateDemodata(SymfonyStyle $io, array $entitiesToGenerate): array
    {
        $request = new DemodataRequest();
        foreach ($entitiesToGenerate as $entityToGenerate) {
            $entityName = $entityToGenerate['class']::ENTITY_NAME;
            if ($entityToGenerate['alwaysCreateNewEntities']) {
                $request->add($entityToGenerate['class'], $entityToGenerate['quantity']);
            } else {
                $requiredCount = $entityToGenerate['quantity'];
                $additionalFilter = $entityToGenerate['existingEntityFilter'] ?? '1=1';
                $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$entityName} WHERE {$additionalFilter}");
                if ($count < $requiredCount) {
                    $request->add($entityToGenerate['class'], $requiredCount - $count);
                }
            }
        }

        $this->demodataService->generate($request, Context::createDefaultContext(), $io);

        $dataPool = [];
        foreach ($entitiesToGenerate as $entityToGenerate) {
            $entityName = $entityToGenerate['class']::ENTITY_NAME;
            $limit = $entityToGenerate['quantity'];
            $additionalFilter = $entityToGenerate['existingEntityFilter'] ?? '1=1';
            $dataPool[$entityToGenerate['identifier'] . 'Ids'] = $this->connection->fetchFirstColumn(
                sprintf(
                    'SELECT LOWER(HEX(id)) FROM `%s` WHERE %s ORDER BY `created_at` DESC LIMIT %d',
                    $entityName,
                    $additionalFilter,
                    $limit,
                ),
            );
        }

        return $dataPool;
    }

    /**
     * @param array<string> $userIds
     */
    private function ensureUsersHaveAclRole(array $userIds): void
    {
        $wmsAclRoleId = $this->entityManager->findIdsBy(
            AclRoleDefinition::class,
            ['name' => PickwareWmsAclRoleFactory::PICKWARE_WMS_ROLE_NAME],
            Context::createDefaultContext(),
        )[0];

        /** @var EntityCollection<UserEntity> $users */
        $users = $this->entityManager->findBy(
            UserDefinition::class,
            ['id' => $userIds],
            Context::createDefaultContext(),
            ['aclRoles'],
        );

        $this->entityManager->update(
            UserDefinition::class,
            ImmutableCollection::create($users)
                ->filter(fn(UserEntity $user) => $user->getAclRoles()->count() === 0 && !UserExtension::isAdmin($user))
                ->map(function(UserEntity $user) use ($wmsAclRoleId): array {
                    $shouldBeAdmin = Random::getBoolean();

                    return [
                        'id' => $user->getId(),
                        'aclRoles' => $shouldBeAdmin ? [] : [['id' => $wmsAclRoleId]],
                        'admin' => $shouldBeAdmin,
                    ];
                })->asArray(),
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string> $orderIds
     * @param array<string> $warehouseIds
     */
    private function ensureOrdersArePickable(array $orderIds, array $warehouseIds): void
    {
        $productIds = $this->connection->fetchFirstColumn(
            '
            SELECT DISTINCT LOWER(HEX(`p`.`id`))
            FROM `order` `o`
            INNER JOIN `order_line_item` `oli` ON `oli`.`order_id` = `o`.`id` AND `oli`.`order_version_id` = `o`.`version_id`
            INNER JOIN `product` `p` ON `oli`.`product_id` = `p`.`id`
            WHERE `o`.`id` IN (:orderIds)
            AND `o`.`version_id` = :versionId',
            [
                'orderIds' => array_map('hex2bin', $orderIds),
                'versionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'orderIds' => ArrayParameterType::BINARY,
            ],
        );
        $this->stockMovementService->moveStock(
            ImmutableCollection::create($productIds)
                ->flatMap(fn(string $productId) => ImmutableCollection::create($warehouseIds)
                    ->map(fn(string $warehouseId) => StockMovement::create([
                        'productId' => $productId,
                        'quantity' => 1_000_000,
                        'source' => StockLocationReference::unknown(),
                        'destination' => StockLocationReference::warehouse($warehouseId),
                    ])))->asArray(),
            Context::createDefaultContext(),
        );
    }

    /**
     * Ensures all orders have at least one product line item by adding one if missing.
     *
     * @param array<string> $orderIds
     * @param array<string> $productIds
     */
    private function ensureOrdersHaveProductLineItems(array $orderIds, array $productIds): void
    {
        $ordersWithoutProductLineItems = $this->connection->fetchFirstColumn(
            '
            SELECT DISTINCT LOWER(HEX(`o`.`id`))
            FROM `order` `o`
            WHERE `o`.`id` IN (:orderIds)
            AND `o`.`version_id` = :versionId
            AND NOT EXISTS (
                SELECT 1
                FROM `order_line_item` `oli`
                WHERE `oli`.`order_id` = `o`.`id`
                AND `oli`.`order_version_id` = `o`.`version_id`
                AND `oli`.`product_id` IS NOT NULL
            )',
            [
                'orderIds' => array_map('hex2bin', $orderIds),
                'versionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'orderIds' => ArrayParameterType::BINARY,
            ],
        );

        if (empty($ordersWithoutProductLineItems)) {
            return;
        }

        $productId = $productIds[0];
        $price = new CalculatedPrice(
            10.0,
            100.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            10,
        );

        $lineItemsToCreate = array_map(
            function(string $orderId) use ($productId, $price): array {
                $id = Uuid::randomHex();

                return [
                    'id' => $id,
                    'identifier' => $id,
                    'orderId' => $orderId,
                    'productId' => $productId,
                    'referencedId' => $productId,
                    'quantity' => 10,
                    'label' => 'Demo Product',
                    'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                    'position' => 1,
                    'price' => $price,
                    'priceDefinition' => null,
                    'payload' => [
                        'productNumber' => 'DEMO-001',
                    ],
                    'good' => true,
                    'removable' => true,
                    'stackable' => true,
                ];
            },
            $ordersWithoutProductLineItems,
        );

        $this->entityManager->create(
            OrderLineItemDefinition::class,
            $lineItemsToCreate,
            Context::createDefaultContext(),
        );
    }

    /**
     * @param DataPool &$dataPool
     * @param array<string, int> $lifecycleSequenceDistribution
     */
    private function simulatePickingProcessLifecycles(SymfonyStyle $io, array &$dataPool, array $lifecycleSequenceDistribution): void
    {
        $totalLifecycles = count(PickingProcessDefinition::PICKING_MODES) * array_sum(array_values($lifecycleSequenceDistribution));
        $progressBar = $io->createProgressBar($totalLifecycles);
        $progressBar->start();

        foreach (PickingProcessDefinition::PICKING_MODES as $pickingMode) {
            foreach ($lifecycleSequenceDistribution as $sequence => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $this->simulatePickingProcessLifecycle(
                        $dataPool,
                        match ($sequence) {
                            'standard' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Complete,
                            ],
                            'cancelled_before_completion' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Cancel,
                            ],
                            'cancelled_after_completion' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Complete,
                                PickingProcessLifecycleEvent::Cancel,
                            ],
                            'taken_over' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::TakeOver,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Complete,
                            ],
                            'deferred_continued' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Defer,
                                PickingProcessLifecycleEvent::Continue,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Complete,
                            ],
                            'deferred_only' => [
                                PickingProcessLifecycleEvent::CreateAndStart,
                                PickingProcessLifecycleEvent::Pick,
                                PickingProcessLifecycleEvent::Defer,
                            ],
                            default => throw new InvalidArgumentException('Invalid sequence: ' . $sequence),
                        },
                        $pickingMode,
                    );

                    $progressBar->advance();
                }
            }
        }

        $progressBar->finish();
        $io->newLine(2);
    }

    /**
     * @param DataPool &$dataPool
     * @param array<PickingProcessLifecycleEvent> $pickingProcessLifecycleEvents
     */
    private function simulatePickingProcessLifecycle(array &$dataPool, array $pickingProcessLifecycleEvents, string $pickingMode): void
    {
        $this->setClockToRandomDateTime();
        $userId = $this->getRandomArrayElementWithUnevenDistribution($dataPool['userIds']);
        $context = $this->createContextWithUserIdAndDeviceId($userId, Random::getRandomArrayElement($dataPool['deviceIds']));
        if (array_shift($pickingProcessLifecycleEvents) !== PickingProcessLifecycleEvent::CreateAndStart) {
            throw new InvalidArgumentException('Picking process lifecycle events must start with CreateAndStart.');
        }
        $warehouseId = Random::getRandomArrayElement($dataPool['warehouseIds']);
        $orderId = array_shift($dataPool['orderIds']);
        /** @var OrderEntity $order */
        $order = $dataPool['orders']->get($orderId);
        $pickingProcessProperties = [
            'warehouseId' => $warehouseId,
            'orderId' => $orderId,
            'pickingMode' => $pickingMode,
            'productId' => $order->getLineItems()
                ->firstWhere(fn(OrderLineItemEntity $orderLineItem) => $orderLineItem->getProductId() !== null)
                ->getProductId(),
            'pickedQuantity' => 0,
            ...$this->createAndStartPickingProcess(
                $orderId,
                $warehouseId,
                Random::getRandomArrayElement($dataPool['pickingProfileIds']),
                $pickingMode,
                $context,
            ),
        ];
        foreach ($pickingProcessLifecycleEvents as $pickingProcessLifecycleEvent) {
            $this->sleepRespectingEndDate(match ($pickingProcessLifecycleEvent) {
                PickingProcessLifecycleEvent::Complete,
                PickingProcessLifecycleEvent::Pick => random_int(10, 100), // 10-100 seconds
                PickingProcessLifecycleEvent::Cancel,
                PickingProcessLifecycleEvent::Defer => 60 * random_int(1, 10), // 1-10 minutes
                PickingProcessLifecycleEvent::CreateAndStart,
                PickingProcessLifecycleEvent::TakeOver,
                PickingProcessLifecycleEvent::Continue => 60 * 60 * random_int(10, 20), // 10-20 hours
            });
            match ($pickingProcessLifecycleEvent) {
                PickingProcessLifecycleEvent::Defer => $this->pickingProcessService->defer(
                    $pickingProcessProperties['pickingProcessId'],
                    $context,
                ),
                PickingProcessLifecycleEvent::Continue => $this->pickingProcessService->startOrContinue(
                    $pickingProcessProperties['pickingProcessId'],
                    Random::getRandomArrayElement($dataPool['pickingProfileIds']),
                    $context,
                ),
                PickingProcessLifecycleEvent::Pick => $this->pickItem(
                    $pickingProcessProperties,
                    $context,
                ),
                PickingProcessLifecycleEvent::Complete => $this->completePickingProcess(
                    $pickingProcessProperties,
                    $context,
                ),
                PickingProcessLifecycleEvent::TakeOver => (function() use ($pickingProcessProperties, $dataPool, &$context): void {
                    $context = $this->createContextWithUserIdAndDeviceId(
                        Random::getRandomArrayElement($dataPool['userIds']),
                        Random::getRandomArrayElement($dataPool['deviceIds']),
                    );
                    $this->pickingProcessService->takeOver(
                        $pickingProcessProperties['pickingProcessId'],
                        Random::getRandomArrayElement($dataPool['pickingProfileIds']),
                        $context,
                    );
                })(),
                PickingProcessLifecycleEvent::Cancel => $this->pickingProcessService->cancel(
                    $pickingProcessProperties['pickingProcessId'],
                    $context,
                    StockReversionAction::StockToUnknownLocation,
                ),
                PickingProcessLifecycleEvent::CreateAndStart => throw new InvalidArgumentException('Picking process can only be created once.'),
            };
        }
    }

    /**
     * @return array{
     *     pickingProcessId: string,
     *     deliveryId: string,
     *     preCollectingStockContainerId: string,
     * }
     */
    private function createAndStartPickingProcess(string $orderId, string $warehouseId, string $pickingProfileId, string $pickingMode, Context $context): array
    {
        $pickingProcessId = Uuid::randomHex();
        $preCollectingStockContainerId = Uuid::randomHex();

        $payload = [
            'id' => $pickingProcessId,
            'warehouseId' => $warehouseId,
            'pickingMode' => $pickingMode,
            'deliveries' => [
                [
                    'stockContainer' => [],
                    'orderId' => $orderId,
                ],
            ],
        ];
        if (in_array($pickingMode, [PickingProcessDefinition::PICKING_MODE_PRE_COLLECTED_BATCH_PICKING, PickingProcessDefinition::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING], true)) {
            $payload['preCollectingStockContainer'] = [
                'id' => $preCollectingStockContainerId,
            ];
        }
        $this->pickingProcessCreation->createPickingProcess(
            $payload,
            $pickingProfileId,
            $context,
        );

        $this->pickingProcessService->startOrContinue(
            $pickingProcessId,
            $pickingProfileId,
            $context,
        );

        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            ['deliveries'],
        );

        return [
            'pickingProcessId' => $pickingProcessId,
            'deliveryId' => array_values($pickingProcess->getDeliveries()->getIds())[0],
            'preCollectingStockContainerId' => $preCollectingStockContainerId,
        ];
    }

    /**
     * @param PickingProcessProperties $pickingProcessProperties
     */
    private function completePickingProcess(array $pickingProcessProperties, Context $context): void
    {
        if (
            in_array($pickingProcessProperties['pickingMode'], [
                PickingProcessDefinition::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING,
                PickingProcessDefinition::PICKING_MODE_PRE_COLLECTED_BATCH_PICKING,
            ], true)
        ) {
            $this->pickingProcessService->completePreCollecting(
                $pickingProcessProperties['pickingProcessId'],
                $context,
            );
            $this->pickingProcessService->pickItemIntoDelivery(
                $pickingProcessProperties['deliveryId'],
                new PickingItem(
                    stockMovementId: Uuid::randomHex(),
                    source: StockLocationReference::stockContainer($pickingProcessProperties['preCollectingStockContainerId']),
                    productId: $pickingProcessProperties['productId'],
                    batchId: null,
                    quantity: $pickingProcessProperties['pickedQuantity'],
                    pickingPropertyRecords: [],
                ),
                $context,
            );
            $this->deliveryService->completeDelivery(
                $pickingProcessProperties['deliveryId'],
                $context,
            );
        } else {
            $this->pickingProcessService->complete(
                $pickingProcessProperties['pickingProcessId'],
                $context,
            );
        }
    }

    /**
     * To be able to easily calculate how many orders we need for the required number of picks, we set all order line
     * items to have the same quantity.
     * @param array<string> $orderIds
     */
    private function setAllOrderLineItemQuantitiesToSameQuantity(array $orderIds): void
    {
        $this->connection->executeStatement(
            'UPDATE `order_line_item` SET `quantity` = 10 WHERE `order_id` IN (:orderIds) AND `version_id` = :versionId',
            [
                'orderIds' => array_map('hex2bin', (array)$orderIds),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['orderIds' => ArrayParameterType::BINARY],
        );
    }

    private function createContextWithUserIdAndDeviceId(string $userId, string $deviceId): Context
    {
        $source = new AdminApiSource($userId);
        // Usually, Shopware's ApiRequestContextResolver takes care of copying over the admin flag and the permissions
        // array from the user to the source. However, as we directly call the service here and don't use a request, we
        // need to manually set these values. Because it's easier and achieves the same (bypassing acl validation), we
        // just set the admin flag to true.
        $source->setIsAdmin(true);
        $context = new Context($source);
        /** @var DeviceEntity $device */
        $device = $this->entityManager->getByPrimaryKey(DeviceDefinition::class, $deviceId, $context);
        (new Device($deviceId, $device->getName()))->addToContext($context);

        return $context;
    }

    /**
     * Returns a random element from the given array with higher indices having an exponentially higher probability of
     * being selected.
     * @template T
     * @param list<T> $array
     * @return T
     */
    private function getRandomArrayElementWithUnevenDistribution(array $array): mixed
    {
        $weights = array_map(
            fn($key) => 0.6 ** $key,
            array_keys($array),
        );
        $totalWeight = array_sum($weights);
        $cumulativeWeights = [];
        foreach ($weights as $key => $weight) {
            $cumulativeWeights[$key] = ($cumulativeWeights[$key - 1] ?? 0) + $weight;
        }

        $randomValue = (mt_rand() / mt_getrandmax()) * $totalWeight;
        foreach ($cumulativeWeights as $key => $cumulativeWeight) {
            if ($randomValue <= $cumulativeWeight) {
                return $array[$key];
            }
        }

        return end($array);
    }

    /**
     * @param PickingProcessProperties $pickingProcessProperties
     */
    private function pickItem(array &$pickingProcessProperties, Context $context): void
    {
        $pickingItem = new PickingItem(
            stockMovementId: Uuid::randomHex(),
            source: StockLocationReference::warehouse($pickingProcessProperties['warehouseId']),
            productId: $pickingProcessProperties['productId'],
            batchId: null,
            quantity: 2,
            pickingPropertyRecords: [],
        );
        $pickingProcessProperties['pickedQuantity'] += 2;
        if (
            in_array($pickingProcessProperties['pickingMode'], [
                PickingProcessDefinition::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING,
                PickingProcessDefinition::PICKING_MODE_PRE_COLLECTED_BATCH_PICKING,
            ], true)
        ) {
            $this->pickingProcessService->pickItemIntoPickingProcess(
                $pickingProcessProperties['pickingProcessId'],
                $pickingItem,
                $context,
            );
        } else {
            $this->pickingProcessService->pickItemIntoDelivery(
                $pickingProcessProperties['deliveryId'],
                $pickingItem,
                $context,
            );
        }
    }

    private function setClockToRandomDateTime(): void
    {
        $randomTimestamp = (new DateTime())->setTimestamp(random_int($this->startDate->getTimestamp(), $this->endDate->getTimestamp()));

        Clock::set(new MockClock(new DateTimeImmutable($randomTimestamp->format('Y-m-d H:i:s.u'))));
    }

    private function sleepRespectingEndDate(int $desiredSeconds): void
    {
        $currentTimestamp = Clock::get()->now()->getTimestamp();
        $maxAllowedTimestamp = $this->endDate->getTimestamp();

        $actualSleepSeconds = min($desiredSeconds, $maxAllowedTimestamp - $currentTimestamp);

        if ($actualSleepSeconds > 0) {
            Clock::get()->sleep($actualSleepSeconds);
        }
    }

    /**
     * Parses the sequences option into a distribution array.
     *
     * @return array<string, int>|null Distribution array or null if invalid
     */
    private function parseSequencesOption(string $sequencesOption): ?array
    {
        $values = array_map('trim', explode(',', $sequencesOption));

        if (count($values) !== 6) {
            return null;
        }

        $sequences = [];
        $keys = [
            'standard',
            'cancelled_before_completion',
            'cancelled_after_completion',
            'taken_over',
            'deferred_continued',
            'deferred_only',
        ];

        foreach ($values as $index => $value) {
            if (!is_numeric($value) || (int)$value < 0) {
                return null;
            }
            $sequences[$keys[$index]] = (int)$value;
        }

        return $sequences;
    }

    /**
     * Validates that a value is a strictly positive integer.
     *
     * @return int|null The validated integer or null if invalid
     */
    private function validatePositiveInteger(mixed $value): ?int
    {
        if (!is_int($value) && !ctype_digit($value)) {
            return null;
        }

        $intValue = (int)$value;
        if ($intValue <= 0) {
            return null;
        }

        return $intValue;
    }
}
