# Backstage Outreach local acceptance report

Date: 2026-09-02
Environment: disposable/backed-up Local WordPress site only
Candidate: Backstage Venue Manager 1.2.0 with Backstage Outreach 1.0.0
Scope: local acceptance only; no staging, production, packaging, deployment, commit, push, or real delivery action

## 1. Environment and baseline

- Repository branch: `work/unreleased-2026-06-18`
- Repository HEAD and `origin/work/unreleased-2026-06-18`: `ec9934131a951faae2ac4250915cb8bb2683cdfe`
- Ahead/behind at the start: `0/0`
- The protected stash `WPORG-16D preserve unrelated sidebar+doc work` was present at `stash@{0}` and was not manipulated.
- The required `scripts/codex-preflight.sh` check completed before activation. It reported only the already expected recovered-candidate worktree changes.
- The local database was backed up before activation to `/Users/treyconey/Local Sites/serenade-range-local-test-site/app/sql/outreach-local-acceptance-pre-20260902-184007.sql` (SHA-256 `9b20664791ea5a5b76c4b2b9ce473ce8fcfd017001ba1282744ef3aeeb0e16cd`).
- The inactive legacy VMS tree fingerprint was `9ea701779da0f4797a18549f971b69457d50dc51e02114e7b6b758dacc1a5293` before acceptance and remained the same afterward.
- Before candidate activation, Outreach schema version was `1.0.0` and admissions schema version was `1.4.0`.
- Read-only pre-activation database baseline:

| Item | Baseline |
| --- | ---: |
| Campaigns | 3 |
| Campaign states | 1 active, 2 draft |
| Recipients | 208 |
| Recipient states | 207 ready, 1 claimed |
| Send states | 208 not sent |
| Contacts | 1 |
| Suppressions | 0 |
| Reserved token relationships | 208 |
| Distinct reserved token IDs | 208 |
| Orphan checks | 0 for every checked relationship |
| Duplicate reserved-token groups | 0 |
| Missing batch/source relationships | 0 |

## 2. Plugin activation state before and after

Before the run, the legacy `vms` plugin was inactive. The recovered candidates were installed into the local canonical plugin directories, Backstage Venue Manager was activated first, and Backstage Outreach was activated second.

Final local state:

| Plugin | State | Version |
| --- | --- | --- |
| `backstage-venue-manager` | active | 1.2.0 |
| `backstage-outreach` | active | 1.0.0 |
| legacy `vms` | inactive | 1.1.0 |

Activation produced no PHP fatal, activation warning, legacy constant/function conflict, duplicate Outreach table, or data-count change. Outreach schema advanced from `1.0.0` to `1.1.0`; the admissions schema remained `1.4.0`. The new claim-attribution columns and indexes were present. A second schema-upgrade invocation was idempotent: table/relationship fingerprints did not change.

Immediately after activation, the historical totals remained 3 campaigns and 208 recipients.

## 3. Existing-data verification

All three historical campaigns appeared in the Outreach UI and opened read-only:

| ID | Name | State | Batch | Recipients | Delivery/claim state |
| ---: | --- | --- | ---: | ---: | --- |
| 4 | Test Campaign | active | 12 | 2 | 1 ready, 1 claimed |
| 5 | Test Campaign 2 | draft | 21 | 103 | 103 ready |
| 10 | Test Campaign 2 | draft | 26 | 103 | 103 ready |

Campaign subjects and templates loaded, IDs and batch relationships were preserved, the claimed recipient remained claimed, and the remaining recipients remained ready. Legacy recipients with a null delivery method rendered with the compatible Email fallback. The claimed campaign still had two attributed admission entries, neither checked in.

For a stronger identity check, the pre-activation backup was loaded into a disposable comparison database. Every common column was compared for the original campaigns, recipients, contact, suppressions, reserved tokens, attributed claim, reservation entry, related batches, and sources. All rows matched exactly. Representative canonical hashes before/current were:

