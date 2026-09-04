# Guest Pass Outreach recovery report

Date: 2026-09-02

## Baseline

- Repository: `vms-github-reconcile` at
  `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/public/wp-content/plugins/packages/vms-github-reconcile`
- Remote: `https://github.com/coneyproductions/vms.git`
- Branch: `work/unreleased-2026-06-18`
- Starting HEAD and starting origin branch HEAD:
  `ec9934131a951faae2ac4250915cb8bb2683cdfe`
  (`Record staging communications verification`)
- Starting ahead/behind: `0/0`
- Starting mirror worktree: clean
- Sibling historical live tree: `../../vms`
- Protected stash: `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`
- Preflight: `scripts/codex-preflight.sh` passed. The protected stash was not
  applied, modified, dropped, or otherwise manipulated.

## Forensic lineage

The current Backstage Venue Manager 1.2.0 mirror has no tracked Outreach source
in its current tree, history, tags, or branches. No pre-existing Backstage
Outreach companion package was found. The complete code survived only in the
inactive sibling VMS tree and its local release archives.

The last verified working/deployed lineage is VMS 1.1.0: the July 1 production
record documents the Outreach UI and safe synthetic campaign/recipient/delivery
smoke test. The closest complete recoverable source is `../../vms.zip`, dated
2026-07-04, whose Outreach files match the inactive local tree byte-for-byte. A
deploy-time source hash was not recorded, and two recovered files have July 3/4
archive timestamps, so exact byte identity with the July 1 production tree
cannot be proved. The principal source hashes are:

- `includes/modules/admissions/outreach.php`:
  `324b6e7ba3ee33c79ce370a2bc8bde79e2bec930719e0cf7a6da4e15721c00ca`
- `includes/modules/admissions/outreach-recipients.php`:
  `4ea049507f6ec7337fc3023289d0a20e34d86d4c2d11f38a6036fb8c107643df`

There is no source commit to cite: `git log --all` found no tracked Outreach
module in this mirror. The version evidence is the VMS 1.1.0 production deploy
record in `../../vms/docs/OUTREACH-PRODUCTION-DEPLOY-2026-07-01.md`, plus the
matching July 4 archive/inactive-tree pair. The July 4 archive is therefore the
source-of-record recovery baseline, with the deploy-hash limitation stated
above.

The implementation was composed of:

- `includes/modules/admissions/outreach.php`
- `includes/modules/admissions/outreach-recipients.php`
- `includes/modules/outreach/outreach.php`
- `includes/modules/outreach/admin-ui.php`
- `includes/modules/outreach/db.php`
- `includes/modules/outreach/helpers.php`
- `includes/modules/outreach/contacts.php`
- `includes/modules/outreach/suppression.php`
- `assets/css/vms-pass-claims-admin.css`
- `assets/js/vms-pass-claims-admin.js`

`../../vms 2.zip`, dated 2026-07-10, is materially later but is not the recovery
baseline. It contains an unverified automatic invite-capacity/token-expansion
change (`vms_pass_outreach_add_invite_capacity` and
`vms_pass_claims_add_tokens_to_batch`) for which no deployment/acceptance record
was found. That enhancement is explicitly outside the recovery-first scope.

The code disappeared because the public BVM package was produced without the
live-only/local Outreach module when the VMS monolith was replaced. BVM 1.2.0
retained add-on registration and navigation hooks but did not retain the
Outreach source or its claim-flow coupling. The sibling VMS tree still contains
the old integrated implementation, but that plugin is inactive and is not a
supported dependency.

## Historical data contract

The recovered implementation uses the following prefix-relative tables:

- `vms_pass_outreach_campaigns`
- `vms_pass_outreach_recipients`
- `vms_outreach_contacts`
- `vms_outreach_suppressions`
- the BVM-owned `vms_pass_claims` table, including
  `outreach_campaign_id` and `outreach_recipient_id`

It uses the `vms_outreach_db_version` option. Campaign templates remain in
`email_subject` and `message_template`; recipient delivery history remains in
`send_status`, `sent_at`, `sent_by`, `send_method`, `last_send_error`, and
`last_contacted_at`. Campaign, recipient, pass-token, pass-claim, reservation
entry, contact, source, and batch IDs are preserved.

A read-only inspection of the local WordPress database found:

- 3 campaigns: 1 active and 2 draft; all are `guest_pass_invitation`
- 208 recipients: 207 ready/not-sent and 1 claimed/not-sent
- all 208 legacy rows currently have a blank `send_method`, which the recovered
  compatibility logic derives as Email only when a valid address exists and as
  Draft otherwise, without rewriting the row during read
