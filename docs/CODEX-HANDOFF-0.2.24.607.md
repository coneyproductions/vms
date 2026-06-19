# CODEX HANDOFF — VMS 0.2.24.607 — Email Follow-Ups Smart Greeting Tokens

🚨 **Codex/staging test recommended before production customer sends.** This pass changes rendered customer email copy only; automatic sends should remain off until previews and test emails are confirmed.

## Build

- Version: `0.2.24.607`
- Package: `vms-0.2.24.607-email-followups-smart-greeting-tokens.zip`

## Changed files

- `includes/modules/email-followups/sender.php`
- `includes/modules/email-followups/templates.php`
- `includes/modules/email-followups/settings.php`
- `includes/core/registry/constants.php`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/01-project-handoff.md`
- `docs/05-revision-log.md`
- `docs/06-test-plan.md`
- `docs/test-plan-0.2.24.607-email-followups-smart-greeting-tokens.md`

## What changed

- Added `{customer_first_name}` token.
- Added `{customer_greeting}` token.
- `{customer_greeting}` renders `Hi First,` when a usable first name is available, otherwise `Hi there,`.
- Default Email Follow-Ups templates now begin with `{customer_greeting}`.
- One-time migration prepends `{customer_greeting}` to saved templates that do not already contain any customer/name token.

## Test target

Run: `docs/test-plan-0.2.24.607-email-followups-smart-greeting-tokens.md`

## Notes

- Prefer `{customer_greeting}` over hand-written greetings such as `Dear {customer_first_name},` because `{customer_first_name}` can intentionally render blank when no name is discoverable.
- If Codex makes any repair after testing, update plugin header version, `VMS_VERSION`, `vms-build.txt`, revision log, handoff, test-plan/Codex handoff docs, and package filename before returning a replacement zip.
