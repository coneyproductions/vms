# VMS 0.2.24.676 — Shared Ticket Ratio Allowance Groups

## Purpose

Extends the ticket ratio rules added in `0.2.24.675` so multiple limited ticket rows can share the same allowance pool.

This is intended for event-specific youth-heavy shows where separate rows such as `8 & Under` and `Youth 9-18` must be counted together against the same adult/GA allowance.

## New behavior

Each ticket with `Limit by qualifying tickets` can now optionally set a `Shared allowance group`.

Tickets with the same group are summed together before enforcing the max-per-qualifying-ticket limit.

Example:

- Adult/GA: `Counts toward add-on unlock` checked
- 8 & Under: `Limit by qualifying tickets` checked, `Max per qualifying ticket = 3`, `Shared allowance group = youth`
- Youth 9-18: `Limit by qualifying tickets` checked, `Max per qualifying ticket = 3`, `Shared allowance group = youth`

Results:

- 1 Adult + 3 total youth/children across both rows = allowed
- 1 Adult + 4 total youth/children across both rows = blocked
- 2 Adults + 6 total youth/children across both rows = allowed
- 2 Adults + 7 total youth/children across both rows = blocked

## Files touched

- `assets/admin-ticketing.js`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/cpt/event-plans.php`
- `includes/core/registry/meta-keys.php`
- version markers

## Notes

- Empty shared group preserves the previous per-ticket behavior.
- A limited ticket group cannot qualify itself.
- The strictest/lower max-per value is used if rows in the same group are accidentally configured with different limits.
