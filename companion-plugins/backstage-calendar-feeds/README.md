# Backstage Calendar Feeds

Backstage Calendar Feeds is a private Coney Productions WordPress plugin that generates secure calendar and availability feeds from registered Backstage-compatible calendar providers. Version 0.1.4 integrates its administrator screen with the canonical Backstage Venue Manager page registry while preserving the standalone screen and all 0.1.3 feed behavior.

Administrative diagnostics remain available in Backstage, while consumer-facing calendar summaries intentionally avoid internal workflow terminology.

**Backstage Calendar Feeds does not write upstream calendar data.** It does not create, update, delete, move, or synchronize Google Calendar events, Intake records, Router decisions, VMS/BVM records, Event Plans, TEC events, tickets, notifications, or destination configuration.

## Architecture

The plugin has five explicit layers:

1. **Provider** — `DRM_Calendar_Intake_Provider` implements `Provider_Interface` and consumes DRM Calendar Intake occurrence contract version 2 plus safe source-discovery capability version 1. It obtains authoritative source metadata through `drm_ci_router_source_discovery_version()` / `drm_ci_query_router_sources()`, selects only enabled opaque source refs, and passes those refs to `drm_ci_query_router_records()`. It forwards only normalized allowlisted occurrence fields and fails closed if either dependency, capability version, or response is unavailable or invalid. An authoritative zero-enabled-source result produces an empty feed and never falls back to all retained lineages.
2. **Availability policy** — `Strict_Availability_Policy` decides inclusion, BUSY/TENTATIVE/CANCELLED state, safe summary, safe location, privacy mode, and review warnings. It has no ICS formatting responsibility.
3. **Supersession resolver** — `Supersession_Resolver` consumes only an explicit opaque predecessor-to-successor relation. It never infers a relation from time, title, source, act, recurrence, fingerprint, or venue.
4. **Publication ledger** — `Publication_Ledger` applies snapshot-feed cancellation semantics after supersession. It stores only derived public UIDs, bounded timestamps/status, and a projection hash in BCF-owned state.
5. **Formatter** — `ICS_Formatter` accepts only normalized feed occurrences and emits RFC 5545 iCalendar. It does not know how Intake stores events.

`Feed_Service` composes these layers and runs exact duplicate diagnostics after explicit supersession resolution. Additional providers, policies, profiles, and formatters can be registered without rewriting existing layers.

## Initial profile

Version 0.1.0 includes one runtime profile, **Dalene — BAND Availability**, configured in `includes/profile.php`:

- Provider: DRM Calendar Intake
- Policy: Strict Availability
- Format: ICS
- Timezone: America/Chicago

The profile owns consumer-specific act display names. Unknown act slugs become `Scheduled Act`; they are never exposed as private identifiers. Dālene-specific configuration is deliberately absent from product names, core prefixes, namespaces, routes, and generic UI labels.

## Strict availability and privacy

The policy favors false-BUSY over false-AVAILABLE:

- Active confirmed public and private performances, holds, personal events, media, other records, and unknown records remain busy.
- Google tentative records remain opaque/busy and use `STATUS:TENTATIVE`.
- `source_missing` remains opaque/busy as `Unavailable`; disappearance is never inferred as cancellation, while its internal warning and review/source state remain available to administrators.
- Only an explicit provider cancellation creates an internal same-UID `STATUS:CANCELLED` projection. The snapshot publication layer omits it from final ICS and records a safe diagnostic.
- Intake classification `ignored` is omitted.

Only an active public performance with public/default visibility may use the Intake contract's safe summary and resolved venue fields. Private, confidential, unknown, missing, cancelled, hold, personal, media, and other records use conservative summaries and omit location. Unknown, missing, and unresolved-review occurrences use the consumer-facing summary `Unavailable`; their review state, source state, classification, inclusion reason, privacy mode, and warnings remain separate internal diagnostics. Intake post IDs, Google IDs, calendar IDs, source UUIDs, opaque occurrence keys, raw locations, descriptions, reviewer identities, and other provider internals are never rendered in ICS or the admin preview.

Cancelled projections remain internally eligible until 45 days after their scheduled end. This preserves bounded supersession and diagnostic reasoning, but final `METHOD:PUBLISH` availability output does not introduce a cancelled `VEVENT`.

## Publication history and cancellation snapshots

BCF's URL is a polled availability snapshot, not an iTIP cancellation-delivery channel. The target BAND consumer was observed to display `STATUS:CANCELLED` `VEVENT`s as visible unavailable entries, while a manual refresh removed an explicitly superseded UID that disappeared from the feed. Version 0.1.2 therefore removes every explicit cancellation from final snapshot ICS after supersession resolution.

The BCF-owned publication ledger distinguishes:

- `previously_emitted_cancellation_omitted` — the derived UID was previously emitted as confirmed/tentative BUSY and is now explicitly cancelled;
- `historical_cancellation_omitted` — BCF has no prior BUSY publication for that derived UID, so it must not introduce the cancellation as a new snapshot event.

The ledger is keyed by feed profile and derived public UID. Each entry contains first/last emitted timestamps, last emitted status, and a compact projection fingerprint. It stores no raw Google/Intake/source identifiers, summaries, locations, or reviewer data. Entries expire after 90 days without publication. Actual capability-feed responses update the ledger; administrator previews are read-only. Atomic option locking prevents concurrent writes, and corrupt/locked/write-failed state makes the feed fail closed. Missing history treats cancellations as unpublished and can never suppress active, tentative, or `source_missing` BUSY records.

## ICS identity and time handling

Each occurrence UID is derived as:

```text
sha256("bcf-ics-uid-v1\n" + opaque_occurrence_identity) + "@backstage-calendar-feeds"
```

