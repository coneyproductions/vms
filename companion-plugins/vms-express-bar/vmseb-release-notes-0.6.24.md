# VMS Express Bar 0.6.24

- Invalidates the cached public TEC event page, linked Event Plan page, and dedicated Express Bar page after Express Bar event configuration changes.
- Invalidates the affected public pages once after an Express Bar version change so a direct in-place deployment cannot leave pre-update anonymous HTML behind.
- Bounds the one-time version migration to Event Plans with Express Bar enabled before checking auto-embed eligibility.
- Invalidates the dedicated Express Bar page after menu settings or enabled catalog rows change.
- Force-deletes only the affected WP Super Cache page directories when that cache is available, avoiding its stale `.needs-rebuild` response; other environments fall back to WordPress `clean_post_cache()` without adding a hard dependency.
- Preserves the public `bvmgr_*` resolver path before the retained legacy `vms_*` fallback when locating a linked TEC event.
- Does not change ordering windows, menu contents, cart/checkout behavior, WooCommerce behavior, or Event Plan configuration.
