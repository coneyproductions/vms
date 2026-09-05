# The Alternatives Check-In Scanner Readiness

Date: 2026-09-04

## Result

**PASS WITH SHOW-DAY LIMITATION.** The staging-accepted repair is deployed to
production as Ops Console Premium `0.1.65.2`. The production tree matches the
accepted 61-file candidate manifest. No production admission, check-in, order,
eligibility, queue, presence, or audit record was deliberately changed during
acceptance.

The remaining manual requirements are a physical iPhone/camera scan and an
authorized staff Ops-session check before gates open. The available browser
reached the OS camera-permission boundary on staging but had no physical camera,
and the authenticated production WordPress shell was intentionally not advanced
through the separate Ops shift login because that would write presence state.

## Repository baseline

The mandatory preflight began on `work/unreleased-2026-06-18` at
`51084fb5260089cce510116d7b21a0ab3ab904eb`, equal to
`origin/work/unreleased-2026-06-18` at `0/0`. The index was empty,
`git diff --check` passed, and protected stash
`d08e726804712dc233f0e37b217abd6389963863` remained
`WPORG-16D preserve unrelated sidebar+doc work`.

The exact 19-modified/12-top-level-untracked/60-file inventory documented in
`docs/eligibility-proof-inline-preview.md` matched and was accepted as the
pre-existing dirty baseline. No scanner implementation file in the Git mirror
overlapped that baseline. The canonical Ops addon is an untracked sibling tree,
so the exact repair is recorded here as reproducible patch artifacts.

## Architecture and request flow

1. The Ops PWA loads its event selector from `GET /vms-ops/v1/ticket/events`
   and its searchable attendee cache from
   `GET /vms-ops/v1/ticket/events/{event_id}/attendees`.
2. The event resolver merges The Events Calendar ticket events and BVM Event
   Plans. A Plan linked through `_vms_tec_event_id` now defers to the TEC event,
   and the TEC event inherits the linked Plan's BVM venue.
3. The browser uses `html5-qrcode` for camera decoding. Decoded URLs and raw
   values are normalized into a scan reference; manual search selects the same
   attendee record and invokes the same check-in request.
4. Check-in posts `venue_id`, `event_id`, `event_source`, `scan_reference`, and
   quantity to `POST /vms-ops/v1/ticket/checkin`.
5. The REST permission callback requires a logged-in WordPress user, a valid
   `wp_rest` nonce, and the ticket-scanning capability (administrators are
   permitted). The server resolves venue identity and revalidates event/venue
   ownership rather than trusting the client.
6. The server normalizes the reference, validates the selected event and scan
   window, rejects cross-event references, resolves either an Event Tickets
   attendee or a BVM guest-list entry, and validates admission eligibility.
7. Ticket mutations use the actual Event Tickets provider. Guest entries use a
   compare-and-swap update. Both paths require their audit/log writes and run
   inside a database transaction while a named per-event/reference MySQL lock
   serializes rapid or concurrent scans.
8. The response drives distinct success, duplicate, wrong-event, not-found,
   ineligible, and network/server feedback. Successful UI state and counts are
   refreshed from the accepted response/cache path.
9. Offline operation is deliberately narrow: only previously cached,
   server-marked eligible ticket rows can be queued. Guest-list rows, unknown
   references, ineligible rows, reservations, and add-ons are rejected offline.
   A rejected queued item loses its optimistic local checked marker.
10. Ops ticket check-in has no undo endpoint. BVM guest administration has a
    separate deliberate, permission-protected, audited correction mechanism;
    it was not invoked on production.

## Demonstrated defects and repair

- The linked BVM Event Plan and TEC event were both selectable, while the TEC
  event resolved to venue `0`. The resolver now maps both directions, inherits
  venue `1153`, enforces venue authorization, and suppresses the duplicate Plan.
- The attendee-cache route accepted an event without validating it against the
  selected venue. It now resolves the venue server-side and returns `403` on a
  mismatch.
- The previous ticket path wrote only the Ops scan log instead of invoking the
  Event Tickets provider. It now calls the resolved provider, verifies its
  result, recognizes Event Tickets' native checked-in metadata, and rejects
  already-checked attendees.
- Cancelled/refunded or otherwise invalid Woo order states could reach the
  mutation path. Ticket admission now permits `completed` and `processing` by
  default and rejects cancelled, refunded, failed, missing, and other states.
- Separate devices could race between duplicate detection and mutation. A
  deterministic MySQL named lock plus InnoDB transaction now encloses identity,
  eligibility, native/guest mutation, BVM audit, and Ops scan-log recording.
- Partial guest-list scans were immediately repeatable. Fully checked entries
  remain blocked, and recent partial scans now have a four-second duplicate
  cooldown.
- The client changed its selected event before the server had validated a
  wrong-event scan. It now keeps the staff-selected event and presents a clear
  rejection.
