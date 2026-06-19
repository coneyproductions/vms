# VMS 0.2.24.703 Test Plan — Qualified Credential Proof Upload Normalization

## Pre-checks

1. Install/activate VMS `0.2.24.703`.
2. Confirm version markers:
   - Plugin page shows `0.2.24.703`.
   - `vms/includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.703`.
   - `vms/vms-build.txt` begins with `0.2.24.703`.
3. Confirm at least one test customer account can access the **My Account -> Verification Discounts** form.
4. Confirm **VMS -> Eligibility Approvals** is accessible to an operator with verification-management permissions.

## Admin settings

1. Open **VMS -> Eligibility Approvals**.
2. Find **Verification Upload Settings**.
3. Confirm the page shows:
   - original upload limit field
   - configured limit text
   - effective server cap text
   - normalization target note (`2200px`, quality `86`)
4. Save the default `20 MB` limit once.
5. Expected:
   - success notice appears
   - the saved value persists after reload

## Large JPG upload

1. Log in as a normal customer.
2. Open the verification form.
3. Upload a large JPG from a phone or desktop source with a long edge above `2200px`.
4. Submit the form.
5. Expected:
   - the form shows preparing/normalizing/uploading progress
   - the request submits successfully
   - the saved proof opens in admin as a JPG
   - the saved proof remains readable

## PNG upload

1. Submit a PNG proof image.
2. Expected:
   - upload succeeds
   - admin proof view opens
   - stored proof is a JPG, not a PNG
   - text/details remain readable

## WEBP upload

1. Submit a WEBP proof image from a browser/environment that can select WEBP files.
2. Expected:
   - upload succeeds
   - stored proof is a JPG
   - proof remains readable

## Oversized file errors

1. In **Verification Upload Settings**, temporarily lower the original upload limit to a small value such as `1 MB`.
2. Try an image larger than that limit.
3. Try a PDF larger than that limit.
4. Expected:
   - image error clearly says the image is too large and shows the current limit
   - PDF error clearly says the PDF is too large and suggests JPG/PNG screenshot fallback
5. Restore the limit to `20 MB`.

## Unsupported format guidance

1. Try a HEIC/HEIF image if your test device/browser allows selecting one.
2. Expected:
   - upload is blocked with guidance to take a screenshot or export as JPG/PNG
3. Try an invalid/unsupported non-PDF document renamed with a `.pdf` extension.
4. Expected:
   - customer sees a clearer PDF-specific unsupported error instead of a generic file-missing failure

## PDF regression

1. Submit a normal PDF proof under the limit.
2. Expected:
   - upload succeeds
   - admin proof view serves the PDF inline
   - PDF is not converted into an image

## Existing record regression

1. Open older approved and denied verification requests created before `0.2.24.703`.
2. Expected:
   - existing proof links still open for any proof file that still exists
   - approved/denied records still render normally in the approvals table

## Performance sanity

1. Submit a large but allowed phone image from a current browser.
2. Watch the browser/network behavior and server response time.
3. Expected:
   - browser-side normalization occurs before upload when supported
   - request completes without obvious admin/server stall
   - no new fatal errors or timeouts appear in the PHP error log

## Local automated smoke checks

1. Run `php vms/tests/verification-proof-normalization.php`.
2. Run:
   - `php -l vms/includes/helpers/image-normalization.php`
   - `php -l vms/includes/integrations/ticketing-verifications.php`
   - `node --check vms/assets/js/vms-image-normalize.js`
   - `node --check vms/assets/js/vms-verification-upload.js`
3. Expected:
   - smoke harness passes for JPG, PNG, WEBP, and oversized-output cases
   - syntax checks pass
