# Backstage Outreach Production Deployment Plan

Date: 2026-09-03

Status: audited plan only. This task made no production, staging, plugin, database, cron, cache, mail, source-control, package, or release change.

The future deployment requires a new, explicit authorization after the source-control and release-artifact preparation gate in sections 22–24 is complete.

## 1. Local accepted-candidate baseline

- Repository: `vms-github-reconcile`
- Branch: `work/unreleased-2026-06-18`
- HEAD/origin: `ec9934131a951faae2ac4250915cb8bb2683cdfe`
- Ahead/behind: `0/0`
- Staging area: empty
- Worktree: intentionally dirty with the accumulated accepted candidate and unrelated work; status filename fingerprint `019618fe1063c45aa4c26597cbc0ab28748e6727c7b050dd001dee3f18456dad`
- Protected stash: `WPORG-16D preserve unrelated sidebar+doc work`, present and untouched
- `git diff --check`: pass
- Accepted BVM file hashes:
  - `includes/admin-ui/shell.php`: `9c41808eca46ff43908db80f62049de2ffb4474b3423c9201363ad4adaacedc8`
  - `includes/modules/admissions/pass-claims.php`: `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436`
- Accepted Backstage Outreach: version `1.0.0`, exactly `14` files, tree SHA-256 `84f6e7cbe4e4b3c5dcc7c5b3641a03be0f7fd4dbd89e707278a803e548b7b0f2`
- Focused Outreach suites: `10`, `3`, `55`, `34`, `37`, and nonce-DOM/security `220` assertions, all pass.
- PHP `8.3` compatibility controls: official-five `18/18 PASS` at `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.1tZj6S/`; additional suite `49/49 PASS` at `/var/folders/33/ltvj2kb927dcmnpdb1x8qd0h0000gn/T/bvm-addon-compat-report.jZgRt2/`.

The local PHP default is now `8.5.9`; the two compatibility matrices must be invoked with `BVM_COMPAT_PHP_BIN=/opt/homebrew/opt/php@8.3/bin/php` to reproduce the production/staging PHP `8.3` result without WP-CLI dependency deprecation output corrupting configuration reads.

## 2. Production environment baseline

- URL: `https://serenaderange.com`
- WordPress root: `/home/coney/public_html/serenaderange.com`
- Server: `p3plmcpnl504592.prod.phx3.secureserver.net`
- PHP CLI: `8.3.32`
- WordPress: `7.1`
- Database prefix: `micd_`
- `DISABLE_WP_CRON`: `true`
- External crontab: `9` active entries; `2` match WordPress cron; `0` match Outreach.
- WordPress cron option: `59` distinct hooks / `60` instances. Relevant existing non-Outreach hooks are `action_scheduler_run_queue`, `vms_email_followups_cron`, and `vms_social_process_queue`, one instance each.
- Production database size at inspection: `171,933,696` bytes.
- Filesystem free space at inspection: `471,873,968` KiB.
- BVM directory owner/mode: `coney:coney`, `0755`; the two target files are `0644`.
- Production BVM directory size: `11,534,770` bytes.

All inspection commands were read-only: static file/version/hash reads, direct SQL `SELECT`, `information_schema` reads, and crontab inspection. WordPress plugins were not booted for the production inventory.

## 3. Production BVM file-diff inventory

Production and accepted local BVM trees each contain `383` files. The production tree SHA-256 is `fbbcf0cd1517c5da3c6281ae681ed81527ad2e68f81e7595398d022a15caba5a`; the accepted candidate tree SHA-256 is `a4c5acf4f54eec6ac9e234f412028e66b8dbc6ef62c28744b1aad595664dd5e1`.

Exactly two files differ:

| Relative file | Production SHA-256 | Accepted SHA-256 | Classification |
| --- | --- | --- | --- |
| `includes/admin-ui/shell.php` | `68786ee6db0dc274eaf20a8b057e75dc6ba08c514813f2bc3e19614215fd3c84` | `9c41808eca46ff43908db80f62049de2ffb4474b3423c9201363ad4adaacedc8` | Staging-accepted UTF-8 shell prerequisite; replace surgically |
| `includes/modules/admissions/pass-claims.php` | `f62ba438bbe9144819fad1f0336c625b0c230369891d1a2577bc9f90b7e3829d` | `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436` | Staging-accepted Outreach claim integration prerequisite; replace surgically |

