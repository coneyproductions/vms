# VMS 0.2.24.660 — Event Plan Lightweight Update Guard

## Purpose

This build repairs the 0.2.24.659 staging smoke-test failure found by Codex: a content-only WordPress **Update** on a published Event Plan still queued Ticket Integrity work even though no ticket config/sync data changed.

The patch keeps the new Event Module Hub direction, but tightens the save-path behavior so normal Event Plan editor saves act more like lightweight shell saves.

## What changed

### Ticket Integrity queue guard

Normal WordPress editor saves for `vms_event_plan` no longer queue a Ticket Integrity spot scan by default.

Ticket Integrity still queues for meaningful ticket/event risk paths:

- explicit VMS actions such as **Publish Now**, cancellation, and live-refund actions
- WordPress publish transitions already handled by the publish transition watcher
- ticketing config/sync meta changes watched by the ticketing meta watcher
- nightly Ticket Integrity scans

A filter escape hatch was added for legacy installs/custom code that intentionally want the old behavior:

```php
add_filter('vms_ticket_integrity_queue_on_general_plan_save', '__return_true');
```

### Event Plan review no-op warning guard

The review/change display now filters no-op stored changes where the resolved labels are the same, such as:

```text
Secondary vendor type changed from Food Truck to Food Truck
```

This applies both when building new change payloads and when reading older stored change payloads for display, so stale no-op warnings should stop showing in the Event Module Hub / Needs Review surfaces.

## Files changed

- `includes/ticketing/ticket-integrity-cron.php`
- `includes/core/event-plan-review.php`
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `BUILD-NOTES-0.2.24.660.md`
- `vms-test-plan-0.2.24.660.md`

## Build discipline

Version bumped consistently to `0.2.24.660` in:

- plugin header
- `VMS_VERSION`
- `vms-build.txt`
