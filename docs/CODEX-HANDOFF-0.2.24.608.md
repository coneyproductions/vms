# CODEX HANDOFF — VMS 0.2.24.608 — Email Follow-Ups Template Timing + Signatures

## Build

- Version: `0.2.24.608`
- Package: `vms-0.2.24.608-email-followups-template-timing-signatures.zip`
- Baseline: `0.2.24.607-email-followups-smart-greeting-tokens`

## Purpose

This pass makes Email Follow-Ups clearer and more flexible:

- Each template now owns its own send timing.
- A global signature can be inserted with `{signature}`.
- Admins can add/delete custom templates.

## Files changed

- `includes/modules/email-followups/settings.php`
- `includes/modules/email-followups/templates.php`
- `includes/modules/email-followups/scheduler.php`
- `includes/modules/email-followups/sender.php`
- `includes/modules/email-followups/admin-ui.php`
- `assets/css/vms-email-followups-admin.css`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- docs listed below

## Test plan

Run:

`docs/test-plan-0.2.24.608-email-followups-template-timing-signatures.md`

🚨 Keep automatic scheduled sends disabled until the preview/test/custom-template flow is verified on staging.

## Specific things to verify

1. Templates tab loads and saves without resetting Overview settings.
2. Overview settings save without resetting template timing or template enabled states.
3. Per-template timing changes are reflected in Preview & Test scheduled timing.
4. Custom templates appear in the Preview & Test dropdown after save.
5. `{signature}` renders properly in test emails.
6. Existing `{feedback_url}` behavior still works for Post-Event Thank You.
