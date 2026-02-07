<?php declare(strict_types=1);

namespace LieferzeitenManagement\Subscriber;

use LieferzeitenManagement\Core\Content\Task\LieferzeitenTaskDefinition;
use LieferzeitenManagement\Service\Task\TaskCompletionNotifier;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TaskCompletionSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LieferzeitenTaskDefinition> $taskRepository
     */
    public function __construct(
        private readonly EntityRepository $taskRepository,
        private readonly TaskCompletionNotifier $notifier
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lieferzeiten_task.written' => 'onTaskWritten',
        ];
    }

    public function onTaskWritten(EntityWrittenEvent $event): void
    {
        $completedIds = [];

        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                continue;
            }

            $payload = $result->getPayload();
            if (($payload['status'] ?? null) !== 'completed') {
                continue;
            }

            $primaryKey = $result->getPrimaryKey();
            $id = is_array($primaryKey) ? ($primaryKey['id'] ?? null) : $primaryKey;

            if ($id) {
                $completedIds[] = $id;
            }
        }

        if (!$completedIds) {
            return;
        }

        $criteria = new Criteria($completedIds);
        $criteria->addAssociation('order');
        $criteria->addAssociation('createdBy');

        $tasks = $this->taskRepository->search($criteria, $event->getContext());

        foreach ($tasks as $task) {
            $this->notifier->notify($task, $event->getContext());
        }
    }
}
