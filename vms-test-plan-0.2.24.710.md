# VMS 0.2.24.710 Test Plan — Vendor Onboarding Phase 2

## Pre-checks

1. Activate VMS `0.2.24.710`.
2. Confirm version markers:
   - plugin header shows `0.2.24.710`
   - `VMS_VERSION` is `0.2.24.710`
   - `vms/vms-build.txt` begins with `0.2.24.710`
3. Confirm the confirmation-token table exists:
   - `wp_vms_vendor_app_confirm_tokens`

## Submission and confirmation gating

1. Submit a new application anonymously with an email that has no WP user.
2. Confirm the application record is created with:
   - `_vms_app_status = pending`
   - `_vms_app_confirmation_state = unconfirmed`
   - no `_vms_app_submitted_user_id`
3. Confirm the applicant sees the confirmation-pending screen.
4. Confirm the confirmation email plainly says:
   - confirm your email to submit your vendor application for review
   - the application will not be reviewed until this step is complete
5. Confirm the application is not counted in the normal pending review queue yet.

## Existing-account resolution

1. Submit with an email that already belongs to a logged-out WP user.
2. Confirm no duplicate WP user is created.
3. Click the confirmation link.
4. Confirm the existing WP user is attached to `_vms_app_submitted_user_id`.

Repeat the same check for:

1. an existing customer account
2. an existing vendor-linked account
3. an existing unlinked account

## Logged-in submitter behavior

1. Sign in as a user and submit an application with the same email as the current account.
2. Confirm the application is immediately:
   - `confirmed`
   - review-ready
   - linked to the current WP user
3. Confirm the flow skips the applicant confirmation email and goes straight to the success/review-ready state.

1. Sign in as a user and submit an application with a different email.
2. Confirm the submitted email wins.
3. Confirm the current logged-in user is not attached.
4. Confirm the application stays `unconfirmed` until that submitted email is confirmed.

## Duplicate handling

1. Re-submit the same email + business while an unconfirmed application exists.
2. Confirm no new application is created.
3. Confirm the user is routed back to the confirmation-pending state.

1. Re-submit the same email + business while a confirmed pending application exists.
2. Confirm no new application is created.
3. Confirm the user is routed to the existing pending status guidance.

Repeat the same check for:

1. confirmed `holding`
2. confirmed `approved`

1. Submit the same email with a different business name.
2. Confirm a new application is allowed.

## Confirmation endpoint and token states

1. Open a valid confirmation URL.
2. Confirm the screen says:
   - email confirmed
   - application is now ready for review
   - this does not mean approved yet
3. Confirm the application becomes:
   - `confirmed`
   - review-ready
   - linked to the resolved WP user

1. Re-open the same token after it has been used.
2. Confirm the already-confirmed guidance appears.

1. Force or wait for token expiration.
2. Confirm the expired screen appears with a resend path.

1. Use an invalid token.
2. Confirm the request fails safely.

1. Issue a `HEAD` request to the confirmation URL.
2. Confirm the token is not consumed.

## Resend and rate limits

1. Request a resend immediately after a confirmation email is sent.
2. Confirm the cooldown warning appears.

1. Force the daily send count to the cap and request another resend.
2. Confirm the daily-cap warning appears.

1. Force the per-IP send cap and request another resend.
2. Confirm the IP-throttle warning appears.

## Queue and admin behavior

1. Confirm `vms_vendor_app_count_pending()` counts only review-ready applications.
2. Confirm the approvals queue vendor summary excludes unconfirmed apps.
3. Confirm Applications admin exposes:
   - `Ready for Review`
   - `Awaiting Email Confirmation`
   - `Confirmation Expired`
4. Confirm unconfirmed applications cannot be approved, held, or rejected from the edit screen.
5. Confirm unconfirmed applications cannot be approved or rejected through the legacy admin-post handlers.

## Approval finalization

1. Approve a confirmed pending application with a resolved WP user.
2. Confirm approval:
   - creates or syncs the vendor profile
   - creates the canonical vendor-user link
   - allows the approved email
3. Confirm the approved email includes:
   - Vendor Portal URL
   - My Account warning/guidance
   - linked login identity
   - password-reset guidance when a user is linked

1. Approve a confirmed application that still has no valid resolved WP user.
2. Confirm the vendor profile is created if possible.
3. Confirm the approved email is blocked.
4. Confirm operator repair guidance is surfaced.

## Vendor Portal / My Account states

1. Sign in as a user with an unconfirmed applicant record and no active vendor link.
2. Confirm the portal shows:
   - `Confirm your email before we can review your application`
   - resend CTA

1. Sign in as a user with a confirmed pending applicant record and no active vendor link.
2. Confirm the portal shows:
   - `Application pending review`

1. Sign in as a user with an active vendor link.
2. Confirm the normal Vendor Portal renders instead of the applicant panels.

1. Open WooCommerce My Account as a vendor-linked user.
2. Confirm the vendor notice appears.

1. Open WooCommerce My Account as a non-vendor user.
2. Confirm the vendor notice does not appear.

## Backfill and rollback

1. Verify a pre-Phase-2 pending application with no confirmation meta remains visible in the normal pending queue.
2. Verify a pre-Phase-2 approved application remains approved after backfill.
3. Enable the confirmation-bypass kill switch locally.
4. Submit a new application and confirm it goes straight to review-ready without applicant confirmation.
