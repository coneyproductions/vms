<?php
declare(strict_types=1);

$g17b_root = dirname(__DIR__);
$g17b_shadow = dirname($g17b_root, 2) . '/vms';
$g17b_artifact = '/tmp/wporg-g16-checkpoint-final.aOSh8U/plugin-check.strict.json';

function g17b_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g17b_same($expected, $actual, string $message): void
{
	g17b_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g17b_read(string $path): string
{
	$value = file_get_contents($path);
	g17b_assert(is_string($value) && $value !== '', 'Unable to read ' . $path);
	return $value;
}



function g17b_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g17b_assert($start !== false && $brace !== false, 'Missing function ' . $name);
	$depth = 1;
	for ($index = (int) $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, (int) $start, ($index - (int) $start) + 1);
		}
	}
	throw new RuntimeException('Unclosed function ' . $name);
}

function g17b_extract_adapter_call(string $source, string $event_code): string
{
	$event_at = strpos($source, "'" . $event_code . "'");
	g17b_assert($event_at !== false, 'Missing operational event ' . $event_code);
	$start = strrpos(substr($source, 0, (int) $event_at), 'bvmgr_record_operational_issue(');
	g17b_assert($start !== false, 'Missing adapter call for ' . $event_code);
	$open = strpos($source, '(', (int) $start);
	$depth = 0;
	for ($index = (int) $open, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '(' ? 1 : 0;
		$depth -= $source[$index] === ')' ? 1 : 0;
		if ($depth === 0) {
			$end = $index + 1;
			if (($source[$end] ?? '') === ';') {
				$end++;
			}
			return substr($source, (int) $start, $end - (int) $start);
		}
	}
	throw new RuntimeException('Unclosed adapter call for ' . $event_code);
}

function g17b_compact(string $source): string
{
	return (string) preg_replace('/\s+/', '', $source);
}



$g17b_paths = array(
	'helpers' => 'includes/helpers.php',
	'tours' => 'includes/tours/class-vms-tours-registry.php',
	'approvals' => 'includes/admin/approvals-review-queue.php',
	'menu' => 'includes/admin/menu.php',
	'vendor_list' => 'includes/admin/vendor-list-ui.php',
);
$g17b_sources = array('mirror' => array(), 'shadow' => array());
foreach ($g17b_paths as $key => $relative) {
	$g17b_sources['mirror'][$key] = g17b_read($g17b_root . '/' . $relative);
	$g17b_sources['shadow'][$key] = g17b_read($g17b_shadow . '/' . $relative);
}

