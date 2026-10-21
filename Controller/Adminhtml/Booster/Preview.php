<?php
/**
 * AJAX: SQL-level candidate count for a prospective booster batch.
 * Returns {count: int} — an upper bound before per-order config checks.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Booster;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use ReviewAutomation\Reviews\Model\BoosterEligibility;

class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::booster';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly BoosterEligibility $eligibility
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $dateFrom = (string) $this->getRequest()->getParam('date_from');
        $dateTo = (string) $this->getRequest()->getParam('date_to');
        $minValue = (float) $this->getRequest()->getParam('min_order_value', 0);

        if (!$this->isValidDate($dateFrom) || !$this->isValidDate($dateTo) || $dateFrom > $dateTo) {
            return $result->setData(['error' => (string) __('Please pick a valid date range.')]);
        }

        try {
            $count = $this->eligibility->countCandidates($dateFrom, $dateTo, $minValue);
        } catch (\Throwable $e) {
            return $result->setData(['error' => (string) __('Preview failed: %1', $e->getMessage())]);
        }

        return $result->setData(['count' => $count]);
    }

    private function isValidDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