The other `381` BVM files are byte-identical. There is no unknown production BVM drift to reconcile and the future deployment must not replace the BVM directory recursively.

## 4. Production plugin activation inventory

- Canonical BVM basename `backstage-venue-manager/backstage-venue-manager.php`, version `1.2.0`: active.
- Legacy VMS `vms/vendor-management-system.php`, version `1.1.0`: installed, inactive; tree SHA-256 `e94b4ae2d92d8e23044b13252824a0df9641772ade51953760c7c981a6755f3f`.
- Backstage Outreach: absent/inactive.
- No network-active plugin entry was found.

The `29` active plugins and inspected versions are:

| Plugin basename | Version |
| --- | ---: |
| `backstage-venue-manager/backstage-venue-manager.php` | `1.2.0` |
| `contact-form-7/wp-contact-form-7.php` | `6.1.7` |
| `event-tickets-plus/event-tickets-plus.php` | `6.9.3` |
| `event-tickets/event-tickets.php` | `5.29.3.1` |
| `event-venue-map-modal/event-venue-map-modal.php` | `1.2.4` |
| `events-calendar-pro/events-calendar-pro.php` | `7.8.2` |
| `flamingo/flamingo.php` | `2.6.4` |
| `insert-headers-and-footers/ihaf.php` | `2.3.9` |
| `kadence-blocks/kadence-blocks.php` | `3.7.10` |
| `mailpoet/mailpoet.php` | `5.37.0` |
| `pixelyoursite/facebook-pixel-master.php` | `11.4.0` |
| `seo-by-rank-math/rank-math.php` | `1.0.277.2` |
| `simple-cloudflare-turnstile/simple-cloudflare-turnstile.php` | `1.42.1` |
| `sr-image-reel/sr-image-reel.php` | `0.2.0` |
| `sr-tec-custom-css/sr-tec-custom-css.php` | `1.0.0` |
| `the-events-calendar/the-events-calendar.php` | `6.17.3.1` |
| `user-switching/user-switching.php` | `1.12.2` |
| `vms-agreements/vms-agreements.php` | `0.3.48` |
| `vms-data-tools/vms-data-tools.php` | `0.5.54` |
| `vms-events-slider/vms-events-slider.php` | `1.0.10` |
| `vms-express-bar/vms-express-bar.php` | `0.6.24` |
| `vms-investor-portal/vms-investor-portal.php` | `0.2.3` |
| `vms-meta-ads/vms-meta-ads.php` | `0.1.105.1` |
| `vms-ops-console-premium/vms-ops-console-premium.php` | `0.1.65.1` |
| `vms-refer-a-friend/vms-refer-a-friend.php` | `0.2.6` |
| `vms-sponsorships/vms-sponsorships.php` | `0.1.7.1` |
| `woocommerce-square/woocommerce-square.php` | `5.5.0` |
| `woocommerce/woocommerce.php` | `11.1.0` |
| `wp-super-cache/wp-cache.php` | `3.1.3` |

Eight MU-plugin files are present: `00-sr-silence-notices.php`, `01-sr-find-post-type.php`, `automation-by-installatron.php`, `disable-password-change-admin-email.php`, `health-check-troubleshooting-mode.php`, `vms-rsvp-labels.php`, `zz-loopback-admin-action-trace.php`, and `zz-pys-server-event-route-guard.php`.

## 5. Production Outreach schema/version

- `vms_outreach_db_version`: `1.0.0`.
- All four Outreach tables exist.
- Both claims attribution columns and both supporting indexes exist.
- A prefix-normalized fingerprint over the four table definitions plus claims attribution columns/indexes is `4249f9b67f71fe21da274fd41bb9f9d80c7ecd1772e83677b3c6e826c4ce9c21` in production and accepted staging; the normalized definitions have no diff.
- Migration candidates: blank campaign purpose `0`; invalid send status `0`; `last_contacted_at` backfill from `sent_at` `0`.

Production therefore has the accepted `1.1.0` physical schema while retaining the `1.0.0` marker. Recheck the schema fingerprint and all three candidate counts immediately before deployment. Any difference or nonzero candidate count is a stop requiring migration review.

## 6. Production Outreach aggregate data inventory

