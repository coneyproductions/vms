# Codex Handoff — VMS 0.2.24.710

## What changed

- Implemented Vendor onboarding Phase 2.
- Vendor applications now use email confirmation as the review-queue gate instead of dropping every raw submit directly into the operator pending pool.
- Confirmed applicant emails now resolve to a canonical website account:
  - existing WP user by email is attached
  - otherwise a WP user is created only after confirmation
- Added a native confirmation-token table, resend throttles, a public confirmation endpoint, portal applicant states, and confirmation-aware review filters/counts.
- Approval still uses the existing vendor profile create/sync flow, but approved response emails are now blocked when the confirmed website account cannot be linked safely.

## Intentionally not changed

- No new `_vms_app_status` values were added.
- No change to the existing operator disposition model:
  - `pending`
  - `holding`
  - `approved`
  - `rejected`
- No new WP vendor role was introduced.
- No change to the core vendor profile create/sync source-of-truth functions.
- No global customer/admin login redirect change was introduced beyond the already-shipped Vendor Portal-origin behavior.

## Key implementation notes

- New runtime lives primarily in `includes/core/vendor-application-confirmation.php`.
- New DB table:
  - `{$wpdb->prefix}vms_vendor_app_confirm_tokens`
- Canonical resolved applicant user still lands on:
  - `_vms_app_submitted_user_id`
- Review-queue gating uses confirmation meta, not new statuses.
- The confirmation route is GET-only for mutation; HEAD requests are explicitly non-mutating.
- The kill switch is filter/constant driven through:
  - `vms_vendor_app_confirmation_bypass_enabled`
  - `VMS_VENDOR_APP_CONFIRMATION_BYPASS`

## Local verification performed

- `php -l` passed for the edited PHP files.
- Live local HTTP checks confirmed:
  - confirmation-pending page copy and resend form
  - expired confirmation screen and resend path
  - confirmation success screen copy
  - HEAD requests to confirmation URLs do not consume tokens
- Local WordPress runtime harnesses confirmed:
  - anonymous submit stays `unconfirmed`
  - same-email logged-in submit auto-confirms
  - different-email logged-in submit still requires confirmation
  - existing customer / vendor-linked / unlinked WP users are reused on confirm
  - no duplicate WP user is created for existing-email confirms
  - resend cooldown / daily cap / IP throttle all work
  - invalid / expired / replayed token handling works
  - queue counts/filtering separate review-ready vs awaiting/expired confirmation
  - edit-screen approval is blocked while unconfirmed
  - legacy admin-post approve/reject leave unconfirmed apps in `pending`
  - confirmed approval creates the vendor profile and canonical vendor-user link
  - approved email is sent only when the link/user state is valid
  - Vendor Portal applicant states render correctly
  - vendor-only Woo My Account notice and portal-origin redirect behavior still work
  - legacy pending/approved applications backfill safely to confirmed state
  - bypass kill switch makes new submissions review-ready immediately

## Follow-up notes

- I did not package the plugin or deploy anything in this step.
- The local site still emits large third-party deprecation noise and heavy CLI boot cost from Event Tickets / TEC on PHP 8.3+, so the reliable Phase 2 verification path was:
  - direct PHP boot against the Local MySQL socket
  - targeted runtime harnesses
  - live local HTTP spot checks
