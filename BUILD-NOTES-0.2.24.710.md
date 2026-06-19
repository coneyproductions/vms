# VMS 0.2.24.710

## Scope

- Vendor onboarding Phase 2.
- Adds email-confirmation gating and confirmed-account resolution for vendor applications.
- Preserves the existing operator status model and vendor approval/profile sync flow.
- Does not package or deploy anything in this build step.

## What changed

- Added a native confirmation-token table for vendor applications:
  - `{$wpdb->prefix}vms_vendor_app_confirm_tokens`
  - hash-only token storage
  - expiration, invalidation, consume audit fields
- Added confirmation-state meta on vendor applications:
  - `_vms_app_confirmation_state`
  - `_vms_app_email_confirmed_at`
  - `_vms_app_review_ready_at`
  - `_vms_app_confirmation_last_sent_at`
  - `_vms_app_confirmation_send_count`
  - `_vms_app_confirmation_source`
  - supporting lookup / notification fields
- Added a public confirmation endpoint at `/vendor-application-confirm/` with:
  - `nocache_headers()`
  - `noindex`
  - GET-only confirmation handling
  - HEAD requests treated as non-mutating
- Changed application submit behavior:
  - anonymous or mismatched-email submits now save as `pending + unconfirmed`
  - operator review notification is deferred until the email is confirmed
  - logged-in same-email submits are auto-confirmed and move straight into the real review queue
  - a documented kill switch can bypass confirmation and auto-confirm new apps
- Added account resolution on confirmation:
  - existing WP user by email is attached
  - otherwise a new WP user is created only after confirmation
  - resolved user ID is stored on `_vms_app_submitted_user_id`
- Added resend controls and throttles:
  - 10-minute resend cooldown
  - 5 sends per 24 hours per application
  - per-IP resend throttle
  - failed-confirmation attempt throttle
- Added duplicate handling for same email + business:
  - unconfirmed duplicate routes back to confirmation pending
  - confirmed pending routes to existing pending status
  - holding routes to existing holding status
  - approved routes to existing approved/support guidance
- Changed queue/admin behavior:
  - `vms_vendor_app_count_pending()` now counts only review-ready applications
  - approvals queue vendor summary excludes unconfirmed apps
  - Applications admin now exposes:
    - `Ready for Review`
    - `Awaiting Email Confirmation`
    - `Confirmation Expired`
  - operator approve/reject/hold actions are blocked until confirmation
- Added applicant state rendering in the Vendor Portal:
  - `Confirm your email before we can review your application`
  - `Application pending review`
- Hardened approval finalization:
  - approval still uses the existing vendor profile create/sync functions
  - canonical link creation still uses `vms_vendor_user_link_upsert()`
  - approved response email is blocked if no valid confirmed website account can be linked
- Added legacy backfill:
  - pre-Phase-2 applications get `confirmed` confirmation state
  - existing pending/approved applications remain usable
  - no retroactive WP user creation for old records

## Files changed

- `includes/core/vendor-application-confirmation.php`
- `includes/core/load.php`
- `includes/core/plugin.php`
- `includes/core/registry/constants.php`
- `includes/core/registry/meta-keys.php`
- `includes/db/migrations.php`
- `includes/activation.php`
- `includes/admin/approvals-review-queue.php`
- `includes/portal/vendor-portal.php`
- `includes/vendor-applications.php`
- `assets/css/vms-portal.css`
- `vendor-management-system.php`
- `vms-build.txt`
- `docs/05-revision-log.md`
- `BUILD-NOTES-0.2.24.710.md`
- `vms-test-plan-0.2.24.710.md`
- `docs/CODEX-HANDOFF-0.2.24.710.md`

## Local verification summary

- `php -l` passed for:
  - `includes/core/vendor-application-confirmation.php`
  - `includes/vendor-applications.php`
  - `includes/portal/vendor-portal.php`
  - `includes/admin/approvals-review-queue.php`
- Live local HTTP checks confirmed:
  - `/vendor-application/?vms_app=confirm_pending&...` shows the new confirmation-gated pending screen
  - expired confirmation state shows the resend path
  - `/vendor-application-confirm/?token=...` shows the ready-for-review success screen
  - `HEAD` requests to the confirmation URL do not consume the token
- Local WordPress runtime harnesses confirmed:
  - anonymous submit creates `pending + unconfirmed`, sends a confirmation email, and does not assign a WP user yet
  - logged-in same-email submit auto-confirms, stores `_vms_app_submitted_user_id`, and notifies review-ready
  - logged-in different-email submit stays unconfirmed and requires email confirmation
  - confirmation attaches existing customer, vendor-linked, and unlinked WP users by email without duplicates
  - new WP user creation happens only on confirmation for new-email applicants
  - resend cooldown, daily cap, and IP throttle all fire correctly
  - invalid, expired, and replayed tokens are handled correctly
  - pending counts and review filters separate review-ready vs awaiting/expired confirmation states
  - Applications edit-screen approval is blocked while unconfirmed
  - legacy admin-post approve/reject paths leave unconfirmed apps in `pending`
  - confirmed approvals create the vendor profile, create the canonical vendor-user link, and allow the approved email
  - approvals with no confirmed website account block the approved email
  - Vendor Portal applicant panels show the correct unconfirmed and pending-review states
  - vendor-only Woo My Account notice and portal-origin login redirect behavior still work
  - legacy pending/approved applications backfill to confirmed state without status regression

## Package

- No package built in this step.
