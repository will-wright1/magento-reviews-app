# Validation and staging acceptance

## Local checks

Run `composer validate --strict`, `python3 tools/check.py` and `php tests/run.php` from this repository. The PHP suite uses small framework doubles for isolated logic; it does not claim to emulate Magento. The CI matrix checks syntax and this suite on PHP 8.1–8.5.

## Magento runtime

Use a disposable Magento installation with a database, store view, product, complete order, cron and a mail-capture transport. Match its PHP version to the Magento release. Install this package using a Composer path repository or a tagged VCS release.

```sh
bin/magento module:enable ReviewAutomation_Reviews
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
php '/path/to/magento reviews app/tools/magento-smoke.php' /path/to/magento
```

The smoke script constructs services, controllers and blocks and checks table presence. It does not send emails or modify module records. Compilation and the script must pass before accepting platform compatibility.

## Acceptance scenarios

1. Fresh install stays disabled; an empty store allowlist blocks population and sending. Enable the default scope and a single allowed store. Verify excluded stores do not generate queue rows.
2. Use two stores with different names, logos, colours, reply-to addresses, company platforms, delays and timezones. Check each message and landing page uses the appropriate branding. A valid daytime window must be evaluated against the actual store; invalid windows must block sends.
3. Send the admin test email. Confirm only the logged-in admin receives it, no queue row is created and the subject is labelled [TEST]. Follow each link through the preview: positive, problem, feedback confirmation, public review and unsubscribe. No customer tokens or entered feedback should appear in preview URLs; no queue, feedback or suppression records should change. Old test emails can still show the unavailable-link page.
4. Queue one complete order with a shipment and one shipment-less complete order. Confirm fulfilment-based timing, the five-minute queue buffer and one queue row per order. Check partial-refund, incomplete, cancelled, low-value and disallowed orders are excluded as configured.
5. Exercise positive, negative and no-response paths. GET requests to satisfaction links must not change sentiment or queue status. The POST confirmation records the response. All three paths must receive the same company invitation at the configured time and retain access to public review links.
6. Submit private feedback with a valid form key and timestamp. Verify one stored feedback row, an admin notification, optional rating, notes and resolution. Resubmit after the company email has been sent; no duplicate feedback record should appear. Invalid CSRF, honeypot, expired timestamp and excessive feedback must fail safely.
7. Open an opt-out link using GET; no unsubscribe is created yet. Submit its POST form, then confirm future automated and manual sends are blocked. Repeat the POST and test a link older than the review-token expiry. Old queue rows stay parked when an administrator later removes suppression.
8. Verify single satisfaction reminder, single company reminder and single product review request. Already-sent timestamps must prevent repeats. Platform visits are visit counts, not published-review counts.
9. Run two sender workers and a manual send concurrently against the same row. One transport submission should occur. Simulate a transport failure; the timestamp should remain unset, allowing a retry. Note that SMTP acceptance followed by a process crash is an uncertain delivery and cannot be made atomic with a database write.
10. Verify the monthly campaign cap at dispatch, including multiple queued orders for the same address. Check a booster preview, daily queue cap, pause, resume and cancellation. Historical campaigns bypass organic order age but still respect suppression and monthly limits.
11. Test a restricted administrator role: queue, feedback, suppression, booster and config/test-email access must follow their ACL resources. Mutating admin actions must require POST or an explicit confirmation form.
12. Exercise legacy import preview, destination-not-empty rejection, duplicate-source rejection, transactional rollback and successful ID/token preservation on fixture copies. Check imported campaigns are paused and already-sent links work.
13. Verify upgrade and disable/re-enable on the exact target Magento version and theme, including mobile feedback forms, admin grids, mail transport and static-content deployment.

Record the Magento version, PHP version, theme, database version, mail transport and results in the release notes. A passing isolated suite alone is not production acceptance.
