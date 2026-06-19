# VMS 0.2.24.682 Test Plan — Payment Gateway Health

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.682`.
3. Confirm `VMS_VERSION` reports `0.2.24.682`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.682`.

Expected: all markers match `0.2.24.682`.

## B. Healthy Checkout Baseline

Use a site where WooCommerce checkout works and Square is intentionally connected.

1. Open `VMS > Ticket Integrity`.
2. Confirm the new **Payment Gateway Health** section is visible.
3. Confirm **Checkout Methods** is greater than `0`.
4. Confirm Square shows:
   - plugin active
   - gateway enabled
   - connection present
   - environment `Production` on a live site or `Sandbox` on a non-production site
5. Confirm the status badge is `OK`.

Expected: the panel reports at least one available payment method and no critical incident.

## C. Square Disconnect / No Checkout Methods

Use a staging/local copy only.

1. Disconnect Square or temporarily remove its active access token/location configuration.
2. Ensure no other payment gateway is left available at checkout.
3. Run the scheduled check or open Ticket Integrity after the stored health result is stale.
4. Confirm the Payment Gateway Health status changes to `CRITICAL`.
5. Confirm the message reads `No payment methods are currently available at checkout.` when WooCommerce returns zero available methods.
6. Confirm a VMS admin error notice appears.
7. Confirm the Ticket Integrity menu badge turns red.

Expected: checkout failure becomes visible as a critical payment incident.

## D. Square Disabled While It Is the Only Method

Use a staging/local copy only.

1. Leave WooCommerce Square installed.
2. Disable the Square card gateway.
3. Leave no other enabled payment gateway as fallback.
4. Refresh the Payment Gateway Health result.

Expected: status is `CRITICAL` and the diagnostics note that Square is disabled with no fallback payment method.

## E. Live Site Using Sandbox

Validate on a safe test copy that reports `production` from `wp_get_environment_type()`.

1. Put Square into Sandbox mode.
2. Refresh Payment Gateway Health.

Expected: status is `CRITICAL` and the Square connection check reports that a live site is using Sandbox.

## F. Apple Pay Registration Failure

Use a staging/local copy only.

1. Leave normal card payments available.
2. Force or simulate `apple_pay_domain_registered = no` with `apple_pay_domain_registration_attempted = yes`.
3. Refresh Payment Gateway Health.

Expected: the Apple Pay check reports `WARNING`, while overall status remains `WARNING` or `OK` unless card payments are also unavailable.

## G. Incident Memory / Recovery

1. Trigger a critical payment incident.
2. Confirm **Incident Memory** shows the first-detected timestamp and failed checks.
3. Restore the payment configuration so checkout returns to normal.
4. Refresh the health check.

Expected: status returns to `OK`, the active incident clears, and the most recent resolved incident remains visible in the panel/report.

## H. Scheduled Check / Daily Report

1. In Ticket Integrity settings, enable scheduled payment gateway health checks.
2. Test both cadence options:
   - `Every 15 minutes`
   - `Hourly`
3. Send **State of the Range Now**.
4. Confirm the email includes a **Payment Gateway Health** section with:
   - status
   - last checked timestamp
   - available checkout methods
   - Square mode/connection summary
   - active or most recent incident memory

Expected: cron scheduling updates cleanly and the daily report includes the new payment-health summary.

## I. Syntax Checks

Run:

```bash
php -l vendor-management-system.php
php -l includes/core/registry/constants.php
php -l includes/ticketing/ticket-integrity-payment-gateway-health.php
php -l includes/ticketing/ticket-integrity-monitor.php
php -l includes/ticketing/ticket-integrity-daily-report.php
php -l includes/ticketing/ticket-integrity-cron.php
php -l includes/admin/ticket-integrity-page.php
```

Expected: all commands pass.