- Campaigns: `2`, IDs `2,3`, both `active`.
- Recipients: `132`; statuses `129 ready`, `3 claimed`.
- Stored send status: all `132 sent`.
- Stored send method: all `132 vms_email`; normalized delivery method: all `132 email`.
- `sent_at`: set for all `132`.
- Distinct reserved pass tokens: `132`; null reservations: `0`.
- Claimed recipient rows: `3`.
- Outreach-attributed claims: `3`.
- Outreach-linked admissions: `6`.
- Contacts: `0`; suppressions: `0`.
- Related core totals: `5` sources, `6` batches, `161` tokens, `9` claims, and `30` admissions; the Outreach-linked subsets are `132` tokens, `3` claims, and `6` admissions.
- Stored queued recipients: `0`; structurally runnable queued recipients: `0`; structurally queueable `not_sent` candidates: `0`.

No names, emails, phones, notes, invite secrets, token material, or raw customer rows were printed or copied.

## 7. Production orphan/duplicate checks

All inspected counts are zero:

- recipient-to-campaign orphans
- recipient-to-token orphans
- recipient-to-claim orphans
- recipient-to-reservation-entry orphans
- recipient-to-contact orphans
- campaign-to-source orphans
- campaign-to-batch orphans
- claim-to-Outreach-campaign orphans
- claim-to-Outreach-recipient orphans
- linked-token-to-batch orphans
- linked-token-to-source orphans
- duplicate pass-token reservation groups

## 8. Deterministic production data hashes

The future deployment must calculate each row as `SHA2(JSON_ARRAY(<all columns in physical schema order>), 256)`, order row hashes by primary key `id`, then SHA-256 the newline-delimited row-hash stream. Relationship tables use the fixed predicates described below. Raw row values never leave production.

No columns are excluded: current migration candidate counts are zero, so no historical field or timestamp is expected to change. Track `vms_outreach_db_version`, `backstage_outreach_flush_rewrite`, and the rewrite-rules hash separately because those deployment-owned options are expected to change.

Current read-only planning baselines, which must be freshly recaptured during the authorized deployment window, are:

| Dataset | Scope | Planning baseline SHA-256 |
| --- | --- | --- |
| Campaigns | every campaign column/row | `4d06177f0b9bb674f3c7efaf8c131a6a9c2b3117a82a5cdf498b84e67b25cb90` |
| Recipients | every recipient column/row | `7472443998112e17ae139d7b3a469725e98110f4e8ec3e42f4f16b033275f982` |
| Contacts | every contact column/row | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| Suppressions | every suppression column/row | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| Linked tokens | token IDs referenced by recipients | `90daea87ba64fca37e9de27c5df4e71168d1a04c849103470ccbb96ead34a010` |
| Linked claims | attributed or recipient-referenced claims | `f8eec89da4e073b5f06c737440b48692649c768534d1b21346d43452341f6a29` |
| Linked admissions | admissions tied to the linked claims | `18442a5518355be520f5a824a28a79bc1f1617fdfe42d8f8f7f78031d6c14206` |
| Linked sources | sources referenced by campaigns or linked tokens | `21d2049ab6ade748083131259db8c2ef457110fd39d2ac5f76f34a2bd93ff248` |
| Linked batches | batches referenced by campaigns or linked tokens | `e1c4c8294a6e19ea182ae5b74cb18417f4f99d0486319b18432e0f0c70f25371` |

Current option baselines are active-plugin value hash `3060c5195514db5c1a15931e210fbaead61d264bc3a4a54c9fb7f4569185e62b`, rewrite-rules value hash `3d14f394b13075b215471283a8722332a52e334e6a3d1619186cba1cd4105484`, marker `1.0.0`, and no `backstage_outreach_flush_rewrite` row.

Capture the same manifest at `PRE`, after the two BVM replacements, immediately after Outreach migration, after UI smoke, and at the end of the observation window. Any historical-data hash difference is an immediate stop unless it is an explicitly approved concurrent business write that can be independently identified.

## 9. Email/send safety analysis

- The Outreach candidate registers no cron or scheduled-send hook.
- Activation only sets a rewrite-flush flag; normal boot performs schema/version reconciliation and a soft rewrite flush.
- `wp_mail()` is reachable only from explicit nonce/capability-protected admin-post send handlers.
- Queue mutations likewise require explicit admin actions.
- Production currently has zero queued, runnable-queued, or `not_sent` structural queue candidates; all `132` historical recipients are already `sent`.
- Existing production email and queue systems must remain online; do not install a global mail blocker or suspend unrelated cron.

