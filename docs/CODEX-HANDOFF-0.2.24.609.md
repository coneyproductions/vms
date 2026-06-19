# CODEX HANDOFF — VMS 0.2.24.609 — Email Follow-Ups Template Save + Character Cleanup

## Package

- Version: `0.2.24.609`
- Package: `vms-0.2.24.609-email-followups-template-save-character-cleanup.zip`
- Base: `0.2.24.608`

## Scope

This pass addresses two live UX/regression issues in Email Follow-Ups:

1. Operators can forget to save template edits because the only save button was at the bottom of a long Templates page.
2. Saved template bodies started showing mojibake/crazy-character artifacts such as `Ã¢Â€Â¢` instead of bullets.

## Implementation notes

- Added per-template **Save Template Changes** buttons below each template body textarea.
- Added **Save New Template** button inside the Add Template card.
- Buttons submit the same Templates form intentionally; labels/microcopy clarify that the page's template changes are saved.
- Added `vms_email_followups_clean_template_text()` to normalize common mojibake/smart-character artifacts in template-related inputs.
- Applied cleanup to template subject/body, custom template label/description, new template fields, from-name, MailPoet list field, and default signature field where relevant.
- Added one-time migration `vms_email_followups_migrate_template_encoding_609()` to repair existing saved settings.
- Default templates now use ASCII-safe hyphen bullets and straight punctuation to avoid recurring encoding artifacts.

## Test plan

Run:

`docs/test-plan-0.2.24.609-email-followups-template-save-character-cleanup.md`

🚨 Keep automatic scheduled sends disabled until the follow-up email system is fully staged and approved.
