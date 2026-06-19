# VMS 0.2.24.716 Test Plan

## Scope

Follow-up polish for the public TEC event details card introduced in 0.2.24.715. This keeps the Google-readable Event JSON-LD behavior and makes the visible card more matter-of-fact.

## Changes

- Rename the default visible heading to “Event Details.”
- Remove the casual eyebrow heading.
- Reduce the heading size and card visual weight.
- Remove the bottom CTA button row from the visible card.
- Keep a small inline “Get directions” link inside the Location block.
- Replace hardcoded “Good to Know” copy with a Questions block linking to the site Questions page.
- Simplify the visible Tickets text so it uses derived minimum public ticket price without listing internal/free ticket labels.
- Preserve VMS-generated Event JSON-LD for Google readability.

## Validation

- PHP syntax check changed PHP files.
- Confirm version markers read 0.2.24.716.
- Confirm package top-level folder is `vms/`.
- Staging smoke: single TEC event page shows one compact Event Details card below tickets/meta area.
- Staging smoke: no duplicate eyebrow or bottom CTA row.
- Staging smoke: JSON-LD script with class `vms-event-json-ld` appears once in the document head.
