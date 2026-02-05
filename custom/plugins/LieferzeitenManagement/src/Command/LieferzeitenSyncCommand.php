<?php declare(strict_types=1);

namespace LieferzeitenManagement\Command;

use LieferzeitenManagement\Service\San6SyncService;
use LieferzeitenManagement\Service\OrderPositionSyncService;
use LieferzeitenManagement\Service\TrackingSyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lieferzeiten:sync',
    description: 'Sync San6 packages and tracking events.'
)]
class LieferzeitenSyncCommand extends Command
{
    /**
     * @param EntityRepository<\LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition> $trackingNumberRepository
     */
    public function __construct(
        private readonly San6SyncService $san6SyncService,
        private readonly OrderPositionSyncService $orderPositionSyncService,
        private readonly TrackingSyncService $trackingSyncService,
        private readonly EntityRepository $trackingNumberRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        $this->san6SyncService->sync($context);
        $this->orderPositionSyncService->sync($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addAssociation('package.order.orderCustomer');

        $trackingNumbers = $this->trackingNumberRepository->search($criteria, $context);

        foreach ($trackingNumbers as $trackingNumber) {
            if (!$trackingNumber->getTrackingNumber()) {
                continue;
            }

            if ($this->isTestOrder($trackingNumber->getPackage()?->getOrder())) {
                continue;
            }

            $this->trackingSyncService->syncTrackingNumber(
                $trackingNumber->getId(),
                $trackingNumber->getTrackingNumber(),
                $context
            );
        }

        $output->writeln(sprintf('Synced %d tracking numbers.', $trackingNumbers->count()));

        return Command::SUCCESS;
    }

    private function isTestOrder(?\Shopware\Core\Checkout\Order\OrderEntity $order): bool
    {
        if (!$order) {
            return false;
        }

        $orderNumber = strtoupper((string) $order->getOrderNumber());
        $customerEmail = strtolower((string) $order->getOrderCustomer()?->getEmail());

        return str_contains($orderNumber, 'TEST') || str_contains($customerEmail, 'test');
    }
}
