<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\Framework\Context;

class CorrelationIdProvider
{
    public function current(?Context $context = null): string
    {
        if ($context !== null && method_exists($context, 'getRuleIds')) {
            $source = $context->getSource();
            if ($source !== null && method_exists($source, 'getUserId')) {
                $userId = (string) ($source->getUserId() ?? '');
                if ($userId !== '') {
                    return 'ctx-' . substr(hash('sha256', $userId . ':' . microtime(true)), 0, 24);
                }
            }
        }

        return 'cid-' . bin2hex(random_bytes(12));
    }
}
