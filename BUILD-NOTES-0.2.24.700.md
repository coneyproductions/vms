# VMS 0.2.24.700

## Root Cause

- Event Plan publish and explicit Re-sync paths already copied the current Event Plan featured image to the linked TEC event.
- Ordinary later Event Plan updates did not run any generic linked-TEC featured-image reconciliation, so a plan that was first published with only the vendor fallback could stay stuck on that older TEC `_thumbnail_id`.
- The homepage/main slider reads upcoming `tribe_events` thumbnails, so a stale linked TEC thumbnail also kept the slider on the old fallback image.

## Files Changed

- `includes/cpt/event-plans.php`
- `includes/portal/vendor-portal.php`
- `tests/reporting-bonus-count-guards.php` (removed from the production-bound package)
- `vendor-management-system.php`
- `includes/core/registry/constants.php`
- `vms-build.txt`
- `docs/05-revision-log.md`

## Behavior Changed

- Event Plans now have a focused linked-TEC featured-image sync helper.
- Changing an Event Plan featured image after initial publish now updates the linked TEC event thumbnail.
- A late generic `save_post_vms_event_plan` reconciliation pass now repairs already-linked TEC thumbnails on later Event Plan saves/updates.
- The existing vendor-logo fallback behavior remains in place for no-image Event Plans.
- Because the linked TEC event thumbnail is updated, TEC-template consumers such as the event page and `vms-events-slider` can pick up the newer banner.

## Undocumented .699 Drift

- The unrelated local `.699` vendor-portal attendance-bonus rendering change was reverted from `includes/portal/vendor-portal.php`.
- The related local-only guard file `tests/reporting-bonus-count-guards.php` was removed so it does not silently ship in `.700`.
- Result: `.700` keeps the approved `.699` check-in-close work, excludes the undocumented vendor-portal drift, and adds only the Event Plan image propagation fix.

## Local Tests Performed

- Test A: Published an Event Plan with no featured image and a vendor-logo fallback, confirmed the linked TEC event started on the fallback thumbnail, then added a real Event Plan featured image, simulated a stale TEC thumbnail, ran a normal Event Plan update, and confirmed:
  - Event Plan thumbnail = `2084` (`test-sponsor-logo.png`)
  - Linked TEC thumbnail repaired from fallback `2093` to `2084`
  - Public TEC page rendered `test-sponsor-logo.png`
  - Public calendar feed rendered `test-sponsor-logo.png`
- Test B: Published an Event Plan that already had a featured image and confirmed:
  - Event Plan thumbnail = `2073` (`vms-test-logo.png`)
  - Linked TEC thumbnail = `2073`
  - Public TEC page rendered `vms-test-logo.png`
  - Public calendar feed rendered `vms-test-logo.png`
- Test C: Published an Event Plan with no featured image and confirmed:
  - Event Plan thumbnail stayed empty
  - Linked TEC thumbnail used the vendor fallback `2093` (`test-sponsor-logo-1.png`)
  - Public TEC page rendered `test-sponsor-logo-1.png`
  - Public calendar feed rendered `test-sponsor-logo-1.png`
  - No warnings or notices were raised during the test harness runs
- Slider path:
  - Verified `vms-events-slider` reads upcoming `tribe_events` `_thumbnail_id` values and busts its cache when TEC `_thumbnail_id` changes.
  - Verified linked TEC thumbnails changed as expected after Event Plan image updates, which is the slider’s source of truth.

## Package

- Production-bound package slug: `vms-0.2.24.700.zip`
