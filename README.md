# Magento 2 Review Automation

A standalone Magento 2 extension for post-purchase feedback, Google and Trustpilot review invitations, product review requests and historical-order campaigns.

- Composer package: `review-automation/magento2-reviews`
- Magento module: `ReviewAutomation_Reviews`
- PHP: 8.1 or later in the PHP 8 series; use the PHP version supported by your Magento installation.
- Platform: Magento 2.4 / Adobe Commerce dependency ranges are declared in `composer.json`. Full runtime compatibility must be checked on your exact Magento release before deployment.
- Licence: MIT.

Extracted from the custom Vanimal and 4x4AT implementations into one configurable package. No client credentials, order records or production configuration are included.

## Features

| Feature | Behaviour |
| --- | --- |
| Satisfaction email | Sent after fulfilment; optional single reminder for non-responders |
| Company reviews | Google or Trustpilot, with optional stable split or Gmail targeting |
| Fair invitations | Happy, unhappy and non-responding customers receive the same scheduled company invitation |
| Product reviews | Links to Magento product review forms for purchased, reviewable products |
| Feedback inbox | Private feedback, optional rating, internal notes and resolved status |
| Suppression | Customer unsubscribe, order-view button, order-grid bulk action and suppression grid |
| Historical campaigns | Preview a date window, create a daily queue allowance, pause or cancel batches |
| Branding | Store-specific business name, optional HTTPS logo, accent colour and editable email templates |
| Operations | Store allowlist, daytime sending window, monthly campaign limit and admin test email |

Sending is **disabled by default**. An empty allowed-store list also blocks sending. The test-email button sends only to the logged-in administrator and does not change the customer queue.

## Installation

After publishing a tagged release on Packagist, run from the Magento root:

```sh
composer require review-automation/magento2-reviews:^0.1
bin/magento module:enable ReviewAutomation_Reviews
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Deploy static content using your normal production deployment process. Magento cron must be installed and running. Use your existing Magento email transport; no particular SMTP extension is required.

For a local staging installation before publication:

```sh
composer config repositories.review-automation path '/absolute/path/to/magento reviews app'
composer require 'review-automation/magento2-reviews:@dev'
```

Then enable the module and run the Magento commands above. Do not install a second copy under `app/code` when Composer already provides the module.

## Configuration

Open **Stores → Configuration → Review Automation → Review Automation**.

1. At Default Config scope, choose the allowed storefronts under Order Filters. Keep marketplace-only stores excluded.
2. Set sender and notification addresses, company review URLs and branding. Empty sender values inherit Magento's general contact identity; business name falls back to the store or sender name.
3. Send a test email to the logged-in administrator. Its random token is intentionally not a live customer token.
4. Check the queue and campaign settings, then enable sending at Default Config scope and each relevant store scope. Default scope is the global cron switch; stores can opt out individually.

The store allowlist is controlled at Default Config scope. Timing, branding, addresses, platform selection, enablement and suppression settings can vary by store. Unsubscribes and the calendar-month campaign allowance apply to a customer email across store views. The monthly limit counts **order campaigns**, not every email in a campaign. A historical batch's daily allowance controls queue entries, not total outbound messages.

| Default | Value |
| --- | --- |
| Satisfaction delay | 7 days after the latest shipment, or completion update for shipment-less complete orders |
| Satisfaction reminder | 3 days after the initial email if no satisfaction response |
| Company invitation | 96 hours after the initial email, independent of satisfaction |
| Company reminder | 5 days after the company invitation if no recorded platform visit |
| Product request | 14 days after the company invitation, if Magento product reviews are enabled |
| Token expiry | 60 days from queue creation; opt-out links remain usable after expiry |
| Organic order window | 60 days from fulfilment |
| Sending window | 08:00–19:00 in each store's configured timezone |
| Campaign limit | One order campaign per customer email per UTC calendar month |

The module processes complete orders. Historical campaigns bypass the organic age and initial delay checks, but still honour store enablement, unsubscribe, sender validity, refunds, order completeness, sending windows, token expiry and monthly limits. Cron sends at most 50 messages per run. The first message waits at least five minutes after queue insertion.

## Administration and templates

Open **Marketing → Review Automation** for Review Queue, Customer Feedback, Suppression List and Review Booster. Admin ACL resources allow access to be restricted by role. Manual sends require a confirmation form and honour the same guard checks as cron; they bypass scheduled delays only.

Emails can be copied through **Marketing → Email Templates** and selected in configuration. They use `__PLACEHOLDER__` substitutions; preserve required placeholders when editing. Common placeholders are `__BRAND_NAME__`, `__BRAND_HEADER_HTML__`, `__ACCENT_COLOR__` and `__FIRSTNAME__`. Each default file shows its message-specific placeholders. Custom `{{var ...}}` directives inside these templates are not supported; only the internal passthrough template uses Magento directives. Missing or unresolved templates fail rather than sending broken content.

The order, platform-button and product-button placeholders (`__ORDER_ITEMS_HTML__`, `__PLATFORM_BUTTONS_HTML__`, `__PRODUCT_REVIEW_BUTTONS_HTML__`) contain table rows. Put each inside its own presentation table and `<tbody>`, as the default templates do; inserting rows directly into a `<td>` can move content outside the email card. Updated package defaults do not overwrite copies saved in Marketing → Email Templates. Select the default again or update the saved copy to pick up layout fixes.

Default customer emails include a responsive layout, a light logo area and dark-mode styles for clients that support them. Check a test email in your customers’ mail clients before enabling a campaign.

Email GET links do not record satisfaction or unsubscribe customers. Those actions require a form POST and form key. Public-platform redirects record a visit, which may include automated scanner visits; this is **not proof of a published review**. There is no Google or Trustpilot review-import API integration.

## Existing installations

Do not replace the client-specific modules by renaming their folders. Preserve opt-outs and already-sent links using the explicit migration procedure in [Migration](docs/MIGRATION.md). Migration is never run automatically by `setup:upgrade`.

## Development and validation

```sh
composer validate --strict
python3 tools/check.py
php tests/run.php
```

These checks run without Magento dependencies and cover package wiring, isolated logic and 20 fully rendered email variants using the real row renderers. Email checks verify table structure, link containment, escaping and logo sizing. GitHub Actions runs them on PHP 8.1–8.5. They do not substitute for Magento schema installation, dependency compilation, browser checks or a real email transport test.

Use [Testing](docs/TESTING.md) for the staging acceptance procedure and `tools/magento-smoke.php` for read-only runtime construction checks. This workspace has no installed Magento runtime or database; end-to-end Magento acceptance has not been executed here.

See [Publishing](docs/PUBLISHING.md) for GitHub Desktop and Packagist release steps, and [Changelog](CHANGELOG.md) for the initial extraction.

## Operational limits

Email delivery and database updates cannot be one atomic transaction. The shared send lock and persisted timestamps prevent normal concurrent or repeated sends, but a process crash after SMTP accepts a message and before its timestamp is saved can still cause a retry. Review mail transport logs before retrying an uncertain delivery. A send already accepted by the transport cannot be recalled by a simultaneous unsubscribe.

Customer names, emails, order references and feedback are stored in the module tables. Apply your store's retention, access and deletion procedures to them. No automatic retention or customer-erasure integration is provided in this release.