// Historical projection retired; current runtime assertions remain below.
foreach (array('mirror', 'shadow') as $tree) {
	$combined = implode("\n", $g17b_sources[$tree]);
	g17b_same(0, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $combined), $tree . ' G17-B source must contain no direct server logs');
	g17b_same(0, preg_match_all('/(?<![A-Za-z0-9_])debug_backtrace\s*\(/', $combined), $tree . ' G17-B source must contain no stack traces');
	g17b_same(0, preg_match_all('/phpcs:[^\n]*(?:DevelopmentFunctions|error_log)/i', $combined), $tree . ' G17-B source must contain no logging suppression');

	$helper_call = g17b_extract_adapter_call($g17b_sources[$tree]['helpers'], 'tax_profile_meta_shape_invalid');
	g17b_same(
		g17b_compact("bvmgr_record_operational_issue('tax_profile_meta_shape_invalid',array('entity_id'=>\$id,'entity_type'=>'vendor','operation'=>'read_meta','status'=>'invalid',));"),
		g17b_compact($helper_call),
		$tree . ' tax-profile adapter contract changed'
	);
	$vendor_call = g17b_extract_adapter_call($g17b_sources[$tree]['vendor_list'], 'vendor_list_meta_shape_invalid');
	g17b_same(
		g17b_compact("bvmgr_record_operational_issue('vendor_list_meta_shape_invalid',array('vendor_id'=>\$post_id,'operation'=>'read_meta','status'=>'invalid',));"),
		g17b_compact($vendor_call),
		$tree . ' vendor-list adapter contract changed'
	);
	foreach (array($helper_call, $vendor_call) as $meta_call) {
		foreach (array('$key', '$meta_key', "'key'", "'value'") as $forbidden) {
			g17b_same(0, substr_count($meta_call, $forbidden), $tree . ' meta adapter leaked a forbidden key/value field');
		}
	}

	$approvals = $g17b_sources[$tree]['approvals'];
	$logger = g17b_extract_function($approvals, 'bvmgr_approvals_queue_log');
	g17b_same(1, substr_count($logger, 'bvmgr_record_operational_issue('), $tree . ' approvals wrapper must invoke the adapter once');
	g17b_same(1, substr_count($logger, "array('provider', 'operation', 'status')"), $tree . ' approvals wrapper allowlist changed');
	foreach (array('message', 'queue_id', 'item_id', 'actor_id', 'from_status', 'to_status', 'wp_json_encode') as $forbidden) {
		g17b_same(0, substr_count($logger, $forbidden), $tree . ' approvals wrapper retained unsafe field ' . $forbidden);
	}
	foreach (array(
		'approvals_provider_url_callback_failed',
		'approvals_provider_pending_callback_missing',
		'approvals_provider_pending_callback_failed',
		'approvals_provider_pending_callback_threw',
		'approvals_provider_summary_callback_threw',
		'approvals_provider_url_missing',
	) as $event_code) {
		g17b_same(1, substr_count($approvals, "'" . $event_code . "'"), $tree . ' approvals fixed event count changed: ' . $event_code);
	}
	g17b_same(0, substr_count($approvals, '->getMessage()'), $tree . ' approvals must not serialize Throwable messages');
	g17b_same(0, substr_count($approvals, '->get_error_message()'), $tree . ' approvals must not serialize WP_Error messages');
	g17b_same(0, substr_count(g17b_extract_function($approvals, 'bvmgr_approvals_queue_record_transition'), 'bvmgr_approvals_queue_log('), $tree . ' successful transitions must not duplicate the durable audit');

	$tours = $g17b_sources[$tree]['tours'];
	g17b_same(0, substr_count($tours, '$this->debug('), $tree . ' Tours debug calls remain');
	g17b_same(0, substr_count($tours, 'function debug('), $tree . ' Tours debug sink remains');
	g17b_same(5, substr_count(g17b_extract_function($tours, 'normalize_tour'), 'return array();'), $tree . ' Tours rejection return inventory changed');
	g17b_same(1, substr_count(g17b_extract_function($tours, 'sanitize_placement'), "return 'auto';"), $tree . ' Tours invalid-placement normalization changed');

	$menu = g17b_extract_function($g17b_sources[$tree]['menu'], 'bvmgr_admin_render_season_dates_page');
	g17b_same(1, substr_count($menu, "echo '<div class=\"wrap\">';"), $tree . ' Season Dates fallback wrapper changed');
	g17b_same(1, substr_count($menu, 'Season Dates page renderer is missing.'), $tree . ' Season Dates fallback copy changed');
}

g17b_same($g17b_sources['mirror']['tours'], $g17b_sources['shadow']['tours'], 'Tours full-file mirror/shadow parity changed');
g17b_same($g17b_sources['mirror']['vendor_list'], $g17b_sources['shadow']['vendor_list'], 'Vendor-list full-file mirror/shadow parity changed');
foreach (array(
	'helpers' => array('bvmgr_vendor_tax_profile_missing_items'),
	'approvals' => array(
		'bvmgr_approvals_queue_log',
		'bvmgr_approvals_queue_provider_url',
		'bvmgr_approvals_queue_provider_pending_count',
		'bvmgr_approvals_queue_provider_summary_items',
		'bvmgr_approvals_queue_collect_snapshot',
		'bvmgr_approvals_queue_record_transition',
	),
	'menu' => array('bvmgr_admin_render_season_dates_page'),
) as $key => $functions) {
	g17b_assert($g17b_sources['mirror'][$key] === $g17b_sources['shadow'][$key], 'Canonical source parity changed: ' . $key);
	foreach ($functions as $function) {
		g17b_same(
			g17b_extract_function($g17b_sources['mirror'][$key], $function),
			g17b_extract_function($g17b_sources['shadow'][$key], $function),
			'Owned mirror/shadow function parity changed: ' . $function
		);
	}
}

// Historical projection retired; current runtime assertions remain below.
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}

