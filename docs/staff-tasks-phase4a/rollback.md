# Phase 4A rollback and promotion boundary

The normal installed plugin remains at Phase 3D `f5a3df7c600ea1d4c7946ae1adc4072512f05ccc`. No Phase 4A runtime file, schema, Google credential or persistent Google state was installed there. Temporary read-containment MU files were removed and all source manifests rechecked. **Do not run the Phase 3D rollback to undo this unpromoted work.**

The private Phase 4A `rollback/manifest.json` records parent/candidate SHA-256 for the seven runtime paths, alongside the original module bootstrap backup. `rollback/restore.py --check-only` verifies isolated mirror/sibling files and the backup. `rollback/restore.py` restores that original bootstrap and removes only the six matching new Google files in those two isolated trees, refusing later source drift. It never targets the historical workspace or normal installed plugin. This is an optional deliberate rollback and leaves a normal Git diff against the candidate commit; it does not reset Git or touch a stash.

The private candidate patch and focused Git commit preserve the implementation. No ZIP was built. Private SQL/read snapshots are evidence, not a restoration instruction; never restore them over later real work.

Before any later normal-local promotion, record a fresh full normal-source baseline and take backups of the actual installed seven paths. Verify accepted Phase 3D authority and stop on drift. Bind a separate normal rollback manifest to the exact installed/candidate hashes. Promote only those runtime paths, leaving the frozen release and dirty historical tree alone. Real Google acceptance requires safe test credentials and the configuration/origin setup in `configuration.md`.

After a later installation, rollback should restore only the seven source paths from that promotion's verified backup. Do not drop the Google state option or erase task/audit history. Removing the module loader stops future Google work without deleting historical calendars/events. Retain encrypted state and its key securely to allow same-account/idempotent recovery. Re-enabling synchronization on a clone requires explicit origin/credential review. If an operator intentionally discards encrypted credentials, retain Google subject, site namespace and calendar identities. Never reset task identity or invent dates for the two legacy review tasks.


## Real-account acceptance follow-up

The real Google gate has now passed with a narrowly necessary response-comparison correction, followed by controlled normal-local promotion. See [local-acceptance.md](local-acceptance.md) for current accepted status, evidence, preserved boundaries and the separate normal rollback. Earlier statements above describe the unpromoted original candidate.
