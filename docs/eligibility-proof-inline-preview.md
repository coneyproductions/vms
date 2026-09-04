# Eligibility Proof Inline Preview

Date: 2026-09-04

## Accepted pre-existing dirty baseline

The task began on branch `work/unreleased-2026-06-18` at
`a37459408c39075610ab6d4019404e08e7ba2880`, equal to its upstream at `0/0`.
The index was empty, `git diff --check` passed, and protected
`stash@{0}: WPORG-16D preserve unrelated sidebar+doc work` was present and
unchanged.

The documented earlier baseline was 20 modified paths and 13 top-level
untracked entries. The current inventory was 19 modified paths and 12
top-level untracked entries because the current HEAD committed exactly the
former `includes/portal/vendor-portal.php` modification and former untracked
`tests/vendor-portal-bonus-progress-paid-basis.php`. File timestamps, history,
the prior deployment ledger, and the remaining path inventory establish the
rest as pre-existing work. This exact inventory was therefore accepted as the
dirty baseline for this task.

### Modified paths (19)

- `docs/wporg-remediation-ledger.md`
- `includes/admin-ui/shell.php`
- `includes/modules/admissions/pass-claims.php`
- `release-public-excludes.txt`
- `scripts/test-bvm-additional-runtime-compatibility.sh`
- `scripts/test-bvm-addon-runtime-compatibility.sh`
- `tests/addon-compatibility/additional-build-report.php`
- `tests/addon-compatibility/additional-runtime-contracts-test.php`
- `tests/addon-compatibility/additional-runtime-contracts.php`
- `tests/addon-compatibility/additional-runtime-probe.php`
- `tests/addon-compatibility/additional-source-manifest.php`
- `tests/addon-compatibility/build-report.php`
- `tests/addon-compatibility/runtime-contracts-test.php`
- `tests/addon-compatibility/runtime-contracts.php`
- `tests/addon-compatibility/runtime-probe.php`
- `tests/addon-compatibility/source-manifest.php`
- `tests/fill-dates-admin-notice-placement.php`
- `tests/fill-dates-menu-hook-compatibility.php`
- `tests/pass-claims-public-status-output-remediation.php`

### Top-level untracked entries (12)

- `companion-plugins/`
- `docs/backstage-outreach-production-deployment-plan.md`
- `docs/guest-pass-outreach-local-acceptance.md`
- `docs/guest-pass-outreach-recovery.md`
- `tests/additional-suite-targeted-remediation.php`
- `tests/backstage-outreach-bootstrap.php`
- `tests/backstage-outreach-bvm-integration.php`
- `tests/backstage-outreach-legacy-guard.php`
- `tests/backstage-outreach-local-stabilization.php`
- `tests/backstage-outreach-nonce-dom.php`
- `tests/backstage-outreach-recovery.php`
- `tests/fill-dates-canonical-bvm-compatibility.php`

### Files beneath the untracked entries (60)

- `companion-plugins/backstage-outreach/README.md`
- `companion-plugins/backstage-outreach/assets/css/outreach-admin.css`
- `companion-plugins/backstage-outreach/assets/js/outreach-admin.js`
- `companion-plugins/backstage-outreach/backstage-outreach.php`
- `companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php`
- `companion-plugins/backstage-outreach/includes/admissions/outreach.php`
- `companion-plugins/backstage-outreach/includes/compat-bvm.php`
- `companion-plugins/backstage-outreach/includes/integration-bvm.php`
- `companion-plugins/backstage-outreach/includes/outreach/admin-ui.php`
- `companion-plugins/backstage-outreach/includes/outreach/contacts.php`
- `companion-plugins/backstage-outreach/includes/outreach/db.php`
- `companion-plugins/backstage-outreach/includes/outreach/helpers.php`
- `companion-plugins/backstage-outreach/includes/outreach/outreach.php`
- `companion-plugins/backstage-outreach/includes/outreach/suppression.php`
- `companion-plugins/vmsx-weather-risk/assets/admin/weather-risk.css`
- `companion-plugins/vmsx-weather-risk/assets/admin/weather-risk.js`
- `companion-plugins/vmsx-weather-risk/docs/01-build-spec.md`
- `companion-plugins/vmsx-weather-risk/docs/02-test-plan.md`
- `companion-plugins/vmsx-weather-risk/docs/03-revision-log.md`
- `companion-plugins/vmsx-weather-risk/includes/admin/assets.php`
- `companion-plugins/vmsx-weather-risk/includes/admin/load.php`
- `companion-plugins/vmsx-weather-risk/includes/admin/menu.php`
- `companion-plugins/vmsx-weather-risk/includes/admin/metabox-event-plan.php`
- `companion-plugins/vmsx-weather-risk/includes/admin/page-event-risk.php`
- `companion-plugins/vmsx-weather-risk/includes/admin/page-settings.php`
- `companion-plugins/vmsx-weather-risk/includes/ajax/refresh.php`
- `companion-plugins/vmsx-weather-risk/includes/bootstrap.php`
- `companion-plugins/vmsx-weather-risk/includes/cache/cache.php`
- `companion-plugins/vmsx-weather-risk/includes/cache/scheduler.php`
- `companion-plugins/vmsx-weather-risk/includes/capabilities.php`
- `companion-plugins/vmsx-weather-risk/includes/compatibility.php`
- `companion-plugins/vmsx-weather-risk/includes/helpers.php`
- `companion-plugins/vmsx-weather-risk/includes/providers/interface-provider.php`
- `companion-plugins/vmsx-weather-risk/includes/providers/provider-noaa.php`
- `companion-plugins/vmsx-weather-risk/includes/providers/provider-openmeteo.php`
- `companion-plugins/vmsx-weather-risk/includes/providers/provider-openweather.php`
- `companion-plugins/vmsx-weather-risk/includes/providers/provider-weatherapi.php`
- `companion-plugins/vmsx-weather-risk/includes/registry.php`
- `companion-plugins/vmsx-weather-risk/includes/services/advisory-engine.php`
- `companion-plugins/vmsx-weather-risk/includes/services/decision-window.php`
- `companion-plugins/vmsx-weather-risk/includes/services/dt-enrichment.php`
- `companion-plugins/vmsx-weather-risk/includes/services/logging.php`
- `companion-plugins/vmsx-weather-risk/includes/services/ticket-context.php`
- `companion-plugins/vmsx-weather-risk/includes/services/venue-location.php`
- `companion-plugins/vmsx-weather-risk/includes/services/weather-normalizer.php`
- `companion-plugins/vmsx-weather-risk/includes/settings.php`
- `companion-plugins/vmsx-weather-risk/uninstall.php`
- `companion-plugins/vmsx-weather-risk/vms-build.txt`
- `companion-plugins/vmsx-weather-risk/vmsx-weather-risk.php`
- `docs/backstage-outreach-production-deployment-plan.md`
- `docs/guest-pass-outreach-local-acceptance.md`
- `docs/guest-pass-outreach-recovery.md`
- `tests/additional-suite-targeted-remediation.php`
- `tests/backstage-outreach-bootstrap.php`
- `tests/backstage-outreach-bvm-integration.php`
- `tests/backstage-outreach-legacy-guard.php`
- `tests/backstage-outreach-local-stabilization.php`
- `tests/backstage-outreach-nonce-dom.php`
- `tests/backstage-outreach-recovery.php`
- `tests/fill-dates-canonical-bvm-compatibility.php`

