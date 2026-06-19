# VMS 0.2.24.675 — Ticket Ratio Rules for Child/Comp Admission

## Purpose
Add an event-specific ticket wiring option for shows where one admission ticket should unlock only a limited number of another ticket type. This was added for child-heavy events such as the Taylor Swift tribute scenario where free child admission needs a clear per-adult cap.

## Changes
- Added optional per-ticket **Limit by qualifying tickets** settings in the Ticketing v2 admin editor.
- Added normalized config fields:
  - `ratio_rule_enabled`
  - `ratio_rule_max_per_qualifying`
  - `ratio_rule_qualifier_mode`
- Added product/runtime meta for ratio-limited ticket rows so pushed tickets retain the rule.
- Added server-side enforcement for ticket ratio rules during:
  - classic add-to-cart validation
  - cart validation
  - checkout validation
  - Woo Store API / block checkout notices through existing cart error collection
  - VMS progressive atomic add-to-cart flow
- Ratio rules use tickets marked **Counts toward add-on unlock** as the qualifying ticket pool.
- The protected/limited ticket does **not** qualify itself, even if its own unlock checkbox is accidentally left enabled.

## Example configuration
For an event where each paid adult ticket should unlock up to four free child tickets:

1. Adult / General Admission
   - Counts toward add-on unlock: checked
   - Limit by qualifying tickets: unchecked
2. Child Admission
   - Price: `$0`
   - Counts toward add-on unlock: unchecked
   - Limit by qualifying tickets: checked
   - Max per qualifying ticket: `4`

Expected behavior:
- 0 Adult + 1 Child = blocked
- 1 Adult + 4 Child = allowed
- 1 Adult + 5 Child = blocked
- 2 Adult + 8 Child = allowed
- 2 Adult + 9 Child = blocked

## Files changed
- `assets/admin-ticketing.js`
- `includes/core/registry/meta-keys.php`
- `includes/cpt/event-plans.php`
- `includes/integrations/ticketing-phase-b.php`
- `includes/integrations/ticketing-rules-v2.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.675.md`
- `vms-test-plan-0.2.24.675.md`
- `docs/05-revision-log.md`

## Validation performed
- PHP lint passed across all plugin PHP files.
- JavaScript syntax check passed for `assets/admin-ticketing.js`.

## Release package
- Versioned zip filename: `VMS_675_ticket_ratio_rules.zip`
