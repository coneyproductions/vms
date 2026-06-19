# VMS 0.2.24.659 — Event Plan Module Hub Groundwork

## Purpose

This build starts the move from a giant all-in-one Event Plan form toward an Event Plan command-center model.

The Event Plan still remains the center of the event, but this pass adds a read-only **Event Module Hub** metabox that keeps high-value module visibility on the Event Plan while giving operators direct links to manage the heavier workspaces.

## What changed

### Event Plan editor

Added a new **Event Module Hub** metabox on `vms_event_plan` edit screens.

The hub currently summarizes:

- Core Event Details
- Tickets & Add-ons
- Lineup & Vendors
- Staffing
- Compensation / Finance
- Marketing / Promo

Each module card includes:

- module status chip
- short at-a-glance summary
- warning/action text when available
- manage/edit link to the relevant workspace or Event Plan anchor
- secondary links where useful, such as Ticket Integrity or Social Sharing

### Reused existing Command Center logic

The hub reuses existing Event Command Center formatting/link helpers, but it uses a lighter Event Plan editor payload so simply opening the Event Plan does not run the full Command Center ticket-integrity/staffing deep scan.

This keeps the first implementation lightweight and avoids adding a competing source of truth.

### Styling

Added responsive styling to `assets/css/vms-event-command-center.css` for the Event Plan Module Hub cards.

No inline CSS was added.

## Important scope note

This is **Phase 0 / groundwork**, not the full modular Event Plan refactor.

This build does **not** yet remove heavy fields from the Event Plan form or rewrite save behavior. It creates the visible hub pattern that later patches can use as sections are converted from full editors into summary cards plus dedicated module workspaces.

## Files changed

- `includes/admin/event-command-center.php`
- `assets/css/vms-event-command-center.css`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`

## Build discipline

Version bumped consistently to `0.2.24.659` in:

- plugin header
- `VMS_VERSION`
- `vms-build.txt`

