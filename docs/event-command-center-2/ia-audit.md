# ECC 2.0 information architecture and route audit

Audited baseline: `5200bebbf0eb12c70c2fb56e367ff678b1f32853`, clean preflight on `work/event-command-center-2` in `/private/tmp/bvm-ecc2-20260907/packages/vms-github-reconcile`; disposable sibling `/private/tmp/bvm-ecc2-20260907/vms` present, protected stash present. Line references below describe that baseline, before concurrent authorized edits. Runtime changes from this subtask are only the new context adapter and focused test; source reading of installed Agreements never booted WordPress or changed normal Local.

Read evidence: remediation workflow and ledger; `docs/authority-integration/consolidated-report.md`, `ecc-design-brief.md`, and existing report discoverability test. Accepted financial/staffing/ticket conclusions were reused, not rederived from historical screenshots. Historical payables strict fixture remains unavailable.

## Baseline inventory and disposition

| Existing item/control | Evidence and authority | Prominence/actionability and disposition |
|---|---|---|
| Event picker, title/date/time/location/status | `event-command-center.php:449,519,1700`; Event Plan metadata and standard edit links | Keep/promote; orientation and switching remain ECC. Configuration stays Event Plan. |
| Health chip + highlight chips + Show Health card | `:418,1496,1823`; count of local red/yellow alerts | Group into one explainable readiness area. Existing three-yellow threshold is arbitrary presentation; do not retain as opaque score. Duplicate chips/card consume attention. |
| Header Open Event Plan | `:1794` | Keep/promote primary editing destination. |
| Header public event/calendar edit/ticket page | `:1797–1805` | Keep/de-emphasize as contextual tools, not equal priority to operational exceptions. |
| Header Open Marketing / Jump to Notes | `:1807` | Marketing link out; notes jump only when notes exist. Generic marketing is planning-oriented. |
| Weather / Venue Conditions | `:1211,1814`; only menu availability, no actual weather risk | Promote actual cached provider weather assessment; active module is not safety clearance. Optional absence graceful. |
| Ticket Snapshot paid/receipts/comp/remaining/sell-through | `:605,735,799,1836`; shared ticket authority adapter plus integrity scanner | Keep/promote Audience; paid/free/forecast populations explicit. Current/valid zero/stale/pending/unavailable retained. Transaction receipts primarily Financial. |
| “Total admitted/ticketed” sentence | `:1855`; arithmetic paid plus comp count | Rename/remove misleading attendance language. These are not check-ins or deduplicated guests. Link canonical Event-Day report for admissions populations. |
| Alerts & Next Actions | `:1307,1872`; venue/date/vendor missing, tracked changes, integrity, inventory, required staffing, conflicts, lineup timing, listing/social/promo | Promote strongest area. Stable condition codes preserve semantics. Required conflicts/critical open seats blocking; proposed awaiting response attention; stale data incomplete. Optional empty promo/social suppression not show-day blockers. |
| Schedule / Timeline | `:1545,1898`; event/lineup/staffing time anchors | Keep/promote operational schedule. Editing remains Event Plan; no scheduling engine duplication. |
| Talent primary/supporting | `:886,1915`; shared lineup getter and metadata | Keep/group People with vendors/docs; do not infer agreement acceptance from “Booked.” |
| Secondary vendors | `:886,1935`; assigned vendor rows/status metadata | Keep/group People; absent optional secondary vendors should not create a large empty card. |
| Staff participant list | `:1951`; shared staffing roles but assigned/needed displayed as green coverage | Move into dedicated Staffing operational section. Proposed and Confirmed visible separately; no green assigned-only readiness. |
| Financial Snapshot | `:879,1976`; `bvmgr_financial_get_event_snapshot` | Keep/promote distinct Current/Transactional, Forecast/Planned, Reported/Manual. Actual paid labor and final accounting unavailable. Planned/committed overlap, never add. Dense provenance collapsible/report-owned. |
| Marketing Snapshot listing/social/promo/Ads | `:1053,1984`; listing URL, do-not-post, portal media, registered workspaces | Keep/de-emphasize; separate from customer occurrence notices. Available workspace does not prove campaign sent/spend tracked. |
| Promo Video Control: hide current, approve/reject vendor clip, upload file, external URL, clear | `:85–205,1109`; existing capability, nonce and admin-post handlers | Keep/de-emphasize in collapsed advanced controls for compatibility. Current canonical manager lives only here; removing it would remove a capability. Future move to Event Plan requires deliberate destination parity. |
| Internal Notes | `:1228,2019`; saved Event Plan notes, escaped display | Keep/group operational support; only show when populated. Edit in Event Plan. |
| Recent Activity | `:1240,2028`; modified time, review change time, ticket cache refresh, actuals pull, promo update | Keep/de-emphasize as collapsed history. These are independent timestamps, not one trustworthy global freshness. |
| Event Plan Module Hub (Core, Tickets/Add-ons, Lineup/Vendors, Staffing, Finance, Marketing) | `:2440–2799`; lightweight ticket adapter/shared staffing/financial | Preserve planning navigation/filter hook; ECC full page redesign does not need wholesale Event Plan redesign. Existing fuller-report secondary link remains compatible. |
| ECC row action and Event Plan submitbox | `:2884–2917` | Keep canonical direct ECC URL. Existing Event-Day link in submitbox informs reusable launch. |