Deployment operators must not invoke Send, Send Queued, Queue, bulk mark-sent, recipient resend, a send-capable URL, or any WP-CLI send/eval command. Capture mail/log counters immediately before and throughout the stability window; any Outreach-attributed send activity triggers rollback.

## 10. Exact expected production migration behavior

On the first controlled plugin boot after activation:

1. The `1.0.0` marker differs from target `1.1.0`.
2. `dbDelta()` reconciles the four existing tables. The accepted normalized schema already matches, so no physical schema change is expected.
3. Claims columns/index guards find all four objects already present.
4. The campaign-purpose, send-status, and last-contacted backfills find zero candidate rows; full historical row hashes remain unchanged.
5. `vms_outreach_db_version` becomes `1.1.0`.
6. The activation-owned rewrite flag causes one soft rewrite-rules refresh and is removed.

No `DROP`, `TRUNCATE`, destructive rename, recipient/campaign reset, reservation reassignment, send-state change, scheduled send, or email is expected. Stop before activation if the preflight schema fingerprint or candidate counts differ from section 5.

## 11. Backup procedure for the future authorized run

Backup generation is a production filesystem write and was not performed in this planning task. During the authorized deployment, resolve one UTC deployment ID and create a mode-`0700` directory owned by `coney:coney` at:

`/home/coney/codex-backups/backstage-outreach-production-<UTC_DEPLOYMENT_ID>`

Before any plugin-file write, create:

1. `production-before.sql`: full single-transaction database dump including triggers and routines where supported.
2. `production-before.sql.gz`: compressed copy; require successful compressor exit and `gzip -t`.
3. `backstage-venue-manager-before.tar.gz`: exact current `wp-content/plugins/backstage-venue-manager` directory.
4. `backstage-outreach-before.tar.gz` if the directory unexpectedly exists; otherwise `backstage-outreach-before.absent` containing only the resolved path and inspection time.
5. `active-plugins-before.txt`: direct-SQL active-plugin inventory plus canonical/legacy/Outreach state.
6. `cron-hooks-before.txt`: external-cron aggregate and WordPress hook/count inventory; no credential-bearing command lines.
7. `production-pre-counts-hashes.txt`: section 5–8 schema, counts, distributions, orphan checks, and row hashes.
8. `target-files-before.sha256`: the two production BVM hashes and any existing Outreach manifest.
9. `manifest.sha256`: SHA-256 for every artifact above.

For each artifact, record absolute path, byte size, owner/mode, and SHA-256. Require the SQL command to exit zero, the dump to be nonempty and readable, its completion marker to be present, the gzip integrity test to pass, both tar archives to list successfully, and every recorded checksum to verify. Stop if any backup check fails. Do not store this backup in the repository or copy raw production data to local/staging evidence.

## 12. Exact file deployment manifest

Destination root is `/home/coney/public_html/serenaderange.com/wp-content/plugins`.

