## 0.2.24.748
- Fixed the current-event Event Details sidebar guard so automatic sidebar injection only suppresses duplicates inside the real target sidebar, while manual `[vms_plan_your_visit layout="sidebar"]` placement still prevents a later auto duplicate when it renders first.
- Enforced Vendor Type requirements at runtime for public vendor profiles so typeless vendors no longer expose public profile URLs or sidebar links just because older enablement meta was left behind.
- Retired the orphaned legacy `vms_square_nightly_sync` schedule with a no-op callback plus bounded safe-context cleanup that unschedules every WP-Cron argument variant, cancels pending Action Scheduler rows, deletes failed/canceled retired-hook history, fails closed when the cron or Action Scheduler query APIs report an error, and stores its one-time completion marker only after the full cleanup verifies that no retired-hook rows remain.
- Normalized both native `rest_cookie_invalid_nonce` responses and VMS Admissions nonce failures to the exact operator-facing refresh message `Your Admissions session expired. Refresh this page, then try again.` while preserving meaningful validation and permission errors.

## 0.2.24.746
- Fixed cancellation notification recipient discovery so the notifications step reads current staffing slot assignments first, falls back to legacy `_vms_staff_assignments` only when needed, and resolves staff emails in this order: staff `_vms_linked_user_id` user email, any user with `_vms_staff_id` pointing at the staff post, then older staff post meta fallbacks.
- Stopped silently dropping assigned staff without email by recording typed `missing_email` skips in the cancellation job data while keeping vendor and secondary vendor recipient behavior intact.
- Expanded the Event Plan cancellation job panel to show grouped vendor/secondary-vendor/staff notification totals plus readable sent, failed, and skipped line items that include recipient type, display name, and skip/failure reason, while preserving fallback rendering for older raw-email job history.
- Added a WordPress-backed regression harness for cancellation staff email resolution so linked-user precedence, `_vms_staff_id` fallback, and `missing_email` skips can be re-verified locally without sending real mail.

## 0.2.24.745
- Reordered the public sale ticket card enhancer so active sale rows render `On Sale`, then the ticket title, then the Early Bird/sale detail line instead of placing the sale detail above the title.
- Kept the existing sale-detail copy, sale pricing, quantity controls, and qualified/free ticket messaging unchanged while limiting the fix to the sale-row title stack.

## 0.2.24.742
- Fixed refund-aware active ticket counts on HPOS sites by recognizing refunds from `wp_wc_orders.type = shop_order_refund` when `wp_posts` only has `shop_order_placehold` rows.
- Added a server-side event-page HTML correction/removal pass for TEC's native `You have X Tickets for this Event` notice so fully refunded orders disappear and partial refunds show the net active count before client JS runs.

## 0.2.24.741

- Added capped Early Bird pricing for VMS Ticketing V2 so one paid ticket can carry an early sale price that ends by deadline, quantity cap, or whichever condition happens first.
- Added separate public display controls for total ticket availability versus capped sale availability, including global defaults and Event Plan overrides.
- Updated public sale copy to show capped Early Bird quantity remaining without exposing full event capacity when total availability is hidden or low-only.
- Made ticket product sold counts and the logged-in customer `You have X Tickets for this Event` notice refund-aware by subtracting full/partial refunds from active quantities.

## 0.2.24.740

- Reworked the public event vendor sidebar so it renders grouped vendor sections from Event Plan-owned vendor assignment data instead of showing only a single primary/secondary teaser path.
- Public output now merges food-related types under one `Food Vendors` heading, displays all assigned public food vendors in a compact card grid, and shows logo, display name, and cuisine/sub-category when available.
- Kept the public renderer scoped to finalized Event Plan data only: TEC event -> linked Event Plan -> lineup entries and secondary assignment map, with legacy Event Plan fallback fields still retained for compatibility when canonical fields are absent.
- Prevented duplicate public sidebar output when legacy `vms_vendor_teaser` and `vms_secondary_vendor_teaser` shortcodes both appear on the same event page.

## 0.2.24.732

- Fixed duplicate VMS admin navigation on `VMS -> Marketing & Social -> Email Follow-Ups` by registering the page as a shared VMS shell page, so the global top-nav hook no longer renders a second primary/subnav stack above the page shell.
- Preserved the Marketing & Social primary active state and the Email Follow-Ups secondary active pill across Overview, Templates, Preview & Test, and Logs.
- Confirmed neighboring Marketing & Social pages keep a single nav stack: Social Sharing remains a shell page, while Meta Ads Builder, Promotable Events, Performance, Logs, and Settings continue to render the shared global nav once.
- No public Event Feedback, email sending, template, log, MailPoet sync, or scheduling behavior was changed in this patch.

## 0.2.24.731

- Replaced the ambiguous attendee Bar and Bathroom elaboration checkbox labels with sentiment-specific, reportable choices while keeping the public Event Feedback form otherwise unchanged.
- Preserved backward compatibility for older Event Feedback submissions by treating the pre-`0.2.24.731` bar/bathroom detail keys as legacy selections in the admin response view instead of re-labeling them with the new sentiment-specific meanings.
- Tightened the Event Feedback admin response display so skipped food truck/vendor detail blocks no longer render grids full of `--`, and website detail rows only appear when that website data was actually relevant/submitted.

## 0.2.24.730

- Added an attendee-facing `Website / Ticket Purchase Experience` section to the public Event Feedback survey, including conditional website follow-up questions for respondents who used the site to buy or attempt to buy tickets.
- Changed secondary vendor / food truck blocks so attendees always answer `Did you order from them?` first, with the detailed wait/friendliness/menu/value/quality/accuracy/bring-back questions shown only when the answer is `Yes`.
- Hardened Event Feedback submission storage and admin reporting so hidden conditional website/vendor fields are ignored server-side, detailed food truck averages count only actual orders, and legacy pre-change feedback payloads still render cleanly.

## 0.2.24.714

- Changed Event Plan staffing candidate discovery from a raw role-term bucket to an explicit eligibility pass: staff must match the selected role and cannot appear as a new candidate when that role's qualification rules hard-block them.
- Preserved already-assigned staff who are no longer role-eligible or who now fail hard-block qualifications by keeping them visible in the staffing editor with warnings instead of silently removing them from the UI or save payload.
- Kept soft-block and warn-only qualification semantics intact for non-bar roles, so roles like Cleanup continue to show their explicit candidates while still surfacing qualification review warnings.
- Added a disposable WordPress-backed staffing eligibility regression harness covering filtered bar candidates, cleanup isolation, assigned-but-ineligible preservation, and save-time blocking of newly posted ineligible assignments.

## 0.2.24.713

- Stopped the generic Event Plan collapsible wrapper from moving already-rendered lazy sections into the preceding generated section by treating existing `.vms-collapsible-section[data-section-key]`, explicit break markers, and the Readiness Summary card as hard boundaries.
- Added an explicit post-compensation boundary and a Readiness Summary boundary marker so Secondary Vendors, Staff, Readiness Summary, and Readiness Details remain siblings instead of children of Primary Vendor Compensation.
- Forced lazy Readiness Details to boot collapsed whenever the section has not loaded yet, so the first click now loads/opens details instead of collapsing a visually open shell.
- Verified locally that the `.712` vendor-preservation harness still passes unchanged and that a disposable Lee Mathis-style Event Plan clone now renders the expected sibling order and loads Secondary Vendors, Staff, and Readiness Details successfully on first click.

## 0.2.24.712

- Hardened Event Plan editor saves so blank or missing deferred-section POST fields no longer clear the primary vendor, primary lineup vendor, or secondary vendor state unless an explicit clear intent is posted.
- Added explicit clear-intent controls for primary and secondary vendor removal, while preserving legitimate reassignment flows for valid submitted vendor IDs and vendor types.
- Made lazy Event Plan admin section load failures visible by auto-expanding failed sections and rendering an inline error state, and added focused scroll/focus behavior after the full ticketing-editor reload path.
- Added a local Event Plan vendor-preservation harness covering blank-save preservation, explicit clears, valid reassignment, and deferred/unloaded section save behavior; live execution remains dependent on a reachable local WordPress database.

## 0.2.24.711

- Treated unregistered premium modules as disabled in the VMS module loader so companion add-ons fail closed until the core module registry can verify their gate state.
- Added `vms_module_is_registered()` as the explicit registry probe used by companion add-ons that need to defer privileged boot until the VMS module system is available.
- Prepared the companion VMS core package for MAB `0.1.90` so the premium-module gate fix can ship without reopening broader licensing or admin-menu behavior.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff notes, revision log, and packaged test-plan docs for the new `.711` release marker.

## 0.2.24.710

- Added Phase 2 vendor onboarding identity gating so new vendor applications stay out of the real review queue until the submitted email is confirmed, while preserving the existing operator-facing `_vms_app_status` values (`pending`, `holding`, `approved`, `rejected`).
- Added a native vendor-application confirmation-token table plus hashed single-use tokens, resend cooldown/daily/IP throttles, a noindex confirmation endpoint, and public/portal applicant states for awaiting confirmation vs pending review.
- Resolved confirmed applicant emails to canonical website accounts by attaching an existing WP user when one already uses that email or creating a new account only after confirmation, then storing the resolved user on `_vms_app_submitted_user_id`.
- Hid unconfirmed applications from the normal pending-review counts/summary, added confirmation-state filters to Applications admin, blocked approve/reject/hold actions until confirmation, and required canonical vendor-user linking before the approved response email is allowed out.
- Added legacy confirmation-state backfill plus a documented confirmation-bypass kill switch so older pending/approved applications do not regress and emergency rollback remains low-risk.

## 0.2.24.709

- Redesigned the logged-out Vendor Portal entry screen into two clear public paths: approved-vendor login and apply-for-access, with responsive card layout and explicit guidance that approval is manual and not instant.
- Replaced the vendor application success flag with a fuller thank-you state that explains what happens next, tells applicants to watch email plus spam/junk folders, and clarifies that vendor tools live in the Vendor Portal instead of WooCommerce My Account.
- Expanded approved-application email guidance to include the Vendor Portal URL, linked login identity when available, password-reset or reply-for-linking instructions, and explicit Vendor Portal versus WooCommerce My Account routing notes.
- Added a vendor-only WooCommerce My Account notice with a direct Vendor Portal link, plus a safe login redirect that only applies to Vendor Portal-origin logins for non-admin users with active vendor links.

## 0.2.24.707

- Decoupled Event Plan featured-image-only save classification and downstream gating from `VMS_EP_PERF_TRACE` so production save behavior no longer depends on diagnostic tracing being enabled.
- Kept profiler persistence optional by moving `_vms_last_save_profile` / history recording behind a diagnostics-only recording gate while preserving runtime scope detection, `_thumbnail_id` carry-forward, and heavy-branch skip logic for all environments.
- Stopped absent lineup POST data from synthesizing fresh lineup row IDs and rewriting `_vms_lineup_entries_v1` / `_vms_lineup_primary_entry_id` on ordinary editor saves, which was falsely widening no-op and image-only saves to `mixed`.
- Verified locally with tracing disabled and enabled that no-op saves stay `no_op`, content edits stay `mixed`, featured-image-only saves stay targeted, linked TEC thumbnail sync runs once from the thumbnail-meta path, and no calendar/staffing queue rows, cron rows, or Action Scheduler rows are added.

## 0.2.24.706

