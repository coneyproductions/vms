# Build Notes 1.3.10

## Scope

- Adds preview-and-confirm invitation delivery for an existing eligible individual Guest Pass claim link.
- Reuses the existing signed claim URL and does not generate tokens, batches, campaigns, or admission rights.
- Adds an additive invitation-attempt audit table keyed to Guest Pass token, batch, source, and an optional exact-match canonical Party ID for future Wave 2B.2 reporting compatibility.

## Safety

- Revalidates operator capability, nonce, token/batch status, expiration, remaining batch capacity, recipient details, and Outreach suppression at send time.
- Binds the preview digest to the operator, exact message, signed link, token/batch state, and prior attempt ID.
- Serializes send intents per token and distinguishes WordPress mail handoff, rejection, and uncertain transport outcomes.
- Stores a claim-link fingerprint rather than the raw signed URL in invitation audit records.
- Does not subscribe recipients to MailPoet or alter existing claim, check-in, customer, order, or Outreach records.

## Rollback

Restore the previous plugin files. The additive `vms_pass_invitations` table may remain inert for forensic history; do not restore a stale database over concurrent transactions.
