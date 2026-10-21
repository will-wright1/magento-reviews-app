# Changelog

## Unreleased — initial standalone extraction

- Extracted the shared Magento review automation functionality into `ReviewAutomation_Reviews`.
- Added Composer packaging, configurable store branding, neutral emails and disabled-by-default sending.
- Unified cron and manual email dispatch with shared locking, suppression and eligibility checks.
- Made public invitations independent of satisfaction and replaced sentiment/unsubscribe GET mutations with POST confirmation forms.
- Added order/feedback uniqueness, explicit transactional legacy imports and compatibility routing for imported tokens.
- Included isolated regression checks, PHP CI, runtime smoke tooling and installation, migration and publishing instructions.

Magento end-to-end staging acceptance remains required before the first stable release.
