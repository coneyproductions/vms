# CODEX Handoff — VMS 0.2.24.636

## Scope
This is a customer-facing public ticket-row copy polish for approved/free admission tickets.

## What changed
- Removed the row-level `Qualified ticket.` wording.
- Removed row-level `Submit verification before checkout` language.
- Added a collapsed `First time? More info` disclosure inside each approved/free ticket row.
- Expanded help explains the approval flow and links to the verification flow when available.
- Existing ticket eligibility and registered-guest assignment logic should remain unchanged.

## Files touched
- `includes/integrations/ticketing-rules-v2.php`
- `includes/integrations/ticketing-claims-customer.php`
- `assets/vms-ticketing-front.js`
- `assets/vms-ticketing-progressive-ui.js`
- `assets/css/ticketing-front/80-ticket-ui-rewrite.css`
- `assets/css/vms-ticketing-front.css`
- version/build/test-plan docs

## Primary regression risks
- The short ticket row description could duplicate expanded help after quantity changes.
- The more-info disclosure could be hidden by progressive-row CSS before quantity selection.
- Existing approved-account and registered-guest enforcement could be weakened by the copy/UI change.

## Test plan
Run `vms-test-plan-0.2.24.636.md`.
