# VMS 0.2.24.731

## Scope

- Ship a narrow Event Feedback follow-up patch after `0.2.24.730`.
- Clarify attendee Bar and Bathroom elaboration checkbox labels.
- Improve admin response readability for legacy venue-detail selections, skipped vendor blocks, and hidden website fields.

## What changed

- Replaced the public Event Feedback Bar elaboration checkbox labels with sentiment-specific choices:
  - fast service vs slow service
  - friendly staff vs less friendly staff
  - good selection vs limited selection
  - fair pricing vs pricing felt high
  - easy ordering/payment vs confusing ordering/payment
- Replaced the public Event Feedback Bathroom elaboration checkbox labels with sentiment-specific choices:
  - clean vs needed cleaning
  - stocked supplies vs low/missing supplies
  - good lighting vs lighting needed attention
  - easy access vs hard access
  - little/no wait vs long wait
- Updated the Bar and Bathroom elaboration headings/prompts to use clearer `Additional ... feedback` summaries and `Select anything that applies` prompt text.
- Kept backward compatibility for old Event Feedback responses by:
  - accepting legacy bar/bathroom detail keys during sanitization
  - rendering legacy ambiguous detail choices in admin as `Legacy selections: ...`
  - preserving the old freeform comments unchanged
- Tightened the admin response view so:
  - current bar/bathroom selections render human-readable labels instead of internal keys
  - food truck/vendor blocks with `Ordered? = No` or another non-`Yes` value show a short skipped-status line instead of empty detail grids
  - website detail rows only render when the website section was actually relevant/populated

## Files changed

- `includes/core/event-feedback.php`
- `includes/public/event-feedback.php`
- `includes/admin/event-feedback.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.731.md`
- `vms-test-plan-0.2.24.731.md`
- `docs/CODEX-HANDOFF-0.2.24.731.md`

## Local verification summary

- `php -l` passed for:
  - `includes/core/event-feedback.php`
  - `includes/public/event-feedback.php`
  - `includes/admin/event-feedback.php`
- Live local feedback-form smoke returned `200` and confirmed the updated public text appears:
  - `Additional bar feedback`
  - `Additional bathroom feedback`
  - the new sentiment-specific checkbox labels
- Disposable runtime submission checks confirmed:
  - new bar/bathroom selections store the new keys
  - `website_used = did_not_use` still clears and hides all website detail fields in storage/admin display
  - a mixed two-vendor response stores Vendor A detail ratings, clears stale Vendor B detail fields when `did_order = no`, and renders the new skipped-status message instead of empty detail rows
- Disposable legacy-response rendering confirmed:
  - older ambiguous bar/bathroom keys render as `Legacy selections: ...`
  - legacy comments still display
- Temporary local test responses were deleted after verification.

## Notes

- Local WP-CLI and WordPress runtime checks can still emit unrelated PHP `8.5` deprecation noise from The Events Calendar / Event Tickets. Those notices were outside the VMS files touched here.

## Package

- Production-bound package slug: `vms-0.2.24.731-event-feedback-label-clarity-followup.zip`
