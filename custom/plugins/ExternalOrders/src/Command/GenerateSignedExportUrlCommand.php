<?php declare(strict_types=1);

namespace ExternalOrders\Command;

use Doctrine\DBAL\Connection;
use ExternalOrders\Service\TopmSan6OrderExportService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'external-orders:export:generate-signed-url',
    description: 'Generate a signed ExternalOrders export URL for a given export ID.'
)]
class GenerateSignedExportUrlCommand extends Command
{
    public function __construct(
        private readonly TopmSan6OrderExportService $exportService,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('exportId', InputArgument::REQUIRED, 'UUID of external_order_export.id (hex format).')
            ->addOption('expires-in', null, InputOption::VALUE_REQUIRED, 'TTL in seconds (default: 600).', '600')
            ->addOption('validate-exists', null, InputOption::VALUE_NONE, 'Fail if export ID does not exist in external_order_export.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $exportId = strtolower((string) $input->getArgument('exportId'));
        if (!Uuid::isValid($exportId)) {
            $io->error('Invalid exportId. Please provide a valid UUID.');

            return self::FAILURE;
        }

        $expiresIn = (int) $input->getOption('expires-in');
        if ($expiresIn <= 0) {
            $io->error('Option --expires-in must be a positive integer.');

            return self::FAILURE;
        }

        if ((bool) $input->getOption('validate-exists')) {
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM external_order_export WHERE id = :id LIMIT 1',
                ['id' => Uuid::fromHexToBytes($exportId)]
            );

            if ($exists === false || $exists === null) {
                $io->error(sprintf('No export found for exportId %s.', $exportId));

                return self::FAILURE;
            }
        }

        $expiresAt = time() + $expiresIn;
        $url = $this->exportService->generateSignedFileTransferUrl($exportId, $expiresAt);

        $io->success('Signed export URL generated.');
        $io->writeln(sprintf('exportId: %s', $exportId));
        $io->writeln(sprintf('expiresAt: %s (%s)', $expiresAt, date(DATE_ATOM, $expiresAt)));
        $io->writeln(sprintf('url: %s', $url));

        return self::SUCCESS;
    }
}
