<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

final class BvmOutreachIntegrationStop extends RuntimeException
{
	/** @var array<string,mixed> */
	public $payload;

	/** @param array<string,mixed> $payload */
	public function __construct(string $surface, array $payload)
	{
		parent::__construct($surface);
		$this->payload = $payload;
	}
}

if (!class_exists('WP_Error')) {
	final class WP_Error
	{
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_code(): string
		{
			return $this->code;
		}

		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}

final class BvmOutreachIntegrationWpdb
{
	/** @var int */
	public $insert_id = 0;
	/** @var array<int,array<string,mixed>> */
	public $inserts = array();
	/** @var array<int,string> */
	public $queries = array();
	/** @var array<int,array<string,mixed>> */
	public $updates = array();
	/** @var array<int,array<string,mixed>> */
	public $deletes = array();

	public function prepare(string $query, ...$args): string
	{
		return $query . ' ' . json_encode($args);
	}

	public function query(string $query): int
	{
		$this->queries[] = $query;
		return 1;
	}

	public function get_var(string $query): int
	{
		$this->queries[] = $query;
		return 0;
	}

	/** @param array<string,mixed> $data @param string[] $formats */
	public function insert(string $table, array $data, array $formats): bool
	{
		$this->insert_id++;
		$this->inserts[] = array('table' => $table, 'data' => $data, 'formats' => $formats, 'id' => $this->insert_id);
		return true;
	}

	/** @param array<string,mixed> $data @param array<string,mixed> $where */
	public function update(string $table, array $data, array $where, array $formats = array(), array $where_formats = array()): int
	{
		$this->updates[] = compact('table', 'data', 'where', 'formats', 'where_formats');
		return 1;
	}

	/** @param array<string,mixed> $where */
	public function delete(string $table, array $where, array $where_formats = array()): int
	{
		$this->deletes[] = compact('table', 'where', 'where_formats');
		return 1;
	}
}

$GLOBALS['bvm_outreach_filters'] = array();
$GLOBALS['bvm_outreach_actions'] = array();
$GLOBALS['bvm_outreach_request_method'] = 'get';
$GLOBALS['bvm_outreach_token'] = array('id' => 7, 'batch_id' => 8, 'status' => 'unclaimed', 'token_public_key' => 'public');
$GLOBALS['bvm_outreach_batch'] = array('id' => 8, 'source_id' => 9, 'status' => 'active', 'admissions_per_link' => 4, 'value_type' => 'free', 'value_amount' => 0);
$GLOBALS['bvm_outreach_events'] = array(array('id' => 101, 'venue_id' => 5), array('id' => 102, 'venue_id' => 5));
$GLOBALS['bvm_outreach_filtered_events'] = array(array('id' => 102, 'venue_id' => 5));
$GLOBALS['bvm_outreach_context'] = array();
$GLOBALS['bvm_outreach_recipient_preflight'] = array('ok' => true);
$GLOBALS['bvm_outreach_campaign_preflight'] = array('ok' => true);
$GLOBALS['bvm_outreach_claim_evaluation'] = array('ok' => true);
$GLOBALS['bvm_outreach_party_cap'] = 2;
$GLOBALS['bvm_outreach_calls'] = array();
$GLOBALS['wpdb'] = new BvmOutreachIntegrationWpdb();

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
{
	$GLOBALS['bvm_outreach_filters'][(string) $hook_name][(int) $priority][] = array($callback, (int) $accepted_args);
	return true;
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
{
	$GLOBALS['bvm_outreach_actions'][(string) $hook_name][(int) $priority][] = array($callback, (int) $accepted_args);
	return true;
}

function apply_filters($hook_name, $value, ...$args)
{
	$callbacks = $GLOBALS['bvm_outreach_filters'][(string) $hook_name] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $at_priority) {
		foreach ($at_priority as $registration) {
			$callback_args = array_slice(array_merge(array($value), $args), 0, $registration[1]);
			$value = call_user_func_array($registration[0], $callback_args);
		}
	}
	return $value;
}

function do_action($hook_name, ...$args): void
{
	$callbacks = $GLOBALS['bvm_outreach_actions'][(string) $hook_name] ?? array();
	ksort($callbacks);
	foreach ($callbacks as $at_priority) {
		foreach ($at_priority as $registration) {
			call_user_func_array($registration[0], array_slice($args, 0, $registration[1]));
		}
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function sanitize_key($value): string
{
	return is_scalar($value) ? (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function sanitize_email($value): string
{
	return is_scalar($value) ? (string) filter_var((string) $value, FILTER_SANITIZE_EMAIL) : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_unslash($value)
{
	return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value);
}

function wp_json_encode($value): string
{
	return (string) json_encode($value);
}

function is_admin(): bool
{
	return false;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url): string
{
	return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}

function esc_attr($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url($value): string
{
	return esc_attr($value);
}

function esc_html($value): string
{
	return esc_attr($value);
}

function bvmgr_request_method(string $fallback = 'get'): string
{
	unset($fallback);
	return (string) $GLOBALS['bvm_outreach_request_method'];
}

function bvmgr_request_remote_addr(): string
{
	return '127.0.0.1';
}

function bvmgr_request_user_agent(): string
{
	return 'paired-integration-test';
}

function bvmgr_nonce_action_for_value(string $nonce, string $action): string
{
	unset($nonce);
	return $action;
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	return $nonce === 'valid' && $action === 'bvmgr_pass_claim_submit';
}

function bvmgr_pass_claims_find_token_by_raw(string $raw_token): ?array
{
	$GLOBALS['bvm_outreach_calls']['raw_token'][] = $raw_token;
	return $GLOBALS['bvm_outreach_token'];
}

function bvmgr_pass_claims_get_batch_by_id(int $batch_id): ?array
{
	$GLOBALS['bvm_outreach_calls']['batch_id'][] = $batch_id;
	return $GLOBALS['bvm_outreach_batch'];
}

function bvmgr_pass_claims_rate_limit_hit(string $ip, string $token_key): bool
{
	$GLOBALS['bvm_outreach_calls']['rate_limit'][] = array($ip, $token_key);
	return false;
}

function bvmgr_pass_claims_eligible_events_for_batch(array $batch): array
{
	$GLOBALS['bvm_outreach_calls']['core_events'][] = $batch;
	return $GLOBALS['bvm_outreach_events'];
}

function bvmgr_pass_claims_empty_events_notice(array $batch): array
{
	unset($batch);
	return array('title' => 'No Eligible Events', 'message' => 'No eligible events.');
}

function bvmgr_pass_claims_render_public_status_screen(string $headline, string $title, string $message): void
{
	throw new BvmOutreachIntegrationStop('status', compact('headline', 'title', 'message'));
}

function bvmgr_pass_claims_render_public_form(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): void
{
	throw new BvmOutreachIntegrationStop('form', compact('batch', 'eligible_events', 'posted', 'error', 'max_party_size'));
}

function bvmgr_pass_claims_render_public_success_confirmation(array $success, string $posted_email): void
{
	throw new BvmOutreachIntegrationStop('success', compact('success', 'posted_email'));
}

function bvmgr_pass_claims_render_public_claimed_card(int $entry_id): void
{
	throw new BvmOutreachIntegrationStop('claimed', compact('entry_id'));
}

function bvmgr_admission_table_pass_tokens(): string
{
	return 'pass_tokens';
}

function bvmgr_admission_table_pass_claims(): string
{
	return 'pass_claims';
}

function bvmgr_admission_table_entries(): string
{
	return 'admission_entries';
}

function bvmgr_admission_settings(): array
{
	return array('max_party_size' => 6);
}

function bvmgr_admission_now_mysql(): string
{
	return '2026-09-27 12:00:00';
}

function bvmgr_admission_normalize_name(string $value): string
{
	return strtolower(trim($value));
}

function bvmgr_admission_normalize_email(string $value): string
{
	return strtolower(trim($value));
}

function bvmgr_admission_ensure_entry_token(int $entry_id): string
{
	return 'admission-token-' . $entry_id;
}

function bvmgr_admission_scan_url(string $token): string
{
	return 'https://example.test/scan/' . rawurlencode($token);
}

function bvmgr_admission_audit_log(...$args): void
{
	$GLOBALS['bvm_outreach_calls']['audit'][] = $args;
}

function vms_outreach_admin_page_url(): string
{
	return 'https://example.test/wp-admin/admin.php?page=backstage-outreach';
}

function vms_pass_outreach_claim_context_for_token(array $token_row, array $batch): array
{
	$GLOBALS['bvm_outreach_calls']['context'][] = array($token_row, $batch);
	return $GLOBALS['bvm_outreach_context'];
}

function vms_pass_outreach_recipient_preflight(array $recipient, array $campaign, array $batch, array $token_row): array
{
	$GLOBALS['bvm_outreach_calls']['recipient_preflight'][] = compact('recipient', 'campaign', 'batch', 'token_row');
	return $GLOBALS['bvm_outreach_recipient_preflight'];
}

function vms_pass_outreach_campaign_preflight(array $campaign): array
{
	$GLOBALS['bvm_outreach_calls']['campaign_preflight'][] = $campaign;
	return $GLOBALS['bvm_outreach_campaign_preflight'];
}

function vms_pass_outreach_log_claim_denial(array $context): void
{
	$GLOBALS['bvm_outreach_calls']['denials'][] = $context;
}

function vms_pass_outreach_public_failure_message(): string
{
	return 'Outreach claim unavailable.';
}

function vms_pass_outreach_filter_events_for_campaign(array $campaign, array $events): array
{
	$GLOBALS['bvm_outreach_calls']['event_filter'][] = array($campaign, $events);
	return $GLOBALS['bvm_outreach_filtered_events'];
}

function vms_pass_outreach_effective_recipient_cap(array $batch, ?array $campaign): int
{
	$GLOBALS['bvm_outreach_calls']['party_cap'][] = array($batch, $campaign);
	return (int) $GLOBALS['bvm_outreach_party_cap'];
}

function vms_pass_outreach_evaluate_recipient_claim(array $recipient, array $campaign, array $batch, array $token_row, array $event_plan, array $claimant): array
{
	$GLOBALS['bvm_outreach_calls']['claim_evaluation'][] = compact('recipient', 'campaign', 'batch', 'token_row', 'event_plan', 'claimant');
	return $GLOBALS['bvm_outreach_claim_evaluation'];
}

function vms_pass_outreach_record_recipient_claim_success(array $recipient, array $campaign, int $claim_id, int $entry_id, int $party_size, int $token_id): void
{
	$GLOBALS['bvm_outreach_calls']['claim_success'][] = compact('recipient', 'campaign', 'claim_id', 'entry_id', 'party_size', 'token_id');
}

function vms_pass_outreach_public_error_message_for_claim_error(WP_Error $error): string
{
	$GLOBALS['bvm_outreach_calls']['public_error'][] = $error->get_error_code();
	return 'Outreach override: ' . $error->get_error_message();
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function bvm_outreach_run(array $command, string $cwd): array
{
	$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $cwd);
	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start source export command.');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array('exit_code' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr);
}

function bvm_outreach_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/** @return array<string,mixed> */
function bvm_outreach_capture_render(callable $callback, string $expected_surface): array
{
	try {
		$callback();
	} catch (BvmOutreachIntegrationStop $stop) {
		bvm_outreach_assert($stop->getMessage() === $expected_surface, 'Unexpected public render surface: ' . $stop->getMessage());
		return $stop->payload;
	}
	throw new RuntimeException('Expected public render surface was not reached: ' . $expected_surface);
}

$root = dirname(__DIR__);
require $root . '/includes/modules/admissions/pass-claims.php';
$core_filters = $GLOBALS['bvm_outreach_filters'];
$core_actions = $GLOBALS['bvm_outreach_actions'];

$outreach_commit = '27ee1bbac2ebc1a6122a886dd171605130393657';
$outreach_path = 'companion-plugins/backstage-outreach/includes/integration-bvm.php';
$export = bvm_outreach_run(array('git', '-C', $root, 'show', $outreach_commit . ':' . $outreach_path), $root);
bvm_outreach_assert($export['exit_code'] === 0, 'Unable to export the accepted Outreach integration source: ' . trim($export['stderr']));
bvm_outreach_assert(hash('sha256', $export['stdout']) === '63d4251c3e2e93b4c1e33ed5ac85b846f40097a50038a55f65608688261235ff', 'Accepted Outreach integration source hash changed.');
$outreach_fixture = tempnam(sys_get_temp_dir(), 'bvm-outreach-integration-');
bvm_outreach_assert(is_string($outreach_fixture), 'Unable to allocate the Outreach integration fixture.');
bvm_outreach_assert(file_put_contents($outreach_fixture, $export['stdout']) === strlen($export['stdout']), 'Unable to write the Outreach integration fixture.');
try {
	require $outreach_fixture;
} finally {
	unlink($outreach_fixture);
}

$required_filters = array(
	'bvmgr_pass_claims_admin_tabs',
	'bvmgr_pass_claims_admin_tab_url',
	'bvmgr_pass_claims_claim_context',
	'bvmgr_pass_claims_claim_preflight_error',
	'bvmgr_pass_claims_eligible_events',
	'bvmgr_pass_claims_default_posted',
	'bvmgr_pass_claims_max_party_size',
	'bvmgr_pass_claims_claim_validation_error',
	'bvmgr_pass_claims_claim_insert_payload',
	'bvmgr_pass_claims_claim_meta',
	'bvmgr_pass_claims_public_claim_error',
);
foreach ($required_filters as $required_filter) {
	bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_filters'][$required_filter]), 'Outreach did not register ' . $required_filter . '.');
}
bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_actions']['bvmgr_pass_claims_claim_created']), 'Outreach did not register the claim-created action.');

ob_start();
bvmgr_pass_claims_render_tab_nav('outreach');
$tab_markup = (string) ob_get_clean();
bvm_outreach_assert(strpos($tab_markup, 'backstage-outreach') !== false && strpos($tab_markup, '>Outreach</a>') !== false, 'BVM did not invoke Outreach admin tab filters.');

$GLOBALS['bvm_outreach_context'] = array(
	'campaign' => array('id' => 301, 'status' => 'active'),
	'recipient' => array('id' => 401, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '555-0100', 'email' => 'ada@example.test'),
);
$form = bvm_outreach_capture_render(static function (): void {
	bvmgr_pass_claims_render_public_claim('paired-token');
}, 'form');
bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_calls']['context']), 'BVM did not request Outreach claim context.');
bvm_outreach_assert(($form['eligible_events'][0]['id'] ?? 0) === 102 && count($form['eligible_events']) === 1, 'Outreach event filtering did not alter the BVM eligible-event set.');
bvm_outreach_assert(($form['posted']['first_name'] ?? '') === 'Ada' && ($form['posted']['email'] ?? '') === 'ada@example.test', 'Outreach recipient prefill did not reach BVM public claim state.');
bvm_outreach_assert(($form['max_party_size'] ?? 0) === 2, 'Outreach party-size cap did not constrain BVM.');

$GLOBALS['bvm_outreach_recipient_preflight'] = array('ok' => false, 'reason_code' => 'recipient_revoked', 'admin_reasons' => array('Synthetic denial'));
$status = bvm_outreach_capture_render(static function (): void {
	bvmgr_pass_claims_render_public_claim('preflight-token');
}, 'status');
bvm_outreach_assert(($status['title'] ?? '') === 'Claim Unavailable' && ($status['message'] ?? '') === 'Outreach claim unavailable.', 'Outreach campaign preflight did not deny the BVM claim.');
bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_calls']['denials']), 'Outreach preflight denial was not recorded.');
$GLOBALS['bvm_outreach_recipient_preflight'] = array('ok' => true);

$GLOBALS['bvm_outreach_claim_evaluation'] = array('ok' => false, 'reason_code' => 'campaign_eligibility_failed', 'admin_reasons' => array('Synthetic validation denial'));
$GLOBALS['bvm_outreach_request_method'] = 'post';
$_POST = array(
	'vms_pass_claim_submit' => '1',
	'_bvmgr_pass_claim_nonce' => 'valid',
	'first_name' => 'Ada',
	'last_name' => 'Lovelace',
	'phone' => '555-0100',
	'email' => 'ada@example.test',
	'event_plan_id' => '102',
	'party_size' => '1',
);
$form = bvm_outreach_capture_render(static function (): void {
	bvmgr_pass_claims_render_public_claim('validation-token');
}, 'form');
bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_calls']['claim_evaluation']), 'BVM did not invoke Outreach campaign claim validation.');
bvm_outreach_assert(($form['error'] ?? '') === 'Outreach override: Outreach claim unavailable.', 'Outreach public claim error did not override the generic BVM message.');
bvm_outreach_assert(!empty($GLOBALS['bvm_outreach_calls']['public_error']), 'BVM did not invoke the Outreach public-error filter.');

$GLOBALS['bvm_outreach_claim_evaluation'] = array('ok' => true);
$GLOBALS['wpdb'] = new BvmOutreachIntegrationWpdb();
$result = bvmgr_pass_claims_create_claim(
	$GLOBALS['bvm_outreach_token'],
	$GLOBALS['bvm_outreach_batch'],
	array('id' => 102, 'venue_id' => 5, 'title' => 'Synthetic Event'),
	array('first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '555-0100', 'email' => '', 'party_size' => 2),
	$GLOBALS['bvm_outreach_context']
);
bvm_outreach_assert(is_array($result), 'Paired BVM/Outreach claim did not complete.');
$claim_insert = $GLOBALS['wpdb']->inserts[0]['data'] ?? array();
bvm_outreach_assert(($claim_insert['outreach_campaign_id'] ?? 0) === 301 && ($claim_insert['outreach_recipient_id'] ?? 0) === 401, 'Outreach attribution did not reach the BVM claim insert payload.');
$entry_meta = json_decode((string) ($GLOBALS['wpdb']->inserts[1]['data']['claim_meta'] ?? ''), true);
bvm_outreach_assert(is_array($entry_meta) && ($entry_meta['outreach_campaign_id'] ?? 0) === 301 && ($entry_meta['outreach_recipient_id'] ?? 0) === 401, 'Outreach claim metadata extension did not reach the BVM admission entry.');
$claim_success = $GLOBALS['bvm_outreach_calls']['claim_success'][0] ?? array();
bvm_outreach_assert(($claim_success['claim_id'] ?? 0) === 1 && ($claim_success['entry_id'] ?? 0) === 2 && ($claim_success['party_size'] ?? 0) === 2, 'BVM did not invoke Outreach successful-claim completion.');

$GLOBALS['bvm_outreach_filters'] = $core_filters;
$GLOBALS['bvm_outreach_actions'] = $core_actions;
$GLOBALS['bvm_outreach_request_method'] = 'get';
$_POST = array();
$ordinary_form = bvm_outreach_capture_render(static function (): void {
	bvmgr_pass_claims_render_public_claim('ordinary-token');
}, 'form');
bvm_outreach_assert(count($ordinary_form['eligible_events']) === 2, 'Ordinary BVM eligible events changed without a companion.');
bvm_outreach_assert(($ordinary_form['posted']['first_name'] ?? null) === '' && ($ordinary_form['posted']['party_size'] ?? null) === 1, 'Ordinary BVM claim defaults changed without a companion.');
bvm_outreach_assert(($ordinary_form['max_party_size'] ?? 0) === 4, 'Ordinary BVM party-size behavior changed without a companion.');

$GLOBALS['wpdb'] = new BvmOutreachIntegrationWpdb();
$ordinary_result = bvmgr_pass_claims_create_claim(
	$GLOBALS['bvm_outreach_token'],
	$GLOBALS['bvm_outreach_batch'],
	array('id' => 101, 'venue_id' => 5, 'title' => 'Ordinary Event'),
	array('first_name' => 'Grace', 'last_name' => 'Hopper', 'phone' => '555-0101', 'email' => '', 'party_size' => 1)
);
bvm_outreach_assert(is_array($ordinary_result), 'Ordinary BVM claim failed without a companion.');
$ordinary_claim = $GLOBALS['wpdb']->inserts[0]['data'] ?? array();
bvm_outreach_assert(!array_key_exists('outreach_campaign_id', $ordinary_claim) && !array_key_exists('outreach_recipient_id', $ordinary_claim), 'Ordinary BVM claim acquired companion attribution without a companion.');
$ordinary_meta = json_decode((string) ($GLOBALS['wpdb']->inserts[1]['data']['claim_meta'] ?? ''), true);
bvm_outreach_assert($ordinary_meta === array('first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => '', 'group_size' => 1, 'group_slot' => 1), 'Ordinary BVM claim metadata changed without a companion.');

fwrite(STDOUT, "Current BVM + Backstage Outreach 1.0.0 paired integration PASS\n");
