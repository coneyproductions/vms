# VMS 0.2.24.703

## Scope

- Improve qualified-ticket credential proof uploads so modern phone images are normalized into readable JPG proofs instead of failing on size/format drift.
- Preserve the separate PDF upload path while making PDF and unsupported-format errors clearer.
- Add an admin-editable original upload limit without introducing any dependency on the VMS Ops plugin at runtime.

## Ops reference points

- Ops client-side profile-photo normalization lives in `vms-ops-console-premium/pwa/assets/js/app.js`.
- Ops server-side profile-photo normalization lives in `vms-ops-console-premium/includes/private-club/member-lookup.php`.
- This VMS core pass adapts the same general client/server normalization behavior but keeps the implementation self-contained inside core VMS.

## Behavior change

- Verification image uploads now target a normalized JPG proof instead of preserving PNG/WEBP output formats.
- Browser-side uploads attempt to normalize supported images before upload using a new shared helper:
  - long edge capped at `2200px`
  - JPEG quality `86`
  - white background fill for non-JPEG sources
  - orientation respected where the browser supports it
- Server-side verification uploads normalize supported images to JPG again before storage so the proof path still works when browser-side normalization is unavailable.
- Normalized image proofs are stored without keeping the oversized original file.
- The default original upload limit is now `20 MB`, with a new admin setting on **VMS -> Eligibility Approvals**.
- The effective customer-facing limit now reflects the lower of:
  - the VMS admin-configured limit
  - the current WordPress/PHP upload ceiling
- PDF uploads still bypass image normalization and remain PDFs.
- Upload errors now distinguish:
  - oversized image
  - oversized PDF
  - unsupported HEIC/HEIF image
  - unsupported/invalid PDF
  - generic save/processing failures

## Files changed

- `includes/helpers/image-normalization.php`
- `includes/core/load.php`
- `includes/integrations/ticketing-verifications.php`
- `assets/js/vms-image-normalize.js`
- `assets/js/vms-verification-upload.js`
- `tests/verification-proof-normalization.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Local tests performed

- `php -l vms/includes/helpers/image-normalization.php`
- `php -l vms/includes/integrations/ticketing-verifications.php`
- `php -l vms/tests/verification-proof-normalization.php`
- `node --check vms/assets/js/vms-image-normalize.js`
- `node --check vms/assets/js/vms-verification-upload.js`
- `php vms/tests/verification-proof-normalization.php`

## Package

- Production-bound package slug: `vms-0.2.24.703.zip`
