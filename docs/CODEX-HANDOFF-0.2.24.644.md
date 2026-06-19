# Codex Handoff — VMS 0.2.24.644

## Purpose
Patch a Progressive ticket UI regression where one event hid the add-on/amenities section for logged-out users while another event displayed it correctly.

## Change summary
- Broadened front-end Progressive add-on source detection beyond only `#vms-reserved-addons.vms-entitlements-block`.
- Recognizes `#vms-reserved-addons`, `.vms-entitlements-block`, `.vms-rw-addons`, `[data-vms-addons-mounted]`, and server-control add-on markers.
- Keeps the section hidden only when no real add-on source/content exists.
- Removes inline `display:none` from moved add-on sources when mounted into Progressive UI.

## Files changed
- `assets/vms-ticketing-progressive-ui.js`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `vms-test-plan-0.2.24.644.md`
- `docs/test-plan-0.2.24.644-progressive-addon-detection-hardening.md`

## Test focus
Run `vms-test-plan-0.2.24.644.md`, especially the event that previously hid add-ons while another event worked.