| Destination relative to plugin root | Accepted SHA-256 | Current production | Action |
| --- | --- | --- | --- |
| `backstage-venue-manager/includes/admin-ui/shell.php` | `9c41808eca46ff43908db80f62049de2ffb4474b3423c9201363ad4adaacedc8` | `68786ee6db0dc274eaf20a8b057e75dc6ba08c514813f2bc3e19614215fd3c84` | replace |
| `backstage-venue-manager/includes/modules/admissions/pass-claims.php` | `ebcf893380bea92a14ffb40de3606140eab8b4033eb8eb9b89430c10d992d436` | `f62ba438bbe9144819fad1f0336c625b0c230369891d1a2577bc9f90b7e3829d` | replace |
| `backstage-outreach/README.md` | `0c9d943497367a06e00c36c64a2e6b22b943692c5ddfca6145b81be46596e28d` | absent | create |
| `backstage-outreach/assets/css/outreach-admin.css` | `e0af5a1bf9ac04f54d4b3aa6f409ba6345ea36e8ed930a4bd8d1fca149297d16` | absent | create |
| `backstage-outreach/assets/js/outreach-admin.js` | `853995622873d8b8fc70d9a59f938f1b04c7754846efbfb7f9e37f0fce3d24bf` | absent | create |
| `backstage-outreach/backstage-outreach.php` | `546e33db008e358f2b493bc167fdadbfeabe55ba241cba4c63106be459ed1216` | absent | create |
| `backstage-outreach/includes/admissions/outreach-recipients.php` | `9b19713b14e53404b2ab1ffbf5e3e0335105e118f9e3f7a4f12ed6b6b0d5b793` | absent | create |
| `backstage-outreach/includes/admissions/outreach.php` | `66df1b61e73f877a06bb006377ca0047e57f8fba854a42327f219f8e3a261e33` | absent | create |
| `backstage-outreach/includes/compat-bvm.php` | `248e8813801d417b35ff35e82e837ab1e5d151f6cd841de380391195f88f0df1` | absent | create |
| `backstage-outreach/includes/integration-bvm.php` | `63d4251c3e2e93b4c1e33ed5ac85b846f40097a50038a55f65608688261235ff` | absent | create |
| `backstage-outreach/includes/outreach/admin-ui.php` | `6be69e69b1da671b3cf9dddb35e3cd6f57c78a51c1e705e5023e9bcdf0413aeb` | absent | create |
| `backstage-outreach/includes/outreach/contacts.php` | `e26d8278e9588172acb2e2ad77b2456d95568554d23c0085ccfe77623326920b` | absent | create |
| `backstage-outreach/includes/outreach/db.php` | `253dd7d69b37098e771aca2a9f385ff6ae82646cd352949974cd52c6d74fff07` | absent | create |
| `backstage-outreach/includes/outreach/helpers.php` | `ddea77a7a29410a06276ce572d37f9bfbdc4a6db6dc7ca2b3a4082fcb1622d05` | absent | create |
| `backstage-outreach/includes/outreach/outreach.php` | `5a688e6ea6930275942c548a1e381ab0c01efe08b5100876c6d295dbd9e23cac` | absent | create |
| `backstage-outreach/includes/outreach/suppression.php` | `ae836c6f6c2a508bf40b67c8bdc9e2d8cca978695446c7dfe02a629bd37c1b92` | absent | create |

Outreach directories must be `0755`, files `0644`, owned by `coney:coney`. The verified 14-file tree must hash to `84f6e7cbe4e4b3c5dcc7c5b3641a03be0f7fd4dbd89e707278a803e548b7b0f2` before activation.

## 13. Exact future deployment order

1. Obtain explicit authorization naming the immutable source commit(s), artifacts, hashes, production root, backup plan, rollback plan, and send-safety boundary.
2. Establish a low-traffic maintenance/readiness window; do not toggle sitewide maintenance unless separately authorized.
3. Re-run read-only environment, active-plugin, legacy-VMS, file-drift, schema, migration-candidate, queue, count, orphan, and free-space gates. Stop on any drift.
4. Record the starting PHP error-log byte offset and current mail/cron counters.
5. Create and fully verify the section 11 backup.
6. Capture the fresh `PRE` schema/count/hash manifest.
7. Transfer the immutable artifacts to a non-public deployment staging directory; verify artifact and extracted per-file hashes before touching plugin destinations.
8. Place only the two BVM files via same-directory temporary files and atomic renames, preserving `coney:coney`/`0644`. Verify both destination hashes and confirm all other `381` BVM files still match.
9. Perform read-only BVM Dashboard and ordinary Guest Pass smoke. Roll back the two files immediately on a fatal or regression.
10. Build the Outreach directory under a temporary sibling name, verify exactly `14` files, modes, per-file hashes, and tree hash, then atomically rename it to `backstage-outreach`. Stop if the destination unexpectedly exists.
11. Confirm BVM remains active and legacy VMS remains inactive. Confirm Outreach is installed but inactive.
12. Activate only `backstage-outreach/backstage-outreach.php`.
13. Perform one controlled plugin boot to run the migration/rewrite step; confirm marker `1.0.0 -> 1.1.0` and activation-owned rewrite flag removal.
14. Immediately capture `POST-MIGRATION` schema/count/hash, queue, plugin, cron, and log evidence. Stop and enter rollback if any historical hash/count/status/relationship changes.
15. Run the section 14–16 read-only production acceptance. Do not submit any Outreach mutation form.
16. Capture `POST-SMOKE` hashes and complete the section 20 stability window.

## 14. Historical-data acceptance plan

Immediately after migration and again after UI smoke, require:

- campaign count `2`, IDs `2,3`, both active
- recipient count `132`; status distribution `129 ready / 3 claimed`
- all `132 sent`, `132 vms_email`, and `132 sent_at` set
- `132` distinct reservations and no null reservation
- claimed recipient count `3`, Outreach claims `3`, linked admissions `6`
- contacts `0`, suppressions `0`
- all orphan/duplicate checks in section 7 remain zero
- every section 8 historical hash equals the fresh `PRE` hash

