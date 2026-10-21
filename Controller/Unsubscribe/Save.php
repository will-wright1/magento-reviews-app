<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Controller\Unsubscribe;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;
use ReviewAutomation\Reviews\Model\SuppressionService;
use ReviewAutomation\Reviews\Model\TokenLookup;
class Save implements HttpPostActionInterface
{
    public function __construct(private readonly RequestInterface $request, private readonly TokenLookup $tokens,
        private readonly RedirectFactory $redirects, private readonly Validator $formKey,
        private readonly SuppressionService $suppression, private readonly ManagerInterface $messages) {}
    public function execute(): ResultInterface
    {
        $redirect = $this->redirects->create();
        $token = (string) $this->request->getParam('token');
        $queue = $this->tokens->loadByToken($token, true);
        if (!$queue || !$this->formKey->validate($this->request)) {
            return $redirect->setPath('reviewautomation_reviews/response/expired');
        }
        try {
            $this->suppression->suppress((string) $queue->getCustomerEmail(), SuppressionService::SOURCE_SELF);
            $this->messages->addSuccessMessage(__('You have been unsubscribed from review emails.'));
        } catch (\Throwable $e) {
            $this->messages->addErrorMessage(__('Could not save your preferences. Please try again.'));
        }
        return $redirect->setPath('reviewautomation_reviews/unsubscribe/index', ['token' => $token]);
    }
}
