# VMS 0.2.24.696 — Verification Upload FormData Ordering

## Purpose

This follow-up build fixes the remaining frontend verification upload blocker discovered during staging retest.

## Changes

- Builds the verification upload `FormData` before the form is disabled, so hidden fields such as `action`, `response_mode`, nonce, and program survive the AJAX submission.
- Keeps the earlier `admin-post.php` target fix and safe image-processing error handling intact.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `assets/js/vms-verification-upload.js`

## Validation Planned

- `node --check assets/js/vms-verification-upload.js`
- staging verification upload success and bad-image error handling
- staging mixed Ops/public concurrency rerun
