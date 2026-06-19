## Targeted through 0.2.24.477

- Event Plan editor inline **Start Guided Tour** in **Vendor-Managed Guest Admissions** could still fail silently because the runtime waited for the registered-tour promise to settle before trying the button-provided fallback. The runtime now verifies visible tour chrome shortly after launch and triggers the inline fallback immediately when nothing appeared.

## Resolved in 0.2.24.469

- Event Plan editor (`admin:vms_event_plan`) could still auto-open guided-tour overlays from other screen-bound tours, blocking Save Draft and title controls even after disabling the basic editor tour auto-run. Fixed by making the remaining Event Plan screen tours manual-launch only.

## Resolved in 0.2.24.468
- Event Plan editor guided tour was auto-running on load/reload and could intercept core controls like Save Draft and Auto-update title to Band. The Event Plan editor tour is now manual-launch only; the guided tour remains available from its explicit help trigger instead of hijacking the editor.

## Resolved in 0.2.24.467
- Floating public Help button could appear on frontend/public screens even when that entry point was not appropriate for visitors. The global floating Help button is now admin-only; explicit frontend tour buttons/links remain available where intentionally rendered.

---
title: 02 Bug Log
slug: bug-log
since: 0.2.24.455
---

# Bug Log

Use `docs/bugs.txt` as the canonical long-form bug tracker. This file remains the current sprint / stabilization bug-note layer inside the zip.

Use this as the in-zip working bug ledger.

## Active items

### High priority

- Ticket max-qty semantics drift: `0` must mean unlimited for max-per-order and must not inherit stale caps
- Ticket inventory drift / available-on-hand corruption
- Any remaining guest-list rule confusion between primary vendors and food trucks
- Event Plans regression risk due to file size and mixed UI logic
- Guided-tour behavior should still be watched on other admin fixtures. The Event Plan editor auto-run blocker is addressed in 0.2.24.469, the Vendor Guest inline manual-launch trigger was first hardened in 0.2.24.470, and the runtime launch-timing fallback is tightened again in 0.2.24.475.

### Medium priority

- Dormant/staged modules still living in core paths create operator and developer confusion
- Inline CSS / JS remains in several admin and portal screens
- Some terminology still leans music-specific instead of venue-neutral

## Recently addressed in this build

- Added project tracking docs back into the zip
- Cleaned stale build fallback values in active metadata paths
- Limited the docs admin page include to admin bootstrap only
- Normalized same-directory admin loader paths and removed duplicate loader references

## How to use this file

- Add one bullet per real bug
- Note build introduced / build fixed when known
- Move fixed items to a historical section instead of deleting them outright
