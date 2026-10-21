<?php

declare(strict_types=1);

namespace ReviewAutomation\Reviews\Controller\Adminhtml\Queue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\Email\SendResult;
use ReviewAutomation\Reviews\Model\QueueFactory;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;

class SendNow extends Action
{
    public const ADMIN_RESOURCE = 'ReviewAutomation_Reviews::queue';

    public function __construct(
        Context $context,
        private readonly QueueFactory $queueFactory,
        private readonly QueueResource $queueResource,
        private readonly SendDispatcher $sendDispatcher,
        private readonly \Magento\Framework\Data\Form\FormKey $formKey
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $queueId = (int) $this->getRequest()->getParam('queue_id');
        if ($queueId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid queue ID.'));
            return $redirect->setPath('reviewautomation_reviewautomation/queue/index');
        }

        $queue = $this->queueFactory->create();
        $this->queueResource->load($queue, $queueId);
        if (!$queue->getId()) {
            $this->messageManager->addErrorMessage(__('Queue entry %1 not found.', $queueId));
            return $redirect->setPath('reviewautomation_reviewautomation/queue/index');
        }

        if (!$this->getRequest()->isPost()) {
            $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $action = $esc($this->getUrl('*/*/sendNow', ['queue_id' => $queueId]));
            $key = $esc($this->formKey->getFormKey());
            $email = $esc((string) $queue->getCustomerEmail());
            $back = $esc($this->getUrl('reviewautomation_reviewautomation/queue/index'));
            return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setContents(
                '<h1>Confirm review email</h1><p>Send the next message to ' . $email . '?</p>'
                . '<form method="post" action="' . $action . '"><input type="hidden" name="form_key" value="' . $key . '">'
                . '<button type="submit">Send email</button></form><p><a href="' . $back . '">Back to queue</a></p>'
            );
        }
        $result = $this->sendDispatcher->dispatch($queue, true);

        if ($result->isSuccess()) {
            $emailLabel = match ($result->getMessageType()) {
                SendResult::TYPE_PRODUCT_REVIEW => 'Product review request',
                SendResult::TYPE_EMAIL2 => 'Email 2',
                SendResult::TYPE_EMAIL1_REMINDER => 'Email 1 reminder',
                default => 'Email 1',
            };
            $this->messageManager->addSuccessMessage(__(
                '%1 sent to %2. Queue row %3 advanced.',
                $emailLabel,
                (string) $queue->getCustomerEmail(),
                $queueId
            ));
        } else {
            $this->messageManager->addErrorMessage(__(
                'Send failed for queue row %1: %2 (Row status not advanced.)',
                $queueId,
                (string) ($result->getErrorMessage() ?? 'unknown error')
            ));
        }

        return $redirect->setPath('reviewautomation_reviewautomation/queue/index');
    }
}
