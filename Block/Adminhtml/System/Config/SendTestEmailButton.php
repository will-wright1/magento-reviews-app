<?php
/**
 * Renders the "Test Email Pipeline" button in Stores → Configuration →
 * Review Automation → Review Automation. Click triggers a JS POST to the test
 * controller; the JSON response is rendered into a result div next to
 * the button.
 *
 * No JS module file — the inline handler is small enough to read at a
 * glance and avoids a build step. If we add more than one of these in
 * future, extract.
 */
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SendTestEmailButton extends Field
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Hides the standard label/scope columns — we render full-width.
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $url     = $this->getUrl('reviewautomation_reviewautomation/test/sendTestEmail');
        $formKey = $this->getFormKey();

        $html = <<<HTML
<button type="button"
        id="reviewautomation-review-test-email-button"
        class="action-default scalable"
        style="background-color:#F58220; color:#ffffff; border:none; border-radius:3px; padding:8px 18px; font-weight:600; cursor:pointer;">
    Send Test Email Now
</button>
<div id="reviewautomation-review-test-email-result" style="margin-top:12px;"></div>
<script>
require(['jquery'], function (\$) {
    \$('#reviewautomation-review-test-email-button').on('click', function () {
        var btn = \$(this);
        var out = \$('#reviewautomation-review-test-email-result');
        btn.prop('disabled', true).text('Sending…');
        out.html('');
        \$.ajax({
            url: '{$url}',
            type: 'POST',
            data: { form_key: '{$formKey}' },
            dataType: 'json'
        }).done(function (data) {
            var box;
            if (data && data.success) {
                box = \$('<div></div>')
                    .css({
                        padding: '12px 14px',
                        border: '1px solid #b3d9b3',
                        background: '#e8f5e8',
                        color: '#1f5d1f',
                        borderRadius: '3px'
                    })
                    .text(data.message || 'Test email sent successfully.');
            } else {
                box = \$('<div></div>')
                    .css({
                        padding: '12px 14px',
                        border: '1px solid #d99',
                        background: '#fde9e9',
                        color: '#9e1f1f',
                        borderRadius: '3px'
                    })
                    .text((data && data.message) ? ('Test email FAILED: ' + data.message) : 'Test email FAILED (unknown error).');
            }
            out.append(box);
        }).fail(function (xhr) {
            out.html('<div style="padding:12px 14px;border:1px solid #d99;background:#fde9e9;color:#9e1f1f;border-radius:3px;">Test endpoint returned ' + xhr.status + '. Check var/log/exception.log.</div>');
        }).always(function () {
            btn.prop('disabled', false).text('Send Test Email Now');
        });
    });
});
</script>
HTML;

        return $html;
    }
}