- Campaigns: `78316b10d9981b03cf375c8a2ad814ac9a940dd5a6b6227a150e530acf71d56a`
- Recipients: `ecd6467831dbbaaff9cb377c74830bf32f11f13e67600dfafbc7abf7f7be08af`
- Contacts: `2f04240d04cdb804c876b6135f0328cc62e16d1afbeeaea8ce5f8c56d5ddfb7c`
- Suppressions: `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`

## 4. Navigation and UI acceptance

The Backstage Venue Manager Guest Passes screen exposed Sources, Batches, Guest Passes, Reports, and Outreach. Outreach was discoverable in the Marketing & Social cluster and opened successfully. Registry and WordPress submenu inspection found one Outreach registration, but the rendered Outreach page displayed the global Backstage navigation twice.

At a 390-by-844 viewport, the Outreach page measured `clientWidth=390` and `scrollWidth=574`; its duplicate navigation stacked and overflowed horizontally. The ordinary Guest Passes screen measured `clientWidth=390` and `scrollWidth=570`; its tabs were available but also overflowed horizontally. These are visible acceptance defects, detailed in section 14.

## 5. Campaign and template acceptance

A clearly isolated campaign, `LOCAL ACCEPTANCE TEST - DO NOT SEND` (temporary ID 11), was created, edited, saved, and reloaded. It used temporary source 17 and batch 27. The final subject was `LOCAL ACCEPTANCE EDITED: {campaign_name} for {first_name}`. The message exercised `{first_name}`, `{invite_url}`, `{campaign_name}`, and an unknown tag.

Supported tags rendered in preview and persisted across reload. The unknown tag was removed. Copy Invite Message returned a rendered 198-character message containing the campaign and invite URL. No Send action was invoked.

The originally requested Unicode em dash was also tested. It exposed a UTF-8 corruption defect in notice extraction, so the fixture was changed to ASCII only to finish the remaining workflow without confusing the persisted-value check. The direct reproducer returned `output_has_utf8_dash=false`, `output_has_mojibake=true`, and `notice_extracted=true`.

## 6. Recipient and delivery-method acceptance

Six temporary recipients were created in the synthetic campaign:

| Method | Count | Validation/result |
| --- | ---: | --- |
| Email | 3 | Valid `example.invalid` addresses accepted; missing/invalid email rejected |
| Manual / Social | 1 | Accepted without email and retained a reserved claim token |
| Text / Phone | 1 | Accepted without email when a phone was present; missing phone rejected |
| Draft / no delivery method | 1 | Accepted and excluded from the send queue |

All six had distinct reserved claim tokens and `not_sent` delivery state. Invalid attempts created no token. No historical recipient was edited. No real or synthetic recipient was marked sent.

## 7. CSV import acceptance

A three-row synthetic CSV with six headers exercised header detection, automatic suggestions, manual remapping, Do not import, optional name behavior, company fallback, preview, stale-preview protection, and commit. This importer does not offer a delivery-method mapping field; imported rows with valid emails therefore used its default Email behavior.

- Automatic mapping: `name,email,company,phone,ignore,ignore`
- Final manual mapping: `name,email,company,phone,ignore,group`
- Missing email mapping failed with `recipient_import_email_mapping_required`.
- Duplicate mapping failed with `recipient_import_duplicate_mapping`.
- Preview reported 3 total, 3 ready, 3 valid, 0 skipped, 0 duplicate, and 0 blank.
- A blank-name row was retained using the email/company fallback.
- Changing the campaign after preview disabled commit; refreshing the preview allowed the three rows to commit, matching the preview.

The Chrome automation connection could not attach a local file to the native file chooser because that extension lacked file-URL permission. The same local CSV was therefore passed through the production parser/mapping and actual UI-handler preview/commit path in a temporary runtime harness. The file chooser itself is the only interaction not driven through the browser UI; parsing, validation, preview persistence, stale protection, and commit were exercised.

