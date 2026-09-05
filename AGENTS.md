# AGENTS.md

This repository is the version-controlled mirror for `Backstage Venue Manager`.

Before WordPress.org remediation work, shared runtime edits, packaging work, or any task that touches the sibling live local plugin tree, read:

- `docs/wporg-remediation-workflow.md`
- `docs/wporg-remediation-ledger.md`

Standing rules:

- Public plugin name: `Backstage Venue Manager`
- WordPress.org slug and text domain: `backstage-venue-manager`
- Internal `vms` identifiers may remain when changing them is unnecessary or unsafe.
- Shared files in this mirror and the sibling live local plugin tree at `../../vms` must stay synchronized.
- Preserve existing structural differences between the mirror and live trees unless a task explicitly authorizes changing them.
- Never manipulate the protected stash named `WPORG-16D preserve unrelated sidebar+doc work`.
- Start every task with `scripts/codex-preflight.sh`.
- Stop if preflight reports unexpected changes, a missing protected stash, a missing tree, or an obvious path mismatch.
- Keep changes narrowly scoped. No unrelated refactors or broad formatting churn.
- Do not accidentally change authentication, authorization, escaping, sanitization, nonce handling, REST behavior, AJAX behavior, or public behavior.
- Run relevant focused tests and `git diff --check` before completion.
- Inspect your own diff before reporting completion.
- No commit unless the individual task explicitly authorizes one.
- No push, packaging, ZIP creation, tagging, deployment, production or staging modification, WordPress.org submission, or reviewer reply without explicit authorization.
