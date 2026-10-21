<?php
/**
 * Renders the created_by_admin_user_id column as the admin's username,
 * falling back to "(deleted)" if the admin user has been removed since
 * the suppression row was created.
 *
 * Soft reference. We don't FK to admin_user because
 * we don't want cascade weirdness.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\User\Model\UserFactory;

class AdminUserName extends Column
{
    /** @var array<int, string|null> in-request cache to avoid N+1 lookups */
    private array $usernameCache = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UserFactory $userFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $field = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$row) {
            $userId = (int) ($row['created_by_admin_user_id'] ?? 0);
            $row[$field] = $userId > 0 ? $this->resolveUsername($userId) : '—';
        }
        return $dataSource;
    }

    private function resolveUsername(int $userId): string
    {
        if (array_key_exists($userId, $this->usernameCache)) {
            return $this->usernameCache[$userId] ?? '(deleted)';
        }
        $user = $this->userFactory->create()->load($userId);
        $username = $user->getId() ? (string) $user->getUsername() : null;
        $this->usernameCache[$userId] = $username;
        return $username ?? '(deleted)';
    }
}