The raw provider identity is never emitted. A reschedule keeps the same UID, while already-expanded recurring occurrences retain their own identity and produce one `VEVENT` each without `RRULE`.

Timed events are emitted as UTC instants. This preserves America/Chicago local time, DST transitions, and cross-midnight boundaries without relying on a consumer-specific timezone definition. All-day events use exclusive `VALUE=DATE` endpoints. Output uses UTF-8, CRLF endings, escaped TEXT values, and 75-octet content-line folding.

## Explicit occurrence supersession

BCF suppresses a predecessor only when Intake explicitly marks it superseded and its opaque successor identity is both present in the enabled-source provider result and eligible for policy/retention projection. Active, tentative, source-missing, and retention-eligible cancelled successors are all projectable for relation resolution. Valid chains suppress their explicit predecessors; a cancelled final successor is then omitted by the publication layer.

The conservative fallback is always false-BUSY:

- A missing successor retains the predecessor with `supersession_target_missing`.
- An ignored or retention-ineligible successor retains the predecessor with `supersession_target_not_available_for_projection`.
- A malformed reference or duplicate provider identity retains the predecessor with `malformed_supersession_reference`.
- A self-reference or multi-occurrence cycle retains every affected occurrence with `supersession_cycle`.

Only the predecessor is suppressed; the Intake record and its provider state remain unchanged. Resolution applies equally to standalone occurrences, expanded recurring occurrences, and cancelled predecessors. Unlinked same-time events remain separate BUSY entries. The administrator preview includes an identifier-free relation diagnostic with safe predecessor/successor source labels, source policy, disposition, and reason. Stats distinguish confirmed suppression, missing/unavailable targets, malformed references/cycles, and unresolved possible migration duplicates. ICS contains emitted successors only.

## Cross-source duplicate diagnostics

The 0.1.0 deterministic high-confidence rule flags occurrences when the Intake safe contract reports the exact same material `source_fingerprint` on two or more distinct opaque `source_ref` values. Intake fingerprints cover source summary, source location, time endpoints/timezones, all-day state, Google status, source state, and privacy visibility where applicable.

The detector does not fuzzy-match, merge, or suppress. It runs after explicit supersession resolution, so an explicitly suppressed predecessor is excluded. Every unresolved unlinked pair remains in the feed and the admin preview receives `possible_cross_source_duplicate`. The summary count represents duplicate groups.

Disabled and replaced/archived Intake source lineages are excluded before occurrence fetching. BCF never receives a raw calendar ID or Intake source UUID and never calls the occurrence query with an empty ref list.

## Capability URL security

The route is:

```text
/backstage-calendar-feeds/<43-character-random-secret>.ics
```

Tokens use 256 bits from `random_bytes()`. WordPress stores a SHA-256 validation hash plus a Sodium-encrypted copy needed for the authenticated administrator's Copy/View action. Encryption keys derive from the site's auth salt. Validation uses `hash_equals()`. Rotation requires `manage_options` and a WordPress nonce and immediately invalidates the previous URL.

The plugin does not log capability secrets or include them in errors. Feed responses use `text/calendar; charset=utf-8`, private short caching, `nosniff`, and `X-Robots-Tag: noindex, nofollow`, with no theme output. Only authenticated administrators can see the capability URL in wp-admin.

## Administrator screen

With canonical Backstage Venue Manager active, open **Backstage Venue Manager → Planning → Calendar Feeds**. BVM owns the navigation and shell; Calendar Feeds retains the direct `admin.php?page=backstage` URL. Without BVM (including a legacy-only environment), the plugin registers its standalone **Backstage → Calendar Feeds** menu and does not require a legacy VMS API.

The screen shows provider/feed health, policy/format, occurrence and privacy counts, cancellation publication outcomes, bounded history size, source-missing records, excluded source lineages, explicit supersession outcomes, unresolved duplicate groups, capability URL actions, secret rotation, and safe projected/diagnostic previews. The projected preview keeps review state, source state, classification, inclusion reason, privacy mode, and warnings visible even when the external summary is simply `Unavailable`. No action on this screen writes to an upstream system or records a publication.

## Local validation

The isolated suite has no Google access and creates no WordPress/Intake fixtures:

```bash
php tests/run.php
```

It covers ICS escaping, 75-octet folding, CRLF, timed and all-day events, America/Chicago DST, cross-midnight events, stable cancellation UID, private sanitization, ignored omission, tentative behavior, source-missing BUSY behavior, consumer-summary diagnostic hygiene, same-UID presentation updates, historical and previously published cancellation omission, ledger projection updates/privacy/retention/corruption/loss/concurrency behavior, unavailable/wrong-version providers, enabled/disabled/archived source selection, duplicate-storage failure, zero-enabled-source behavior, malformed/missing source discovery, capability validation, unauthorized rotation decisions, expanded recurrence output, duplicate diagnostics, and explicit supersession across active/tentative/source-missing/cancelled/ignored/missing/malformed/cycle/chain/retention/recurring cases.

For a Local runtime, activate and inspect read-only provider output with WP-CLI:

```bash
wp --path=/path/to/wordpress plugin activate backstage-calendar-feeds
wp --path=/path/to/wordpress eval 'var_export((new ConeyProductions\\BackstageCalendarFeeds\\DRM_Calendar_Intake_Provider())->health());'
```

Then inspect the authenticated admin preview and retrieve the capability URL there. Validate the response headers and parse the ICS without subscribing any destination. The current Intake data should be used read-only for canaries; source-missing and cancellation cases may be proved with isolated fixtures instead of changing real records.

## Current boundaries

Version 0.1.4 provides a staging availability feed for controlled inspection. It does not subscribe BAND or any other destination, perform background upstream synchronization, write Google Calendar, or publish event data into downstream systems.
