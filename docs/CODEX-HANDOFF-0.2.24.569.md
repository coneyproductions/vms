# CODEX HANDOFF - VMS Core 0.2.24.569 Vendor Portal Add-On Hooks

## Build

- Plugin: `vms`
- Version: `0.2.24.569`
- Package target: `vms-0.2.24.569-vendor-portal-addon-hooks.zip`
- Baseline: `0.2.24.568-final-payment-terms`

## What Changed

Added a stable dashboard extension point to the native vendor portal:

- File changed: `includes/portal/vendor-portal.php`
- New action: `vms_vendor_portal_dashboard_after_cards`
- Fired immediately after the native dashboard card grid.
- Passes the existing `$portal_context` array to add-ons.

## Why

VMS Agreements `0.3.9` could render a Pending Agreements card by shortcode, but Core did not fire the guessed dashboard hook. This version makes the portal integration first-class so Agreements and future add-ons can appear automatically without manually placing shortcode content.

## Guardrails Preserved

- No vendor portal routing logic changed.
- No vendor account-linking logic changed.
- No availability, opportunities, profile, tax profile, tech docs, or event history behavior changed.
- No nested forms were added.
- This hook is additive and does not require Agreements to be active.

## Testing Required

🚨 Run `docs/test-plan-0.2.24.569-vendor-portal-addon-hooks.md` before considering this build validated.
