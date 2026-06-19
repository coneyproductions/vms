# VMS Test Plan — 0.2.24.607 — Email Follow-Ups Smart Greeting Tokens

🚨 **Codex/staging test recommended before enabling customer sends.** This pass changes email template rendering and should be previewed with real event buyers before use.

## Version checks

1. Confirm plugin header reports `0.2.24.607`.
2. Confirm `includes/core/registry/constants.php` defines `VMS_VERSION` as `0.2.24.607`.
3. Confirm `vms-build.txt` contains `0.2.24.607`.

## Token help / template checks

1. Visit `VMS → Marketing & Social → Email Follow-Ups → Templates`.
2. Confirm token help includes:
   - `{customer_name}`
   - `{customer_first_name}`
   - `{customer_greeting}`
3. Confirm default/saved template bodies contain `{customer_greeting}` at the top unless the template already had a customer/name token.

## Preview checks

1. Visit `Email Follow-Ups → Preview & Test`.
2. Choose a recent event with at least one ticket buyer whose billing/customer name is known.
3. Preview each template.
4. Confirm the rendered email begins with `Hi First,` using only the first name.
5. Use an event/recipient fixture where no usable name is available, or temporarily remove the recipient name in a local fixture.
6. Confirm the rendered email begins with `Hi there,` and does not produce `Dear ,`, `Hi ,`, or any extra blank-name punctuation.

## Test email checks

1. Send a test email to the admin/test address.
2. Confirm the subject/body render successfully.
3. Confirm `{customer_greeting}` in test mode uses the test recipient value, currently expected as `Hi Test,`.
4. Confirm `{customer_first_name}` can be inserted manually and renders only the first name when known.

## Regression checks

1. Confirm `{feedback_url}` still appears in the Post-Event Thank You template and renders for an event with the Event Feedback module available.
2. Confirm recent/past events still appear in the Preview & Test selector.
3. Confirm automatic scheduled sends remain disabled unless intentionally enabled in settings.
4. Confirm manual send still requires the confirmation checkbox.

## Rollback

If previews show broken token rendering or saved templates are unexpectedly altered, roll back to `0.2.24.606`, document the template key, recipient/order fixture, and rendered output, then repair the smallest durable cause.
