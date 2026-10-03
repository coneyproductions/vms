# VMS Express Bar 0.6.24 Test Plan

1. Run `php tests/bvm-public-runtime-compatibility.php` for `modern`, `legacy`, `post-type`, and `absent`.
2. Run `php tests/public-cache-invalidation.php` for `modern`, `legacy`, and `meta`.
3. Confirm a version change queries only Express Bar-enabled Event Plans, then force-deletes only the WP Super Cache directories for auto-embedded plans, their linked TEC events, and the dedicated Express Bar page; confirm the generic fallback uses `clean_post_cache()`.
4. Confirm saving an Event Plan's Express Bar settings invalidates its linked TEC event page and the dedicated Express Bar page.
5. Confirm Bar Menu settings, registry row changes, and registry pruning invalidate the dedicated Express Bar page.
6. Deploy to staging and confirm the cache-version marker advances to `0.6.24` and the expected public cache entries are removed.
7. Verify anonymous HTML for an eligible event contains one `.vmseb-event-cta` and the correct `event_plan_id` link.
8. Verify disabled and auto-embed-disabled events do not gain a CTA.
9. Confirm the dedicated Express Bar page retains the configured ordering state and menu behavior.
10. Run PHP lint across every plugin PHP file and validate the release ZIP with `unzip -t`.