- 1 contact and 0 suppressions
- no orphan campaign, token, claim, or reservation-entry references
- no duplicate reserved pass-token groups
- no campaign missing its batch or source
- both historical claim-attribution columns and indexes already present
- `vms_admission_db_version=1.4.0` and `vms_outreach_db_version=1.0.0`

No local data was changed by the inspection. Production and staging were not
accessed.

## Architecture decision

Outreach is restored as the companion plugin **Backstage Outreach**, not put
back into public BVM core. This preserves the public/private feature boundary,
avoids restoring the legacy monolith, and uses BVM 1.2's intended module/admin
registry surface.

The companion requires BVM 1.2.0 or newer and fails closed if the legacy
`VMS_PLUGIN_FILE` constant is present. It does not load old VMS bootstrap code.
Historical `vms_*` identifiers are retained only where changing them would
break tables, actions, URLs, stored state, or the recovered internal call graph.

Integration is deliberately narrow:

- BVM registers the companion's admin page and module metadata.
- BVM's Guest Pass navigation exposes an Outreach tab; Marketing & Social also
  exposes the page.
- `/pass/invite/{invite-token}` resolves the historical recipient reservation,
  reconstructs the current raw BVM claim token, and hands off to the current BVM
  public claim renderer.
- Small BVM claim filters/actions supply campaign context, preflight denial,
  eligible-event filtering, recipient prefill, party-size caps, validation,
  claim attribution, and successful-recipient completion.
- Ordinary BVM Guest Pass claims receive an empty context and retain existing
  behavior.
- Current BVM claim creation still creates the same admission entries/tokens
  consumed by scanner and check-in code.

## Dependency audit

The recovered code calls 42 VMS runtime functions outside the recovered module.
They were classified and handled as follows:

- Renamed but equivalent BVM APIs: module/admin-page registration, admin shell,
  admission audit and normalization helpers, admission table helpers, vendor
  guest helpers, ticket status helpers, Guest Pass capability/URL/list/get/
  token-generation/date/notice/public-shell helpers.
- Companion-owned historical contracts: Outreach campaign/recipient/contact/
  suppression table names and schema management.
- Reimplemented narrow presentation/source helpers: inline tracking-category
  creation and three Guest Pass admin label/help renderers, because BVM does not
  expose those historical private functions as a stable API.
- Removed legacy coupling: old plugin constants, old bootstrap discovery, old
  menu loading, and direct dependency on an active VMS installation.

The compatibility layer delegates to `bvmgr_*` functions rather than checking
both plugin identities throughout the recovered code. An automated call-graph
check found no unresolved `vms_*` or `bvmgr_*` calls in the companion.

The historical admin-post, database, and explicit delivery boundaries remain
inside the recovered module. No cron sender or REST sender was introduced.
Assets were moved under the companion and load only on its admin page.

## Implementation and migration

Added:

- `companion-plugins/backstage-outreach/backstage-outreach.php`
- `companion-plugins/backstage-outreach/includes/compat-bvm.php`
- `companion-plugins/backstage-outreach/includes/integration-bvm.php`
- the eight recovered Outreach PHP modules listed above
- companion-scoped recovered admin CSS and JavaScript
- two focused regression suites

Modified BVM core only in `includes/modules/admissions/pass-claims.php`. The
change adds the extension hooks required for a companion to participate in a
claim without copying or replacing BVM's claim engine. It does not change the
default result when no add-on supplies context.

`release-public-excludes.txt` excludes `companion-plugins/`, preventing a public
BVM release from accidentally absorbing the add-on. The companion uses the
`backstage-outreach` translation domain while retaining compatibility-sensitive
historical function/action/table identifiers.

The schema target advances from historical `1.0.0` to companion `1.1.0`. The
migration is additive and idempotent:

- `dbDelta` creates any missing historical Outreach table or missing column;
- `maybe_add_column` adds the two claim attribution columns only if absent;
- guarded `ALTER TABLE ... ADD KEY` adds only missing attribution indexes;
- backfills normalize only missing/legacy campaign purpose, recipient send
  status, and last-contacted values;
- no drop, rename, truncate, reset, ID rewrite, or duplicate-record import is
  present.

Because the local tables already match the recovered contract, the 3 campaigns
and 208 recipients should reappear automatically after activation. The only
remaining data risk is environmental: this conclusion must be confirmed against
a backed-up staging copy before any staged migration is authorized.

## Security and delivery safety

- All 23 recovered `admin_post_*` handlers retain capability and nonce checks.
- Ten request-routed recipient actions were hardened to reject array-shaped
  nonce values before scalar sanitization/verification.
- Recovered input sanitization, output escaping, prepared queries, stale-preview
  checks, token-exhaustion checks, duplicate-reservation checks, suppression,
  queue, resend, and bulk-action guardrails remain in place.
