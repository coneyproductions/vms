# Test Plan — 0.2.24.583 Email Follow-Ups foundation

🚨 **Codex/staging testing required before production confidence.** This build adds customer-email tooling. Automatic scheduled sends are off by default, but manual recipient sends can email real buyers if confirmed.

## Install / version checks
- Install the zip over the current VMS build on staging.
- Confirm WordPress shows VMS version `0.2.24.583`.
- Confirm `vms/vms-build.txt` contains `0.2.24.583`.
- Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.583`.
- Confirm previous 0.2.24.582 GA ticket image repair tools still exist in **VMS > Settings > Ticketing Image Tools**.
- Confirm previous staffing-template per-role threshold UI still exists.

## Navigation checks
- Open **VMS > Marketing & Social** and confirm an **Email Follow-Ups** card appears.
- Open **VMS > Email Follow-Ups** directly from the VMS menu.
- Confirm tabs render: **Overview**, **Templates**, **Preview & Test**, **Logs**.
- Confirm the guided-tour help trigger can find the Email Follow-Ups tour if guided tours are enabled.

## Settings checks
- On **Overview**, confirm MailPoet status renders without fatal errors whether MailPoet is active or inactive.
- Confirm **Automatic scheduled sends** is off by default after install.
- Save settings with automatic sends still off.
- After saving Overview, reopen **Templates** and confirm template enabled/disabled choices did not reset.
- After saving Templates, reopen **Overview** and confirm global module/automatic-send toggles did not reset.
- Reopen the page and confirm settings persist.
- If MailPoet is active, enter a valid MailPoet list ID and enable subscriber sync only on staging.

## Template checks
- Open **Templates**.
- Confirm the token list displays.
- Confirm all four starter templates render and can be saved:
  - Know Before You Go
  - Day-of Reminder
  - Post-Event Thank You
  - Weather / Event Update
- Edit a subject/body in staging, save, and confirm it persists.
- Confirm no inline style blocks or template `style="..."` attributes were added by this patch.

## Preview and recipient discovery checks
- Open **Preview & Test**.
- Select an upcoming published Event Plan with real or staging Woo/TEC ticket sales.
- Confirm the page shows:
  - eligible recipient count
  - net tickets represented
  - send allowed yes/no
  - scheduled timing for scheduled templates
  - rendered subject/body
- Expand the recipient list and confirm buyer emails/order numbers match the expected Woo orders for that Event Plan.
- Confirm refunded/cancelled/zero-net lines are not counted as eligible recipients.
- Confirm unpublished, cancelled, or missing-date Event Plans show a blocked send reason rather than sending.

## Test-send checks
- On **Preview & Test**, send a test email to an admin/staging address.
- Confirm the email arrives with `[TEST]` in the subject.
- Confirm event tokens resolve correctly:
  - event title
  - date
  - gates time
  - start time
  - venue name
  - event URL
- Confirm a log row is created for the test send.

## Guarded manual-send checks
- Attempt manual recipient send without checking the confirmation checkbox.
- Confirm no buyer emails are sent and a warning notice appears.
- On staging only, check the confirmation checkbox and send to eligible recipients for a small fixture event.
- Confirm duplicate protection prevents sending the same template/event/recipient combination twice.
- Confirm log rows show sent/skipped/error statuses clearly.

## Scheduler safety checks
- Confirm automatic scheduled sends remain off unless explicitly enabled.
- Enable automatic sends on staging only, with a fixture event/template whose scheduled time is due within the configured window.
- Trigger WP-Cron or wait for the hourly cron on staging.
- Confirm due scheduled email sends once and duplicate protection prevents repeat sends.
- Turn automatic sends back off before production install unless the recipient preview has been validated.

## Regression checks
- Run a light smoke test on:
  - VMS dashboard loads.
  - Marketing & Social hub loads.
  - Social Sharing page still loads.
  - Event Plan edit screen still loads.
  - Staffing template screen still loads.
  - Ticketing Image Tools page still loads.

## Failure handling
If any customer-email recipient discovery, send guard, duplicate protection, or MailPoet sync behavior fails, do not enable automatic sends. Fix the smallest durable root cause, rerun this test plan, and update all version markers/build notes before returning a replacement zip.
