# VMS 0.2.24.681 — Relative Ticket Sale Dates + Event-End Sales Guardrail

## Purpose

This build follows `0.2.24.680` and adds safer, reusable ticket sale-date controls for Event Plan templates.

The main operator need is to define dates such as “early price ends 31 days before the event” once in a template, then have VMS recalculate the actual calendar date for each Event Plan. It also adds a hard safety guard so ticket sales cannot remain open after the event ends.

## Changes

- Adds per-ticket relative date controls in Ticketing v2:
  - Early price start: days before event start.
  - Early price end: days before event start.
  - Sales start: days before event start.
  - Sales end: days before event end.
- Defaults new ticket Sales end to the Event Plan end time instead of the Event Plan start time.
- Lets an operator set `31` on **Early ends → Ends days before event** so early pricing ends 31 days before showtime and the regular price takes over inside the final 30-day window.
- Recalculates relative ticket dates when the Event Plan date/start/end time changes in the editor.
- Preserves relative date fields in the saved Ticketing v2 config, template hashing, tier hashing, and legacy GA compatibility fields.
- Resolves relative dates server-side during config normalization and ticket sync so the browser UI is not the only protection layer.
- Adds an event-end guardrail that clamps ticket Sales end dates to the Event Plan / TEC event end before product sync.
- Updates the template Sales end review guardrail so it can catch both stale-before-event values and unsafe-after-event values.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/ticketing-phase-b.php`
- `assets/admin-ticketing.js`
- `BUILD-NOTES-0.2.24.681.md`
- `vms-test-plan-0.2.24.681.md`
- `docs/05-revision-log.md`

## Validation Performed

- `php -l` across all plugin PHP files
- `node --check` across all non-minified plugin JS files
- `zip -T VMS_681_relative_ticket_sale_dates.zip`

## Notes / Caveats

- Relative early-price dates are anchored to the Event Plan start time.
- Relative ticket Sales end dates are anchored to the Event Plan end time.
- Sales end dates after the event end are clamped rather than allowed through to TEC/Woo.
