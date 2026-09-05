# WordPress.org Remediation Workflow

Use this workflow for Backstage Venue Manager WordPress.org remediation and any task that touches shared mirror/live files.

## Project Identity

- Public plugin name: `Backstage Venue Manager`
- WordPress.org slug: `backstage-venue-manager`
- WordPress.org text domain: `backstage-venue-manager`
- Mirror repository: `packages/vms-github-reconcile`
- Live local plugin tree: sibling `vms/`
- Internal `vms` identifiers are compatibility-sensitive and may remain unless a task explicitly authorizes changing them.

## Tree Rules

- Shared runtime files must stay synchronized between the mirror and live trees.
- Preserve existing structural differences.
- The mirror intentionally contains repo-only material such as `docs/`, `tests/`, `scripts/`, `dist/`, and Git metadata.
- The live local tree may contain newer local-only outreach or admissions files that do not exist in the mirror. Do not invent mirror counterparts for them without explicit task scope.

## Standing Stop Conditions

Stop and report before editing if any of the following are true:

- `scripts/codex-preflight.sh` cannot identify the protected stash `WPORG-16D preserve unrelated sidebar+doc work` by message.
- `git status --short` or `git diff --check` shows unexpected preexisting changes.
- The mirror repository or live tree is missing or obviously mislocated.
- Existing project instructions conflict with the requested task.
- The requested work cannot be isolated cleanly from unrelated implementation changes.
- An unexpected file or diff appears after the task begins and cannot be explained by the currently authorized scope.

## Baseline For Every Task

1. Run `scripts/codex-preflight.sh`.
2. Require a clean preflight before beginning edits. If preflight reports existing dirt, stop unless the task explicitly authorizes dealing with that exact dirt first.
3. Record the repository root, branch, HEAD SHA and subject, `git status --short`, `git diff --check`, remotes, and protected-stash presence.
4. Determine whether the task touches shared mirror/live files or an intentionally tree-specific file.
5. Review the current durable evidence before editing:
   - `docs/WPORG_PREREVIEW_REMEDIATION.md`
   - `docs/WPORG_COMPLIANCE_REPORT_1.0.0.md`
   - `BUILD-NOTES-1.0.0.md`
   - `docs/WPORG_PLUGIN_CHECK_TRIAGE_1.0.0.md`
   - `docs/WPORG_PLUGIN_CHECK_HEATMAP_1.0.0.md`
   - `docs/wporg-remediation-ledger.md`
6. Use `docs/wporg-review-source.md` only when the original reviewer correspondence or a faithful sanitized transcription exists locally. Do not invent or reconstruct that source if it is absent.

## Editing Rules

- Keep changes narrowly scoped to the selected remediation batch.
- Do not add unrelated refactors, cleanup-only churn, or broad formatting passes.
- Preserve authentication, authorization, escaping, sanitization, nonce handling, REST behavior, AJAX behavior, and public behavior unless the task explicitly targets one of those surfaces.
- Mirror any shared-file runtime change into the sibling live tree.
- Preserve tree-specific files and paths unless the task explicitly authorizes changing them.

## Verification Rules

- Run focused regression coverage relevant to the selected scope.
- Run `git diff --check`.
- After authorized edits, a dirty worktree is normal only when it contains the task's expected files and nothing else.
- Unexpected files or unexpected changes remain a stop condition even after a task has started.
- Inspect your own diff before reporting completion.
- If a task relies on lint, Plugin Check, parser scans, or packaging checks, run the applicable command or explicitly document why the environment blocked it.
- Do not treat checklist labels as complete unless repository evidence supports them.

## Completion Rules

- Update `docs/wporg-remediation-ledger.md` at the end of every completed remediation task.
- Record scope, touched mirror/live paths, focused tests, verification commands and results, commit SHA when applicable, dependencies, and remaining concerns.
- Before any authorized commit, stage only the authorized tracked or newly staged files and run `git diff --cached --check`.
- No commit is allowed unless the individual task explicitly authorizes it.
- No push, packaging, ZIP creation, tagging, release, deployment, production or staging modification, WordPress.org submission, or reviewer reply is allowed without explicit authorization.
- Before any future resubmission, audit the complete current repository directly against the original reviewer correspondence, not against conversation memory.