- Preserved `_thumbnail_id` as an early effective save key so published Event Plan featured-image edits are recognized as image-only changes even though the thumbnail meta update lands before the main `save_post_vms_event_plan` profiler starts.
- Reclassified Event Plan save scope around meaningful keys instead of housekeeping-only writes like `_vms_unpublished_changes_at`, and fixed the final save-profile builder to honor post-field changes when distinguishing `no_op`, `mixed`, and `featured_image_only` updates.
- Skipped unrelated Event Plan publish/save side effects on featured-image-only updates, including TEC status sync, `vms_event_plan_saved` follow-on work, staff-task generation, and staffing rollup/seed queue maintenance, while keeping the linked TEC thumbnail sync on the early thumbnail-meta path.
- Added richer Event Plan save instrumentation for scope-effective meta keys plus verified locally that no-op saves stay `no_op`, content-only saves stay `mixed`, featured-image-only saves stay targeted, no extra cron/action rows are created, and the linked TEC/public-facing image consumers still pick up the new image.

## 0.2.24.705

- Hardened the State of the Range daily-report path with explicit delivery-state tracking for scheduled run, render start/finish, send attempt, successful send, recipient, subject, mailer, next scheduled run, result, and last error without storing message bodies.
- Added self-healing daily cron scheduling for the Ticket Integrity scan/report hooks so duplicate hooks, wrong recurrences, and local wall-clock drift after timezone/DST shifts are repaired automatically.
- Added a Ticket Integrity admin State of the Range status/preview surface plus safe preview and dry-run controls, and added WP-CLI helpers for `status`, dry-run `render`, `send-test`, and `reschedule`.
- Added standalone regression harnesses covering same-day upcoming inclusion, dry-run/send state transitions, and stale scan-lock recovery, and verified the new CLI/admin diagnostic paths locally without sending a real email.

## 0.2.24.704

- Packaged the Event Plan performance hardening set from patches 1 through 10 into a production-ready VMS build, keeping the no-change save guards, summary-first admin rendering, lazy-loaded detail panels, and Command Center ticket-card query reduction intact.
- Confirmed the local Event Plan perf trace, query fingerprint capture, memory checkpoints, and temporary `SAVEQUERIES` enablement remain fully dormant unless `VMS_EP_PERF_TRACE` is explicitly enabled.
- Prepared the release zip to exclude local perf reports, trace logs, `/tmp` runners, and editor junk while keeping the plugin package independent from local `wp-config.php` changes.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff notes, revision log, and packaged test-plan docs for the new `.704` release marker.

## 0.2.24.703

- Added a shared core image-normalization helper plus a browser-side normalization helper so qualified-ticket verification uploads now prefer client-side reduction and still normalize server-side when the browser cannot.
- Changed supported verification image proofs to store as normalized JPG files with a readable `2200px` long-edge target and JPEG quality `86`, while keeping PDF uploads on their own unchanged path.
- Replaced the hard-coded `10 MB` verification upload cap with an admin-editable original upload limit defaulting to `20 MB`, while surfacing the effective lower server cap when PHP/WordPress is stricter.
- Added clearer customer-facing errors for oversized PDFs, unsupported PDFs, and HEIC/HEIF fallback guidance.
- Added a standalone verification-proof normalization smoke harness covering JPG, PNG, WEBP, and oversized-output behavior.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff notes, revision log, and packaged test-plan docs for the new `.703` release marker.

## 0.2.24.702

- Filtered State of the Range upcoming events at email render time by comparing each event's local site day against the report's local site day, so past events no longer leak into a later report when they remain in the stored snapshot.
- Recomputed the email's tracked-upcoming tickets sold, gross sales, attention count, events scanned, and red/yellow/green rollup from the same filtered event set, while continuing to include same-day events in the local site timezone.
- Added a standalone State of the Range regression harness for the 2026-06-01 report-date case and verified the WordPress-backed render path locally without sending a real email.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, and revision log for the new `.702` release marker.

## 0.2.24.701

- Added a request-scoped guard around linked TEC featured-image sync so repeated Event Plan save hooks in the same request stop re-running identical thumbnail reconciliation work.
- Stopped deferred calendar publish, calendar/vendor maintenance, and staffing seed queue paths from rewriting their queue-state meta when the same Event Plan job is already queued or locked with the same identifiers.
- Preserved the existing deferred scheduling behavior and `.700` linked-image stale-thumbnail repair behavior while reducing duplicate meta churn on repeated saves.
- Left the secondary-vendor derived-meta dirty check out of `.701` because that block also feeds downstream maintenance and needs a separate Phase 2 pass.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, and revision log for the new `.701` release marker.

## 0.2.24.700

- Added a dedicated linked-TEC featured-image sync helper so Event Plans that gain or change a featured image after initial publish now push that banner to the linked `tribe_events` record.
- Wired the same helper into Event Plan thumbnail-meta changes and a late generic `save_post_vms_event_plan` reconciliation pass so stale linked TEC thumbnails are repaired on later Event Plan updates instead of only on initial publish/resync.
- Kept vendor-logo fallback behavior intact for no-image Event Plans while ensuring the public TEC page and any TEC-thumbnail consumers track the newer Event Plan banner once one exists.
- Reverted the unrelated local `.699` vendor-portal attendance-bonus rendering drift and removed its local-only guard file so `.700` stays scoped to approved work plus the previously documented check-in-close changes.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, and revision log for the new `.700` release marker.

## 0.2.24.699

- Persisted explicit `_checkin_close_at` values on Event Plans from the normal save/publish/sync flow instead of leaving future scanner close windows implicit.
- Synced explicit `_checkin_close_at` to linked `tribe_events` records so scanner payloads and API checks can read the same close timestamp from either side of the link.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, and handoff notes for the new `.699` release marker.

## 0.2.24.698

- Added a new public-calendar `Compact` view that renders three months at a time in denser month cards instead of one full seven-column month grid.
- Compact mode keeps only weekday columns that are actually open or booked for each month, so the usual Fri/Sat-heavy schedule collapses into weekend chunks while still surfacing one-off weekday events when they exist.
- Added a VMS Settings -> Calendar -> Public Calendar default-view control so operators can make Compact, Month, List, or Auto the site default without changing the shortcode.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, and revision log for the new `.698` release marker.

## 0.2.24.694

- Reworked State of the Range event-row metrics so Sold, Paid sold, Free/qualified sold, and Gross all use the same completed-order basis across active mapped ticket rows instead of mixing Woo analytics public-ticket totals with Woo `total_sales` / stock snapshots.
- Expanded report inclusion beyond only `customer_facing` public/login rows so verified zero-dollar ticket products now contribute to completed sold totals and available inventory when they are active mapped ticket rows.
- Changed the report copy from `Left` to `Available inventory`, `Capacity` to `Ticket capacity`, and `Free/comp sold` to `Free/qualified sold` so the row labels match the intended ticket semantics.
- Decoded HTML entities before sending the plain-text State of the Range email so values like `$`, dashes, and ampersands no longer leak as `&#36;`, `&#8211;`, or `&amp;`.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff notes, revision log, and packaged test-plan docs for the new `.694` release marker.

## 0.2.24.693

- Reworked Ticket Integrity sold-quantity scanning to aggregate Woo order data in SQL instead of hydrating full order objects and line items for every ticket product.
- Tightened Ticket Integrity target discovery to upcoming linked Event Plans and replaced the heavy target-time ticketing snapshot check with lighter raw config/sync/meta checks.
- Added fatal guards plus explicit `scan_failed`, `scan_failed_memory`, `daily_report_started`, `daily_report_failed`, and `daily_report_skipped_scan_failed` logging so scheduled report failures no longer disappear after a PHP fatal.
- Added State of the Range refresh-failure handling that can send from the last good snapshot with a warning, or clearly skip/fail when no usable snapshot exists.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff notes, revision log, and packaged test-plan docs for the new `.693` release marker.

## 0.2.24.687

- Fixed checkbox-mode reserved add-ons on Chrome mobile by adding explicit touch-toggle handling and click dedupe instead of relying only on native touch-to-click checkbox synthesis.
- Applied the same checkbox touch hardening in both the main front-end ticketing bundle and the inline server-controls fallback controller so the checkbox path behaves consistently across render modes.
- Added mobile checkbox touch-target hardening in the public ticketing stylesheet.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, revision log, and packaged test-plan docs for the new `.687` release marker.

## 0.2.24.686

- Count prior customer purchases of qualifying event tickets toward reserved add-on eligibility so returning buyers can unlock add-ons with tickets they already bought for the same event.
- Count prior purchased add-ons in each entitlement pool toward the same shared-pool math so customers cannot exceed per-ticket add-on allowances across separate orders.
- Added current-customer purchase-history helpers for event add-on rendering, the reserved add-on cart-context AJAX endpoint, inline server-controls state, Woo add-to-cart validation, and cart/checkout validation so the UI and server rules stay aligned.
- Added new reserved add-on source data attributes plus cart-context payload fields for prior qualifying quantity and prior pool usage.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, revision log, and packaged test-plan docs for the new `.686` release marker.

## 0.2.24.685

- Fixed progressive qualified-ticket rows on mobile so late native qty updates no longer miss the selection sync; the qualifying row now gains `vms-qualified-ticket-selected` once the quantity actually changes, which reveals the signup/login/help stack instead of leaving it hidden behind stale CSS state.
- Reworked progressive watcher scheduling so follow-up refreshes are deduped by delay instead of clearing each other; multiple retry windows now survive long enough to catch delayed native ticket input updates on Chrome mobile.
- Added touch-safe toggling for the qualified-ticket `Click here for more info.` disclosure, including explicit `aria-expanded` state and touch/click dedupe so the summary opens reliably on mobile.
- Added touch-action/tap-highlight hardening to the qualified-ticket more-info summary.
- Preserved the separate staging-page finding that an inline event-page script still throws `TICKETS_SEL is not defined`; that defect remains outside the VMS bundle.

## 0.2.24.684

- Reduced mobile native ticket stepper lag by shortening the touch-fallback delay and suppressing the later synthetic click only when the fallback already applied the quantity change.
- Expanded progressive section touch handling to the full header surface with shared dedupe so add-on accordions can still open when mobile browsers deliver the finishing touch event to the header but not the inner button node.
- Synced mobile progressive control sizing across both `vms-ticketing-front.css` and `vms-entitlements-public.css` so server-controls progressive pages stop falling back to `38px` touch targets when both stylesheets are loaded.
- Increased public mobile ticket stepper hit targets to `44px`, aligned the input height to match, and added touch-action/tap-highlight hardening on the progressive header and native ticket controls.
- Preserved the separate page-level finding on staging that an inline event-page script still throws `TICKETS_SEL is not defined`; that script is outside the VMS bundle and still needs to be corrected at its source.

## 0.2.24.683

- Hardened public ticket quantity controls for mobile Chrome by adding a touch fallback for native TEC `+` / `-` steppers when click synthesis or tiny hit areas make the first tap miss.
- Hardened the progressive add-ons accordion by binding its toggle to touch/pointer interactions instead of relying on click-only behavior.
- Increased mobile ticket/add-on tap-target reliability with `touch-action: manipulation`, larger progressive control sizing, and non-selectable touch-friendly toggle/button affordances.
- Prevented atomic ticket submits from stalling indefinitely behind `cart_context` on slow or overloaded environments by skipping that prefetch for ticket-only submits and timing out slow add-on prefetches.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, revision log, and packaged test plan for the new `.683` release marker.