Missing operational surfaces at baseline: canonical Event-Day launch on full ECC, separate customer communication ledger status, real cached weather assessment, agreement exception summary, permission-scoped tech-document links, event-enabled Express Bar, and deliberate tools grouping.

## Responsibility model

Event Plan owns durable configuration, requirements, staffing management, compensation inputs and schedule editing. ECC owns event orientation, current evidence, explainable exceptions and direct action destinations. Reports own dense/printable accounting, admissions and audit output. Portals own authenticated person-specific responses and documents. ECC must not expose unguarded private portal URLs or create a second lifecycle/write service.

## Canonical Event-Day report

`includes/admin/event-day-report.php:12` exposes `bvmgr_event_day_report_url($event_plan_id, $args=[])`. It builds `admin-post.php?action=vms_event_day_report&event_plan_id=ID` using `wp_nonce_url(..., 'bvmgr_event_day_report_ID')`. Registered only at `admin_post_vms_event_day_report` (`:1707`), not unauthenticated dispatch.

Handler `:1676`: admission manage capability (fallback `manage_options`) first; missing/wrong post type gives 404; missing/invalid event-specific nonce gives 403; absent model gives 404. No-cache, noindex and nosniff headers preserved. Normal mode is interactive full report; `print=guests|reservations|full` is already supported. Existing launch points: `includes/modules/admissions/admin-ui.php:103` and Event Plan submitbox in `event-command-center.php:2911`. ECC must call the helper with the same capability and correct plan ID, not reconstruct reporting logic.

## Readiness inputs that are deterministic

- Shared staffing `includes/core/staffing.php:2481,2676–2737,2741`: authority/provenance/rollup state; planned/required-now, assigned, proposed, confirmed, unknown legacy responses, open planned/required, critical open, conflicts, duplicates. Core readiness can currently say ready while proposals are assigned; ECC presentation explicitly adds tentative attention without changing staffing authority.
- Ticket authority states CURRENT/VALID_ZERO/STALE/PENDING_REFRESH/UNAVAILABLE and integrity severity come from accepted full/light adapter. Missing provider must not become 0 or ready.
- Financial contract v2 `includes/core/financial-snapshot.php:31,116`: amount null means unavailable; real zero numeric. Revenue scope, freshness, basis and calculated timestamp are separate. Never interpret incomplete accounting as a blocking operational failure without a stated rule; unavailable receipts are incomplete data.
- Communications use actual occurrence-history operations plus canonical ledger summary. Pending/failed, missing affected ledger and unfinished send attempt require review; sent/manual/excluded resolved states remain distinct from campaign delivery.
- Weather concern uses provider `weather_risk.band` (Low/Watch/High/Critical), not combined sales/financial advisory. Missing weather source is optional. Installed enabled source with stale/unavailable assessment is incomplete data.
- Agreements use the add-on's active packet status and terms state. Pending/stale/unknown active packets need review; current acknowledged calm; superseded/voided history excluded. No packet is not proof a required contract is missing.

