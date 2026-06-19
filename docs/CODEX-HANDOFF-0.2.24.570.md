# CODEX HANDOFF - VMS Core 0.2.24.570 Vendor Portal Pattern Preference Reload Fix

## Build

- Plugin: `vms`
- Version: `0.2.24.570`
- Package target: `vms-0.2.24.570-vendor-portal-pattern-preference-fix.zip`
- Baseline: `0.2.24.569-vendor-portal-addon-hooks`

## What Changed

Repaired the vendor portal Availability renderer so it recognizes the already-saved `pattern` preferred method on normal page loads.

- File changed: `includes/portal/vendor-portal.php`
- Stored values now accepted: `manual`, `pattern`, `ics`
- No schema/meta-key changes were introduced.

## Why

Codex testing of VMS Core `0.2.24.569` with VMS Agreements `0.3.10` exposed a vendor portal regression in the Availability tab:

- Saving Pattern availability wrote `_vms_availability_preferred_method = pattern`.
- The immediate POST response looked correct.
- The next GET render rejected `pattern`, normalized it back to `manual`, and reopened the wrong section.

This repair keeps the existing pattern save path intact and fixes the reload path only.

## Guardrails Preserved

- The `vms_vendor_portal_dashboard_after_cards` hook from `0.2.24.569` remains unchanged.
- VMS Agreements dashboard card and custom tab integration remain additive and functional.
- Manual and ICS availability save flows remain unchanged.
- Vendor portal routing, preview nonce handling, and portal tab rendering were not reworked.

## Validation Summary

Validated on `2026-04-26` against the local `serenaderange.local` environment:

- PHP lint passed for VMS Core and VMS Agreements.
- Vendor portal dashboard hook still rendered Agreements content for a linked vendor.
- Empty vendor dashboards still withheld the Agreements entry points and showed a friendly empty state on the Agreements tab.
- Admin preview mode still rendered the portal correctly for a chosen vendor.
- Vendor Availability now keeps the Pattern section open after reload when `_vms_availability_preferred_method` is `pattern`.

## Required Test Plan

Run `docs/test-plan-0.2.24.570-vendor-portal-pattern-preference-fix.md` before considering this build validated on another environment.
