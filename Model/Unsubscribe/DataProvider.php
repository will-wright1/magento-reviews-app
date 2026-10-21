<?php
/**
 * Form data provider for the suppression-entry add/edit form.
 *
 * Edit mode: pulls the row from the registry (set by the Edit controller).
 * Add mode: provides an empty record so the form binds cleanly.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Unsubscribe;

use Magento\Framework\Registry;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\User\Model\UserFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Unsubscribe\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Registry $registry,
        private readonly UserFactory $userFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Provide the form's data array. Magento UI form components key data by
     * the row's primary field, so we always return an array shaped:
     *   [<id|new> => [...row fields...]]
     */
    public function getData()
    {
        $current = $this->registry->registry('reviewautomation_review_unsubscribe_current');
        if ($current === null) {
            return [];
        }

        $id = (int) $current->getId();
        $row = $current->getData();

        // Resolve admin user ID → username for read-only display.
        $adminUserId = (int) ($row['created_by_admin_user_id'] ?? 0);
        if ($adminUserId > 0) {
            $user = $this->userFactory->create()->load($adminUserId);
            $row['created_by_admin_user_label'] = $user->getId()
                ? sprintf('%s (#%d)', (string) $user->getUsername(), $adminUserId)
                : sprintf('(deleted, was #%d)', $adminUserId);
        } else {
            $row['created_by_admin_user_label'] = '—';
        }

        return [$id ?: 'new' => $row];
    }
}