## Actual optional APIs and route limits

Weather 0.1.12: `companion-plugins/vmsx-weather-risk/includes/services/advisory-engine.php:7` `VMSX_Weather_Risk_Advisory_Engine::get_snapshot` reads cache only. Never call `refresh_snapshot` on ECC load (HTTP/writes). Snapshot fields: event_id, computed_at_utc, window, weather_risk/reasons, provider_health.responded. `VMSX_Weather_Risk_Capabilities::can_view_event` and Settings::get enabled gate access. `VMSX_Weather_Risk_Admin_Menu::details_url(ID)` (`admin/menu.php:135`) preserves scope. Cache age presentation follows existing scheduler cadence (`cache/scheduler.php:98`): 1h within 2 days,4h within5 days,1day farther out; past windows stale. No freshness implies no current assessment.

Communications: `bvmgr_event_occurrence_history` in `includes/core/event-reschedule.php:1343` reads metadata; `bvmgr_event_communication_get_ledger` in `core/event-communications.php:416` checks plan/operation identity; summary `:516`; unfinished-attempt helper `:789`. Admin link helper `includes/admin/event-communications.php:31` targets `#bvmgr-event-communications`; edit_post capability. No ledger creation or send calls.

Agreements installed source was read only under normal plugin `vms-agreements/includes/`. `admin.php:371` event-scoped packet IDs; `:1356` operator queue row (status, terms_state, bucket,event_id); `snapshots.php:1068` terms status uses read-only snapshot diagnostics. `helpers.php:50` vmsa_can_manage; `:452` admin page URL; `:457` agreement setup URL. Use existing packet admin route; avoid review token generation (can write) and public review links. Independent row event_id check prevents cross-event leakage.

Tech docs: `includes/portal/staff-portal.php:648` event tech-doc resolver uses assigned event vendors; `vendor-portal.php:6715` authorizes each private download; secure download URL retains plan ID and nonce. Missing optional stage plots/input lists are not invented requirements.

Admissions management/check-in lives at Event Plan `#vms_guest_list_comp_admission` (`includes/modules/admissions/admin-ui.php:70`); require edit_post plus admission manage cap. Public door shortcode does not support an event-ID launch parameter: `assets/js/vms-door-checkin.js` selects first fetched event. Do not invent a preselected public check-in URL.

Staffing management anchor is `#vms-ep-staff-headcount-summary`, verified in `includes/cpt/event-plans.php:6823` and `includes/cpt/event-plans/partials/staff.php:9`.

Profitability `includes/admin/event-profitability-report.php:25` supports only `profit_view` and title-substring `s`, not an exact event ID. Link real registered route explicitly labeled all events. Express Bar `includes/admin/express-bar.php:103,151` supports event_plan_id filter; only link with registered route, manage_options, WooCommerce and `_vms_express_bar_enabled` exactly 1. Marketing/Social links use only registered workspace routes; do not claim event-scoped delivery authority. Ops Console has multiple owners/aliases and no verified common event scope; defer a new integration rather than create a dead or misleading tool.

## Follow-up boundaries

Future Event Plan cleanup, promo-manager relocation with parity, exact-ID profitability filtering, public door preselection, unified report launcher, richer Ops Console handoff and authoritative required-document contracts are separate work. No new Sponsorship empty card is necessary. Weather scoring, financial math, ticket population and staffing lifecycle remain their accepted owners' responsibility.
