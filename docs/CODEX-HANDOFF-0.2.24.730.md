# Codex Handoff — VMS 0.2.24.730

## What changed

- Added an attendee-facing `Website / Ticket Purchase Experience` section to the public Event Feedback survey.
- Added conditional website follow-up questions that only appear when the attendee used the website to buy or attempt to buy tickets.
- Changed each food truck / secondary vendor survey block so attendees answer `Did you order from them?` first, and only `Yes` responses expose the detailed rating/comment fields.
- Updated Event Feedback storage and admin reporting so hidden website/vendor fields are ignored, detailed vendor averages count only actual orders, and legacy feedback rows still render.

## Intentionally not changed

- No change to feedback link generation or prior invite URLs.
- No database migration.
- No change to primary-vendor performance feedback structure.
- No deployment or staging push was performed.

## Local verification performed

- `php -l` passed for:
  - `vms/includes/public/event-feedback.php`
  - `vms/includes/core/event-feedback.php`
  - `vms/includes/admin/event-feedback.php`
- `node --check` passed for `vms/assets/js/vms-event-feedback.js`.
- Public feedback route smoke returned `200` for real local feedback URLs covering:
  - one event plan with no secondary vendors
  - one event plan with one secondary vendor
- Disposable local runtime submissions confirmed:
  - website detail fields are ignored when `website_used = did_not_use`
  - website detail fields are stored when `website_used = tried_issue` / `bought_online`
  - secondary-vendor detail fields are ignored when `did_order = no` even if stale values are posted
  - secondary-vendor detail fields are stored when `did_order = yes`
- A temporary local-only meta override on the one-vendor fixture confirmed the survey renders two separate secondary-vendor blocks and still includes the website section; the original meta value was restored immediately after the check
- WP-CLI output during fixture discovery included unrelated PHP `8.5` deprecation noise from The Events Calendar / Event Tickets; that noise was outside the VMS files touched here.

## Packaging note

- Package name: `vms-0.2.24.730-event-feedback-public-questionnaire-update.zip`
