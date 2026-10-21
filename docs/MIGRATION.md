# Migrating Vanimal or 4x4AT

Perform this on a backed-up staging copy first. The standalone module has different table and config names. Its invitation flow intentionally includes unhappy customers and non-responders. Imported unsent invitations can become due when you enable it; inspect the queue before enabling.

## Preparation

1. Back up the database, code and existing configuration. Record the old module version and cron state.
2. Stop Magento cron and any external review-email workers. Enable maintenance mode during installation and import.
3. Disable the old module's review sending through configuration at **every scope**, but keep its code and module registration installed. Do not disable/uninstall the legacy Magento module or remove its declarative schema yet: a later `setup:upgrade` may remove its tables.
4. Install and enable the new module, then run `setup:upgrade` with the old module still registered. Leave the new sending configuration disabled at every scope.
5. Flush config cache. All four destination tables must be empty. Do not create test campaigns or suppression records in the new module before import.

## Preview and execute

From the Magento root, choose the appropriate source:

```sh
bin/magento reviewautomation:import-legacy vanimal
# Or:
bin/magento reviewautomation:import-legacy fourbyfourat
```

The command previews counts and checks destination emptiness, source tables and duplicate order/feedback identifiers. It makes no changes without `--execute`.

After reviewing counts, with cron stopped and maintenance mode enabled:

```sh
bin/magento reviewautomation:import-legacy vanimal --execute
# Or use fourbyfourat --execute
```

The command copies shared columns in one database transaction. Queue IDs, order references, tokens, timestamps, feedback and suppression records retain their IDs. A missing legacy booster table is allowed. Active booster campaigns are imported as paused. Source tables remain untouched. Existing records in the destination cause the command to refuse a second import; this is not a merge or sync tool.

The command imports the `*_review_*` tables used by the ReviewAutomation modules. It does not import the older separate `ReviewEmails` queue or `will_review_unsubscribe` tables. If those still contain opt-outs that were never migrated into the current suppression table, reconcile them before proceeding.

## Cutover verification

- Compare counts and sample rows, including customer opt-outs and sent-message timestamps.
- Reconfigure store selection, sender identity, timing, branding and platform URLs in the new module. Configuration and custom email-template records are not copied automatically. Rebuild custom templates from the new defaults so they contain the new routes and placeholders.
- Check public-review behaviour for a positive response, negative response and no response.
- Check at least one already-sent legacy satisfaction link and unsubscribe link. The compatibility router recognises imported tokens at the old `/vanimal_reviews/...` and `/fourbyfourat_reviews/...` paths and routes them to the new confirmation forms. Non-imported tokens remain with the old module.
- Review imported pending and feedback rows before enabling. Previously excluded negative/non-responding customers may now have scheduled company invitations.
- Resume only the historical campaigns you intend to continue.
- Enable the new sender only, remove maintenance mode and restart cron. Keep the legacy sender disabled.

Retain the legacy module and schema until a separate, backed-up retirement procedure is approved. The import never drops source tables or staging tables.

## Rollback

Before any new outbound messages, disable the new sender and restore the pre-import database/code snapshot if cutover fails. After new emails have been sent, reconcile those timestamps and new opt-outs before re-enabling the legacy system; restoring an old snapshot alone can lose opt-outs and resend messages. Never run both senders concurrently.