## 0.2.24.682

- Added a new Payment Gateway Health section to Ticket Integrity and the State of the Range report so VMS can explicitly surface whether checkout currently has any usable payment methods.
- Added Square health inspection for plugin activity, card gateway enablement, connection/authentication presence, location presence, environment mode, and Apple Pay domain-registration status without exposing secret values.
- Added incident memory that records first detection time, failed checks, and the most recent resolved payment incident, and kept that history visible after recovery.
- Added scheduled payment-gateway health monitoring with 15-minute/hourly cadence options, critical admin notices, menu alert badge support, and optional critical email alerts through existing Ticket Integrity email settings.

## 0.2.24.681

- Added Ticketing v2 relative sale-date controls so templates can define early/sales windows as days before the Event Plan date/time.
- Anchored early-price relative dates to event start and ticket Sales end relative dates to event end.
- Changed new-ticket Sales end defaults to the Event Plan end time and added server-side clamping so ticket sales cannot remain open after the event ends.
- Updated template Sales end guardrails to catch both stale-before-event and unsafe-after-event dates.

## 0.2.24.680

- Trimmed public sale styling so the sale price is red without becoming oversized.
- Moved sale deadline styling inline beside the On Sale badge to reduce ticket-card vertical height.
- Added safe template-replacement cleanup that retires VMS-owned ticket products no longer present in the current ticket payload by drafting/hiding them instead of deleting order history.

## 0.2.24.675 — Ticket ratio rules for child/comp admission

- Added an optional per-ticket ratio rule so one ticket type can be limited by the quantity of qualifying tickets in the same event cart.
- Added admin Ticketing v2 controls for **Limit by qualifying tickets** and **Max per qualifying ticket**.
- Added normalized config/runtime meta fields for ratio-limited ticket rows.
- Enforced ratio limits during add-to-cart, cart, checkout, Store API/block checkout error collection, and VMS progressive atomic add-to-cart.
- Protected ratio-limited tickets from qualifying themselves, even if the operator accidentally leaves the unlock checkbox enabled.
- Added build notes and a focused staging test plan for the Taylor Swift / child-heavy event scenario.

## 0.2.24.674 — Event Plan empty-save load shed and TEC author hardening
- Fixed the no-ticket Event Plan publish/save performance regression observed on `0.2.24.662`.
- Added guards to skip heavy ticketing, staffing, and Ticket Integrity work for empty Event Plans.
- Prevented public calendar dead cards while the linked TEC event is still syncing by hiding public-feed entries until the TEC event is published and has a real permalink.
- Explicitly set and backfilled TEC event authors during VMS sync so linked `tribe_events` posts do not remain at `post_author=0`.
- Added shared Event Plan performance helpers for request/job tracing, effective-ticket detection, actor capture, per-event transient locks, and TEC author resolution/backfill.
- Deferred TEC vendor/category maintenance and staffing template seeding out of inline `save_post_vms_event_plan` work and deduped each per-event background job with `wp_next_scheduled()` plus a transient single-flight lock.
- Added temporary Event Plan tracing around core save/publish hooks and deferred jobs so operators can correlate request ID, hook/job name, status transition, ticket count, cron/AJAX/REST context, PID, and elapsed milliseconds during production smoke tests.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, handoff, revision log, and packaged test plan.

## 0.2.24.673 — Async suppression marker context fix
- Preserved the scoped Action Scheduler async suppression list from `0.2.24.672`, including ECC, DT pages, and the intentionally scoped Event Plan editor.
- Added an explicit `action_scheduler_async_blocked` marker entry alongside the existing suppression flag.
- Adjusted stored fingerprint export so the saved health-screen log keeps readable page/slug/scope values instead of compacting them down to `...`.
- Preserved the `0.2.24.671` WP-CLI lifecycle-hook compatibility fix so plugin activation/deactivation still tolerates a nullable `network_wide` argument.
- Updated version markers, build notes, handoff, revision log, packaged test plan, and release zip filename (`VMS_673_async_suppression_marker_context_fix.zip`).

## 0.2.24.672 — Scoped Action Scheduler async suppression markers
- Added reusable heavy-page scope detection for Action Scheduler async suppression and included the missing `vms-event-command-center` admin slug in that scoped list.
- Kept the Event Plan editor intentionally scoped because the editor still loads the Module Hub / report snapshot path and can trigger the same heavy admin overlap concerns as DT/ECC.
- Added explicit `action_scheduler_async_blocked` fingerprint flags for scoped pages, including the current page slug and scope reason, and made those flags log-worthy even when the page returns quickly.
- Preserved the `0.2.24.671` WP-CLI lifecycle-hook compatibility fix so plugin activation/deactivation still tolerates a nullable `network_wide` argument.
- Updated version markers, build notes, handoff, revision log, packaged test plan, and release zip filename (`VMS_672_scoped_action_scheduler_async_suppression_markers.zip`).

## 0.2.24.671 — Activation hook compatibility parse-fix
- Removed an invalid no-op cast from the `0.2.24.670` nullable plugin lifecycle handlers. The nullable signature itself was correct; the cast line caused a parse error on staging.
- Preserved the intended `activated_plugin` / `deactivated_plugin` compatibility change so WP-CLI can still pass a nullable network-wide flag.
- Updated version markers, build notes, handoff, revision log, and packaged test plan.

## 0.2.24.670 — WP-CLI activation hook compatibility follow-up
- Fixed a staging-discovered fatal in the new resource-fingerprint lifecycle hooks: WP-CLI plugin activation/deactivation on this host can pass `null` for the `network_wide` argument instead of a strict boolean.
- Relaxed the `activated_plugin` / `deactivated_plugin` fingerprint callback signatures to accept a nullable network-wide flag while preserving the same logging behavior.
- Preserved the 0.2.24.669 fingerprint screen, DT/ECC timing markers, request-level memoization, and Action Scheduler async-runner suppression work.
- Updated version markers, build notes, handoff, revision log, and packaged test plan.

## 0.2.24.669 — Resource fingerprints and report-load guardrails
- Added threshold-based request/task fingerprint logging for slow or heavy VMS/DT requests, WP-Cron, Action Scheduler, plugin lifecycle hooks, ECC calculations, DT reports, and VMS queue work.
- Logged request URI, admin page, current user ID, admin/AJAX/REST/cron/WP-CLI context, runtime, peak memory, due WP-Cron counts, Action Scheduler pending/running counts, and calculation flags/markers.
- Exposed recent fingerprint entries on the VMS Dashboard / Onboarding & Health admin screen with capped retention and a clear-log action.
- Added timing markers around ECC ticket truth/build work and DT ticket report, dataset, event model, evidence, labor overhead, event costs, and single-event render phases.
- Reduced DT repeated work by memoizing request-scoped report builders, short-caching event ticket-report totals, reusing default lifetime website truth, and removing a duplicate single-event summary/cost pass.
- Suppressed Action Scheduler async runner dispatch from heavy DT/ECC admin pages to reduce shared-pool worker overlap during report loads.
- Updated version markers, build notes, handoff, revision log, and packaged test plans.

## 0.2.24.668 — ECC total admitted note regression fix
- Fixed the Event Command Center Ticket Snapshot total admitted/ticketed note so manual true-comp counts are added to paid ticket totals even when the reporting source total still reflects paid rows only.
- Made the note render condition robust when `comp_count > 0`, preventing valid comp/free counts from being hidden.
- Added resource-spike diagnostic carry-forward instructions to the 0.2.24.668 test plan so Codex keeps the cPanel/PHP worker investigation tied to the next regression pass.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, build notes, revision log, and packaged test plan.

## 0.2.24.641 — Ticket quantity / checkout protection hotfix
- Stopped VMS from enforcing `max_qty_per_order` as a hard customer cap for public/general tickets by default. Normal paid ticket quantities now rely on Woo/TEC inventory/capacity instead of an accidental VMS per-customer cap.
- Preserved max/allowance enforcement for login/verified/registered-guest tickets, where customer or credential limits are intentional.
- Added a filterable helper, `vms_ticketing_v2_should_enforce_ticket_max_qty`, so a future site can intentionally opt public tickets into VMS per-customer caps if needed.
- Relaxed the classic cart quantity stabilizer so it no longer stops Woo/theme quantity listeners; it remembers the final customer-entered quantity and reconciles stale snapbacks after Woo settles.
- Preserved the 0.2.24.640 progressive ticket copy/heading polish.

## 0.2.24.640 — Progressive ticket copy, heading, and add-on label polish
- Changed verified/free admission row default copy to `Requires registration` and the collapsed disclosure trigger to `Click here for more info.`.
- Decoded escaped display labels before rendering ticket names so labels such as `Children's Admission (<12yo)` display naturally in the public UI.
- Increased Progressive section title size/weight so section headings stand out from ticket row content.
- Restored the configured Tickets help/custom-instructions block in Progressive layout.
- Changed the default add-on section to `Fire Pits & Tables`, added editable global heading/subtext settings, and set the default add-on subtext to `Click here to add a fire pit or table to your order.`.
- Updated the Ticketing UI guided-tour copy to use neutral add-on wording instead of `Amenities`.
- Preserved the 0.2.24.639 cart quantity multi-change hotfix and existing verified-ticket/add-on enforcement behavior.

## 0.2.24.637 — Staff certification admin visibility and email sender merge
- Preserved the 0.2.24.636 approved/free admission row copy and first-time help changes from the uploaded baseline.
- Added a VMS → Staff Certifications review queue listing staff-uploaded certificates waiting for approval.
- Added pending-review badges/notices in the VMS admin menu, admin notices area, Staff list Certifications column, and staff profile Qualifications / Licenses metabox.
- Expanded staff certification admin notification recipients to include the site admin email and administrator user emails, with a filter still available for custom recipients.
- Added site-branded email sender headers for staff certification submission and approve/reject notifications where WordPress/mail delivery honors From headers.
- Updated version markers, `vms-build.txt`, packaged handoff, and packaged test plan.

## 0.2.24.636 — Approved/free admission row copy and first-time help
- Removed the customer-facing `Qualified ticket.` phrase from approved/free ticket row descriptions.
- Removed row-level `before checkout` verification wording so first-time customers are not led to believe approval happens during checkout.
- Added a collapsed `First time? More info` disclosure inside each approved/free ticket row with plain-language approval steps and a verification CTA when available.
- Kept deeper registered-guest claim panels collapsed until the customer selects an approved/free ticket quantity, and preserved existing eligibility enforcement.

## 0.2.24.632 — Approved-ticket help card collapse polish
- Changed the ticket-specific “Need help ordering…” card into a collapsible help panel that is collapsed by default.
- Removed the ordered-list indentation from the help copy so it does not waste horizontal space on mobile.
- Removed the final “Do not also buy General Admission…” line from the help card copy.
- Preserved the 0.2.24.631 layout hotfix and all recent mobile registered-guest / stepper / Amenities behavior.

## 0.2.24.631 — Approved-ticket help card layout hotfix
- Fixed the `0.2.24.630` desktop row collapse/overlap by moving the ticket-specific help card into the existing ticket status stack between the login/register note and approved guest panel.
- Preserved the help card copy and placement order while keeping the row grid from treating the help card as a stray ticket-grid child.
- Kept recent mobile guest-email stacking, `Add Registered Guest` wording, centered steppers, and progressive Tickets/Amenities behavior intact.

