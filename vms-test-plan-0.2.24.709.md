# VMS 0.2.24.709 Test Plan — Vendor Onboarding Phase 1 UX

## Pre-checks

1. Install or activate VMS `0.2.24.709`.
2. Confirm version markers:
   - plugin header shows `0.2.24.709`
   - `VMS_VERSION` is `0.2.24.709`
   - `vms/vms-build.txt` begins with `0.2.24.709`

## Vendor Portal logged-out entry

1. Open the public Vendor Portal page while logged out.
2. Confirm the entry screen shows two distinct paths:
   - `Approved Vendor Login`
   - `Apply for Vendor Access`
3. Confirm the copy makes these points clear:
   - login is for already-approved vendors
   - new applicants should apply first
   - approval is manual and not instant
4. On desktop-width viewports, confirm the two cards render side-by-side.
5. On mobile-width viewports, confirm the cards stack vertically and remain readable.
6. Confirm the apply button points to the current vendor application page.
7. Confirm the login form still works for existing approved vendors and keeps the forgot-password link available.

## Vendor application success state

1. Open the public vendor application page with `?vms_app=success`.
2. Confirm the page shows a thank-you / next-steps panel instead of the old one-line success message.
3. Confirm the copy explains:
   - the application was received
   - review is manual
   - applicants should watch email plus spam/junk folders
   - Vendor Portal is the vendor workspace, not WooCommerce My Account
4. Confirm this pass does **not** claim that email confirmation is required.

## Application response email guidance

1. On an approved vendor application, open the operator response box and leave the default approved message.
2. Trigger a local-only safe send path or inspect the generated body through a local harness before mailing a real vendor.
3. Confirm the approved email includes:
   - a direct Vendor Portal URL
   - login email / username when a linked WP user exists
   - password-reset guidance when available
   - reply-for-linking guidance when no linked user exists
   - explicit note that vendor tools live in the Vendor Portal, not WooCommerce My Account

## WooCommerce My Account guidance

1. Sign in as a user with active `vms_vendor_user_links` access and open WooCommerce My Account.
2. Confirm a notice appears with:
   - `Looking for your Vendor Portal?`
   - a direct `Open Vendor Portal` button
3. Confirm normal customer My Account content still renders below it.
4. Sign in as a non-vendor user and confirm the vendor notice does not appear.

## Login redirect safety

1. Log out, then log in through the Vendor Portal login form as a non-admin user with active vendor links.
2. Confirm the user lands at the Vendor Portal dashboard instead of WooCommerce My Account.
3. Confirm a normal WooCommerce customer login flow still lands in My Account.
4. Confirm administrators are not forcibly redirected away from their requested destination.

## Regression checks

1. Confirm existing approved vendor users can still open the Vendor Portal after sign-in.
2. Confirm the vendor application form still submits to the existing flow and stores a `vms_vendor_app` record.
3. Confirm no new vendor-application status values were introduced.
4. Confirm no automatic WP user creation occurs on application submit.
