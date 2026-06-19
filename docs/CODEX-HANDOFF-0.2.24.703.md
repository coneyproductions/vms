# VMS 0.2.24.703 — Qualified Credential Proof Normalization

## What changed

- Qualified-ticket verification uploads in core VMS now normalize supported image proofs into stored JPG files instead of leaving customers at the mercy of raw phone-photo size/format drift.
- The public verification uploader now prefers browser-side normalization for supported images so large phone photos are reduced before they ever hit the server.
- The server still normalizes supported image uploads again before storage so the proof path remains reliable when browser-side processing is unavailable.
- PDF uploads remain on their own path and are not forced through the image normalizer.

## Ops behavior audit

- Existing Ops member-profile photo normalization was found in both places:
  - client-side: `vms-ops-console-premium/pwa/assets/js/app.js`
  - server-side: `vms-ops-console-premium/includes/private-club/member-lookup.php`
- This core VMS pass intentionally does **not** call Ops functions or require the Ops plugin to be active.
- Instead, it adapts the same pattern inside core VMS with credential-specific settings:
  - long edge `2200px`
  - JPEG quality `86`
  - JPG output only for supported images

## New core pieces

- `includes/helpers/image-normalization.php`
  - Shared core helper for normalizing uploaded images into JPG proof copies.
  - Resizes oversized images, preserves readable dimensions, and drops most source metadata by re-saving the file.

- `assets/js/vms-image-normalize.js`
  - Shared browser helper for client-side image normalization.

- `includes/integrations/ticketing-verifications.php`
  - Replaced the hard-coded `10 MB` image limit with an admin-editable original upload limit setting.
  - Defaults to `20 MB`.
  - Uses the lower of the configured limit and the current WordPress/PHP ceiling for real enforcement and customer-facing copy.
  - Adds clearer upload error messages for oversized PDFs, unsupported PDFs, and HEIC/HEIF guidance.

## Customer-facing behavior

- Supported proof images: `JPG`, `PNG`, `WEBP`
- Supported non-image proof: `PDF`
- Stored proof for supported images: normalized `JPG`
- Stored proof for PDFs: original `PDF`
- HEIC/HEIF: still not supported as an accepted credential proof format; users are told to take a screenshot or export as JPG/PNG.

## Admin-facing behavior

- New **Verification Upload Settings** section on **VMS -> Eligibility Approvals**
- Admin can set the original upload intake limit in MB.
- The screen also shows:
  - configured limit
  - effective server-limited cap
  - note when PHP/WordPress is capping lower than the VMS setting
  - current normalization target (`2200px`, quality `86`)

## Local verification

- `php -l vms/includes/helpers/image-normalization.php`
- `php -l vms/includes/integrations/ticketing-verifications.php`
- `php -l vms/tests/verification-proof-normalization.php`
- `node --check vms/assets/js/vms-image-normalize.js`
- `node --check vms/assets/js/vms-verification-upload.js`
- `php vms/tests/verification-proof-normalization.php`

## Version markers updated

- Plugin header: `0.2.24.703`
- `VMS_VERSION`: `0.2.24.703`
- `vms-build.txt`: `0.2.24.703`
- Build notes: `BUILD-NOTES-0.2.24.703.md`
- Test plan: `vms-test-plan-0.2.24.703.md`