## 0.2.24.630 — Ticket-specific approved admission help card
- Removed the global `Need help choosing tickets?` progressive Tickets-section helper.
- Added a selected-ticket-specific help card inside each approved/free admission ticket row, between the login/register note and the approved guest email panel.
- Updated public copy to avoid saying customers can register “before checkout”; customers are told to finish registration first and come back after approval.
- Added reassurance that approvals are often completed quickly, without promising instant approval.
- Changed approved guest wording to `Bringing an approved guest?` and `Approved guest email for ticket N` for clearer customer-facing language.
- Preserved the mobile stacked approved-guest input/button layout, `Add Registered Guest` button wording, centered steppers, progressive layout behavior, and add-on gating behavior.

## 0.2.24.629 — Qualified-ticket clarity and ticket-choice help
- Clarified registered-guest helper copy so customers understand the guest email path is an alternative to adding General Admission for that same person.
- Added a compact collapsible `Need help choosing tickets?` explainer inside the progressive Tickets section.
- Preserved the 0.2.24.628 ticket/add-on stepper alignment, registered-guest mobile stack, and Add Registered Guest wording.

## 0.2.24.628 — Desktop ticket stepper alignment polish
- Centered the native ticket `- / +` button contents and quantity number field on the desktop V2/progressive ticket row layout.
- Folded the fix into the existing V2 ticket-row control rules in `70-ticket-ui-v2.css`, `80-ticket-ui-rewrite.css`, and the compiled public stylesheet rather than adding another tail override block.
- Preserved the registered guest mobile stacked input/button layout from `0.2.24.627` and the recovered add-on stepper behavior from `0.2.24.626`.

## 0.2.24.627 — Registered guest mobile stack repair
- Repaired the progressive mobile qualified-ticket guest-email layout after `0.2.24.626` by restoring the stacked input/button layout for phone widths.
- Moved the fix into the existing `@media (max-width: 782px)` progressive ticket UI rule set instead of layering on another late override block.
- Preserved the recovered stepper alignment from `0.2.24.626`, the `Add Registered Guest` wording, and the improved logged-out instructions.

## 0.2.24.626 — Progressive stepper alignment cleanup
- Consolidated recent mobile claim-flow CSS into the existing ticket/add-on stylesheet rules instead of stacking another late override block at the end of the CSS.
- Removed the extra `0.2.24.624` / `0.2.24.625` tail override blocks that were making the cascade harder to reason about.
- Centered native TEC ticket steppers and VMS add-on steppers so the `-` / `+` controls do not appear top-aligned on mobile.
- Kept the `Add Registered Guest` wording and fixed Log In/Register button alignment from `0.2.24.625`.

## 0.2.24.625 — Progressive qualified-ticket vocabulary/alignment polish
- Fixed vertical text alignment for the selected qualified-ticket Log In/Register buttons, especially on mobile Safari.
- Renamed the registered-email action button from `Add Qualified Guest` to `Add Registered Guest` to match the surrounding “registered guest” language.
- Preserved the 0.2.24.624 mobile layout cleanup that put the email input above the action button and removed the extra guest-row outline.

## 0.2.24.624 — Progressive qualified-guest mobile layout cleanup
- Removed the extra outlined box styling around each registered-guest email row inside the selected qualified-ticket claim panel so the mobile layout feels less boxed-in.
- Changed the mobile qualified-guest claim row layout so the registered-email input sits above the `Add Qualified Guest` button instead of beside it.
- Kept the 0.2.24.623 simplified logged-out copy and guest instructions, along with the 0.2.24.622/621/620 ticket UI fixes.

## 0.2.24.623 — Progressive qualified-ticket mobile copy polish

- Simplified the selected qualified-ticket login/register note for logged-out customers so it no longer tries to explain login, registration, and guest claiming in one paragraph.
- Split guest-email guidance into the separate claim panel with wording for already registered/approved guests.
- Hid the redundant “Need more than one qualified ticket?” disclosure when the guest-email panel is already open.
- Updated guest-email row labels to use registered-email wording.
- Added mobile CSS so the registered-email field and Add Qualified Guest button stack full-width on phone layouts instead of cramping side-by-side.
- Preserved the 0.2.24.622 row-description fix, 0.2.24.621 mobile/tablet flattening, and 0.2.24.620 Tickets/Amenities grouping polish.

## 0.2.24.622 — Progressive qualified-ticket short-description hotfix

- Restored the short row-level descriptions for qualified ticket rows in Progressive mode so desktop and mobile still explain Veteran / Police, Fire Fighter, EMT admission before selection.
- Kept the deeper qualified-ticket helper/claim/account panels hidden until the customer selects a qualified-ticket quantity.
- Preserved the 0.2.24.621 mobile/tablet flattening, the single Tickets heading, and the collapsed Amenities section behavior.
- Updated version markers, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.621 — Mobile Progressive ticket UI flattening

- Flattened the Progressive ticket purchase surface on mobile/tablet by removing redundant nested borders, backgrounds, and padding from the outer flow and section wrappers.
- Kept ticket cards and add-on cards visible, but gave them more usable width on narrow screens.
- Tightened mobile ticket quantity controls and ticket price sizing so the controls do not overflow the card.
- Collapsed extra qualified-ticket explanatory copy until the qualified ticket is selected, preserving the cleaner “choose a ticket type first” flow.
- Kept desktop behavior from 0.2.24.620 intact.

## 0.2.24.620 — Progressive ticket UI copy/layout polish

- Changed Progressive public ticket UI from the duplicate **Admission** + **Tickets** heading stack to one customer-facing **Tickets** header.
- Kept the ticket rows exposed/open by default and prevented the Tickets section from being collapsed accidentally.
- Removed the verbose ticket help/subtext paragraph in Progressive mode, including the prior default copy that warned customers not to mix General Admission and qualified tickets.
- Renamed **Amenities / Add-ons** to **Amenities** while preserving the short helper line underneath.
- Tightened collapsed Amenities spacing so the accordion no longer leaves a large empty void when closed.
- Updated version markers, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.616 — Progressive ticket UI admission unification

- Changed Progressive mode so standard and qualified admission rows stay together in one **Admission** card instead of being split into separate Tickets and Qualified Discounts sections.
- Kept qualified-ticket helper/claim/login UI hidden until the customer selects a qualified-ticket quantity, preserving the low-noise first impression.
- Repaired the Amenities/Add-ons accordion wrapper so existing add-on content stays inside the collapsible content area even when the V2 mount script reruns after Progressive initialization.
- Updated default public help copy to avoid telling customers to select General Admission first when a free/qualified ticket applies.
- Updated Ticketing UI settings/tour copy, version markers, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.615 — Progressive public ticket UI foundation

- Added a rollback-safe Progressive Ticket UI layout option that groups the existing ticket surface into Tickets, Qualified Discounts, and Amenities/Add-ons sections.
- Added per-Event Plan Public ticket UI overrides so a single event can inherit, force Progressive, force V2 Unified, or force Legacy / Safe Mode.
- Added a front-end progressive enhancement script that hides qualified verification/helper UI until a qualified-ticket quantity is selected while preserving existing TEC/Woo quantity controls and server-side business rules.
- Added Progressive Ticket UI styling to the compiled public ticketing stylesheet and its source module.
- Updated Ticketing UI guided-tour copy, version markers, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.610 — Email Follow-Ups selected recipients + batch-safe manual sends

- Fixed the template-save regression where the non-empty starter body in the Add Template area created a new empty “Custom Follow-Up” template on every template save.
- Added a one-time cleanup migration that removes the empty placeholder custom templates created by that regression while preserving real custom templates with meaningful subject/body/description changes.
- Added recipient checkboxes to Preview & Test manual sends so admins can include/exclude individual buyers or send to a single selected customer.
- Added Select all / Select none controls and a selected-recipient count in the manual send form.
- Added batched manual send processing with a default batch size of 50 recipients per request plus a Continue Sending flow when recipients remain, reducing timeout risk on larger events.
- Added a client-side sending indicator that disables the send button and tells the admin to keep the tab open until the page returns.
- Added `assets/js/vms-email-followups-admin.js` for Email Follow-Ups admin UI behaviors and expanded `assets/css/vms-email-followups-admin.css` for batch/progress/recipient-picker styling.

## 0.2.24.609 — Email Follow-Ups template save buttons + character cleanup

- Added a Save Template Changes button under every Email Follow-Up template card so operators do not have to scroll to the bottom after editing long template bodies.
- Added a Save New Template button inside the Add Template card.
- Added template-input cleanup for common mojibake/smart-character artifacts such as `Ã¢Â€Â¢`, `â€¢`, curly quotes, em/en dashes, ellipses, non-breaking spaces, and stray `Â` characters.
- Normalized default Email Follow-Up templates to ASCII-safe punctuation and hyphen bullets to avoid the recently observed encoded bullet artifacts.
- Added a one-time 0.2.24.609 migration to repair saved Email Follow-Up template text, custom-template labels/descriptions, from-name, and signature fields.

## 0.2.24.608 — Email Follow-Ups per-template timing, signatures, and custom templates

- Moved the confusing send-hour/post-event-hour controls out of the main mental model by putting timing directly on each template: Manual only, Before event, Day of event, or After event, with days and send hour on the same template card.
- Added a reusable global signature setting on Email Follow-Ups → Overview and a `{signature}` token that can be placed in any template.
- Updated default templates to include `{signature}` and added a one-time migration that appends `{signature}` to existing saved templates when missing.
- Added custom template creation from the Templates tab, including editable name, description, enabled state, timing, subject, body, and delete-on-save support for custom templates.
- Updated scheduler logic to use each template's configured send timing rather than relying on global reminder/post-event hour fields.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, Codex handoff, and packaged test-plan docs.

## 0.2.24.607 — Email Follow-Ups smart customer greeting tokens

- Added `{customer_first_name}` token for first-name-only personalization.
- Added `{customer_greeting}` token that renders `Hi First,` when a first name is available and `Hi there,` when no usable first name is discoverable.
- Updated default Email Follow-Ups templates to start with the smart greeting token instead of risking awkward blank-name salutations.
- Added a conservative migration that prepends `{customer_greeting}` to saved templates only when no existing customer/name token is already present.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, Codex handoff, and packaged test-plan docs.

## 0.2.24.606 — Email Follow-Ups past-event preview repair
- Repaired the Email Follow-Ups Preview & Test event selector so it no longer hides past Event Plans; this is required for post-event thank-you emails and private event feedback invitations.
- Added a preview-oriented event chooser that pulls a one-year past/future window, preserves an explicitly selected Event Plan even if it falls outside that window, and sorts choices by closest event date so recent shows surface first.
- Added event labels for past events and today to reduce operator confusion while testing reminders versus post-event feedback follow-ups.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, Codex handoff, and packaged test-plan docs.

## 0.2.24.605 — Event Feedback + Email Follow-Ups integration