- Offline queuing previously admitted too broad a set. The cache now includes a
  server-derived eligibility bit; the client only queues known eligible ticket
  rows and removes false local success state if synchronization is rejected.

## Files and reproducible candidates

Canonical `0.1.68` source changes:

- `includes/ticket-console/events.php`
- `includes/ticket-console/checkin.php`
- `includes/private-club/visits.php`
- `includes/rest/routes.php`
- `pwa/assets/js/app.js`
- `vms-ops-console-premium.php`
- `vms-build.txt`
- `tests/php/test-scanner-readiness-repair.php`
- `tests/js/test-scanner-readiness-repair.js`
- `tests/php/test-checkin-close-persistence.php` (standalone test-harness stub)

`docs/addon-compatibility/vms-ops-console-premium-0.1.67-to-0.1.68.patch.b64`
decodes to a patch that reproduces that source exactly from the immutable
`0.1.67` artifact.

Production `0.1.65.2` contains only the seven runtime paths above and preserves
the existing production `0.1.65.1` code outside the narrow repair. Its exact
patch record is
`docs/addon-compatibility/vms-ops-console-premium-0.1.65.1-to-0.1.65.2.patch.b64`.
Both records decode to unified diffs whose patch-reproduction comparisons
passed byte-for-byte.

## Staging acceptance

Synthetic staging fixtures covered valid paid, free, guest-list/comp, Outreach,
and registered-guest-different-from-purchaser admissions; wrong event;
cancelled/refunded/native-checked tickets; malformed and unknown values;
reservation/add-on non-admissions; first, duplicate, concurrent, manual, and
partial-guest check-ins; authorization; camera fallbacks; offline behavior; and
responsive behavior.

| Case | Result |
| --- | --- |
| Event selection | One TEC event, linked Plan suppressed, venue `1153` |
| Attendee population | 8 synthetic rows; registered-name search returned one match |
| Paid/free/manual check-in | Success; native Event Tickets state became checked |
| Guest list/Outreach | Success with required BVM audit and Ops scan log |
| Immediate duplicate | Rejected as duplicate |
| Concurrent pair | Exactly one success and one duplicate |
| Wrong venue | `403` |
| Wrong event | `409`; selected event did not change |
| Refunded/cancelled/native checked | `409`, no admission mutation |
| Malformed/unknown/reservation/add-on | Not found or rejected, no admission mutation |
| Authentication | Anonymous denied, bad nonce denied, admin allowed, missing capability denied |
| Camera | Modal reached OS permission boundary; denied/unavailable copy and manual input passed |
| Offline | Eligible cached ticket only; unknown/ineligible/guest-list rejected |
| Responsive | Direct 390 px boundary test selected mobile mode |
| Undo | No Ops ticket undo route exists; no undo was attempted |

The focused addon suite passed 9 PHP tests and 4 JavaScript tests with zero
failures. PHP lint passed for all changed PHP runtime/test files; Node syntax
passed for the app and scanner test. The directly relevant BVM admission,
Outreach, authorization, scan-lock, and compatibility tests passed. Five broad
BVM integrity/remediation tests still fail in accepted pre-existing dirty core
areas (`pass-claims.php`, integrity projections, and shadow-live monitor/event
sources); none reads a task-created patch as runtime input, and this task did
not modify those sources.

Before fixtures, staging held 20 admission rows, 83 admission-audit rows, 4
sources, 6 batches, and 714 Ops scan-log rows. The matrix added exactly 2 audit
rows and 14 scan-log rows while active. Final cleanup restored all five counts
exactly. An early broad cleanup script mistakenly removed pre-existing staging
admission row 17; it was immediately restored byte-for-byte from the accepted
pre-fixture backup, its tuple hash was verified, and every baseline count was
reconfirmed. Subsequent cleanup was exact manifest-scoped.

Staging rollback evidence:

- Database: `/home/coney/codex-backups/checkin-scanner-staging-20260904T1935Z/staging-before-fixtures.sql`
- Database SHA-256: `de61a98793e25b21d4bac64657adb2454945ac92810812b665fa57bfb842f5bd`
- Files: `/home/coney/codex-backups/checkin-scanner-staging-files-20260904T2000Z`

## Production audit and deployment

The production data model was compatible: all check-in and admission/audit
tables involved use InnoDB. Before deployment, The Alternatives resolved to TEC
event `6540`, linked Plan `2534`, venue `1153`, start 19:00, end 21:00, scan open
18:00, and scan close 01:00 the following day. The pre-fix selector exposed both
the Plan and TEC event; after deployment it exposes only TEC event `6540` with
venue `1153`.

