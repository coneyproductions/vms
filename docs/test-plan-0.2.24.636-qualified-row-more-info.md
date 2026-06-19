# VMS 0.2.24.636 Test Plan — Approved/Free Ticket Row Copy + First-Time Help

## Goal
Confirm approved/free admission rows no longer show confusing qualified-ticket jargon or imply verification happens during checkout, while preserving the existing eligibility and registered-guest enforcement.

## Build/version checks
1. Confirm `vms/vendor-management-system.php` plugin header reports `Version: 0.2.24.636`.
2. Confirm `vms/includes/core/registry/constants.php` reports `VMS_VERSION` as `0.2.24.636`.
3. Confirm `vms/vms-build.txt` starts with `0.2.24.636`.

## Public ticket row copy
1. Open an event with General Admission plus approved/free ticket types such as Veteran Admission, Police/Fire/EMT Admission, and Public School Teacher Admission.
2. Confirm approved/free rows do **not** display `Qualified ticket.`.
3. Confirm approved/free rows do **not** display `Submit verification before checkout` or any other row-level wording that suggests approval happens during checkout.
4. Confirm short row copy is plain-language, for example:
   - `Free with approved Veteran verification. Already approved? Select your ticket here.`
   - `Free with approved responder verification. Already approved? Select your ticket here.`
   - `Free with approved teacher verification. Already approved? Select your ticket here.`

## First-time customer help disclosure
1. Confirm each approved/free ticket row shows a collapsed `First time? More info` disclosure.
2. Expand the disclosure and confirm it explains:
   - create or sign in to an account;
   - submit the relevant verification;
   - approval is often completed quickly;
   - return to the event after approval and select the free ticket.
3. Confirm the verification CTA appears when the verification URL is available.
4. Confirm the disclosure is inside the related ticket row, not a global help box.
5. Collapse and re-expand the disclosure, then change quantities; confirm the disclosure does not duplicate itself or copy the expanded text into the short row description.

## Existing enforcement regression
1. Logged-out customer selects one approved/free ticket and clicks Add items to cart.
   - Expected: existing login/register/verification enforcement still blocks cart add when required.
2. Logged-in but unapproved customer selects one approved/free ticket and clicks Add items to cart.
   - Expected: existing verification-required enforcement still blocks cart add.
3. Logged-in approved customer selects an allowed quantity.
   - Expected: self-allowed quantity works as before.
4. Approved customer selects more than their own allowance when registered guest assignment is enabled.
   - Expected: registered guest email panel appears only after quantity selection and still validates approved guest emails.
5. Attempt to continue with missing required approved guest emails.
   - Expected: customer-facing error uses cart/add wording, not verification-before-checkout wording.

## Progressive UI regression
1. Confirm General Admission and all approved/free admission rows remain in the same Tickets section.
2. Confirm Amenities remains collapsed by default and opens normally.
3. Confirm mobile/tablet layout remains compact: no extra indentation, no duplicate help blocks, and no overflow from the new disclosure.
