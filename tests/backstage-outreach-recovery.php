<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class WP_Error
{
	private string $code;
	private string $message;
	public function __construct(string $code = '', string $message = '') { $this->code = $code; $this->message = $message; }
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

function is_wp_error($value): bool { return $value instanceof WP_Error; }
function __($text, $domain = ''): string { return (string) $text; }
function sanitize_key($value): string { return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_email($value): string { return filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) ? strtolower(trim((string) $value)) : ''; }
function sanitize_file_name($value): string { return basename((string) $value); }
function is_email($value) { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ?: false; }
function absint($value): int { return abs((int) $value); }
function apply_filters($hook, $value) { return $value; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool { return true; }
function wp_unslash($value) { return $value; }
function esc_html__($text, $domain = ''): string { return (string) $text; }
$GLOBALS['outreach_test_capability'] = true;
$GLOBALS['outreach_test_nonce'] = true;
function current_user_can($capability): bool { return !empty($GLOBALS['outreach_test_capability']); }
function wp_die($message = ''): void { throw new RuntimeException('wp_die:' . (string) $message); }
function check_admin_referer($action): bool
{
	if (empty($GLOBALS['outreach_test_nonce'])) {
		throw new RuntimeException('nonce_denied:' . (string) $action);
	}
	return true;
}

function vms_admission_normalize_email(string $email): string { return sanitize_email($email); }
function vms_admission_normalize_phone(string $phone): string { return (string) preg_replace('/\D+/', '', $phone); }
function vms_pass_claims_parse_local_datetime(string $raw): string { return $raw === 'bad-date' ? '' : trim($raw); }

$GLOBALS['outreach_test_campaign'] = array('id' => 7, 'related_batch_id' => 9);
$GLOBALS['outreach_test_batch'] = array('id' => 9);
$GLOBALS['outreach_test_duplicate_lookup'] = array('email' => array(), 'phone' => array());
$GLOBALS['outreach_test_recipient'] = null;
$GLOBALS['outreach_test_token'] = array('id' => 101, 'batch_id' => 9, 'status' => 'unclaimed');
$GLOBALS['outreach_test_mail_calls'] = 0;

function vms_pass_outreach_get_campaign_by_id(int $campaign_id): ?array { return $campaign_id === 7 ? $GLOBALS['outreach_test_campaign'] : null; }
function vms_pass_outreach_campaign_batch(array $campaign): ?array { return (int) ($campaign['id'] ?? 0) === 7 ? $GLOBALS['outreach_test_batch'] : null; }
function vms_pass_outreach_campaign_duplicate_lookup(int $campaign_id): array { return $GLOBALS['outreach_test_duplicate_lookup']; }
function vms_pass_outreach_get_recipient_by_id(int $recipient_id): ?array { return is_array($GLOBALS['outreach_test_recipient']) && (int) ($GLOBALS['outreach_test_recipient']['id'] ?? 0) === $recipient_id ? $GLOBALS['outreach_test_recipient'] : null; }
function vms_pass_outreach_find_available_pass_token_row(int $batch_id, int $exclude_recipient_id = 0, int $preferred_token_id = 0): ?array { return is_array($GLOBALS['outreach_test_token']) ? $GLOBALS['outreach_test_token'] : null; }
function vms_pass_outreach_generate_unique_invite_token(): string { return 'fixed-invite-token'; }
function vms_pass_outreach_validate_recipient_delivery(array $recipient, ?array $campaign = null, ?array $claim_guardrail = null): array { return array('ok' => true); }
function wp_mail($to, $subject, $message, $headers = ''): bool { $GLOBALS['outreach_test_mail_calls']++; return true; }

require_once dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$errorCode = static function ($value): string { return is_wp_error($value) ? $value->get_error_code() : ''; };

$methods = vms_pass_outreach_recipient_delivery_method_options();
$assert(array_keys($methods) === array('email', 'manual_social', 'text_phone', 'draft'), 'The four recovered recipient delivery methods must remain available.');
$assert($errorCode(vms_pass_outreach_sanitize_recipient_identity_fields(array(), array('require_email' => true))) === 'recipient_email_required', 'Email delivery must require an email address.');
$assert($errorCode(vms_pass_outreach_sanitize_recipient_identity_fields(array('email' => 'not-an-email'), array('require_email' => true))) === 'invalid_recipient_email', 'Email delivery must reject malformed addresses.');

$manualIdentity = vms_pass_outreach_sanitize_recipient_identity_fields(
	array('full_name' => 'Taylor Agent', 'company' => 'Range Realty'),
	array('require_email' => false, 'require_name_when_no_email' => true, 'require_context_when_no_email' => true)
);
$assert(is_array($manualIdentity) && $manualIdentity['full_name'] === 'Taylor Agent' && $manualIdentity['email'] === '', 'Manual/social recipients must work without email when identifying context is present.');
$assert($errorCode(vms_pass_outreach_sanitize_recipient_identity_fields(array('full_name' => 'Taylor Agent'), array('require_email' => false, 'require_phone' => true))) === 'recipient_phone_required', 'Text/phone delivery must require a usable phone number.');
$textIdentity = vms_pass_outreach_sanitize_recipient_identity_fields(array('full_name' => 'Taylor Agent', 'phone' => '(555) 555-0101'), array('require_email' => false, 'require_phone' => true));
$assert(is_array($textIdentity) && $textIdentity['phone_norm'] === '5555550101', 'Text/phone delivery must normalize a usable phone number.');

$base = array('first_name' => 'Taylor', 'last_name' => 'Agent', 'email' => 'taylor@example.invalid');
$emailPayload = vms_pass_outreach_sanitize_recipient_payload($base + array('delivery_method' => 'email'), 7);
$assert(is_array($emailPayload) && $emailPayload['pass_token_id'] === 101 && $emailPayload['send_method'] === 'email', 'Email recipients must reserve a current BVM pass token.');
$manualPayload = vms_pass_outreach_sanitize_recipient_payload(array('full_name' => 'Taylor Agent', 'company' => 'Range Realty', 'delivery_method' => 'manual_social'), 7);
$assert(is_array($manualPayload) && $manualPayload['email'] === null && $manualPayload['pass_token_id'] === 101 && $manualPayload['send_method'] === 'manual_social', 'Manual/social recipients must retain a reserved token without joining the email queue.');
$textPayload = vms_pass_outreach_sanitize_recipient_payload(array('full_name' => 'Taylor Agent', 'phone' => '555-555-0101', 'delivery_method' => 'text_phone'), 7);
$assert(is_array($textPayload) && $textPayload['send_method'] === 'text_phone', 'Text/phone recipient payloads must retain their delivery method.');
$draftPayload = vms_pass_outreach_sanitize_recipient_payload(array('full_name' => 'Taylor Agent', 'notes' => 'Choose delivery later', 'delivery_method' => 'draft'), 7);
$assert(is_array($draftPayload) && $draftPayload['send_method'] === 'draft' && $draftPayload['send_status'] === 'not_sent', 'Draft recipients must remain out of the delivery queue.');

$assert(empty(vms_pass_outreach_recipient_email_delivery_validation(array('send_method' => 'manual_social', 'email' => 'taylor@example.invalid'))['ok']), 'Manual/social recipients must not be eligible for the email queue.');
$assert(!empty(vms_pass_outreach_recipient_email_delivery_validation(array('send_method' => 'email', 'email' => 'taylor@example.invalid'))['ok']), 'Valid email recipients must be eligible for email delivery checks.');
$manualSend = vms_pass_outreach_attempt_send_invite_email(array('id' => 22, 'campaign_id' => 7, 'send_method' => 'manual_social', 'send_status' => 'not_sent'), array('id' => 7));
$assert($manualSend['status'] === 'skipped' && $GLOBALS['outreach_test_mail_calls'] === 0, 'Attempting the send path for a non-email recipient must skip without invoking wp_mail.');

$GLOBALS['outreach_test_token'] = null;
$assert($errorCode(vms_pass_outreach_sanitize_recipient_payload($base + array('delivery_method' => 'email'), 7)) === 'no_available_campaign_token', 'Token exhaustion must be explicit and non-destructive.');
$GLOBALS['outreach_test_token'] = array('id' => 101, 'batch_id' => 9, 'status' => 'unclaimed');
$GLOBALS['outreach_test_duplicate_lookup']['email']['taylor@example.invalid'] = 22;
$assert($errorCode(vms_pass_outreach_sanitize_recipient_payload($base + array('delivery_method' => 'email'), 7)) === 'duplicate_recipient_email', 'Duplicate recipient email reservations must be rejected.');
$GLOBALS['outreach_test_duplicate_lookup'] = array('email' => array(), 'phone' => array());

$GLOBALS['outreach_test_recipient'] = array('id' => 22, 'campaign_id' => 7, 'pass_token_id' => 101, 'invite_token' => 'preserved-invite', 'status' => 'ready', 'send_status' => 'not_sent');
$editPayload = vms_pass_outreach_sanitize_recipient_payload($base + array('delivery_method' => 'email'), 7, 22);
$assert(is_array($editPayload) && $editPayload['pass_token_id'] === 101 && $editPayload['invite_token'] === 'preserved-invite', 'Editing a recipient must preserve its IDs and reserved invite token.');

$headers = array('Business', 'Contact Name', 'Email Address', 'Mobile', 'Unrelated Column');
$mapping = vms_pass_outreach_suggested_csv_mapping($headers);
$assert($mapping === array(0 => 'company', 1 => 'name', 2 => 'email', 3 => 'phone', 4 => ''), 'CSV headers must be detected without requiring exact VMS names.');
$assert(array_key_exists('', vms_pass_outreach_import_mapping_options()), 'CSV mapping must retain the Do not import option.');
$assert($errorCode(vms_pass_outreach_validate_selected_csv_mapping(array('email', 'email'), array('A', 'B'))) === 'recipient_import_duplicate_mapping', 'CSV mapping must reject duplicate field assignments.');
$assert($errorCode(vms_pass_outreach_validate_selected_csv_mapping(array('name', ''), array('A', 'B'))) === 'recipient_import_email_mapping_required', 'CSV mapping must require an email column before commit.');

$prepared = vms_pass_outreach_prepare_import_rows(
	array(
		array('row_number' => 2, 'values' => array('Alex Guest', 'alex@example.invalid', 'Range Realty')),
		array('row_number' => 3, 'values' => array('Duplicate Guest', 'alex@example.invalid', 'Range Realty')),
	),
	array('name' => 0, 'email' => 1, 'company' => 2)
);
$assert(count($prepared['prepared_rows']) === 1 && $prepared['duplicate_count'] === 1, 'CSV preview must preserve full names and detect duplicates before commit.');
$assert($prepared['prepared_rows'][0]['full_name'] === 'Alex Guest' && $prepared['prepared_rows'][0]['first_name'] === 'Alex', 'CSV preview must preserve full_name while deriving a safe greeting name.');

$rendered = vms_pass_outreach_render_template_text('Hi {first_name} from {company}. {unknown}', array('first_name' => 'Alex', 'company' => 'Range Realty'));
$assert($rendered === 'Hi Alex from Range Realty.', 'Template rendering must replace supported merge tags and remove unknown tags.');
$assert(vms_pass_outreach_recipient_first_name(array('company' => 'Range Realty'), '') === '', 'Company-only records must not invent a company word as a first-name greeting.');

$root = dirname(__DIR__);
$recipientSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php');
$campaignSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$dbSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/db.php');
$compatSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/compat-bvm.php');
$integrationSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');
$mainSource = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$coreSource = (string) file_get_contents($root . '/includes/modules/admissions/pass-claims.php');

$assert(strpos($mainSource, "version_compare((string) BVMGR_VERSION, '1.2.0', '<')") !== false && strpos($mainSource, "defined('VMS_PLUGIN_FILE')") !== false, 'Bootstrap must require BVM 1.2+ and fail closed when legacy VMS is active.');
$assert(strpos($dbSource, "'vms_outreach_db_version'") !== false && strpos($dbSource, "'1.1.0'") !== false, 'The additive companion schema must version the preserved Outreach contract independently.');
foreach (array('vms_pass_outreach_campaigns', 'vms_pass_outreach_recipients', 'vms_outreach_contacts', 'vms_outreach_suppressions') as $tableSuffix) {
	$assert(strpos($dbSource . $compatSource . $campaignSource . $recipientSource, $tableSuffix) !== false, 'Recovered schema must retain table ' . $tableSuffix . '.');
}
foreach (array('campaign_purpose', 'purpose_config_json', 'contact_id', 'send_status', 'last_contacted_at', 'outreach_campaign_id', 'outreach_recipient_id') as $column) {
	$assert(strpos($dbSource, $column) !== false, 'The additive migration must retain column ' . $column . '.');
}
$assert(strpos($dbSource, 'DROP TABLE') === false && strpos($dbSource, 'TRUNCATE ') === false && strpos($dbSource, 'RENAME TABLE') === false, 'The schema migration must not contain destructive operations.');
$assert(strpos($recipientSource, 'Contact filters changed after the last preview.') !== false && strpos($recipientSource, 'preview expired or is missing') !== false, 'Audience commit must retain stale-preview protection.');
$assert(strpos($recipientSource, 'Copy Invite Message') !== false && strpos($recipientSource, 'send-prep') !== false, 'Manual delivery copy and export preparation must remain present.');
$assert(substr_count($recipientSource, 'wp_mail(') === 1, 'Outreach must retain one explicit, auditable email-send boundary.');
$emailValidationPosition = strpos($recipientSource, 'vms_pass_outreach_recipient_email_delivery_validation($recipient)', strpos($recipientSource, 'function vms_pass_outreach_attempt_send_invite_email'));
$mailPosition = strpos($recipientSource, 'wp_mail(', strpos($recipientSource, 'function vms_pass_outreach_attempt_send_invite_email'));
$assert($emailValidationPosition !== false && $mailPosition !== false && $emailValidationPosition < $mailPosition, 'Email delivery validation must run before the only mail boundary.');

preg_match_all("/add_action\\('admin_post_[^']+'/", $recipientSource . $campaignSource . (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/admin-ui.php'), $handlerMatches);
$handlerCount = count($handlerMatches[0]);
$capabilityCount = substr_count($recipientSource . $campaignSource . (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/admin-ui.php'), 'current_user_can(');
$nonceCount = substr_count($recipientSource . $campaignSource . (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/admin-ui.php'), 'check_admin_referer(') + substr_count($recipientSource, 'wp_verify_nonce(');
$assert($handlerCount === 23 && $capabilityCount >= $handlerCount && $nonceCount >= $handlerCount, 'Every recovered admin-post mutation/export handler must retain capability and nonce enforcement.');
$assert(substr_count($recipientSource, "!is_array(\$_REQUEST['_wpnonce'])") === 10, 'All request-routed recipient nonces must reject array-shaped input.');

$GLOBALS['outreach_test_capability'] = false;
try {
	vms_pass_outreach_handle_recipient_save();
	$capabilityDenied = false;
} catch (RuntimeException $error) {
	$capabilityDenied = str_starts_with($error->getMessage(), 'wp_die:Access denied.');
}
$assert($capabilityDenied, 'A recovered admin-post handler must deny a caller without the BVM Guest Pass capability before processing input.');

$GLOBALS['outreach_test_capability'] = true;
$GLOBALS['outreach_test_nonce'] = false;
try {
	vms_pass_outreach_handle_recipient_save();
	$nonceDenied = false;
} catch (RuntimeException $error) {
	$nonceDenied = $error->getMessage() === 'nonce_denied:vms_pass_outreach_recipient_save';
}
$assert($nonceDenied, 'A recovered admin-post handler must stop on nonce failure before mutating recipient data.');
$GLOBALS['outreach_test_nonce'] = true;

foreach (array('bvmgr_pass_claims_admin_tabs', 'bvmgr_pass_claims_claim_context', 'bvmgr_pass_claims_claim_validation_error', 'bvmgr_pass_claims_claim_insert_payload', 'bvmgr_pass_claims_claim_meta', 'bvmgr_pass_claims_claim_created') as $hook) {
	$assert(strpos($coreSource, $hook) !== false && strpos($integrationSource, $hook) !== false, 'BVM and the companion must share integration hook ' . $hook . '.');
}
$assert(substr_count($coreSource, "min(\$max_party_size, max(1, (int) bvmgr_pass_claims_apply_filters('bvmgr_pass_claims_max_party_size'") === 3, 'Add-on party-size filters must only narrow BVM core and batch limits.');
$assert(strpos($integrationSource, "'^pass/invite/([^/]+)/?$'") !== false && strpos($integrationSource, 'bvmgr_pass_claims_render_public_claim($raw_token)') !== false, 'Invite links must resolve into the current BVM claim renderer.');
$assert(strpos($coreSource, "'source' => 'pass_claim'") !== false && strpos($coreSource, 'bvmgr_admission_ensure_entry_token') !== false, 'BVM claim completion must retain scanner/check-in-compatible admission entries and tokens.');

echo 'Backstage Outreach recovery regression OK (' . $assertions . " assertions).\n";
