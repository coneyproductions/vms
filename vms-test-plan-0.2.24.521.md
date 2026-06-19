# VMS Test Plan — 0.2.24.521 Cancelled Public Event Reschedule Messaging

## Goal
Verify that cancelled public TEC event pages switch to reschedule messaging and link visitors to the replacement event when a live linked replacement exists.

## Primary path
1. Use a cancelled Event Plan that already has a linked replacement Event Plan with a live public TEC event URL.
2. Open the original cancelled **public** TEC event page.
3. Confirm the public banner title reads **Event Rescheduled**.
4. Confirm the banner includes a prominent **View New Date** link.
5. Click **View New Date** and confirm it opens the replacement public event page.
6. Return to the cancelled event page and confirm the featured-image overlay reads **Rescheduled**.
7. Confirm ticket / RSVP purchase UI is still suppressed on the cancelled page.

## Fallback path
1. Open a cancelled public TEC event page that does **not** have a live linked replacement event URL.
2. Confirm the banner still reads **Event Cancelled**.
3. Confirm there is no misleading **View New Date** link.
4. Confirm the featured-image overlay still reads **Cancelled**.

## Report back
Report PASS or FAIL only, with brief evidence.
