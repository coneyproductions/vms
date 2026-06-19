# VMS 0.2.24.707 — Event Plan Featured Image Save Scope Without Trace Dependency

## What changed

- Decoupled Event Plan save-scope classification and heavy-branch skip logic from `VMS_EP_PERF_TRACE` so production save behavior no longer depends on diagnostics being enabled.
- Kept `_thumbnail_id` preservation, `featured_image_only` classification, and downstream scope checks active at runtime in all environments.
- Moved only persisted profile/history recording behind a diagnostics-only gate, so tracing still controls saved instrumentation but not the actual save path.
- Stopped the lineup save block from fabricating fresh primary lineup row IDs when no lineup-related request inputs were posted, which removed the last false-positive `mixed` classifications on ordinary editor saves.

## Production interpretation notes

- `VMS_EP_PERF_TRACE` should now affect only diagnostic persistence, not save behavior.
- A published Event Plan featured-image-only change should stay on the narrow image-sync path even when tracing is disabled or undefined.
- Linked TEC featured-image sync still runs on the early `_thumbnail_id` meta path and the later `save_post_vms_event_plan` image sync pass now skips when that earlier sync already completed.
- The production queues previously observed on image-only saves, `vms_event_plan_calendar_maintenance` and `vms_staffing_seed_event_slots_queued`, are now explicitly covered by the trace-independent save-scope gating.

## Verification summary

- Local fixture:
  - Event Plan `2163`
  - linked TEC event `2164`
  - original thumbnail `202`
  - temporary thumbnail `2084`
- Tracing disabled before bootstrap:
  - no-op save: `save_scope = no_op`
  - content save: `save_scope = mixed`
  - featured-image save: `save_scope = featured_image_only`
  - featured-image restore: `save_scope = featured_image_only`
  - no queue meta additions
  - no duplicate cron rows
  - no Action Scheduler rows
  - no ticket/product sync writes
  - linked TEC image sync ran once from `thumbnail_meta`
- Tracing enabled:
  - runtime save scopes matched the tracing-disabled run
  - saved `save_scope` matched runtime values
  - saved `featured_image_sync` showed one completed thumbnail-meta sync only for image-only saves
- Public smoke after the temporary image flip confirmed the updated image appeared on:
  - `https://serenaderange.local/?vms_event_plan=vms-700-slider-control-event-700slidercontrol1780268744`
  - `https://serenaderange.local/event/vms-700-slider-control-event-700slidercontrol1780268744/`
  - `https://serenaderange.local/`
  - `https://serenaderange.local/events-calendar/`

## Version markers updated

- Plugin header: `0.2.24.707`
- `VMS_VERSION`: `0.2.24.707`
- `vms-build.txt`: `0.2.24.707`
- Build notes: `BUILD-NOTES-0.2.24.707.md`
- Test plan: `vms-test-plan-0.2.24.707.md`
- Package slug: `vms-0.2.24.707.zip`