- Integrated the post-event Email Follow-Ups template with the private Event Feedback MVP by adding a `{feedback_url}` token that resolves to the event-specific survey link.
- Added recipient-aware feedback invitation URL markers for post-event emails, using non-guessable invite/recipient hashes without exposing raw order details in the URL.
- Updated the default Post-Event Thank You template and added a one-time settings migration so existing stored post-event templates receive a private feedback link if they do not already have one.
- Preserved manual/test-first safety: automatic sends remain guarded by the Email Follow-Ups settings, and manual sends still require confirmation.
- Added Email Follow-Ups log entries when a feedback form is submitted, allowing the follow-up log to show post-event feedback submissions alongside sent email activity.
- Added shortcuts between Event Feedback and the post-event email preview from the Event Feedback admin card and Event Plan sidebar metabox.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, Codex handoff, and packaged test-plan docs.

## 0.2.24.604 — Private Event Feedback MVP

- Added a private post-event Event Feedback MVP with event-specific survey links, a public one-page survey, and internal-only response storage.
- Added venue feedback with quick ratings for overall venue, bar, bathrooms, arrival/check-in, and sound, with optional expanded detail prompts for bar and bathrooms.
- Added Event Plan-aware primary vendor and secondary vendor feedback sections, including food-truck-friendly wait-time diagnostics, friendliness, selection, value, quality, accuracy, and bring-back questions.
- Added a VMS admin Event Feedback page with survey link, response count, rating averages, secondary-vendor summaries, and detailed private response review.
- Added an Event Plan sidebar metabox with a copyable survey URL and View Responses button.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.603 — Qualified ticket self-allowance follow-up

- Fixed the remaining qualified-ticket allowance issue where a verified buyer's own eligible quantity could still be capped by the public ticket max-qty setting, causing quantity 2+ to ask for an unnecessary additional guest email.
- Treated profile/program allowance and event/direct-grant quantity as the effective per-person claim cap, while keeping prior order/cart consumption and over-limit rejection intact.
- Added direct-grant quantity as an allowance-raising fallback even when the credential-program rule matches first.

## 0.2.24.602 — Qualified ticket multi-claim fix

- Started from the stable `0.2.24.600` baseline and intentionally skipped the `0.2.24.601` server-efficiency experiment as a release baseline.
- Fixed qualified/credential ticket validation so a single approved assignee email can claim multiple tickets for the same event up to that account's effective allowance.
- Removed premature duplicate-email blocks from front-end assignment validation, AJAX guest-email validation, and cart/add-to-cart/checkout assignment validation.
- Preserved the true allowance guard: prior purchases plus existing cart assignments plus current assignments still cannot exceed the assignee's effective event limit.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.599 — Left rail launchers + registry discovery

- Corrected the 0.2.24.598 overcorrection where the WordPress VMS left flyout became a full grouped directory and recreated the original mile-long menu problem.
- Kept the registry-driven section/category discovery model, but restored the WordPress left flyout to primary launchers only: Dashboard, Planning, Vendors & Staff, Marketing & Social, Venues, Settings, Tools.
- Preserved detailed module/page discovery through the VMS top navigation, All VMS Pages directory, direct URLs, and shared active-category mapping.
- Kept the visible flyout rewrite at `admin_head` so WordPress direct-page permission checks still complete before the menu is shortened.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, Codex handoff, revision log, and packaged test-plan docs.

## 0.2.24.598 — Registry-generated grouped left menu

- Rebuilt the visible WordPress VMS flyout from the VMS admin page registry instead of hand-maintaining a compact hardcoded menu.
- The left flyout now uses primary categories with child page links underneath, so registered modules land under their declared `section`.
- Preserved direct URL access by continuing to rebuild the visible flyout at `admin_head`, after WordPress capability/page registration checks.
- Added fallback capture for older modules that still add submenu pages directly, grouping them by the shared VMS section guesser until they are migrated to `vms_register_admin_page()`.
- Updated the add-on convention docs to state the rule clearly: register once, declare section once, and do not patch individual menu arrays.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, and packaged test-plan docs.

## 0.2.24.597 — Admin menu direct URL access repair

- Fixed the 0.2.24.596 regression where physically compacting the VMS submenu during `admin_menu` could cause valid secondary/direct admin URLs such as `vms-square-sync-protection` to fail WordPress capability checks with “Sorry, you are not allowed to access this page.”
- Moved the compact-left-rail reduction to `admin_head`, which runs after WordPress direct-page access checks but before the left admin menu is rendered.
- Kept the visible VMS left flyout compact from normal wp-admin screens while preserving direct URL access, top-nav access, and All VMS Pages discovery.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, Codex handoff, and packaged test-plan docs.

## 0.2.24.596 — Admin menu left-rail hardening

- Fixed the compact VMS WordPress left menu so secondary/detail pages are removed from the wp-admin submenu array instead of relying on CSS that only loaded on VMS screens.
- Preserved direct URL access and All VMS Pages discovery by storing the complete pre-compaction submenu inventory before the rail is reduced to primary section launchers.
- Added a tiny global admin-menu CSS safety net for any legacy/add-on items that still carry the old hidden class.
- Kept the visible VMS left rail aligned with the top-nav primary headings: Dashboard, Planning, Vendors & Staff, Marketing & Social, Venues, Settings, Tools.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, and packaged test-plan docs.

## 0.2.24.595 — Square Sync shell package repair

- Rebuilt from `0.2.24.593` after `0.2.24.594` omitted the Guided Tours module and caused a fatal include error.
- Preserved `includes/tours/tours.php` and related Guided Tours files.
- Re-applied Square Sync Protection shared-shell rendering.
- Kept compact aligned VMS admin menu headings intact.

## 0.2.24.593 — Menu heading alignment

- Aligned the compact WordPress VMS left submenu headings with the primary VMS top-nav headings: Dashboard, Planning, Vendors & Staff, Marketing & Social, Venues, Settings, Tools.
- Kept the left rail concise as section launchers, not a full page list.
- Added grouped active-state support so detailed registry sections still highlight the correct compact heading.
- Preserved registry-driven top-nav and All VMS Pages discovery for secondary pages including Event Command Center and Square Sync Protection.

## 0.2.24.592 — Compact menu discovery correction

- Corrected the 0.2.24.591 overcorrection so the WordPress VMS left rail remains limited to durable section launchers.
- Stopped registry `left_menu` metadata from forcing individual feature pages back into the left rail during the compact-menu pass.
- Marked Event Command Center and Square Sync Protection for registry-driven top-nav/directory discovery instead of left-rail visibility.
- Preserved direct URLs, VMS shell rendering, active top-nav clustering, and All VMS Pages discovery for both pages.
- No ticketing, Square payment, catalog sync, product sync, Event Plan save, or Express Bar behavior was changed.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, Codex handoff, revision log, and packaged test-plan notes.

## 0.2.24.591 — Registry-driven left-menu visibility

- Replaced the one-off visible-page exception behavior with a registry-driven left-menu visibility pass.
- Added a helper that collects pages with `left_menu => true` from the VMS admin menu registry so core/add-on pages can opt into the WordPress VMS left menu without patching the compact menu renderer.
- Marked Event Command Center and Square Sync Protection as visible through the registry metadata instead of hardcoded page pins.
- Kept compact-menu behavior intact: lower-priority pages stay available through All VMS Pages/top nav/direct URLs unless they explicitly opt into left-menu visibility.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, Codex handoff, revision log, and packaged test-plan notes.

## 0.2.24.588 — Admin menu shell repair + Data Tools shell integration

- Fixed the Guided Tours fatal by making the page-content renderer callable by the shared VMS admin shell.
- Wrapped the companion Data Tools home renderer in the VMS admin shell so `admin.php?page=vms-data-tools` inherits the VMS top navigation/theme instead of appearing as a separated Tools screen.
- Added VMS parent/submenu highlighting helpers so registered VMS pages keep the VMS parent active even when a companion plugin also registers a legacy Tools submenu.
- Removed the legacy `Tools > VMS Data Tools` submenu entry when present while preserving direct access through VMS and All VMS Pages.
- Added section metadata to durable left-rail specs so hidden/module pages can resolve back to the correct visible rail section.
- Tightened VMS top-nav quick-menu widths so dropdowns align with their corresponding tabs instead of using a detached fixed minimum width.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, and packaged test-plan notes.

## 0.2.24.586 — Admin menu rail tightening

- Tightened the admin menu registry pass so the WordPress VMS left rail only shows durable section pages.
- Preserved all legacy/module/add-on submenu registrations for direct access and All VMS Pages discovery.
- Changed registry-created pages to avoid left-rail visibility by default; add-ons should opt in only for durable section-level pages.
- Added test plan and Codex handoff for staging verification.

## 0.2.24.584 — Square Sync Protection firewall
- Added a VMS Square Sync Firewall that automatically protects VMS/TEC ticket, admission, pass, and event add-on Woo products from Square catalog/inventory sync.
- Added **VMS > Square Sync Protection** with Scan, Repair, report summary, and CSV download so protected products can be cleaned up in bulk without opening products one by one.
- Repair forces protected products to `Sync with Square = no`, clears stale Square item/variation/image/version metadata, and stamps a VMS protection reason for audit visibility.
- Runtime product-save and marker-meta guards keep future VMS-generated ticket/add-on products protected after Event Plan commits, rebuilds, and republish/repair flows.
- Hardened the existing ticketing reporting-category Square bridge so protected VMS products are protected instead of queued for Square sync if that bridge is ever deliberately enabled.
- Scope is intentionally narrow: Square-owned normal catalog products such as bar/menu items, shirts, eggs, and merch are skipped unless they carry VMS/TEC ticketing signals.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, and packaged test-plan notes.

## 0.2.24.583 — Email Follow-Ups foundation
- Added a core **Email Follow-Ups** module under **VMS > Email Follow-Ups** and linked it from the Marketing & Social hub.
- Added MailPoet API detection with safe status messaging, optional MailPoet subscriber/list/tag sync, and WordPress email delivery so MailPoet can handle transport when configured for site emails.
- Added event-aware default templates for **Know Before You Go**, **Day-of Reminder**, **Post-Event Thank You**, and **Weather / Event Update** with event/date/time/venue/customer tokens.
- Added a Preview & Test screen that resolves upcoming Event Plans, shows eligible ticket-buyer recipients from the existing Woo/TEC ticket sales resolver, renders the email copy, and supports safe admin test sends.
- Added guarded manual recipient sends with a confirmation checkbox, duplicate-send protection, skip handling for unpublished/cancelled/missing-date events, and lightweight option-based logs.
- Added an hourly scheduler that is installed but **off by default** until automatic scheduled sends are explicitly enabled after staging validation.
- Scoped Overview and Template saves so global toggles and template toggles do not reset each other while editing the new screen.
- Added a guided tour for the new module and packaged a dedicated Codex/staging test plan.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, revision log, and packaged test-plan notes.

## 0.2.24.582 — GA ticket product image repair carried onto staffing-template build
- Merged the **0.2.24.580 ticket product image repair** forward onto **0.2.24.581 staffing template per-role thresholds** so the staffing work is preserved while GA ticket order thumbnails are restored.
- GA ticket image resolution now falls back from the Event Plan featured image to the linked TEC event image, then to the primary vendor image as a legacy fallback.
- Ticketing Image Tools now sync GA ticket products along with entitlement / qualified ticket / add-on products.
- Checkout now self-heals GA ticket Woo product images when GA order lines are created, improving customer order/email thumbnails without requiring a manual repair first.
- Added legacy detection for older VMS-linked TEC ticket products that have Event Plan + TEC ticket markers but are missing the newer `product_role` value.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, Codex handoff, and packaged test-plan notes.