final class WP_Error
{
	public function __construct(private string $code, private string $message)
	{
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

final class WP_User
{
	/** @var array<int,string> */
	public array $roles = array();
}

$GLOBALS['g17b_meta'] = array();
$GLOBALS['g17b_options'] = array('vms_settings' => array('tax_w9_provider' => 'upload'));
$GLOBALS['g17b_transients'] = array();
$GLOBALS['g17b_events'] = array();
$GLOBALS['g17b_provider_rows'] = array();
$GLOBALS['g17b_user_id'] = 42;

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$value = strtolower((string) $value);
	$clean = preg_replace('/[^a-z0-9_-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function wp_kses_post($value): string
{
	return is_scalar($value) ? (string) $value : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function __($text, string $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function esc_url_raw($value): string
{
	return is_scalar($value) ? trim((string) $value) : '';
}

function get_post_type(int $post_id): string
{
	unset($post_id);
	return 'vms_vendor';
}

function bvmgr_meta_key(string $scope, string $field): string
{
	unset($scope);
	return '_vms_' . $field;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	return $GLOBALS['g17b_meta'][$post_id][$key] ?? ($single ? '' : array());
}

function get_option(string $key, $default = false)
{
	return $GLOBALS['g17b_options'][$key] ?? $default;
}

function update_option(string $key, $value, bool $autoload = true): bool
{
	unset($autoload);
	$GLOBALS['g17b_options'][$key] = $value;
	return true;
}

function get_current_user_id(): int
{
	return (int) $GLOBALS['g17b_user_id'];
}

function current_time(string $type)
{
	return $type === 'timestamp' ? 1786222800 : '2026-08-08 15:00:00';
}

function get_transient(string $key)
{
	return $GLOBALS['g17b_transients'][$key] ?? false;
}

function set_transient(string $key, $value, int $expiration): bool
{
	unset($expiration);
	$GLOBALS['g17b_transients'][$key] = $value;
	return true;
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$GLOBALS['g17b_events'][] = array(
		'event_code' => $event_code,
		'context' => $context,
		'error' => $error,
	);
	return false;
}

function bvmgr_approvals_queue_get_providers(): array
{
	return $GLOBALS['g17b_provider_rows'];
}

function bvmgr_approvals_queue_user_can_provider(array $provider): bool
{
	unset($provider);
	return true;
}

function user_can(WP_User $user, string $capability): bool
{
	unset($user, $capability);
	return true;
}

eval(g17b_extract_function($g17b_sources['mirror']['helpers'], 'bvmgr_vendor_tax_profile_missing_items'));
eval(g17b_extract_function($g17b_sources['mirror']['vendor_list'], 'bvmgr_admin_vendor_list_get_meta_scalar'));
foreach (array(
	'bvmgr_approvals_queue_notice_transient_key',
	'bvmgr_approvals_queue_log',
	'bvmgr_approvals_queue_add_admin_notice',
	'bvmgr_approvals_queue_provider_url',
	'bvmgr_approvals_queue_provider_pending_count',
	'bvmgr_approvals_queue_provider_summary_items',
	'bvmgr_approvals_queue_collect_snapshot',
	'bvmgr_approvals_queue_audit_option_key',
	'bvmgr_approvals_queue_record_transition',
) as $function) {
	eval(g17b_extract_function($g17b_sources['mirror']['approvals'], $function));
}
eval(g17b_extract_function($g17b_sources['mirror']['menu'], 'bvmgr_admin_render_season_dates_page'));
require $g17b_root . '/includes/tours/class-vms-tours-registry.php';

$GLOBALS['g17b_meta'][77] = array(
	'_vms_tax_profile_completed_at' => 0,
	'_vms_payee_legal_name' => array('secret-meta-key' => 'secret-meta-value'),
	'_vms_entity_type' => 'llc',
	'_vms_addr1' => '100 Main St',
	'_vms_city' => 'Nashville',
	'_vms_state' => 'TN',
	'_vms_zip' => '37201',
	'_vms_w9_upload_id' => 55,
	'_vms_w9_received_date' => '',
	'_vms_w9_attested_at' => 0,
);
$GLOBALS['g17b_events'] = array();
$g17b_missing = bvmgr_vendor_tax_profile_missing_items(77);
g17b_same(array('Legal/Payee Name'), $g17b_missing, 'Tax-profile invalid meta must retain blank-value fallback');
g17b_same(
	array(
		'event_code' => 'tax_profile_meta_shape_invalid',
		'context' => array(
			'entity_id' => 77,
			'entity_type' => 'vendor',
			'operation' => 'read_meta',
			'status' => 'invalid',
		),
		'error' => null,
	),
	$GLOBALS['g17b_events'][0] ?? null,
	'Tax-profile runtime event contract changed'
);
g17b_assert(strpos(serialize($GLOBALS['g17b_events']), 'secret-meta') === false, 'Tax-profile runtime event leaked a meta key/value sentinel');

$GLOBALS['g17b_meta'][88]['secret-vendor-meta-key'] = (object) array('secret-vendor-meta-value' => true);
$GLOBALS['g17b_events'] = array();
g17b_same('', bvmgr_admin_vendor_list_get_meta_scalar(88, 'secret-vendor-meta-key'), 'Vendor-list invalid meta must retain blank-value fallback');
g17b_same(
	array(
		'event_code' => 'vendor_list_meta_shape_invalid',
		'context' => array(
			'vendor_id' => 88,
			'operation' => 'read_meta',
			'status' => 'invalid',
		),
		'error' => null,
	),
	$GLOBALS['g17b_events'][0] ?? null,
	'Vendor-list runtime event contract changed'
);
g17b_assert(strpos(serialize($GLOBALS['g17b_events']), 'secret-vendor-meta') === false, 'Vendor-list runtime event leaked a meta key/value sentinel');

$GLOBALS['g17b_events'] = array();
g17b_same(0, bvmgr_approvals_queue_provider_pending_count(array('id' => 'Provider Unsafe!', 'pending_count_callback' => null)), 'Missing approvals callback result changed');
g17b_same(
	array(
		'event_code' => 'approvals_provider_pending_callback_missing',
		'context' => array('provider' => 'providerunsafe', 'operation' => 'count_pending', 'status' => 'missing'),
		'error' => null,
	),
	$GLOBALS['g17b_events'][0] ?? null,
	'Missing approvals callback event changed'
);

$g17b_wp_error = new WP_Error('provider_failed', 'secret WP_Error message');
$GLOBALS['g17b_events'] = array();
g17b_same(
	0,
	bvmgr_approvals_queue_provider_pending_count(
		array(
			'id' => 'provider_one',
			'pending_count_callback' => static fn() => $g17b_wp_error,
		)
	),
	'WP_Error approvals callback result changed'
);
g17b_assert(($GLOBALS['g17b_events'][0]['error'] ?? null) === $g17b_wp_error, 'WP_Error must pass only as the adapter error identity');
g17b_same('approvals_provider_pending_callback_failed', $GLOBALS['g17b_events'][0]['event_code'] ?? '', 'WP_Error approvals event changed');
g17b_same('warning', $GLOBALS['g17b_transients']['vms_approvals_notice_42'][0]['type'] ?? '', 'WP_Error caller-visible warning changed');

$GLOBALS['g17b_events'] = array();
g17b_same(
	0,
	bvmgr_approvals_queue_provider_pending_count(
		array(
			'id' => 'provider_two',
			'pending_count_callback' => static function (): int {
				throw new RuntimeException('secret pending exception');
			},
		)
	),
	'Throwable approvals callback result changed'
);
g17b_assert(($GLOBALS['g17b_events'][0]['error'] ?? null) instanceof RuntimeException, 'Pending Throwable must pass only as the adapter error identity');
g17b_same('approvals_provider_pending_callback_threw', $GLOBALS['g17b_events'][0]['event_code'] ?? '', 'Throwable approvals event changed');
g17b_same(2, count($GLOBALS['g17b_transients']['vms_approvals_notice_42'] ?? array()), 'Throwable caller-visible warning changed');

$GLOBALS['g17b_events'] = array();
g17b_same(
	'https://example.test/fallback',
	bvmgr_approvals_queue_provider_url(
		array(
			'id' => 'provider_three',
			'screen_url' => 'https://example.test/fallback',
			'screen_url_callback' => static function (): string {
				throw new RuntimeException('secret URL exception');
			},
		)
	),
	'Throwable URL callback fallback changed'
);
g17b_same('approvals_provider_url_callback_failed', $GLOBALS['g17b_events'][0]['event_code'] ?? '', 'Throwable URL event changed');
g17b_assert(($GLOBALS['g17b_events'][0]['error'] ?? null) instanceof RuntimeException, 'URL Throwable must pass only as error identity');

$GLOBALS['g17b_events'] = array();
g17b_same(
	array(),
	bvmgr_approvals_queue_provider_summary_items(
		array(
			'id' => 'provider_four',
			'summary_callback' => static function (): array {
				throw new RuntimeException('secret summary exception');
			},
		)
	),
	'Throwable summary callback fallback changed'
);
g17b_same('approvals_provider_summary_callback_threw', $GLOBALS['g17b_events'][0]['event_code'] ?? '', 'Throwable summary event changed');
g17b_assert(($GLOBALS['g17b_events'][0]['error'] ?? null) instanceof RuntimeException, 'Summary Throwable must pass only as error identity');

$GLOBALS['g17b_provider_rows'] = array(
	array(
		'id' => 'provider_empty_url',
		'label' => 'Provider',
		'pending_count_callback' => static fn(): int => 0,
		'screen_url' => '',
	),
);
$GLOBALS['g17b_events'] = array();
$g17b_snapshot = bvmgr_approvals_queue_collect_snapshot(false);
g17b_same('', $g17b_snapshot['providers'][0]['screen_url'] ?? null, 'Empty approvals URL result changed');
g17b_same('approvals_provider_url_missing', $GLOBALS['g17b_events'][0]['event_code'] ?? '', 'Empty approvals URL event changed');
g17b_same(array('provider' => 'provider_empty_url', 'operation' => 'resolve_url', 'status' => 'missing'), $GLOBALS['g17b_events'][0]['context'] ?? null, 'Empty approvals URL context changed');

$GLOBALS['g17b_options']['vms_approvals_audit_log'] = array();
$GLOBALS['g17b_events'] = array();
bvmgr_approvals_queue_record_transition('Vendor Queue!', 901, 'pending', 'approved', array('note' => 'Approved safely'));
g17b_same(0, count($GLOBALS['g17b_events']), 'Successful transition must not emit a duplicate operational event');
g17b_same(
	array(
		'ts' => '2026-08-08 15:00:00',
		'queue_id' => 'vendorqueue',
		'item_id' => 901,
		'from_status' => 'pending',
		'to_status' => 'approved',
		'actor_id' => 42,
		'note' => 'Approved safely',
	),
	$GLOBALS['g17b_options']['vms_approvals_audit_log'][0] ?? null,
	'Successful transition durable audit changed'
);

ob_start();
bvmgr_admin_render_season_dates_page();
$g17b_menu_html = (string) ob_get_clean();
g17b_same(
	'<div class="wrap"><h1>Season Dates</h1><p>Season Dates page renderer is missing. Expected function <code>bvmgr_render_season_dates_page()</code>.</p></div>',
	$g17b_menu_html,
	'Season Dates fallback HTML changed'
);

$g17b_valid_tour = array(
	'id' => 'Tour One',
	'title' => 'Tour One',
	'screen' => 'admin:test',
	'version' => '1',
	'level' => 'standard',
	'audience' => array(),
	'steps' => array(
		array(
			'id' => 'Step One',
			'selector' => '#target',
			'title' => 'Step title',
			'body' => '<strong>Step body</strong>',
			'placement' => 'diagonal',
		),
	),
);
$g17b_registry = new BVMGR_Tours_Registry();
g17b_assert($g17b_registry->register($g17b_valid_tour), 'Valid Tours registration changed');
$g17b_tour = $g17b_registry->get('tourone');
g17b_same('auto', $g17b_tour['steps'][0]['placement'] ?? null, 'Invalid Tours placement must still normalize to auto');
foreach (array(
	'missing_key' => array_diff_key($g17b_valid_tour, array('title' => true)),
	'invalid_id' => array_replace($g17b_valid_tour, array('id' => '!!!')),
	'invalid_screen' => array_replace($g17b_valid_tour, array('screen' => '!!!')),
	'empty_version' => array_replace($g17b_valid_tour, array('version' => ' ')),
	'no_valid_steps' => array_replace($g17b_valid_tour, array('steps' => array(array()))),
) as $case => $tour_definition) {
	$registry = new BVMGR_Tours_Registry();
	g17b_assert(!$registry->register($tour_definition), 'Tours rejection decision changed: ' . $case);
}

fwrite(STDOUT, "G17 operational logging admin/meta checks passed.\n");
