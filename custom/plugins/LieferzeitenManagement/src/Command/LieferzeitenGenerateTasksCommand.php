<?php declare(strict_types=1);

namespace LieferzeitenManagement\Command;

use LieferzeitenManagement\Service\Task\OverdueShippingTaskGenerator;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'lieferzeiten:tasks:overdue-shipping',
    description: 'Create overdue shipping tasks for packages without shipping date.'
)]
class LieferzeitenGenerateTasksCommand extends Command
{
    public function __construct(private readonly OverdueShippingTaskGenerator $generator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $created = $this->generator->generate($context);

        $output->writeln(sprintf('Created %d overdue shipping tasks.', $created));

        return Command::SUCCESS;
    }
}
