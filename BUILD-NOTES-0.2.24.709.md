# VMS 0.2.24.709

## Scope

- Vendor onboarding Phase 1 UX only.
- No automatic WP user creation.
- No new application statuses.
- No approval/profile creation flow changes.
- No email-confirmation gating.

## What changed

- Reworked the logged-out Vendor Portal entry surface into two explicit paths:
  - `Approved Vendor Login`
  - `Apply for Vendor Access`
- Added responsive portal-auth card styling so the two paths sit side-by-side on desktop and stack on mobile.
- Replaced the old one-line vendor application success flag with a fuller thank-you screen that explains the manual review process, tells applicants to watch email plus spam/junk folders, and points them back to the Vendor Portal as the vendor workspace.
- Upgraded vendor application response guidance for approved applications so outbound copy now includes:
  - direct Vendor Portal URL
  - linked login email / username when available
  - password reset guidance when a linked account exists
  - reply-for-linking guidance when no linked account exists yet
  - explicit warning that vendor tools live in the Vendor Portal, not WooCommerce My Account
- Added a vendor-only notice to WooCommerce My Account with a clear `Looking for your Vendor Portal?` callout and button.
- Added a safe `login_redirect` filter scoped only to Vendor Portal-origin logins, with explicit admin bypass.
- Removed inline styling from the touched public vendor onboarding surfaces and moved the new UI styling into `assets/css/vms-portal.css`.

## Files changed

- `assets/css/vms-portal.css`
- `includes/portal/vendor-portal.php`
- `includes/vendor-applications.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.709.md`
- `vms-test-plan-0.2.24.709.md`
- `docs/CODEX-HANDOFF-0.2.24.709.md`

## Local verification summary

- `php -l` passed for the edited PHP files.
- Public HTTP checks confirmed:
  - the new logged-out Vendor Portal copy is live
  - the apply CTA points to `/vendor-application/`
  - the new success-state copy is live on `?vms_app=success`
- Playwright screenshots confirmed:
  - desktop Portal entry shows two side-by-side cards
  - mobile Portal entry stacks the cards cleanly
- A temporary local-only harness verified through the live local WordPress runtime that:
  - vendor-linked users see the Woo My Account portal notice
  - non-vendor users do not
  - non-admin vendor-linked users redirect to the Vendor Portal after Vendor Portal-origin login
  - admins are not redirected away from their requested destination
  - approved-application guidance contains the Vendor Portal URL and routing instructions

## Package

- Production-bound package slug: `vms-0.2.24.709.zip`
