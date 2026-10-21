<?php
/** Sends rendered HTML through Magento TransportBuilder and the configured mail transport. */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Psr\Log\LoggerInterface;

class MessageBuilder
{
    /**
     * Internal passthrough template registered in etc/email_templates.xml.
     */
    private const PASSTHROUGH_TEMPLATE_ID = 'reviewautomation_review_automation_passthrough';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly LoggerInterface $logger,
        private readonly \ReviewAutomation\Reviews\Model\Config $config
    ) {
    }

    /**
     * Build and send a raw-HTML email.
     *
     * @param string $subject Fully-prepared subject line (already personalised if needed)
     * @param string $htmlBody Full HTML body, inline CSS, no template directives
     * @param string $fromEmail
     * @param string $fromName
     * @param string $toEmail
     * @param string $toName
     * @param int $storeId Store scope for template resolution and transport plugins.
     * @return bool true on success, false on MailException (logged)
     */
    public function send(
        string $subject,
        string $htmlBody,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        int $storeId
    ): bool {
        $this->inlineTranslation->suspend();
        try {
            $builder = $this->transportBuilder
                ->setTemplateIdentifier(self::PASSTHROUGH_TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars([
                    'subject_override' => $subject,
                    'body'             => $htmlBody,
                ])
                ->setFromByScope([
                    'email' => $fromEmail,
                    'name'  => $fromName,
                ], $storeId)
                ->addTo($toEmail, $toName ?: $toEmail);

            // Route replies to a monitored inbox — customers do reply to
            // review emails, and an unanswered reply tends to become a
            // public 1-star review instead.
            $replyTo = $this->config->getReplyToEmail($storeId);
            if ($replyTo !== '' && strcasecmp($replyTo, $fromEmail) !== 0) {
                $builder->setReplyTo($replyTo, $fromName);
            }

            $transport = $builder->getTransport();

            $transport->sendMessage();

            return true;
        } catch (MailException $e) {
            $this->logger->error(sprintf(
                'ReviewAutomation: MailException sending to %s — %s',
                $toEmail,
                $e->getMessage()
            ));
            return false;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'ReviewAutomation: Unexpected error sending to %s — %s',
                $toEmail,
                $e->getMessage()
            ));
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
