# VMS Data Tools 0.5.47 — Admin Memory Containment

## Purpose

Contain admin-memory spikes on Data Tools pages by stopping large row-level report sections from rendering automatically, paging the tables that do render, and leaving request traces when DT home/report pages are loaded.

## Changes

- Added DT fingerprint capture for the Data Tools home page so `page=vms-data-tools` loads are no longer invisible unless they are already slow.
- Added `[VMS DT TRACE]` request traces for:
  - single-event page render
  - ticket-pace page render
  - single-event Square fetch audit
  - single-event supporting-data sections
- Deferred the largest single-event row-detail sections by default:
  - Square fetch audit
  - supporting-data row tables
- Added explicit “Load row detail” / “Hide row detail” gates so passive admin browsing no longer dumps all raw rows into one response.
- Added pagination to large DT row tables:
  - single-event online tickets
  - single-event add-ons
  - door ticket support
  - on-site non-ticket rows
  - held Square rows
  - labor budget rows
  - ticket-pace daily rows
  - single-event Square audit tables
- Added memory-pressure bailouts so DT shows an admin notice instead of continuing to render more large row tables once PHP memory usage is already high.
- Added a small request-memory cache for ticket-pace rows.
- Cleared the existing PHP 8.4-style nullable-array deprecation warnings in `page-reporting-module.php`.

## Files changed

- `vms-data-tools.php`
- `includes/admin/menu.php`
- `includes/admin/page-reporting-module.php`
- `vms-build.txt`
- `docs/BUILD-NOTES-0.5.47.md`
- `docs/TEST-PLAN-0.5.47.md`

## Pairing note

Use this DT build with VMS core `0.2.24.688` so the DT traces can land in the shared VMS resource fingerprint log and the admin-side heavy-work guards are active at the same time.
