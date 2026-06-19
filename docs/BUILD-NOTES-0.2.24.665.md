# VMS 0.2.24.665 — Social Template Default No-Effect Meta Cleanup

## Summary

0.2.24.665 is a narrow follow-up to 0.2.24.664 after local Codex testing showed that content-only Event Plan saves still classified the Marketing module as changed because `_vms_social_template_overrides` was being effectively written during an otherwise unrelated save.

## Changes

- Preserves the Promotion / Social Sharing template selector's `Default` selection instead of converting an implicit default template into an explicit per-event override during render/save.
- Adds a small social-panel meta update helper that compares canonical current/default values before updating Event Plan social meta.
- Avoids persisting default social values on unrelated Event Plan saves when no operator change was made.
- Keeps the 0.2.24.664 no-effect dirty-map cleanup and Add New Vendor shortcuts intact.

## Expected result

A content-only normal WordPress Update should classify as `core` only unless the operator actually changes social/marketing fields. `_vms_social_template_overrides` should not appear as an effective write merely because the Social Sharing panel rendered default templates.

## Validation performed before packaging

- PHP lint passed across plugin PHP files.
- JS syntax checks passed across non-minified plugin JS files.
- Zip integrity passed with `unzip -t`.