## 8. Export and queue acceptance

Both synthetic exports completed:

- Full export: 25 columns and 6 rows
- Send-preparation export: 17 columns and 6 rows

The exports contained all delivery methods, invite URLs, and rendered messages; the unsupported tag was absent. Dry-run delivery analysis selected all six recipients, reported 3 queueable unsent Email recipients, and reported the Manual/Social, Text/Phone, and Draft recipients as three validation failures. None of those three methods entered the email queue. Bulk and delivery-status controls rendered.

A local `pre_wp_mail` safety filter rejected every WordPress mail call during acceptance. No real email was accepted by the mail boundary, no queue-send action was invoked, and no send status changed. The temporary exports and mail filter were removed after evidence capture.

## 9. Synthetic end-to-end claim and check-in result

One synthetic Outreach invite routed through `/pass/invite/{token}` to the current Backstage Venue Manager claim engine. It displayed the correct batch, seven eligible events, a party-size cap of two, and the expected recipient prefill. A synthetic `example.invalid` guest selected event plan 76 and party size two.

The claim completed with claim ID 13 and entries 45/46 (temporary references GL-45/GL-46). Campaign 11 and recipient 317 attribution was present on the claim and both admissions. The page explicitly reported that confirmation email was not sent because of the local mail trap.

A read-only call through the normal admissions scanner lookup returned HTTP 200, status `valid`, and item 45 for the generated scan URL. Checked quantity remained zero, so scanner compatibility was demonstrated without performing check-in or mutating status.

## 10. Ordinary Guest Pass regression result

Sources, Batches, Guest Passes, and Reports opened successfully after Outreach activation. An isolated ordinary batch preview succeeded. After its preview transient was deliberately removed, Commit returned `Preview expired or missing`; the source batch/token counts did not change, proving the commit guard.

An additional ordinary, unreserved token in the synthetic batch used the normal `/pass/claim` route and created one admission (temporary GL-47). Reopening the route showed the Already Claimed card/reference. Its claim had null Outreach campaign/recipient attribution, and no Outreach recipient was attached or altered. This confirms that ordinary Guest Pass behavior remained distinct from Outreach attribution.

## 11. Security and failure-path results

All exercised failures were safe and left campaign/recipient counts and historical fingerprints unchanged:

- Insufficient capability: `Access denied.`
- Invalid nonce: link-expired rejection
- Malformed email: `invalid_recipient_email`
- Text recipient without phone: `recipient_phone_required`
- Historical recipient supplied with the synthetic campaign: `recipient_campaign_mismatch`
- Invalid recipient ID: `invalid_recipient`
- Invalid invite token: `invalid_invite_token`
- Already-reserved preferred token: reservation helper returned null/refused
- Invalid CSV mappings: required-email and duplicate-mapping errors
- Stale CSV preview: commit disabled until a new preview

No unintended mutation followed any failure.

## 12. Database before/after comparison

At the documented peak synthetic state, the database had 4 campaigns and 214 recipients: the original 3/208 plus 1 campaign and 6 recipients. It had 214 distinct reservations, zero reservation duplicates, zero checked relationship orphans, one synthetic Outreach claim, and one synthetic ordinary claim. Every send status was still `not_sent`.

Only the explicitly recorded synthetic campaign, source, batch, recipients, tokens, claims, admissions, audit rows, temporary administrator, test transients, files, and mail filter were removed. Historical references were checked before cleanup, and all pre-existing transient rows matched the pre-acceptance backup afterward. The final state was:

| Item | Before activation | After cleanup |
| --- | ---: | ---: |
| Campaigns | 3 | 3 |
| Historical campaigns | 3 | 3 |
| Recipients | 208 | 208 |
| Historical recipients | 208 | 208 |
| Recipient states | 207 ready / 1 claimed | 207 ready / 1 claimed |
| Send states | 208 not sent | 208 not sent |
| Contacts | 1 | 1 |
| Suppressions | 0 | 0 |
| Reserved/distinct tokens | 208 / 208 | 208 / 208 |
| Synthetic acceptance records | 0 | 0 |
| Checked relationship orphans | 0 | 0 |
| Duplicate reservation groups | 0 | 0 |

