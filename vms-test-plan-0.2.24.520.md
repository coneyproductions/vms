# VMS Test Plan — 0.2.24.520 Cancel/Reschedule Follow-up Fixes

## Goal
Verify that entering a replacement date and clicking **Mark Cancelled** cancels the current Event Plan and immediately creates a linked Draft Event Plan for the new date in the same save.

## Primary path
1. Open a non-cancelled Event Plan with real planning data.
2. In the **Cancellation** section, enter a valid future **Replacement date**.
3. Click **Mark Cancelled** and confirm the prompt.
4. Confirm redirect into a newly created Draft Event Plan.
5. Confirm the new Draft kept the expected planning data:
   - venue
   - event date = replacement date entered
   - start/end times
   - primary vendor
   - secondary vendors if any
   - staffing if any
6. Reopen the source Event Plan and confirm it remains **Cancelled**.
7. Confirm the new Draft shows **Rescheduled from**.
8. Confirm the cancelled source shows a link to the rescheduled draft.

## Fallback path
1. Open an already-cancelled Event Plan.
2. Enter a replacement date.
3. Click **Create Rescheduled Draft**.
4. Confirm that fallback follow-up flow still works.

## Report back
Report PASS or FAIL only, with brief evidence.
