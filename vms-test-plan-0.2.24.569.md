# VMS 0.2.24.569 Test Plan - Vendor Portal Add-On Hooks

🚨 **Codex / live-site testing is required before treating this package as validated.**

This pass adds a stable vendor portal dashboard extension point for companion add-ons. It intentionally does not change vendor portal account linking, profile editing, availability logic, opportunities, tech docs, event history, ticket stats, or compensation snapshots.

## Install / Version Checks

1. Install/replace VMS Core with `vms-0.2.24.569-vendor-portal-addon-hooks.zip`.
2. Confirm WordPress shows VMS version `0.2.24.569`.
3. Confirm `vms/vms-build.txt` reads `0.2.24.569`.
4. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.569`.

## Syntax / Smoke Checks

1. Run PHP lint on all VMS PHP files.
2. Activate VMS Core.
3. Confirm no fatal errors on the public page containing `[vms_vendor_portal]`.
4. Log in as a linked vendor and open the Vendor Portal dashboard.

## Hook Verification

1. Temporarily attach a small test callback to `vms_vendor_portal_dashboard_after_cards` or install VMS Agreements `0.3.10+`.
2. Confirm the callback/card renders after the native dashboard grid.
3. Confirm the callback receives `$portal_context` with at least:
   - `vendor_id`
   - `vendor_ids`
   - `user_id`
   - `base_url`
   - `tab`
4. Confirm the hook is fired on the Dashboard tab only.

## Regression Checks

1. Dashboard still shows Next Booking, Upcoming Bookings, Availability Setup, and Action Needed.
2. Availability tab still renders and can save manual/pattern/ICS settings as before.
3. Profile, Tax Profile, Tech Docs, and Event History tabs still render as before.
4. Vendor portal admin preview mode still works.
5. No nested forms were added by this core hook pass.

## Notes

- Add-ons rendering here should output self-contained cards/sections only.
- Shortcodes remain valid fallback paths, but first-party add-ons should prefer this hook when the native portal is present.
