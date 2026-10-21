<?php
/**
 * Shared helper for loading a Queue row by its opaque URL token and
 * enforcing the configured expiry window.
 *
 * Every frontend controller in this module starts with a token lookup, so
 * centralising the "load + expiry check" flow means each controller only
 * has to handle the happy / not-found / expired cases.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use ReviewAutomation\Reviews\Model\ResourceModel\Queue\CollectionFactory as QueueCollectionFactory;

class TokenLookup
{
    public function __construct(
        private readonly QueueCollectionFactory $queueCollectionFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Load a Queue row by its token. Returns null if the token doesn't
     * exist or if the row has aged past the configured expiry.
     */
    public function loadByToken(?string $token, bool $allowExpired = false): ?Queue
    {
        if ($token === null || strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('token', $token)->setPageSize(1);

        /** @var Queue|false $queue */
        $queue = $collection->getFirstItem();
        if (!$queue->getId()) {
            return null;
        }

        if (!$allowExpired && $this->isExpired($queue)) {
            return null;
        }

        return $queue;
    }

    private function isExpired(Queue $queue): bool
    {
        $expiryDays = $this->config->getTokenExpiryDays((int) $queue->getStoreId());
        $raw = (string) $queue->getCreatedAt();
        if ($raw === '') { return true; }
        $createdAt = strtotime($raw . ' UTC');
        if ($createdAt === false) {
            return true;
        }
        return (time() - $createdAt) > ($expiryDays * 86400);
    }
}