- The one `wp_mail` call remains an explicit admin-triggered boundary. Delivery
  method and address eligibility are validated before it.
- No test invokes the mail boundary, queues live mail, changes send status,
  generates live links, or invalidates a token.

## Verification and current limitation

Focused tests cover the recovered delivery methods and validation, token
reservation/exhaustion/duplicates, edit preservation, CSV header detection and
mapping, CSV preview deduplication and full-name preservation, greeting fallback,
template merge behavior, schema safety, stale-preview guards, the mail boundary,
capability/nonce coverage, BVM navigation/context/event/prefill/party-size/
attribution hooks, ordinary-claim isolation, and scanner-compatible claim output.

The local site's inactive sibling `vms` plugin is an older monolithic runtime and
was intentionally left untouched. Its copy of `pass-claims.php` predates current
BVM request-wrapper remediation and causes the repository's cross-tree public
form source assertion to fail on direct `$_SERVER` reads. That is pre-existing
historical-tree drift, not a failure in the BVM candidate or companion. Porting
the new BVM add-on hooks into that monolith would reactivate the architecture
being retired and would conflict with its integrated Outreach code.

The current BVM mirror is a nested package, not the active plugin installed in
this local WordPress site. Consequently, the candidate has static/unit-style
coverage and read-only database evidence but has not yet had a real WordPress
activation or visual admin/public acceptance pass.

Passing commands:

```text
php tests/backstage-outreach-bootstrap.php
  Backstage Outreach bootstrap/data-read regression OK (10 assertions).
php tests/backstage-outreach-legacy-guard.php
  Backstage Outreach legacy-VMS guard OK (3 assertions).
php tests/backstage-outreach-recovery.php
  Backstage Outreach recovery regression OK (55 assertions).
php tests/backstage-outreach-bvm-integration.php
  Backstage Outreach BVM integration OK (34 assertions).
find companion-plugins/backstage-outreach -name '*.php' -print0 | xargs -0 -n1 php -l
php -l includes/modules/admissions/pass-claims.php
php tests/pass-claims-public-shell-output-remediation.php
php tests/pass-claims-public-claimed-card-output-remediation.php
php tests/pass-claims-public-success-output-remediation.php
php tests/pass-claims-public-status-output-remediation.php
php tests/addon-compatibility/runtime-contracts.php
php tests/addon-compatibility/additional-runtime-contracts.php
php tests/release-compatibility-harness.php
php tests/runtime-stub-guards.php
php tests/admissions-rest-permissions.php
php tests/authorization-boundary-hardening.php
php tests/nonce-input-normalization.php
git diff --check
```

Two broader pre-existing source assertions remain red and were not changed as
part of this recovery:

- `php tests/pass-claims-public-form-output-remediation.php` reaches into the
  inactive sibling VMS tree and fails because its old monolithic
  `pass-claims.php` still reads `$_SERVER` directly.
- `php tests/strict-post-gate-remediation.php` expects the pre-prefix field
  `vms_season_dates_nonce`, while current BVM intentionally uses
  `bvmgr_season_dates_nonce` with a compatibility mapping.

The public release build/pipeline suites were not run because this task does not
authorize ZIP/package creation.

## Candidate state

- Final HEAD: `ec9934131a951faae2ac4250915cb8bb2683cdfe` (unchanged; no commit was
  authorized)
- Refreshed origin branch HEAD:
  `ec9934131a951faae2ac4250915cb8bb2683cdfe`
- Ahead/behind after `git fetch origin`: `0/0`
- Expected dirty worktree: 3 tracked files modified and 19 new files
- Tracked diff summary: 102 insertions and 27 deletions
- Protected stash still present unchanged at `stash@{0}`
- No commit, push, package/ZIP, tag, deployment, activation, database migration,
  production/staging access, customer send, or sent-status mutation occurred

## Acceptance gate

The candidate is ready for **local acceptance**, not staging deployment yet.
In a disposable/local copy with a database backup:

1. Install/activate this BVM 1.2 candidate and keep legacy VMS inactive.
2. Install/activate `companion-plugins/backstage-outreach`.
3. Confirm the four original Guest Pass tabs plus Outreach and the Marketing &
   Social entry.
4. Confirm the existing 3 campaigns and 208 recipients render with unchanged
   IDs, templates, reserved token relationships, and statuses.
5. Exercise create/edit/import-preview using only synthetic `example.invalid`
   data; do not commit against real records.
6. Exercise a reserved synthetic invite through the current claim flow and
   scanner/check-in path without using or invalidating an existing link.
7. Verify delivery previews/exports only. Do not invoke Send, bulk mark-sent, or
   any action against real recipients.
8. If that passes, separately authorize a backed-up staging deployment and
   migration rehearsal.
