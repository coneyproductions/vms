# VMS 0.2.24.730

## Scope

- Ship a narrow Event Feedback attendee-survey update only.
- Add website / ticket-purchase feedback questions.
- Reduce food truck / secondary vendor survey noise with conditional detail blocks.
- Keep existing invite-key routing, token validation, and feedback-link behavior unchanged.

## What changed

- Added a new `Website / Ticket Purchase Experience` section to the public Event Feedback form before the vendor sections.
- Added `website_used` plus conditional website detail fields for:
  - finding the event
  - ticket selection clarity
  - checkout smoothness
  - payment / loading issue type
  - confirmation / ticket clarity
  - freeform website comments
- Changed each secondary vendor / food truck block so the attendee always answers `Did you order from them?` first with four choices:
  - `Yes`
  - `No`
  - `I wanted to, but did not`
  - `I'm not sure / don't remember`
- Hid secondary-vendor detail questions unless the attendee selects `Yes`, using lightweight vanilla JS on the public form and matching server-side sanitization/ignore rules on submit.
- Preserved existing feedback invite, recipient, source, token, and event-plan behavior, and extended the existing JSON-style payload safely without requiring a migration.
- Updated Event Feedback admin summaries and per-response views to:
  - show website usage and issue responses
  - show `Did you order from them?` counts per secondary vendor
  - count detailed vendor averages only for respondents who actually ordered
  - continue rendering older feedback rows that do not contain the new website keys

## Files changed

- `includes/public/event-feedback.php`
- `includes/core/event-feedback.php`
- `includes/admin/event-feedback.php`
- `assets/js/vms-event-feedback.js`
- `assets/css/vms-event-feedback.css`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.730.md`
- `vms-test-plan-0.2.24.730.md`
- `docs/CODEX-HANDOFF-0.2.24.730.md`

## Local verification summary

- `php -l` passed for:
  - `includes/public/event-feedback.php`
  - `includes/core/event-feedback.php`
  - `includes/admin/event-feedback.php`
- `node --check` passed for:
  - `assets/js/vms-event-feedback.js`
- Local HTTP checks confirmed the feedback route still responds with `200` for a real feedback URL.
- Live questionnaire render checks confirmed:
  - a no-secondary-vendor event still renders and now includes the website section
  - a one-secondary-vendor event renders `Did you order from them?` plus a hidden vendor-detail block
- Disposable local runtime submissions confirmed:
  - `website_used = did_not_use` clears and stores all website detail fields as empty / zero
  - `website_used = tried_issue` stores the website detail fields
  - `did_order = no` clears and stores all secondary-vendor detail fields as empty / zero even when stale values are posted
  - `did_order = yes` stores the detailed secondary-vendor ratings, wait-cause values, and comment
- A temporary local-only two-vendor render override confirmed the public survey produces two secondary-vendor blocks and still includes the website section; the temporary meta change was restored immediately after the check
- Note:
  - local WP-CLI output is noisy because of unrelated PHP `8.5` deprecation notices from The Events Calendar / Event Tickets; those notices did not originate from this VMS patch

## Package

- Production-bound package slug: `vms-0.2.24.730-event-feedback-public-questionnaire-update.zip`