## 0.2.24.581 — Staffing template per-role attendance triggers
- Added **Activate at attendance** to each staffing template role slot so one template can scale individual roles without duplicating the whole template for each guest-count step.
- Added a schema migration for `activation_threshold` on staffing template slots and upgraded both activation-time and runtime migration paths through **vendor core v6** so existing installs pick up the new column safely.
- Template apply / auto-seed now carry each slot’s attendance trigger into the Event Plan role-threshold meta, so applied templates preserve role-by-role staffing activation behavior.
- Preserved template-wide attendance bands as optional outer scope, while letting role slots inside the template turn on at different guest counts.
- Tightened template apply behavior so **Replace staffing from template** can fully clear and re-seed an event even when the selected template has no slots.

## 0.2.24.580 — Staffing anticipated-guests sourcing + operator copy cleanup
- Staffing guest-count thresholds now prefer a live ticket-sales read for the Event Plan when ticket product links are available, instead of trusting a stale cached ticket-stats snapshot.
- Fixed the Event Plan staffing summary so real sold tickets are no longer hidden behind a stale `0` headcount when an event already has live ticket sales.
- Reframed staffing copy into operator language: **Anticipated guests**, **Needed now**, **Needed at X+ guests**, and guest-count-based helper text now replace internal phrases like **Current wired attendance** and **True headcount** on the staffing screen.
- Updated staffing template alerts and helper labels to speak in guest-count language instead of internal attendance wiring terminology.

## 0.2.24.579 — Qualified ticket claiming UX + regression pass
- Renamed the public qualified-ticket guest-email action from **Verify** to **Add Qualified Guest** so checkout no longer implies someone is registering or newly verifying from the purchase page.
- Added a compact expandable helper near qualified-ticket claims explaining that each guest must register and be approved separately, and that one buyer can then enter each approved guest email during one transaction.
- Updated the qualified-ticket claim rows to show clearer statuses: malformed emails get normal validation, approved emails show an **Added:** success state, unknown and unapproved emails explain that the guest must register and be approved first, and duplicate guest emails now surface a direct **This guest email has already been added.** message.
- Blocked duplicate qualified guest emails in both the front-end claim UI and the cart/checkout validation path so the same approved guest cannot be claimed twice in one transaction.
- Preserved the existing TEC quantity controls, unified ticket layout, subtotal calculations, add-on threshold behavior, inventory behavior, and checkout gating while tightening only the qualified-ticket claiming UX/copy path.

## 0.2.24.578 — Staffing template replace semantics + expected attendance sourcing
- Event Plan staffing now treats omitted roles as **0 / not in use** once an event already has saved staffing data or an applied staffing template, instead of falling back to the Staff Role default headcount. This makes **Replace staffing from template** behave as operators expect when a role is intentionally left out of the template.
- Staffing attendance thresholds now prefer **expected attendance** from ticket sales and admissions before falling back to true headcount, so a saved true-headcount value of `0` no longer suppresses sold-ticket counts.
- Updated staffing attendance labels to identify expected-attendance sourcing more clearly in the Event Plan UI.
- Suppressed stale staffing validation notices when an operator applies a template in **Replace staffing from template** mode during the same save cycle, so warnings from pre-template defaults do not muddy the result.

## 0.2.24.577 — Staffing template UI parity + layout polish
- Reworked the Staffing Template role-slot editor from one long horizontal row into a multi-row responsive card layout.
- Added conditional time-mode visibility on template slots so Absolute vs Relative fields mirror the Event Plan screen.
- When Duration is entered, the current-mode end fields now hide in the template editor just like Event Plans.
- Renamed template slot **Headcount** to **Staff needed** for wording parity with Event Plans.

## 0.2.24.576 — Verified credential profile controls

- Added **VMS Verified Ticket Credentials** to WordPress user profiles for admins / verification managers so verified ticket eligibility can be manually approved or revoked per user and per program.
- Kept the existing proof-review queue as the normal customer-submission workflow while adding profile controls for support corrections and operator testing.
- Fixed verified credential revocation durability by removing the matching `vms_verified_*` role as well as user meta.
- Stored lightweight manual-change audit metadata for profile-based credential updates.

## 0.2.24.575 — Staffing template migration repair + qualification severity fix

- Re-enabled the canonical plugin activation hooks and explicitly load `includes/activation.php` from the plugin root before registering them.
- Updated the runtime DB migration path so normal plugin loads advance through **vendor core v5**, allowing staffing-template attendance-band columns to be created on installs that skipped activation-based upgrades.
- Added a staffing-template save preflight that re-checks the template table schema and runs the v5 migration before writing `min_headcount` / `max_headcount`.
- Fixed staffing qualification evaluation so mixed rule severities are computed from the **actually missing requirements** for a staff member instead of collapsing to the role's highest possible severity.
- Preserved hard-block enforcement for genuinely hard-blocked missing requirements while letting warn-only and soft-block-only misses remain assignable with warnings.
- Stopped role-save normalization from automatically rewriting the role-level fallback severity upward based on the highest rule severity.

## 0.2.24.574 — Staffing UX refinement + duration logic + per-requirement qualification enforcement

- Renamed the Event Plan staffing labels so operators now see **Staff needed** and **Activate at attendance** instead of two different uses of “headcount.”
- Updated the staffing state pill/copy to speak in **attendance** language and made the required warning line match the new labels.
- Made **Duration** behave like a real alternative to end fields: Event Plans now treat Duration as a valid replacement for Shift end / End anchor timing, save-path validation no longer falsely requires both end fields when Duration is present, and slot-window resolution now honors Duration in both absolute and relative timing flows.
- Added conditional Event Plan timing visibility so the screen hides absolute vs relative timing controls based on Time mode and hides the current-mode end fields whenever Duration is being used.
- Reworked **Staff profile Qualifications / Licenses** from one long horizontal row into a clearer multi-row card layout that fits normal admin widths.
- Reworked **Staff Role required qualifications** from a plain textarea + one shared warning level into repeatable requirement rows with per-qualification enforcement (**Warn only / Soft block / Hard block**).
- Preserved compatibility with previously saved role qualification data by normalizing legacy string lists into the new structured rules model on read/save.

## 0.2.24.573 — Staffing template apply + attendance bands + staff qualifications

- Added an explicit **Apply Staffing Template** control on Event Plans with a template selector and safe apply modes for **Merge missing roles only** or **Replace staffing from template**.
- Broadened staffing auto-seed so empty Event Plans can pick up a matching template outside the narrow Schedule-only creation path.
- Added optional staffing template **attendance bands** (`min_headcount` / `max_headcount`) and Event Plan summary/review alerts when the applied template is outgrown, another template is recommended, or the next staffing threshold is close.
- Expanded staffing slot timing so both templates and Event Plans can use **relative timing** with start/end anchors, offsets, and duration instead of only absolute start/end times.
- Added **staff qualifications / licenses** on Staff profiles, **required qualifications** on Staff Roles, and role-level warn / soft-block / hard-block behavior so invalid assignments can be surfaced or blocked before save.
- Added a schema migration for staffing template attendance-band columns and kept rollup refresh / audit behavior in sync when templates are applied.
- This pass does **not** add true pace-based predictive staffing forecasts yet; the new alerts are based on current wired headcount, thresholds, and applied-vs-recommended template differences.

## 0.2.24.572 — Public calendar destination setting for cancellation notices

- Added a VMS Settings → Public Calendar **Customer-facing event calendar link** control with a WordPress Page dropdown and optional advanced custom URL override.
- Added a shared public calendar URL resolver used by customer-facing event links.
- Resolver order is: custom URL override, selected published WordPress page, stored VMS public calendar page, auto-detected `/events-calendar` VMS page/`[vms_public_calendar]` page, TEC events archive, then home page.
- Updated the public cancelled-event banner's **Browse upcoming events** link to use the resolver instead of hardcoding TEC's `/events` archive.
- Preserved support for operators who want TEC as their primary public calendar by leaving Auto-detect / custom URL behavior available.
## 0.2.24.571 — Cancellation notification polish + public calendar ribbon removal

- Removed the cancelled/rescheduled image ribbon from the VMS public venue calendar view so a cancellation-heavy month does not make the venue look abandoned or out of business.
- Added a per-Event Plan **Primary vendor email message** field in the Cancellation section and stores it as `_vms_cancel_vendor_message`.
- Updated cancellation jobs so `status_only` cancellations still skip provider/refund work but can run notification emails.
- Updated cancellation notification emails so primary vendors receive the custom event-specific message, while staff/secondary/supporting recipients receive a standard assigned-person cancellation notice.
- Stopped including internal cancellation policy/reason/note details in outgoing vendor/staff cancellation emails.
- Kept cancellation job audit/idempotency behavior and rescheduled-draft clearing intact.

## 0.2.24.545 — Checkbox add-on UI polish
- reduced checkbox visual footprint and kept a clear native checkbox look
- changed checkbox CTA label from Select to Reserve
- moved qualifying/unlock helper text next to the control area
- emphasized qualifying-rule messaging for faster scanning

## 0.2.24.542 — Editable ticket/add-on help copy + checkbox-style add-on controls
- Added **global default public help copy** in **VMS → Settings → Ticketing** for one editable explanation block above tickets and another above add-ons.
- Added **per-event override textareas** in **Event Plan → Advanced Controls → Ticketing** so operators can customize either help box for a specific show without changing the sitewide default.
- Added an optional **add-on control mode** for entitlements so bundle-style reserved items such as fire pits can use a **single Reserve checkbox** instead of a quantity stepper, while normal add-ons keep the existing quantity controls.
- Wired the new help copy into the public front-end ticket flow and kept the selector-mode setting out of sync-hash drift so copy/control-mode changes do not create false Preview → Commit mismatch loops.

## 0.2.24.539 — Vendor intro video review workflow + Event Command Center source control
- Changed headliner intro videos from instant-live vendor uploads to a **submit for review** workflow so raw phone clips no longer have to become public the moment a vendor uploads them.
- Added **Event Command Center → Promo Video Control** so operators can approve a vendor-submitted clip, upload a replacement file, clear the live promo, or switch the public source to an external video link such as YouTube or Vimeo.
- Extended public and vendor-facing promo rendering to support either a native uploaded file or an approved external video URL, while keeping the soft-requirement vendor onboarding flow aware of submitted vs live promo status.

## 0.2.24.534 — Added CTA button beside photo-grid date badge
- Added a compact CTA button to the VMS native photo-grid card layout so the empty space beside the date badge now carries a clear action instead of dead space.
- Active events now show **Tickets**; rescheduled events point to the replacement listing with **View New Date**; cancelled events fall back to **View Details**.
- Kept the previously restored photo-grid polish from **0.2.24.533** and limited this pass to the VMS native photo-grid surface.

## 0.2.24.533 — Reapplied VMS photo-grid polish on latest baseline
- Reapplied the **0.2.24.525** front-end polish pass to the current **0.2.24.532** baseline after the photo-grid styling was overwritten by a later zip.
- Restored the tighter **VMS native photo-grid** card spacing, lighter card chrome, compact date badge, status pill treatment, and subtle hover polish.
- Kept the newer **live refund edit-screen routing** fixes from **0.2.24.532** intact; this pass is presentation-only for the VMS photo-grid surface.

## 0.2.24.532 — Live refund action routed through canonical edit-screen request

