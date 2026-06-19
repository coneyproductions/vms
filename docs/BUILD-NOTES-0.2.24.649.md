# Build Notes — VMS 0.2.24.649

## Summary

This build fixes a high-risk Ticketing v2 sequencing issue:

- Disabling a qualified/free ticket and clicking **Save Config** updates the saved VMS config immediately.
- The public Woo/TEC ticket product may still remain reachable until the operator explicitly runs Preview → Commit / Push Ticket Changes.
- In that pending window, the previous runtime could lose the visible registration/verification requirement and allow public free-ticket adds.

0.2.24.649 fails closed during that window.

## Changes

- Added a runtime resolver for the Event Plan owning a public ticket product.
- Added a last-pushed sync-map ticket lookup for legacy/adopted products.
- Added a disabled-ticket config guard for mapped ticket products.
- Blocks disabled mapped tickets at:
  - Classic Woo add-to-cart validation.
  - VMS atomic/progressive add-to-cart endpoint.
  - Cart validation.
  - Checkout validation.
- Added a sync-map fallback so missing product markers cannot silently downgrade a last-pushed `verified` or `login` ticket to `public`.

## Risk / Compatibility

- Intended behavior is conservative: if saved config says the mapped ticket row is disabled, the product cannot be purchased even if still visible publicly.
- Normal GA/public ticket purchasing should remain unaffected.
- Existing Preview → Commit disabled-ticket product retirement behavior is preserved.

## Versioning

Updated:

- `vendor-management-system.php` plugin header to `0.2.24.649`.
- `includes/core/registry/constants.php` `VMS_VERSION` to `0.2.24.649`.
- `vms-build.txt` with the new build summary.
- Added `vms-test-plan-0.2.24.649.md`.

