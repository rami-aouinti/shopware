<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Task;

use LieferzeitenManagement\Core\Content\Task\LieferzeitenTaskEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class TaskCompletionNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function notify(LieferzeitenTaskEntity $task, Context $context): void
    {
        $requester = $task->getCreatedBy();
        if (!$requester || !$requester->getEmail()) {
            return;
        }

        $fromEmail = (string) $this->systemConfigService->get('core.basicInformation.email', $context);
        if (!$fromEmail) {
            $fromEmail = 'no-reply@localhost';
        }

        $fromName = (string) $this->systemConfigService->get('core.basicInformation.shopName', $context);
        if (!$fromName) {
            $fromName = 'Delivery times';
        }

        $orderNumber = $task->getOrder()?->getOrderNumber();
        $taskType = $task->getType();

        $subject = 'Delivery task completed';
        $message = sprintf(
            "The task '%s'%s has been completed.",
            $taskType ?? 'delivery task',
            $orderNumber ? sprintf(' for order %s', $orderNumber) : ''
        );

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address($requester->getEmail(), trim(sprintf('%s %s', $requester->getFirstName(), $requester->getLastName()))))
            ->subject($subject)
            ->text($message);

        $this->mailer->send($email);
    }
}
