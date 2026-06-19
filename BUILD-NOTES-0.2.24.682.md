# VMS 0.2.24.682 — Payment Gateway Health for State of the Range

## Purpose

This build extends Ticket Integrity and the daily State of the Range report with a dedicated payment-health monitor.

The operator need is straightforward: if checkout loses all payment methods, or WooCommerce Square becomes disconnected or misconfigured, VMS should surface that state clearly before revenue is lost.

## Changes

- Adds a new **Payment Gateway Health** section to the Ticket Integrity admin screen.
- Adds a Payment Gateway Health section to the State of the Range email.
- Detects whether WooCommerce currently reports any available checkout payment methods.
- Detects whether WooCommerce Square is active, whether the Square card gateway is enabled, and whether Square appears authenticated/connected with required location data present.
- Detects whether Square is using Production or Sandbox and treats Sandbox on a live/production site as critical.
- Treats Apple Pay domain-registration failure as a warning when regular payment methods are still available.
- Stores last-known payment health, last checked time, active incident state, and the most recent resolved incident in VMS options/results.
- Adds scheduled payment-health checks with configurable `Every 15 minutes` or `Hourly` cadence.
- Raises a visible VMS admin notice and red Ticket Integrity badge when payment health enters a critical state.
- Reuses existing Ticket Integrity email-alert settings for optional critical payment-health emails.

## Files Changed

- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `includes/integrations/load.php`
- `includes/ticketing/ticket-integrity-monitor.php`
- `includes/ticketing/ticket-integrity-payment-gateway-health.php`
- `includes/ticketing/ticket-integrity-daily-report.php`
- `includes/ticketing/ticket-integrity-cron.php`
- `includes/admin/ticket-integrity-page.php`
- `assets/css/admin-ticket-integrity.css`
- `BUILD-NOTES-0.2.24.682.md`
- `vms-test-plan-0.2.24.682.md`
- `docs/05-revision-log.md`

## Validation Performed

- `php -l includes/ticketing/ticket-integrity-payment-gateway-health.php`
- `php -l includes/ticketing/ticket-integrity-monitor.php`
- `php -l includes/ticketing/ticket-integrity-daily-report.php`
- `php -l includes/ticketing/ticket-integrity-cron.php`
- `php -l includes/admin/ticket-integrity-page.php`
- `php -l includes/integrations/load.php`

## Notes / Caveats

- Checkout-availability detection prefers WooCommerce's live available-gateway API and only falls back to enabled-gateway inference if WooCommerce does not return a checkout-context list.
- Square credential and token values are never rendered in the UI or email output; the monitor only reports presence/absence/state.
- Sandbox on non-production environments is treated as informationally healthy; Sandbox on live/production is treated as critical.
