# VMS 0.2.24.643 Test Plan — Progressive Add-ons Logged-Out Visibility

## Goal
Confirm the Progressive ticket UI shows the optional add-on / amenities section to logged-out public visitors when enabled add-ons exist, without weakening qualification rules.

## Tests

1. Deploy the zip and confirm `/wp-content/plugins/vms/vms-build.txt` reports `0.2.24.643`.
2. Open an event with GA tickets and reserved add-ons in a private/incognito window while logged out.
3. Confirm the ticket section renders.
4. Confirm the add-on section heading renders, using the configured/default text such as `Fire Pits & Tables`.
5. Expand the add-on section and confirm the add-on rows/controls are visible.
6. Select fewer qualifying tickets than required and select an add-on; confirm the customer-facing warning still appears and checkout/add-to-cart is not allowed incorrectly.
7. Select enough qualifying GA tickets and one add-on; confirm the cart receives both lines.
8. Log in as admin and confirm the same event still shows the add-on section and help copy.
9. Confirm an event with no configured/mapped add-ons does not show an empty add-on section.

## Regression Notes
- This patch does not remove qualification enforcement.
- This patch only retries/moves the existing server-rendered add-on block into the Progressive section when it mounts later for public users.
