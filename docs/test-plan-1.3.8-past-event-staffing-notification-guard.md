# Test Plan 1.3.8 — Past-Event Staffing Notification Guard

## Local qualification

- Existing staffing notification delivery suite: 132 assertions.
- Focused past-event guard regression: 42 assertions.
- Staffing notification deadlock suite: 41 database assertions.
- All email and HTTP transport must remain intercepted.

## Staging acceptance

With staffing notification delivery initially disabled and fail-closed transport
containment active, verify:

- last-month removals and historical assignment corrections create no email;
- an event ended earlier today is suppressed;
- future and overnight performances remain eligible until their actual end;
- missing or invalid event timing fails closed;
- queued or failed work becomes terminally skipped after the event ends;
- cron and manual retries do not resend suppressed work;
- upcoming cancellation, cutover, duplicate suppression, and authenticated
  assignment links remain correct.

Remove only identified synthetic fixtures, restore the notification activation
option to absent, verify no pending synthetic work, and remove temporary
containment only after restoration while preserving the permanent staging mail
guard.