The final historical campaign, recipient, contact, suppression, and legacy-tree hashes matched their baselines. No historical status, reservation, or delivery state changed.

## 13. Automated test results

The following focused suites passed with exit code 0:

- Backstage Outreach bootstrap/data-read tests: 10 assertions
- Legacy conflict guard: 3 assertions
- Recovery behavior/security suite: 55 assertions
- Backstage Venue Manager integration suite: 34 assertions
- Ordinary claim shell, claimed-card, success, and status regressions
- Runtime stub guards
- Admissions REST permissions
- Authorization-boundary hardening
- Nonce-input normalization
- Add-on compatibility runtime contracts and additional runtime contracts
- Release-compatibility harness self-test
- PHP lint across the 12 changed/recovered PHP files
- `git diff --check`

The two authorized, known unrelated source assertions remained red and their source files were unchanged:

- `pass-claims-public-form-output-remediation` stops on the inactive legacy VMS direct `$_SERVER` assertion.
- `strict-post-gate-remediation` stops on the obsolete `vms_season_dates_nonce` assertion.

The full five-scenario add-on shell compatibility matrix also reported a tooling mismatch: all no-fatal and core-absent checks passed, but identity/load-order assertions still expect the legacy `vendor-management-system.php` basename while the active candidate correctly uses `backstage-venue-manager/backstage-venue-manager.php`. The stale expectations are in `tests/addon-compatibility/source-manifest.php`, `scripts/test-bvm-addon-runtime-compatibility.sh`, and `tests/addon-compatibility/runtime-probe.php`. Those pre-existing harness files were not changed. This is tracked as test-harness drift, not evidence of a runtime fatal.

## 14. Defects discovered and exact files involved

1. **Duplicate global navigation on Outreach pages.** The companion registers an Outreach callback that renders the Backstage shell but omits the shell metadata flag. The outer navigation and shell navigation therefore both render. Directly involved files:
   - `companion-plugins/backstage-outreach/includes/outreach/outreach.php`
   - `includes/core/registry/admin-menu.php`
   - `includes/admin-ui/nav.php`
   - `includes/admin-ui/shell.php`

2. **UTF-8 notice corruption.** Notice extraction turns a valid U+2014 em dash into mojibake because the DOM loading path does not preserve the captured UTF-8 encoding. Directly involved file:
   - `includes/admin-ui/shell.php`

3. **Narrow-width horizontal overflow.** At 390 CSS pixels, both the Outreach screen and ordinary Guest Passes screen overflow horizontally. Relevant style surfaces for focused triage are:
   - `assets/css/vms-admin-ui.css`
   - `assets/css/vms-pass-claims-admin.css`
   - `companion-plugins/backstage-outreach/assets/css/outreach-admin.css`

4. **Canonical-plugin compatibility harness drift.** The shell harness still hard-codes the legacy BVM entry basename and therefore fails its identity/load-order assertions after canonical activation. Exact files:
   - `tests/addon-compatibility/source-manifest.php`
   - `scripts/test-bvm-addon-runtime-compatibility.sh`
   - `tests/addon-compatibility/runtime-probe.php`

The duplicate navigation, mojibake, and mobile overflow are user-visible local defects and must be fixed before a staging rehearsal recommendation.

## 15. Final repository state

- HEAD: `ec9934131a951faae2ac4250915cb8bb2683cdfe`
- Branch: `work/unreleased-2026-06-18`
- Ahead/behind: `0/0`
- Protected stash: still `stash@{0}: On work/unreleased-2026-06-18: WPORG-16D preserve unrelated sidebar+doc work`
- Worktree: recovered candidate changes remain intentionally uncommitted; this acceptance report is an additional untracked deliverable. No unrelated source was intentionally edited.
- Legacy VMS: inactive and byte-fingerprint unchanged.
- Commit/push/package/deploy: none.
- Staging/production changes: none.

