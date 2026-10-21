<?php
/**
 * Loads a Magento email template record by its configured identifier,
 * grabs the raw HTML and subject, strips the Magento header comments,
 * and runs placeholder substitution.
 *
 * Shared by the ReviewAutomation email senders so the
 * load-default-or-custom / strip / replace logic lives in one place.
 *
 * Deliberately does NOT run the Magento template filter — the whole
 * point of our __PLACEHOLDER__ convention is that str_replace is all
 * we need, and we avoid the HTML-escaping behaviour of {{var}}.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\Area;
use Psr\Log\LoggerInterface;

class TemplateLoader
{
    public function __construct(
        private readonly TemplateFactory $templateFactory,
        private readonly LoggerInterface $logger,
        private readonly \ReviewAutomation\Reviews\Model\Config $config
    ) {
    }

    /**
     * Load a template, substitute placeholders, return [subject, htmlBody].
     *
     * @param  string                $templateId   String ID for defaults, numeric ID for custom templates
     * @param  array<string,string>  $replacements ['__KEY__' => 'value', ...]
     * @param  int                   $storeId      Store scope for the design config, so the file
     *                                             lookup resolves under area=frontend regardless
     *                                             of the runtime area. Without setDesignConfig
     *                                             the lookup fails with "Template file '<file>'
     *                                             is not found." in some cron / webapi contexts
     *                                             where design->getArea() returns '' instead of
     *                                             null and the email_templates.xml `area`
     *                                             fallback (via the `??` operator) doesn't
     *                                             trigger. Backported from the equivalent fix
     *                                             in Review Automation/PlywoodUpgradeEmail.
     * @return array{subject: string, html: string}
     */
    public function render(string $templateId, array $replacements, int $storeId): array
    {
        $template = $this->templateFactory->create();

        // Force the area+store the templates were registered under. See PHPDoc
        // above and Magento's OrderSender::getTemplateOptions for the canonical
        // pattern.
        $template->setDesignConfig([
            'area'  => Area::AREA_FRONTEND,
            'store' => $storeId,
        ]);

        if (is_numeric($templateId)) {
            $template->load((int) $templateId);
        } else {
            $template->loadDefault($templateId);
        }

        $html = (string) $template->getTemplateText();
        $subject = (string) $template->getTemplateSubject();

        if ($html === '') {
            throw new \RuntimeException(sprintf(
                'ReviewAutomation: Template "%s" loaded but has empty body — check Marketing → Email Templates.',
                $templateId
            ));
        }

        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = $this->config->getLogoUrl($storeId);
        $name = $escape($this->config->getBrandName($storeId));
        $replacements += [
            '__BRAND_NAME__' => $name,
            '__ACCENT_COLOR__' => $this->config->getAccentColor($storeId),
            '__BRAND_HEADER_HTML__' => $logo !== '' ? '<img src="' . $escape($logo) . '" alt="' . $name . '" style="max-width:220px;max-height:80px;">' : $name,
        ];

        // Substitute custom placeholders in both body and subject.
        $keys = array_keys($replacements);
        $values = array_values($replacements);
        $html = str_replace($keys, $values, $html);
        $subject = str_replace($keys, array_map(static fn(string $value): string => html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $values), $subject);
        $subject = str_replace(["\r", "\n"], '', $subject);

        // Strip the Magento template header comments that are for the admin
        // editor's benefit — they shouldn't appear in the sent email.
        $html = (string) preg_replace('/<!--@subject.*?@-->/s', '', $html);
        $html = (string) preg_replace('/<!--@vars.*?@-->/s', '', $html);
        $html = (string) preg_replace('/<!--@styles.*?@-->/s', '', $html);

        if (preg_match('/__[A-Z0-9_]+__/', $html . $subject)) {
            throw new \RuntimeException('Email template contains unresolved placeholders.');
        }

        return [
            'subject' => trim($subject),
            'html'    => trim($html),
        ];
    }
}