The Outreach paths are known prior Outreach work; the add-on compatibility and
Weather Risk paths are plausibly related prior compatibility work; the three
Fill Dates paths are prior compatibility tests. No Eligibility Approvals
implementation path was dirty, and no secret, private upload, credential, or
generated production data appeared in this inventory.

## Root cause and repair

The Eligibility Approvals link already opened a new tab and did not contain a
download attribute. Commit `d1cdfbd80b05c8254cdc413d0e1bbb821ca13492`
moved proof delivery to the shared private-file streamer, whose response was
hard-coded to `Content-Disposition: attachment`. That server header caused the
download regression.

The shared streamer now accepts a strictly normalized optional disposition,
defaulting to `attachment` so every other private-file workflow retains its
prior behavior. The eligibility handler alone requests `inline` after it
revalidates the private proof path, sanitizes the response filename, detects
the file type, requires the detected and stored MIME types to match an uploader
allowlist value, and confirms that the MIME is browser-previewable. Failed or
unsupported MIME validation falls back to `application/octet-stream` plus
`attachment`. Authentication, capability, request-bound nonce, request post
type, request-owned file lookup, private-root/path checks, no-cache headers,
and direct-upload denial remain in place. Byte ranges were not added: proofs
are limited to 20 MB and the complete response already supplies an exact
`Content-Length`, while range parsing would unnecessarily broaden this private
endpoint's input surface.

## Acceptance evidence

- Local focused coverage exercises authorized PDF and uploader-accepted image
  types, attachment fallback, MIME mismatch, anonymous and under-capability
  denial, expired/malformed and request-substituted nonces, caller-supplied file
  ID substitution, traversal, non-proof buckets, wrong request types, filename
  sanitization, private response headers, new-tab markup, decision/audit
  registrations, and DOM/form invariants.
- Staging used synthetic PDF and image proofs only. Both returned exact MIME
  types with inline/private/no-store/nosniff responses and opened in separate
  browser tabs; unauthorized, expired, under-capability, record-substitution,
  and direct-private-path cases were denied. Approve/reject form structure and
  desktop/mobile layouts remained intact. Fixtures were removed, real status
  counts were unchanged, and no repair-attributable log entry appeared.
- The staging rollback is
  `/home/coney/codex-backups/eligibility-proof-inline-preview-staging-20260904T165908Z`.
- Production received only the exact two staging-accepted runtime files. The
  production rollback is
  `/home/coney/codex-backups/eligibility-proof-inline-preview-production-20260904T170841Z`.
  The deployed SHA-256 values are
  `3838284b82d78730c687aaf54c0e833e7c40b653dd3cdb1fd4920becbebc2e8b`
  for `includes/core/private-files.php` and
  `98372270441cb3b215861bc3f1472406439fc8a8a289a1e22b358c80f3d7f5f1`
  for `includes/integrations/ticketing-verifications.php`.
- Production runtime inspection classified both existing proof-ready records
  as readable, safely named, allowlisted JPEG responses with inline
  disposition; the authenticated hook is registered, the public hook is
  absent, anonymous handler access is denied, and the private upload root
  remains denied. The available browser session does not have the required
  production capability, so production browser rendering was not repeated
  against a real private proof; actual PDF/image browser rendering and headers
  were established on staging with the byte-identical candidate.
- Production Eligibility counts and the ordered request-record hash, proof-ready
  count, private-file count, order count, and pending Action Scheduler queue
  matched the pre-deploy snapshot. Admission rows were only read after deploy
  and were not mutated. The cron option timestamp hash changed during ordinary
  external scheduler operation, while the scheduler inventory, eligibility
  cleanup instance, and pending queue remained intact; neither changed runtime
  file writes cron state. No email or decision handler was invoked. Production
  logs did not grow after the deployment checkpoint.

No legacy VMS activation, WordPress.org action, package, tag, release, scanner
audit, real proof capture, customer-data output, eligibility decision, email,
order, admission, queue, or deliberate cron mutation occurred.
