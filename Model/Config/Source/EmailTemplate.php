<?php
/**
 * Source model for review automation email template selectors.
 *
 * Replaces Magento\Config\Model\Config\Source\Email\Template because that
 * class derives the template ID by calling str_replace('/', '_', $configPath),
 * which only works when the config path structure matches the template ID
 * (e.g. sales_email/order/template → sales_email_order_template).
 *
 * Our config paths (reviewautomation_review_automation/general/satisfaction_email_template)
 * don't follow that convention, so we list the registered templates directly.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Config\Source;

use Magento\Email\Model\ResourceModel\Template\CollectionFactory as TemplateCollectionFactory;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Framework\Data\OptionSourceInterface;

class EmailTemplate implements OptionSourceInterface
{
    /**
     * Our registered template IDs from email_templates.xml.
     */
    private const REVIEWAUTOMATION_TEMPLATES = [
        'reviewautomation_review_satisfaction_check',
        'reviewautomation_review_satisfaction_reminder',
        'reviewautomation_review_request',
        'reviewautomation_review_request_nudge',
        'reviewautomation_product_review_request',
        'reviewautomation_review_admin_feedback_notification',
    ];

    public function __construct(
        private readonly TemplateCollectionFactory $templatesFactory,
        private readonly EmailConfig $emailConfig
    ) {
    }

    /**
     * @return array<int, array{value: string|int, label: string, group?: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        $options[] = [
            'value' => '',
            'label' => __('-- Use Default --'),
        ];

        // Add our registered templates from email_templates.xml
        foreach (self::REVIEWAUTOMATION_TEMPLATES as $templateId) {
            try {
                $label = $this->emailConfig->getTemplateLabel($templateId);
                $options[] = [
                    'value' => $templateId,
                    'label' => __('%1 (Module Default)', $label),
                    'group' => __('Review Automation'),
                ];
            } catch (\UnexpectedValueException $e) {
                // Template not registered — skip silently
            }
        }

        // Add any custom templates the admin has created via Marketing → Email Templates
        $collection = $this->templatesFactory->create();
        $collection->addFieldToFilter('orig_template_code', ['notnull' => true]);
        $collection->addOrder('template_code', 'ASC');

        foreach ($collection as $template) {
            $options[] = [
                'value' => (string) $template->getId(),
                'label' => $template->getTemplateCode(),
                'group' => __('Custom Templates'),
            ];
        }

        return $options;
    }
}
