# CODEX HANDOFF — VMS 0.2.24.595 Square Sync shell package repair

## Summary

This build repairs the broken `0.2.24.594` package. The prior package omitted `includes/tours/tours.php`, causing a fatal error from `includes/bootstrap.php`.

This package was rebuilt from the last full-good `0.2.24.593` zip and then re-applies the intended Square Sync Protection shell-alignment change.

## Important

🚨 Test on staging before production.

## What changed

- Preserved the full Guided Tours module from `0.2.24.593`.
- Kept Square Sync Protection rendering through the shared VMS admin shell.
- Kept the compact/aligned left VMS menu from `0.2.24.593`.
- Updated version markers to `0.2.24.595`.
- Added/updated package test documentation.

## Primary URLs to test

- `wp-admin/admin.php?page=vms-square-sync-protection`
- `wp-admin/admin.php?page=vms-event-command-center`
- `wp-admin/admin.php?page=vms-guided-tours`
- `wp-admin/admin.php?page=vms-schedule`

## Expected outcome

- No fatal error.
- Square Sync Protection appears inside the VMS shell.
- Left menu stays concise.
- Square protection scan/repair tools remain functional.
