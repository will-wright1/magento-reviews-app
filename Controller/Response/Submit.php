<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Controller\Response;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Lock\LockManagerInterface;
use ReviewAutomation\Reviews\Model\Email\SendDispatcher;
use ReviewAutomation\Reviews\Model\Queue;
use ReviewAutomation\Reviews\Model\ResourceModel\Queue as QueueResource;
use ReviewAutomation\Reviews\Model\TokenLookup;
class Submit implements HttpPostActionInterface
{
    public function __construct(private readonly RequestInterface $request, private readonly TokenLookup $tokens,
        private readonly RedirectFactory $redirects, private readonly Validator $formKey,
        private readonly QueueResource $resource, private readonly LockManagerInterface $locks) {}
    public function execute(): ResultInterface
    {
        $redirect = $this->redirects->create();
        $token = (string) $this->request->getParam('token');
        $sentiment = (string) $this->request->getParam('sentiment');
        if (!$this->formKey->validate($this->request) || !in_array($sentiment, ['happy', 'unhappy'], true)) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }
        if (!$this->locks->lock(SendDispatcher::LOCK_NAME, 0)) {
            return $redirect->setPath('reviewautomation_reviews/response/click', ['token' => $token, 'sentiment' => $sentiment]);
        }
        try {
            $queue = $this->tokens->loadByToken($token);
            if (!$queue || in_array($queue->getStatus(), [Queue::STATUS_UNSUBSCRIBED, Queue::STATUS_SUPPRESSED], true)) {
                return $redirect->setPath('reviewautomation_reviews/response/expired');
            }
            if (!$queue->getSentiment()) {
                $queue->setSentiment($sentiment);
                $queue->setClickedAt(gmdate('Y-m-d H:i:s'));
                if (!$queue->getData('email2_sent_at') && $queue->getStatus() !== Queue::STATUS_FEEDBACK_RECEIVED) {
                    $queue->setStatus($sentiment === 'happy' ? Queue::STATUS_CLICKED_HAPPY : Queue::STATUS_CLICKED_UNHAPPY);
                }
                $this->resource->save($queue);
            }
        } finally { $this->locks->unlock(SendDispatcher::LOCK_NAME); }
        return $redirect->setPath($sentiment === 'happy' ? 'reviewautomation_reviews/response/thankyou' : 'reviewautomation_reviews/response/feedback', ['token' => $token]);
    }
}
