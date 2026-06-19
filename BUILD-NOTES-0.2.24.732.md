# VMS 0.2.24.732

## Scope

- Ship a narrow admin UI cleanup patch after `0.2.24.731`.
- Remove the duplicate VMS admin navigation stack on `admin.php?page=vms-email-followups`.
- Preserve the Marketing & Social active state and leave all email/public behavior unchanged.

## Root cause

- The Email Follow-Ups page callback already rendered inside `vms_admin_ui_render_shell()`.
- The page slug was not registered as a shared shell page.
- Because of that mismatch, `vms_admin_ui_render_global_top_nav()` still ran from `all_admin_notices`, then the shell rendered the same primary/subnav again inside the page.

## What changed

- Added a module-local `vms_admin_ui_shell_pages` filter in `includes/modules/email-followups/admin-ui.php`.
- Registered `vms-email-followups` as a shared shell page, matching the existing Social Sharing shell behavior.
- Kept the page renderer, heading, inner Email Follow-Ups tabs, and Marketing & Social cluster wiring unchanged.

## Intentionally not changed

- No public Event Feedback behavior.
- No Email Follow-Ups sending logic.
- No template, log, MailPoet sync, or scheduler behavior.
- No WordPress left admin menu removal.

## Files changed

- `includes/modules/email-followups/admin-ui.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.732.md`
- `vms-test-plan-0.2.24.732.md`
- `docs/CODEX-HANDOFF-0.2.24.732.md`

## Local verification summary

- `php -l includes/modules/email-followups/admin-ui.php`
- Local WordPress runtime probe confirmed combined nav output counts:
  - Email Follow-Ups `overview`, `templates`, `preview`, `logs`: `1` primary row and `1` Marketing & Social secondary row each.
  - Social Sharing: `1` primary row and `1` secondary row.
  - Meta Ads Builder, Promotable Events, Meta Ads Performance, Meta Ads Logs, Meta Ads Settings: `1` primary row and `1` secondary row each.
- The same runtime probe confirmed Email Follow-Ups now resolves as `shell = true` with `cluster = marketing_social`.
- HTML inspection of the rendered Email Follow-Ups secondary row confirmed the current subnav item is `Email Follow-Ups`.

## Notes

- Unrelated PHP deprecations from WP-CLI and third-party plugins were suppressed during the local runtime probe so the VMS admin output could be checked cleanly. No warnings/notices were emitted by the touched VMS file during lint or runtime verification.

## Package

- Production-bound package slug: `vms-0.2.24.732-email-followups-admin-nav-dedupe.zip`