- Replaced the cancelled-plan live-refund rerun link so it targets the normal Event Plan edit screen with a dedicated `vms_live_refund_now=1` action flag instead of relying on `admin-post.php`.
- Added an `admin_init` live-refund request handler so the action always runs in the fully loaded Event Plan admin context, avoiding the blank-screen/no-handler failure on the standalone path.
- Keeps the refund rerun isolated from normal post saves while preserving the existing nonce/capability checks and redirect back to the Cancellation Job panel.

## 0.2.24.531 — Live refund action ID resolution hardening

- Accept the standalone live-refund action when the request arrives with either an Event Plan ID or a linked TEC event ID.
- Resolve revision/linked-event contexts to the canonical `vms_event_plan` before running refunds.
- Keep the standalone refund button out of the main save flow while adding better diagnostics for invalid request IDs.

## 0.2.24.531 — Live refund action Safari-safe link routing
- Replaced the nested standalone refund form with a direct nonce-protected admin-post link for **Run Live Refunds Now**.
- Fixes mobile Safari and other browsers that discard or relocate nested forms inside the WordPress post editor form.
- Confirmation still appears before navigation, but the action no longer depends on an inner hidden form existing in the DOM.

## 0.2.24.528 — Standalone cancelled-plan live refund action
- Reworked **Run Live Refunds Now** so it no longer submits the main Event Plan editor form.
- The refund rerun control now launches a dedicated `admin-post.php` request with its own nonce and confirmation.
- This avoids unrelated Event Plan save validation, staffing notices, and false “not confirmed” refund messages.
- The action now returns directly to the Cancellation Job panel with refund result notices only.

## 0.2.24.526 — Cancelled-plan live refund rerun button
- Added a native **Run Live Refunds Now** action on already-cancelled Event Plans when the cancellation policy is refund-capable.
- The new action re-queues refund discovery/execution, forces explicit live-refund confirmation again, and immediately runs the cancellation refund pass without requiring the event to be uncancelled.
- Safe guardrails remain intact: already refunded lines are skipped, mixed/unsupported orders stay in manual review, and the job log records the manual live-refund request plus any policy upgrade from queue-only to live auto-refund.


## 0.2.24.522 — Reschedule ticketing + broader public reschedule visuals
- Fixed rescheduled Event Plan drafts so they no longer inherit the cancelled source plan's forced ticketing-off override; replacement drafts now start with event-level ticketing set to **On** for review.
- Updated the public cancelled/rescheduled single-event banner copy to read: **“This event has been rescheduled. Please see updated details below.”**
- Broadened the public cancelled/rescheduled image overlay from a small corner pill to a much more obvious diagonal ribbon treatment.
- Applied the cancelled/rescheduled ribbon logic to front-end TEC featured images more broadly, so event cards/widgets using the featured image can show the same status ribbon outside the single-event page.
- Updated the VMS public venue calendar to include cancelled Event Plans, show cancelled/rescheduled image ribbons on calendar artwork, and swap the popup CTA from **Get Tickets** to **View Details** for cancelled entries.
## 0.2.24.504 — Event Plan staff render-context extraction
- Extracted the Event Plan **staff render-context preparation** block out of `includes/cpt/event-plans.php` into a dedicated private helper: `get_event_plan_staff_render_context()`.
- Preserved the existing Staff partial, layout shell, and render flow by rehydrating the same variables immediately before `partials/staff.php` is captured.
- No CSS, no layout changes, and no save-path behavior changes were made in this pass.

## 0.2.24.503 — Event Plan Supporting Vendor wording cleanup
- Updated remaining Event Plan lineup UI strings from **Supporting Entry / Supporting entries** to **Supporting Vendor / Supporting vendors** where surfaced in the add button, summary card, lineup card labels, and empty supporting fallback text.

## 0.2.24.502 — Event Plan Primary Entry wording cleanup
- Updated remaining Event Plan / lineup UI strings from **Primary Entry** to **Primary Vendor** where surfaced in summary cards, unassigned labels, and lineup warnings.

## 0.2.24.501 — Event Plan Primary Vendor wording cleanup
- Updated the remaining Event Plan UI eyebrow from **Primary Entry** to **Primary Vendor**.
- No CSS changes. No layout changes. No behavior changes.

---
title: 05 Revision Log
slug: revision-log
since: 0.2.24.455
---

# Revision Log

## 0.2.24.495 — Event Plan UI parity recovery to 0.2.24.479 baseline

- Restored the Event Plan editor render shell in `includes/cpt/event-plans.php` to the last confirmed visually-correct `0.2.24.479` baseline.
- Restored `includes/cpt/event-plans/partials/staff.php` to the matching `0.2.24.479` card-shell version so the lower editor sections no longer depend on the later collapsible-section wrapper path.
- Intention: recover known-good Event Plan UI structure without discarding unrelated later fixes already present elsewhere in the plugin, including the public-calendar current-month past-events hotfix carried in `0.2.24.492`.

## 0.2.24.492 — Public calendar current-month past-events visibility hotfix

- Updated `includes/public/venue-calendar-shortcode.php` so the public calendar now includes past events for the **current month** as well as prior months.
- This closes the month-boundary/archive gap where older months preserved passed events but the in-progress current month still hid already-passed dates.
- Also switched the default public-calendar month seed to WordPress site time instead of GMT to avoid future month-boundary drift.

## 0.2.24.489 — Event Plans Phase 1K: Secondary Vendors server-shell wrapper extraction

- Moved the Event Plan **Secondary Vendors** server-rendered collapsible shell/wrapper into `includes/cpt/event-plans/partials/secondary-vendors-section.php`.
- Updated `includes/cpt/event-plans.php` to capture/output that wrapper partial while keeping the already-extracted inner editor block in `partials/secondary-vendors.php`.
- Intent: a smaller render-only follow-up that shrinks `event-plans.php` without touching save-path logic or the server-rendered collapsible-shell behavior restored in `0.2.24.486`.

## 0.2.24.486 — Event Plans hotfix: server-rendered collapsible shells for Secondary Vendors and Staff

- Added a server-rendered collapsible shell for the Event Plan **Secondary Vendors** section in `includes/cpt/event-plans.php`.
- Added a server-rendered collapsible shell for the Event Plan **Staff** section in `includes/cpt/event-plans/partials/staff.php`.
- Updated `includes/cpt/event-plans/partials/editor-scripts.php` so the shared collapsible initializer now wires existing `.vms-collapsible-section` shells and only wraps remaining bare-title sections.
- Intent: restore reliable Secondary Vendors and Staff collapse/toggle behavior without changing save-path logic.

## 0.2.24.482 — Event Plans Phase 1I hotfix: restore compensation setup execution

- Fixed a packaging regression introduced after the basic-details extraction where the compensation setup block in `includes/cpt/event-plans.php` was left outside PHP execution.
- That regression prevented the default-pay / acknowledgment variables from being computed before the compensation partial rendered, which could fatal the Event Plan editor through `partials/comp-ack.php`.
- Restored the compensation setup block to execute before the extracted render partials so the editor can render the downstream sections normally again.

## 0.2.24.480 — Event Plans Phase 1H: Basic details + notices render extraction

- Moved the Event Plan **basic details** render block, including integrity/prefill notices and the date/venue/holiday UI, into `includes/cpt/event-plans/partials/basic-details.php`.
- Updated `includes/cpt/event-plans.php` to capture/output that partial instead of carrying that full section inline.
- Intent: render-only extraction with no deliberate save-path rewrite.

## 0.2.24.479 — Event Plans Phase 1G: Primary Vendor Compensation render extraction

- Moved the Event Plan **Primary Vendor Compensation** render block, including its tile-sync script and Draft Pay editor markup, into `includes/cpt/event-plans/partials/compensation.php`.
- Updated `includes/cpt/event-plans.php` to capture/output that partial instead of carrying that full section inline.
- Intent: render-only extraction with no deliberate save-path rewrite.

## 0.2.24.478 — Docs protocol update: tracker continuity in packaged test plan

- Added an explicit **Tracker continuity protocol** section to `docs/06-test-plan.md`.
- Codex is now expected to paste the exact changed block for any packaged continuity-tracker file it edits (`backlog.txt`, `bugs.txt`, `VMS_MASTER_HANDOFF.txt`, `VMS — Market Readiness Checklist (CANONICAL).txt`, `future_enhancements.txt`, `idea_pad_context.txt`).
- Future packaging passes must carry those tracker deltas forward into the next zip even if the user does not re-upload the files.
- No runtime code changed in this pass; runtime behavior intentionally matches `0.2.24.477`.

## 0.2.24.477 — Event Plans Phase 1F: Staff render extraction

- Moved the Event Plan **Staff** render block into `includes/cpt/event-plans/partials/staff.php`.
- Updated `includes/cpt/event-plans.php` to capture/output the Staff partial instead of carrying that full section inline.
- Carried forward the local continuity fix so packaged `docs/backlog.txt` again references `serenaderange.local:10004` for the OPS-06 live runtime note.
- Updated version markers to `0.2.24.477`.

## 0.2.24.476 — Event Plans Phase 1E: Advanced Controls render extraction

- Extracted the Event Plan **Advanced Controls** render block into `includes/cpt/event-plans/partials/advanced-controls.php`.
- Moved the legacy/imported calendar + ticketing troubleshooting UI, linked TEC event info, Ticketing v2 host area, and calendar warning override controls out of the main `event-plans.php` monolith.
- Intent: render-only extraction with no deliberate save-path rewrite.

## 0.2.24.475 — Guided tours visible-chrome fallback hardening

- Tightened `assets/js/vms-tours-runtime.js` so manual-launch buttons verify that tour chrome actually became visible and immediately trigger the inline fallback when the registered launch path stalls silently.
- This closed the remaining Event Plan inline Vendor Guest **Start Guided Tour** regression.

## 0.2.24.474 — Canonical continuity docs restore

- Restored the canonical continuity binder into `vms/docs/`, including the Market Readiness Checklist, handoff template/path, bugs, backlog, future enhancements, idea pad context, add-on convention, and keys/constants registry.
- No runtime code changes were made in this pass; runtime behavior intentionally matched `0.2.24.473`.

## Notes

- Earlier stabilization entries still exist in prior packages/thread history.
- The current active stream is Event Plans Phase 1 render extraction with real Codex checkpoints after each meaningful editor slice.

## 0.2.24.482 — Event Plans Phase 1i: editor scripts partial
- Extracted the inline Event Plan editor script host from `includes/cpt/event-plans.php` into `includes/cpt/event-plans/partials/editor-scripts.php`.
- Intended scope: structural only; no behavior change.


## 0.2.24.499 — Event Plan wording cleanup: remaining Band copy to Primary Vendor

- Updated remaining operator-facing Event Plan wording from **Band** to **Primary Vendor** where this was still visible in notices, validation text, tax helper copy, and lineup helper copy.
- No CSS, layout, or placement changes were made in this pass.
- Intended behavior matches `0.2.24.498`, with wording cleanup only.


## 0.2.24.500 — Event Plan primary vendor label alignment

- Updated the primary lineup/vendor field label from **Vendor** to **Primary Vendor**.
- Updated the primary vendor select placeholder from **Select Primary Entry** to **Select Primary Vendor**.
- No CSS, layout, behavior, or save-logic changes were made in this pass.


## 0.2.24.505 — Event Plan compensation render-context extraction

