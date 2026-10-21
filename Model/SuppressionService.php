<?php
/**
 * Centralised "suppress this email" / "resume this email" service.
 *
 * Every admin entry point (order grid mass action, order view button,
 * standalone admin grid) calls into this class so the side effects stay
 * consistent: insert the unsubscribe row AND park any non-final queue rows
 * for that email at STATUS_UNSUBSCRIBED. Same pattern as the customer-facing
 * Controller/Unsubscribe/Index.php.
 *
 * Email-level granularity throughout. The unique
 * constraint on customer_email guarantees idempotency at the DB layer; we
 * also explicitly check first to give callers a clean "already suppressed"
 * signal without relying on exception-driven control flow.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe as UnsubscribeResource;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe\CollectionFactory as UnsubscribeCollectionFactory;

class SuppressionService
{
    public const SOURCE_SELF             = 'customer_self_unsubscribe';
    public const SOURCE_ADMIN_ORDER_GRID = 'admin_order_grid';
    public const SOURCE_ADMIN_ORDER_VIEW = 'admin_order_view';
    public const SOURCE_ADMIN_MANUAL     = 'admin_manual_grid';
    public const SOURCE_LEGACY_IMPORT    = 'legacy_import';

    public const VALID_SOURCES = [
        self::SOURCE_SELF,
        self::SOURCE_ADMIN_ORDER_GRID,
        self::SOURCE_ADMIN_ORDER_VIEW,
        self::SOURCE_ADMIN_MANUAL,
        self::SOURCE_LEGACY_IMPORT,
    ];

    public function __construct(
        private readonly UnsubscribeFactory $unsubscribeFactory,
        private readonly UnsubscribeResource $unsubscribeResource,
        private readonly UnsubscribeCollectionFactory $unsubscribeCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Returns true if the email is already on the suppression list.
     */
    public function isSuppressed(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $collection = $this->unsubscribeCollectionFactory->create();
        $collection->addFieldToFilter('customer_email', $email)->setPageSize(1);
        return $collection->getSize() > 0;
    }

    /**
     * Load the suppression row for an email, or null if not suppressed.
     */
    public function findByEmail(string $email): ?Unsubscribe
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $collection = $this->unsubscribeCollectionFactory->create();
        $collection->addFieldToFilter('customer_email', $email)->setPageSize(1);
        $row = $collection->getFirstItem();
        return $row->getId() ? $row : null;
    }

    /**
     * Add an email to the suppression list and park any non-final queue rows
     * for that email at STATUS_UNSUBSCRIBED.
     *
     * @return bool true if inserted, false if already suppressed
     * @throws \InvalidArgumentException for invalid source
     */
    public function suppress(
        string $email,
        string $source,
        ?int $adminUserId = null,
        ?string $reason = null
    ): bool {
        $email = strtolower(trim($email));
        if ($email === '') {
            throw new \InvalidArgumentException('Email is required.');
        }
        if (!in_array($source, self::VALID_SOURCES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid suppression source: %s', $source));
        }

        if ($this->isSuppressed($email)) {
            return false;
        }

        $unsub = $this->unsubscribeFactory->create();
        $unsub->setData([
            'customer_email'           => $email,
            'created_at'               => $this->dateTime->gmtDate(),
            'source'                   => $source,
            'reason'                   => $reason ? trim($reason) : null,
            'created_by_admin_user_id' => $adminUserId,
        ]);

        try {
            $this->unsubscribeResource->save($unsub);
        } catch (\Throwable $e) {
            // Race condition: someone else inserted between our isSuppressed
            // check and our save. The unique constraint catches it.
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                return false;
            }
            throw $e;
        }

        $this->parkQueueRows($email);
        $this->logger->info(sprintf(
            '[ReviewAutomation/Suppression] Suppressed %s (source=%s, admin=%s)',
            $email,
            $source,
            $adminUserId !== null ? (string) $adminUserId : 'n/a'
        ));

        return true;
    }

    /**
     * Remove an email from the suppression list.
     *
     * Does NOT un-park existing STATUS_UNSUBSCRIBED queue rows — those
     * represent past decisions made under the previous suppression and
     * shouldn't be retroactively flipped. Resume only affects future orders
     * entering the queue (which won't be blocked by PopulateQueue's
     * NOT EXISTS unsubscribe filter once this row is gone).
     *
     * @return bool true if a row was deleted, false if no row existed
     */
    public function resume(string $email): bool
    {
        $row = $this->findByEmail($email);
        if ($row === null) {
            return false;
        }

        try {
            $this->unsubscribeResource->delete($row);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[ReviewAutomation/Suppression] Failed to delete suppression row %d for %s: %s',
                (int) $row->getId(),
                $email,
                $e->getMessage()
            ));
            throw $e;
        }

        $this->logger->info(sprintf(
            '[ReviewAutomation/Suppression] Resumed %s (deleted row id=%d)',
            $email,
            (int) $row->getId()
        ));

        return true;
    }

    /**
     * Park any non-final queue rows for the given email at STATUS_UNSUBSCRIBED.
     * "Non-final" = not already in unsubscribed/suppressed.
     */
    private function parkQueueRows(string $email): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $queueTable = $this->resourceConnection->getTableName('reviewautomation_review_queue');

            $finalStates = [
                Queue::STATUS_UNSUBSCRIBED,
                Queue::STATUS_SUPPRESSED,
            ];

            $connection->update(
                $queueTable,
                [
                    'status'     => Queue::STATUS_UNSUBSCRIBED,
                    'updated_at' => $this->dateTime->gmtDate(),
                ],
                [
                    'customer_email = ?'    => $email,
                    'status NOT IN (?)'     => $finalStates,
                ]
            );
        } catch (\Throwable $e) {
            // Don't bubble — the suppression row is the source of truth, the
            // queue parking is a UX nicety. Log and move on.
            $this->logger->warning(sprintf(
                '[ReviewAutomation/Suppression] Could not park queue rows for %s: %s',
                $email,
                $e->getMessage()
            ));
        }
    }
}
