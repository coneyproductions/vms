# VMS 0.2.24.655 — Ticketing V2 Default Template Button Binding Fix

## Why this patch exists

Codex staging testing of 0.2.24.654 found that fresh Event Plans using the Ticketing V2 default-template auto-apply path could render enabled **Save config** and **Preview sync** buttons that did nothing.

The authenticated AJAX actions worked when called directly, so the regression was isolated to admin JavaScript initialization rather than backend save/preview/commit logic.

## Root cause

`assets/admin-ticketing.js` returned early from the default-template auto-apply branch before the later Ticketing V2 button listeners were registered.

That meant fresh plans with no saved Ticketing V2 config yet, plus a configured default template, could render the editor but miss handlers for:

- Save config
- Preview sync
- Commit sync
- related lower initializer bindings

## What changed

- Reworked the fresh-plan default-template initializer so it no longer returns before listener registration.
- The editor still renders immediately while the default template applies.
- If the Sales end guardrail appears, the editor remains interactive and the guardrail buttons remain usable.
- The normal Save/Preview/Commit listeners now bind even on the fresh-plan/default-template path.

## Preserved from 0.2.24.654

- Single public ticket with optional early/regular pricing window.
- Early-price guardrails:
  - early price must be lower than regular price
  - early price requires an end date
  - early start cannot be after early end
- Woo/TEC sync of regular/sale/scheduled-sale pricing on the same ticket product.
- Runtime active-price resolution from saved VMS config.

## Files changed

- `assets/admin-ticketing.js`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.2.24.655.md`
- `vms-test-plan-0.2.24.655.md`

## Static validation performed

- `node --check assets/admin-ticketing.js`
- Full PHP lint across all plugin PHP files
- Package top-level folder verified as `vms/`

## Live-test status

Not live-tested in WordPress during packaging. This is a narrow follow-up to the staging failure reported against 0.2.24.654.
