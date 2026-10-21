<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\ResourceModel\Queue\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;
use ReviewAutomation\Reviews\Model\Config;

class FilteredCollection extends SearchResult
{
    private Config $config;

    /**
     * Signature mirrors \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult
     * exactly (8 params), with our Config dependency inserted after the four
     * required type-hinted collaborators and before the optional scalars.
     *
     * @param string|null $resourceModel
     * @param string|null $identifierName
     * @param string|null $connectionName
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        EventManager $eventManager,
        Config $config,
        $mainTable = 'reviewautomation_review_queue',
        $resourceModel = \ReviewAutomation\Reviews\Model\ResourceModel\Queue::class,
        $identifierName = null,
        $connectionName = null
    ) {
        $this->config = $config;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritDoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $allowedStoreIds = $this->config->getAllowedStoreIds();
        $maxAgeDays      = $this->config->getMaxOrderAgeDays();

        if (!empty($allowedStoreIds)) {
            $this->getSelect()->where('main_table.store_id IN (?)', $allowedStoreIds);
        }

        return $this;
    }
}
