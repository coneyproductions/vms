# VMS Test Plan — 0.2.24.544

## Focus
Production-safe timeout pass for Ticketing v2 Save + Preview.

## Test
1. Open an Event Plan with Ticketing v2 enabled.
2. Click **Preview sync** on the production site.
3. Confirm the browser does not fail at the old 20 second mark.
4. Confirm Preview completes and renders results normally.
5. Confirm **Commit sync** still works normally.
6. Confirm local checkbox-style add-ons still render as native checkboxes on the public site.