## 16. Local stabilization follow-up

Date: 2026-09-03

The three user-visible blockers recorded in section 14 are resolved in the recovered candidate and synchronized active local plugin trees:

- Outreach now declares `shell => true` in its canonical registry entry. The registry therefore does not add a second outer shell around the page callback's canonical shell. Campaigns, recipients, contacts, suppressions, CSV/import, and delivery/export surfaces each render one `.vms-admin-shell` and one `.vms-admin-topnav`. Guest Passes still exposes the Outreach tab, Marketing & Social still exposes the Outreach navigation item, and no duplicate physical WordPress submenu row was introduced.
- The shared BVM shell prepends an explicit UTF-8 declaration to its sole `DOMDocument::loadHTML()` input. Focused extraction tests and live Outreach notices preserved `Café`, `Résumé`, em dash, middle dot, curly apostrophe, ampersand/entity content, translated-style text, and emoji without mojibake or entity double-encoding.
- Closed Outreach help popovers now use `display: none`; opened popovers restore display, wrap long content, and use a viewport-contained fixed position at WordPress narrow-admin widths. Wide tables remain internally scrollable. Outreach CSS and JavaScript remain gated to `page=vms-outreach`, with file modification-time versions preventing stale local asset caching.

Responsive browser acceptance produced zero Outreach document overflow at 390x844, 430px wide, and 1440px wide across the campaign list, campaign editor/recipient manager, contacts, suppressions, and CSV/import surfaces. At 390px, contained table scrollers measured 302/760 (campaign list), 272/1386 (recipients), 302/1260 (contacts), and 302/1080 (suppressions) client/scroll widths. At 430px they measured 342/760, 312/1386, 342/1260, and 342/1080. Desktop document overflow was zero; the recipient and contacts tables retained only their intended internal scrolling where their content exceeded the container.

Ordinary Guest Passes, Sources, Batches, and Reports load no Backstage Outreach CSS or JavaScript. Their existing 390px BVM-native overflow remains outside this narrow repair; Outreach-caused overflow is zero, and desktop document overflow is zero. Their shell, headings, content, and notices remained intact.

Historical data matched its pre-change state exactly after acceptance: 3 campaigns (1 active, 2 draft), 208 recipients (207 ready, 1 claimed), 208 `not_sent`, 208 distinct reservations, 1 contact, no suppressions, and zero campaign/token/claim orphans or duplicate reservation groups. Full ordered row hashes for campaigns, recipients, linked tokens, linked claims, contacts, and suppressions were unchanged.

The focused Outreach suites passed 10, 3, 55, 34, and 37 assertions respectively. Runtime-stub guards, Admissions REST permissions, authorization-boundary hardening, nonce-input normalization, the maintained ordinary public-claim shell/claimed-card/status/success regressions, PHP lint, JavaScript syntax checks, and `git diff --check` passed. The previously documented inactive-legacy direct-`$_SERVER` assertion remains unchanged and outside this task. The official-five compatibility matrix remained 18/18 PASS, and the additional supported-suite matrix remained 49/49 PASS.

Synthetic in-memory campaign/recipient fixtures using `example.invalid` rendered a UTF-8 campaign preview, invite subject, invitation body, and `/pass/invite/` URL. The live synthetic invalid-token route safely rendered Claim Unavailable. Existing focused integration coverage reconfirmed claim attribution and scanner/check-in-compatible admission metadata. No mail boundary was crossed, no recipient was marked sent, and no persistent synthetic record required cleanup; the temporary notice transients were consumed.

## Final recommendation

**READY FOR STAGING MIGRATION REHEARSAL**
