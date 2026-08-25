# Follow-up: completed-event occurrence lock and explicit Reschedule workflow

## Requirement

Once an Event Plan occurrence is completed/past, VMS should lock every field that defines that occurrence, including at minimum:

- event date;
- start and end time;
- venue/location fields that change which occurrence attendees were promised;
- linked TEC occurrence identity.

Ordinary Event Plan save, Publish Now, calendar re-sync, and ticket configuration flows must not use edits to those locked fields to reopen native ticket sales for a completed occurrence.

## Explicit exception: event did not occur

Add a separate, deliberate **Reschedule** workflow for the exceptional case where the dated event did not actually occur. That workflow should:

1. require an authorized operator to confirm that the original occurrence did not happen;
2. preserve an audit link between the original and replacement occurrence;
3. collect the new date/time and require review before publication;
4. define how existing orders, ticket holders, inventory, refunds/transfers, entitlements, reminders, check-in state, and public cancellation/reschedule messaging carry forward;
5. re-derive TEC and ticket sale-window state only after those choices are explicit;
6. avoid silently converting a completed event into a new sale.

## Current boundary

The date-synchronization repair intentionally supports legitimate Event Plan date/time changes made before the linked occurrence closes. It deliberately skips automatic ticket-window reopening when the previously linked TEC occurrence has already ended. The full field lock and exceptional Reschedule workflow remain follow-up product work.
