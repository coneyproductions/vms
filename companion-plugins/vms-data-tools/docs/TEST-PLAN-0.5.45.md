# VMS Data Tools 0.5.45 Test Plan

## A. Version and folder sanity
1. Install as the canonical `vms-data-tools` plugin folder.
2. Confirm `wp-content/plugins/vms-data-tools/vms-build.txt` shows `0.5.45`.
3. Confirm no parallel folder such as `vms-data-tools-1`, `vms-data-tools-0.5.44`, or another copy is active.

## B. Activation safety
1. Activate VMS Core first.
2. Activate VMS Data Tools.
3. Confirm there is no fatal error.
4. If an admin notice says another copy is active, deactivate/delete the duplicate Data Tools copy, then activate the canonical `vms-data-tools` copy.

## C. Event Profitability ticket totals
1. Open **Data Tools → Event Profitability**.
2. Confirm The Tuxedo Cats still shows the expected paid/free/total ticket values and ticket sales.
3. Confirm cancelled events still show the cost-review warning when estimated costs remain.

## D. Labor overhead fallback
1. Open an Event Plan with an hourly assigned staff row such as Sound Engineer.
2. Test a row with only one side of the shift time filled where VMS can infer a usable window from event/duration fallback.
3. Confirm Event Profitability includes the labor cost instead of showing zero/excluding it.
4. Confirm the labor evidence table shows a review note explaining the inferred shift window.
5. Test a truly unresolved hourly row with no usable duration/window and confirm it is still excluded with a clear reason.

## E. Regression checks
1. Open Reporting Module, Event Profitability, Single Event Detail, Ticket Pacing, and any Square-connected report normally used on staging.
2. Confirm no fatal errors or duplicate menu/nav stacks are introduced.