- Extracted the Event Plan compensation acknowledgment/render-context preparation into a dedicated helper in `includes/cpt/event-plans.php`.
- Left the compensation partial, section markup, and styling unchanged to preserve the stable Event Plan shell from `0.2.24.504`.
- Intended scope: behind-the-scenes refactor only; no deliberate UI or save-path behavior change.


## 0.2.24.506 — Event Plan vendor-default drift visibility

- Added Compensation-section visibility when the live Primary Vendor default no longer matches the saved Draft Pay on an Event Plan.
- Added an explicit **Apply current Primary Vendor default to Draft Pay** action so operators can adopt the updated vendor default without any silent overwrite.
- Kept the Event Plan shell, section markup, and CSS unchanged; scope is behavior/visibility inside the existing compensation section only.


## 0.2.24.507 — Event Plan vendor-default source transparency

- Expanded the Compensation drift card so it explains **which vendor-default source actually won** (global, venue-specific, or venue + day).
- Added a source ladder and a per-field diff list so operators can see exactly why the card triggered, even when the summary line looked similar.
- Kept scope inside the existing Compensation notice only; no Event Plan layout-shell or CSS changes.

## 0.2.24.521 — Cancelled public-event reschedule messaging
- Updated the public TEC cancelled-event banner so it promotes **Event Rescheduled** when the cancelled Event Plan has a linked replacement Event Plan with a live public event URL.
- Added a prominent **View New Date** link on the cancelled public event page, while keeping **Browse upcoming events** as a secondary path.
- Updated the cancelled-event featured-image overlay so it switches from **Cancelled** to **Rescheduled** when a live replacement event is available.

## 0.2.24.520 — Cancel/reschedule follow-up fixes
- Fixed the one-save cancel-and-reschedule path so the post-save redirect lands directly in the newly created replacement Draft editor.
- Fixed the replacement-plan creation flow so both the one-save and already-cancelled fallback paths keep the new plan in **Draft** instead of inheriting **Cancelled** state.
- Hardened the Event Plan save path against nested replacement-post saves reusing the source request action/state.

## 0.2.24.519 — Cancel-and-reschedule in one save
- Moved the replacement-date reschedule bridge into the live **Mark Cancelled** workflow.
- The Cancellation section now shows **Replacement date** before cancellation, so operators can cancel and spin off a linked replacement draft in the same save.
- If a replacement date is entered when **Mark Cancelled** is clicked, VMS now cancels the source plan, preserves audit history, creates the linked Draft Event Plan, and redirects into the new draft.
- Kept the post-cancellation **Create Rescheduled Draft** action for already-cancelled plans, but the main path no longer requires a second save.

## 0.2.24.518 — Cancelled Event Plan reschedule bridge

- Added a native **Create Rescheduled Draft** action on cancelled Event Plans.
- The new action creates a linked Draft replacement plan instead of forcing a full manual rebuild.
- VMS now carries forward the planning details that are usually still useful, while clearing live calendar/ticket/sales/cancellation state on the replacement draft for safety.
- Added cross-links between the cancelled source plan and the replacement draft so operators can follow the audit trail quickly.


## 0.2.24.525 — True live cancellation auto-refunds
- Enables production-safe live auto-refunds for cancelled Event Plans when the operator explicitly confirms the cancellation.
- Refund execution now asks WooCommerce to refund the payment gateway, calculates ticket line subtotals and taxes separately, and queues unsupported/manual-refund orders for review instead of pretending they were auto-refunded.
- Cancellation job UI now shows refund eligibility, estimated refund totals, clearer manual-review reasons, and a stronger warning before a live auto-refund cancellation is submitted.

## 0.2.24.524 — VMS native public photo-grid shortcode
- Added a new public shortcode: **`[vms_events_photo]`** with an alias of **`[vms_events_photo_grid]`**.
- The new shortcode renders VMS-controlled public event cards so the homepage photo block no longer has to rely on TEC photo-view markup.
- Rescheduled and cancelled Event Plans now carry the same diagonal overlay treatment inside the VMS photo grid, with cancelled/rescheduled status driven by VMS plan state instead of TEC card templates.
- Default homepage swap target: replace **`[tribe_events view="photo"]`** with **`[vms_events_photo limit="4"]`**.

## 0.2.24.536 — VMS photo-grid symmetry cleanup
- Reworked the VMS native photo-grid card body back into a centered vertical stack so the card layout reads title → date → CTA instead of a side-by-side split.
- Restored the primary active-event CTA label to **Get Tickets** and increased button size for a cleaner, more familiar card rhythm.
- Simplified the card/button hover behavior so the grid feels calmer and avoids flashy color/animation changes.

## 0.2.24.563 — Cancellation auto-refund add-ons
- Expanded cancellation refund discovery to include VMS-linked event add-ons/entitlements in addition to TEC ticket products.
- Added a guarded event-product matcher that requires Event Plan, TEC event, ticket, sync-map, or order-item snapshot linkage before any non-ticket line can be refunded.
- Updated refund execution to re-check live per-line refunded quantities before creating a refund, protecting retry runs from duplicate line-item refunds while allowing newly discovered event add-ons to be refunded later.
- Updated cancellation sales-stop so VMS event add-on products are closed along with ticket products.
- Added `vms-test-plan-0.2.24.563.md` with refund/add-on regression coverage.

## 0.2.24.564 — Event Credit foundation
- Added optional Event Credit handling for cancelled Event Plans without replacing the customer refund path.
- Added private `vms_event_credit` records, Woo coupon generation, cancelled Event Plan issuance controls, duplicate-credit protection, email option, redemption sync, and void handling.
- Added Event Credit test coverage requiring local/Codex WooCommerce verification before production use.

## 0.2.24.565 — Event Credit fixed-cart coupon guardrails
- Reconciled the live Codex repair for Event Credit coupon validation after testing showed a fixed-cart coupon could be accepted on an unrelated cart.
- Added/retained cart-level and coupon-validity checks so Event Credit coupons are not treated as generic cart-wide discounts and are rejected when the cart contains no eligible future event products.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, backlog notes, and this revision log so the packaged zip matches the patched code.
- Process reminder: after any Codex code repair, bump the package version/build markers and record the change notes before handing off a zip.



## 0.2.24.570 - Vendor portal pattern preference reload fix

- Repaired the vendor portal Availability tab so a saved `pattern` preference stays selected after the next page load instead of falling back to the Manual section.
- Aligned the vendor portal read path with the already-supported saved values written by the existing pattern availability form.
- Confirmed the dashboard add-on hook, Agreements dashboard/tab integration, empty-state handling, and admin preview flow still behave as expected after the repair.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, test-plan docs, and package slug for the repair build.

## 0.2.24.569 - Vendor portal add-on dashboard hook

- Added a stable `vms_vendor_portal_dashboard_after_cards` action after the native vendor portal dashboard grid.
- Provides premium/companion add-ons, including VMS Agreements, a first-class place to render self-contained vendor dashboard cards without shortcode placement.
- Kept existing vendor portal routing, nav links, custom-tab filter, availability, profile, tax, tech docs, opportunities, and event history behavior unchanged.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, and packaged test docs so the integration hook package is traceable.

## 0.2.24.568 - Final payment terms foundation

- Added Event Plan final payment terms to core compensation: expected final payment timing, days-after-event offset, specific pay date, custom timing text, payment method, and custom method label.
- Added payment method option `ACH / Direct Deposit` using canonical key `ach_direct_deposit`.
- Included final payment terms in `vms_get_event_plan_comp_terms()`, compensation hashes, package/manual Locked Pay snapshots, and human-readable Locked Pay summaries.
- Added canonical meta keys for final payment terms so agreement/proposal add-ons can consume the same source of truth instead of inventing separate payment wording.
- Updated deposit treatment wording from “Creditable toward final pay” to clearer “Applies toward total payment.”

## 0.2.24.567 — Event Plan deposit compensation foundation
- Added event-level Vendor Deposit / Advance fields to Event Plan Draft Pay: amount, status, treatment, due date, paid date, and notes.
- Stored deposits as dedicated Event Plan meta keys and registered them in the VMS key registry/map so future agreement/proposal work has stable canonical keys.
- Included deposit terms in `vms_get_event_plan_comp_terms()`, compensation hashes, package/manual Locked Pay snapshots, and human-readable compensation summaries.
- Preserved deposit terms when applying a comp package, so package/default pay can be refreshed without silently wiping event-specific deposit details.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, and packaged test-plan notes.

## 0.2.24.566 — Agreement/proposal planning docs package
- Added `docs/12-agreement-contract-roadmap.md` to capture the Agreement / Contract / Proposal architecture before implementation begins.
- Documented the core-vs-premium split: VMS core owns cancellation policy profiles, deposits, riders, proposal acknowledgements, clause bypass audit, and nonperformance review data; `vmsx-agreements` later renders PDFs/agreement packets and signed-copy/DocuSign handoff.
- Updated `docs/backlog.txt`, `docs/future_enhancements.txt`, `docs/idea_pad_context.txt`, `docs/idea_pad_contaxt.txt`, `docs/03-feature-enhancements.md`, `docs/11-canonical-continuity-docs-index.md`, and `docs/vms_add-on_convention.md` with the new roadmap references.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, and packaged test-plan notes so this docs/planning zip is clearly distinguishable from `0.2.24.565`.
- No functional runtime behavior was intentionally changed in this pass.

## 0.2.24.585 — Admin menu registry and page directory

- Added a first-pass VMS admin page registry so future add-ons can register admin pages through `vms_register_admin_page()` and `vms_admin_register_pages` instead of hardwiring against the current menu internals.
- Added an **All VMS Pages** directory / health screen at `vms-admin-pages` to surface VMS core, module, and add-on admin pages even when they are not primary left-menu items.
- Updated the compact left-menu behavior so known secondary core pages may still be compacted, but unknown future add-on slugs are not silently assigned the hidden-menu class.
- Reframed the left-menu primary items into durable functional sections: Dashboard, Events & Schedule, Tickets & Admissions, Vendors & Staff, Marketing & Sales, Reports & Finance, Venue Setup, Tools & Integrity, and Settings & Add-ons.
- Moved touched menu styling out of PHP inline output and into `assets/css/vms-admin-ui.css`.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, and packaged test-plan docs.

## 0.2.24.586 — Admin menu rail tightening

- Tightened the new admin menu registry behavior so the visible VMS left rail stays limited to durable section entries instead of exposing every legacy/module/add-on page.
- Kept direct URLs and WordPress page hooks registered for legacy/module/add-on screens while surfacing them through **All VMS Pages**.
- Added an escape-hatch filter for intentionally visible secondary slugs without returning to the old hardcoded sprawl.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, and packaged test-plan docs.

## 0.2.24.587 — Admin menu registry metadata migration

- Centralized durable left-rail section specs in the admin menu registry layer so the compact menu no longer owns its own separate hardcoded section list.
- Added registry metadata for existing direct/legacy VMS admin pages with `register => false`, preserving current callbacks and direct URLs while improving discovery/classification in **All VMS Pages**.
- Added a search field to **All VMS Pages** so operators can find hidden/module/add-on pages by page name, section, source, or slug.
- Preserved the clean 9-entry left rail established in `0.2.24.586` and avoided a risky full rewrite of every `add_submenu_page()` call in one pass.
- Updated plugin header version, `VMS_VERSION`, `vms-build.txt`, handoff notes, add-on convention docs, and packaged test-plan docs.
