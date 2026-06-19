# VMS 0.2.24.687 Test Plan — Chrome Mobile Checkbox Add-Ons

## A. Version Markers

1. Upload/activate the package as the canonical `vms` plugin folder.
2. Confirm the active plugin header reports `0.2.24.687`.
3. Confirm `VMS_VERSION` reports `0.2.24.687`.
4. Confirm `/wp-content/plugins/vms/vms-build.txt` returns `0.2.24.687`.

Expected: all markers match `0.2.24.687`.

## B. Checkbox Add-On Toggle on Chrome Mobile

Use an event built from the current ticketing template where at least one reserved add-on uses the checkbox selector mode.

1. Open the event page in Chrome mobile.
2. Add the qualifying number of tickets.
3. Tap the checkbox add-on once.

Expected: the checkbox stays selected after one tap and does not blink on and off.

## C. Checkbox Unselect on Chrome Mobile

1. Tap the same checkbox add-on again.

Expected: the checkbox cleanly unselects on the second tap.

## D. Desktop Regression Check

1. Open the same event page on desktop.
2. Tap/click the checkbox add-on on and off.

Expected: desktop mouse interaction still behaves normally.

## E. Render-Mode Coverage

1. Test one event using the current main VMS front-end bundle path.
2. Test one event that still uses the inline server-controls path, if applicable.

Expected: checkbox add-ons behave the same in both render paths.

## F. Syntax Checks

Run:

```bash
node --check assets/vms-ticketing-front.js
php -l includes/integrations/ticketing-rules-v2.php
```

Expected: all commands pass.
