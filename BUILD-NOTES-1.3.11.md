# Build Notes 1.3.11

## Scope

- Retains the Wave 2B.1 direct-invitation controls from 1.3.10.
- Treats personal Guest Pass invitations as promotional mail unless a later workflow proves a recipient-agreed transaction.
- Reuses Backstage Outreach's existing suppression, postal-address, signed unsubscribe, endpoint-readiness, and optional RFC 8058 transport services.
- Shows the exact final body, From identity, and Reply-To identity during operator review.

## Safety

- Preview does not call mail transport, create a Guest Pass token, subscribe a recipient, or change admission rights.
- The reviewed payload binds the exact compliance footer and sender headers to the operator, recipient, Guest Pass state, and prior attempt.
- Send time revalidates the Outreach suppression and reviewed unsubscribe token while holding the existing per-recipient Outreach lock.
- Accepted, rejected, and indeterminate outcomes retain the existing invitation audit semantics without storing raw signed URLs.

## Rollback

Restore the previous plugin files. The additive `vms_pass_invitations` table and existing Outreach unsubscribe infrastructure may remain; do not restore a stale database over concurrent transactions.
