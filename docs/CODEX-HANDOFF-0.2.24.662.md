# Codex Handoff — VMS 0.2.24.662

## Issue

0.2.24.661 added the Event Plan Save Profiler + Module Dirty Map, but the staffing save guards referenced `vms_event_plan_save_profiler_active()` and that helper was missing.

The practical result was diagnostic inaccuracy: a content-only normal WordPress Update correctly avoided Ticket Integrity and ticket changes, but the save profile still reported staffing heavy work as triggered.

## Change

Added the missing helper in `includes/core/event-plan-save-profiler.php`:

```php
function vms_event_plan_save_profiler_active(): bool
{
    $state = vms_event_plan_save_profiler_state();
    return !empty($state['active']);
}
```

Version markers were bumped to `0.2.24.662`.

## Expected behavior

For a content-only normal Event Plan Update:

- save succeeds
- profile records `core_wp_update`
- changed modules show Core only
- Ticket Integrity is skipped
- Staffing rollup dirty is skipped
- Staffing template seed is skipped unless staffing or relevant context keys changed
- no Ticket Integrity cron/audit/queue growth occurs

For a real Ticketing V2 Save Config:

- ticket module work is recognized
- profile may record `module_meta_update` with Tickets & Add-ons changed
- Preview Sync still works

## Known follow-up

A title-change save can still be noisier than desired because unrelated vendor/finance meta may be rewritten during the editor save. That should be reviewed later as profile-polish or save-side meta-write reduction, but it does not block the 0.2.24.662 repair.
