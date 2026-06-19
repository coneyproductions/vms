# VMS Test Plan — 0.2.24.506

## Scope
Vendor-default drift visibility + explicit apply action in Event Plan Compensation on top of 0.2.24.505.

## Verify
1. Open an Event Plan for a vendor that already has Draft Pay saved.
2. Change that vendor's default compensation on the vendor profile and save the vendor.
3. Re-open the same Event Plan.
4. Confirm **Primary Vendor Compensation** now shows a warning card when the live vendor default differs from Draft Pay.
5. Confirm the card shows both:
   - **Live Vendor Default**
   - **Current Draft Pay**
6. Click **Apply current Primary Vendor default to Draft Pay**.
7. Confirm Draft Pay updates to the vendor's current default for that venue/date context.
8. Save/reload and confirm the drift warning disappears once Draft Pay matches the live vendor default again.
9. Confirm layout, save, reload, lock-pay controls, and compensation tiles still behave normally.
10. Check `debug.log` if enabled for any new `event-plans.php` or `compensation.php` warnings/fatals.

## Guardrails
- No CSS changes
- No layout-shell changes
- No silent overwrite of Draft Pay
- Apply action only works when a real live Primary Vendor default exists