Read-only population reconciliation found 26 Event Tickets attendee rows plus
8 BVM guest-admission rows/quantity for 34 human admissions: 20 paid, 6 free
online (including 4 internal comps), and 8 guest-list admissions. The observed
12 comp/guest total is therefore 4 ticket comps plus 8 guest-list admissions.
The separate add-on quantity of 1 is not an admission. Eligible production
orders were 24 completed and 2 processing; production contained no
refunded/cancelled attendee in this event population. Native checked-in, Ops
checked-in, event scan-log, and guest checked-in counts were all zero before
deployment and remained zero afterward.

The exact seven staging-accepted runtime files were copied to sibling temporary
paths and atomically renamed into place with permissions preserved. Remote PHP
lint passed, and the complete 61-file production manifest equals the candidate:
`d7b691f426a6967d1e971b730bcb67e60ac732f5af38c135864fe1fc04067479`.

| Production path | SHA-256 |
| --- | --- |
| `vms-ops-console-premium.php` | `39f868dd8470ed8220fd5da955346d278c1d476c8c7850f6fafe6c78ed76fe25` |
| `includes/ticket-console/events.php` | `f836f0adfd6d196ab76c673aa5fb687113ee98a9437d822a5986b5776077b619` |
| `includes/ticket-console/checkin.php` | `6b3c2e6dd241f65874c2c50a9e953f82d8741feb6cc5e9ab6534a555ecef19aa` |
| `includes/private-club/visits.php` | `a5a5de454a5b39ca832d6fe421f6eeb9d77ac6333a90996ee6293323bcb82662` |
| `includes/rest/routes.php` | `3fe685ac7e80651794a84406c8550187da986bd744de863e83ee05e9be7b3e10` |
| `pwa/assets/js/app.js` | `5adb0d986219760671ce10d4cfa6425f1e20da55945e702df8a3d1cd35a753d4` |
| `vms-build.txt` | `cd1f9c0074b0470155aa6a306125d36ceaf12a5d8a856e012ef41c834f240333` |

The production rollback directory is
`/home/coney/codex-backups/checkin-scanner-production-20260904T2200Z`; it
contains and hash-verifies the exact seven original files. The browser loaded
the `0.1.65.2` app asset with no JavaScript error or warning. Production logs
showed no repair-attributable error; the last observed fatal entries predated
deployment and were unrelated WP-CLI/PixelYourSite memory errors.

The available authenticated WordPress browser shell did not expose the REST
nonce until the separate Ops shift login. That login was not started because it
would mutate presence state. Therefore the intentionally malformed production
check-in POST was not sent; its empty-reference rejection and zero-admission
effect were proven on the byte-identical staging candidate and in focused tests.
This is included in the show-day operational acceptance below rather than
misrepresented as a production request.

## Staff event-day fallback

1. If the camera permission prompt is denied, use the browser's site settings to
   allow camera access and reload. While resolving it, keep the scanner page open
   and use **Manual Lookup**; camera failure does not disable that control.
2. If a QR code will not scan, increase screen brightness, clean the camera,
   steady the code in the frame, then search manually by the guest/registered
   attendee name. Purchaser and registered guest can differ, so try the actual
   attendee name as well as the purchaser.
3. If the guest cannot find a ticket, use Manual Lookup. Admit only a matching
   record for The Alternatives that the scanner marks eligible. Guest-list and
   Outreach admissions require a live connection.
4. If **Already checked in** appears, do not scan again and do not use a
   different record to bypass the warning. Confirm non-sensitive context and
   escalate to the entry manager. Ops has no ticket undo button; any correction
   must use the separate deliberate, audited manager workflow.
5. If the internet/server fails, stop ordinary scanning until the connection is
   restored unless the device explicitly shows that The Alternatives attendee
   cache is offline-ready. Offline admission is limited to already cached,
   server-marked eligible ticket rows. It does not cover guest list, Outreach,
   unknown codes, reservations, or add-ons. Keep queued results on that device
   until synchronization completes.
6. Stop and call a manager rather than manually admitting when there is no exact
   event record, the event is wrong, the record is cancelled/refunded/ineligible,
   identity or reservation ownership is unclear, a duplicate cannot be
   explained, offline eligibility is absent, or the server continues rejecting
   the request.

## Before-gates manual acceptance

- On the actual staff iPhone over HTTPS, start an authorized Ops shift, select
  The Alternatives, grant camera permission, and decode one manager-controlled
  non-customer test code at the input boundary. Do not check in a real attendee
  during this check.
- Confirm Manual Lookup opens, event `6540` is the only matching selectable
  event, the page shows 34 discoverable admissions, and the network indicator is
  healthy. If relying on offline backup, deliberately cache the event first and
  verify the page says it is offline-ready.
- Confirm success/duplicate/wrong-event/error audio and visual feedback are
  understandable in the actual entry environment.

No legacy VMS activation, WordPress.org action, package, ZIP, tag, release,
scanner audit, real-ticket scan, production correction, or customer-data output
occurred.
