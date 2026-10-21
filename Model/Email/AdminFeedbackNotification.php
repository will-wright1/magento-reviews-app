<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use ReviewAutomation\Reviews\Model\Config;
use ReviewAutomation\Reviews\Model\Feedback;

class AdminFeedbackNotification
{
    public function __construct(
        private readonly MessageBuilder $messageBuilder,
        private readonly TemplateLoader $templateLoader,
        private readonly Config $config,
        private readonly BackendUrlInterface $backendUrl
    ) {
    }

    public function send(Feedback $feedback, ?int $storeId = null): bool
    {
        $adminEmail = $this->config->getAdminNotificationEmail($storeId);
        if ($adminEmail === '') {
            return false;
        }

        $firstname = (string) ($feedback->getCustomerFirstname() ?: 'Customer');
        $customerEmail = (string) $feedback->getCustomerEmail();
        $incrementId = (string) $feedback->getOrderIncrementId();
        $rating = $feedback->getRating();
        $feedbackText = (string) $feedback->getFeedbackText();

        $ratingHtml = ($rating !== null && $rating !== '' && (int) $rating >= 1)
            ? str_repeat('&#9733;', (int) $rating) . str_repeat('&#9734;', max(0, 5 - (int) $rating))
            : '<em>Not provided</em>';

        $feedbackHtml = nl2br($this->esc($feedbackText));

        $adminUrl = $this->backendUrl->getUrl(
            'reviewautomation_reviewautomation/feedback/edit',
            ['id' => (int) $feedback->getId()]
        );

        $templateId = $this->config->getAdminNotificationEmailTemplate($storeId);
        $result = $this->templateLoader->render($templateId, [
            '__FIRSTNAME__'          => $this->esc($firstname),
            '__CUSTOMER_EMAIL__'     => $this->esc($customerEmail),
            '__ORDER_INCREMENT_ID__' => $this->esc($incrementId),
            '__RATING_HTML__'        => $ratingHtml,
            '__FEEDBACK_HTML__'      => $feedbackHtml,
            '__ADMIN_URL__'          => $this->esc($adminUrl),
        ], (int) ($storeId ?? 0));

        return $this->messageBuilder->send(
            $result['subject'],
            $result['html'],
            $this->config->getSenderEmail($storeId),
            $this->config->getSenderName($storeId),
            $adminEmail,
            'Review Automation Team',
            (int) ($storeId ?? 0)
        );
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
