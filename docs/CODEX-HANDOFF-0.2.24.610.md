# CODEX HANDOFF — VMS 0.2.24.610 — Email Follow-Ups Recipient Selection + Batch Sends

## Build

- Version: `0.2.24.610`
- Package: `vms-0.2.24.610-email-followups-recipient-batch-send.zip`

## Scope

This pass repairs and hardens the Email Follow-Ups manual-send workflow:

- Fixes the regression where each Templates save created another empty **Custom Follow-Up** template.
- Adds a conservative migration to remove empty placeholder custom templates created by that bug.
- Adds selected-recipient checkboxes so admins can send to all, none, one, or a hand-picked subset.
- Adds Select all / Select none controls and selected-count display.
- Adds batched manual-send processing with default 50 recipients per request and a Continue Sending flow when more remain.
- Adds a client-side sending/progress indicator so admins know the request is actively running.

## Files touched

- `includes/modules/email-followups/admin-ui.php`
- `includes/modules/email-followups/settings.php`
- `includes/modules/email-followups/sender.php`
- `assets/js/vms-email-followups-admin.js`
- `assets/css/vms-email-followups-admin.css`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- docs listed below

## Test plan

`docs/test-plan-0.2.24.610-email-followups-recipient-batch-send.md`

🚨 Please test on staging before using this against a large live event buyer list.