Open one historical campaign and its recipients read-only. Do not save, edit, queue, resend, mark sent, import, or export customer data merely to prove UI behavior.

## 15. UI/DOM acceptance plan

Use an authenticated browser session read-only after all hash gates pass:

- Guest Passes: Sources, Batches, Guest Passes, Reports, Outreach
- Marketing & Social: Outreach
- Outreach: campaign list, one historical campaign editor, recipients, Contacts, Suppressions, CSV/import surface, and delivery/export surface

Require one canonical shell, one top navigation, no Outreach-attributable duplicate IDs, unique campaign-editor and Contacts nonce IDs, correct UTF-8, no fatal/recovery notice, and no obvious desktop regression. A 390/430px read-only smoke is permitted; do not create campaign data for it. Do not submit invalid nonces or any mutation form in production.

## 16. Ordinary Guest Pass control plan

After the BVM files and again after Outreach activation, open Sources, Batches, Guest Passes, and Reports read-only. Confirm existing content renders, shell/navigation remain singular, no fatal appears, and no Outreach CSS/JavaScript loads on ordinary pages. Do not consume, claim, create, edit, or revoke a production pass.

## 17. Production synthetic claim recommendation

Do not perform any production claim mutation. Staging already proved Outreach attribution, claim, admissions, scanner/check-in, and ordinary Guest Pass isolation end-to-end. A production synthetic source/batch/campaign/recipient would add unnecessary customer-environment writes and cleanup risk. Any later request for such a test requires separate explicit authorization and an isolated-data/cleanup plan.

## 18. Immediate rollback triggers

- PHP fatal, recovery mode, or new fatal/recovery log entry
- legacy VMS active or dependency-conflict notice
- any unexpected BVM file drift or failed BVM smoke
- Outreach upload/tree/hash/activation failure
- schema fingerprint mismatch or schema/database error
- any campaign, recipient, token/reservation, claim, admission, contact, suppression, source, or batch hash change
- any campaign/recipient ID, status, sent state, claimed state, relationship count, orphan count, or duplicate-reservation change
- any Outreach email, queue mutation, or new Outreach cron hook
- inability to access Outreach administration
- ordinary Guest Pass regression
- severe UTF-8, shell/navigation, or DOM regression that blocks safe administration

## 19. Exact rollback procedure

1. Stop acceptance activity and record the trigger, current plugin states, destination hashes, logs, counts, and data hashes.
2. If Outreach was activated, deactivate only Backstage Outreach.
3. Restore the two original BVM files from the verified backup using atomic replacement; require production hashes `68786ee6db0dc274eaf20a8b057e75dc6ba08c514813f2bc3e19614215fd3c84` and `f62ba438bbe9144819fad1f0336c625b0c230369891d1a2577bc9f90b7e3829d`.
4. If Outreach was absent before deployment, move the deployed directory intact into the backup directory for forensics rather than deleting it. If it existed, restore its exact prior archive.
5. If historical hashes and schema are unchanged, do not import the full database. Restore only deployment-owned option state to its recorded `PRE` values and perform a soft rewrite flush after the plugin is absent.
6. If any historical data/schema hash changed unexpectedly, confirm no unrelated write occurred after backup, then restore the full verified DB dump. Escalate instead of overwriting concurrent legitimate business data.
7. Verify BVM active, legacy VMS inactive, Outreach restored to its prior absent/inactive state, the prior active-plugin inventory, both BVM hashes, normal Guest Pass pages, no Outreach cron, and all historical Outreach counts/hashes.
8. Capture a final rollback manifest with absolute paths, byte sizes, hashes, plugin states, schema/data hashes, and log evidence.

## 20. Stability observation plan

Observe for `60` minutes before closing deployment, with checkpoints immediately, at `15`, `30`, and `60` minutes:

- inspect new PHP error-log content from the saved byte offset
- check WordPress fatal/recovery notices
- open Outreach and ordinary Guest Pass admin pages read-only
- confirm BVM/legacy/Outreach activation states
- compare cron hooks with `PRE`; require no Outreach hook
- check for Outreach-attributed mail/send activity
- recheck recipient counts, sent/claimed/queued distributions, orphans, duplicates, and all historical hashes

If all checkpoints pass, close the immediate deployment window. Perform an additional read-only next-business-day hash/state check; it is follow-up assurance, not a reason to keep the deployment session open.

## 21. Staging-state recommendation

Leave staging in its accepted active-Outreach state, with the sanitized fixture intact, through production deployment and the next-business-day check. It provides a known-good comparison for hashes, navigation, DOM, responsive, and migration behavior. Clean staging only under a separate authorization after production stability is confirmed.

## 22. Source-control isolation analysis

The production payload can be isolated cleanly:

- Production and accepted candidate BVM trees differ only in the two named tracked files.
- The 14-file Outreach directory is self-contained under `companion-plugins/backstage-outreach`.
- Staging passed with only those 16 runtime files; no Fill Dates, Express Bar, Season Passes, Weather Risk, Sponsorships, Checkout Policies, or compatibility-harness runtime candidate is required.
- The Outreach claim integration depends on the accepted `pass-claims.php` extension points; that is the only deployment-order dependency.

Do not stage from the current dirty worktree wholesale. Create a clean isolated worktree/branch at HEAD, copy in only the hash-verified two BVM files and 14-file Outreach tree, then require `git status`, path-scoped diff inspection, focused tests, and all hashes to match this plan. Leave the protected stash untouched.

## 23. Recommended commit/release sequence

No commit, push, package, or tag was created in this task. Before deployment authorization:

1. In the isolated worktree, commit the two BVM prerequisite files as one narrowly scoped commit.
2. Commit the 14-file Backstage Outreach `1.0.0` tree as a second commit that explicitly depends on the first.
3. Commit relevant tests and durable recovery/acceptance/deployment evidence separately so non-runtime evidence is reviewable but cannot enter the production payload accidentally.
4. Re-run focused Outreach, nonce/security, official-five `18/18`, additional `49/49`, PHP lint, JavaScript syntax, and `git diff --check` from the isolated commits using PHP `8.3`.
5. After explicit packaging authorization, create a two-file BVM prerequisite artifact plus a standalone `backstage-outreach-1.0.0` artifact. Do not create a whole-BVM replacement ZIP.
6. Record artifact SHA-256, file count, per-file manifest, extracted tree hash, source commit IDs, build command, and build environment. Verify extracted bytes equal the staging-accepted hashes.
7. Obtain explicit approval before push, tag, artifact transfer, or production write.

## 24. Exact production release files

Only these `16` runtime files belong in the deployment release:

- `backstage-venue-manager/includes/admin-ui/shell.php`
- `backstage-venue-manager/includes/modules/admissions/pass-claims.php`
- the exact 14 files listed under `backstage-outreach/` in section 12

Tests, docs, scripts, compatibility candidates, other add-ons, the legacy `vms` tree, and all other BVM files are excluded from the production payload.

## 25. Unresolved risks and authorization blockers

- The accepted runtime still exists only in a large dirty worktree; isolated commits and immutable release artifacts do not yet exist. This blocks production deployment authorization.
- Production is live and its baseline may change. Recompute every file, schema, queue, count, orphan, and data hash immediately before future writes.
- The `1.0.0` marker will invoke migration code even though the physical schema is already current. Today all backfill candidate counts are zero; any future nonzero count requires migration review.
- Production has functioning unrelated mail and cron infrastructure. It must remain enabled, making strict operator avoidance of Outreach send/queue actions essential.
- Several production add-on versions differ from the locally tested additional-suite source profile. They are outside this payload; retain the post-BVM and post-Outreach read-only smoke and stop on any new notice/fatal.

These are preparation/operational risks, not evidence of a schema incompatibility.

## 26. Production changes made during this task

`NONE`.

## 27. Repository state

The repository remains on `work/unreleased-2026-06-18` at `ec9934131a951faae2ac4250915cb8bb2683cdfe`, equal to origin at `0/0`, with an empty staging area and the protected stash present. The final `git status --short` filename fingerprint is `618bbe1952d2c0e072a15fdbf24046746b21f78392801828dd667777c834f520`; the expected dirty candidate remains isolated from the staging area. This plan and the remediation-ledger entry are the only task-local repository edits. No runtime file was changed during this task. Final preflight completed with only the expected dirty-worktree warning; `git diff --check`, staged-diff, candidate-hash, and production read-only drift checks passed.

## 28. Final recommendation

`PRODUCTION PREPARATION REQUIRED`

The source-control isolation, immutable commits, and release artifacts must be completed and explicitly approved before production deployment authorization.
