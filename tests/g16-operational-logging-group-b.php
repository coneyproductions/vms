<?php

declare(strict_types=1);

$g16b_root = dirname(__DIR__);
$g16b_shadow_root = dirname($g16b_root, 2) . '/vms';

function g16b_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g16b_same($expected, $actual, string $message): void
{
	g16b_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g16b_replace_once(string $search, string $replacement, string $subject, string $message): string
{
	$count = 0;
	$result = str_replace($search, $replacement, $subject, $count);
	g16b_same(1, $count, $message);
	return $result;
}

function g16b_source(string $root, string $relative): string
{
	$path = $root . '/' . $relative;
	g16b_assert(is_file($path), 'Missing source file: ' . $path);
	$source = file_get_contents($path);
	g16b_assert(is_string($source) && $source !== '', 'Unreadable source file: ' . $path);
	return $source;
}

function g16b_extract_guarded_function(string $source, string $name): string
{
	$start = strpos($source, "if (!function_exists('{$name}')) {");
	g16b_assert($start !== false, 'Missing guarded function: ' . $name);
	$depth = 0;
	$opened = false;
	$length = strlen($source);
	for ($offset = (int) $start; $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
			$opened = true;
		} elseif ($source[$offset] === '}') {
			$depth--;
			if ($opened && $depth === 0) {
				return substr($source, (int) $start, $offset - (int) $start + 1);
			}
		}
	}
	throw new RuntimeException('Unclosed guarded function: ' . $name);
}

function g16b_extract_if_block(string $source, string $needle): string
{
	$start = strpos($source, $needle);
	g16b_assert($start !== false, 'Missing branch: ' . $needle);
	$brace = strpos($source, '{', (int) $start);
	g16b_assert($brace !== false, 'Missing branch opening brace: ' . $needle);
	$depth = 0;
	$length = strlen($source);
	for ($offset = (int) $brace; $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
		} elseif ($source[$offset] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, (int) $start, $offset - (int) $start + 1);
			}
		}
	}
	throw new RuntimeException('Unclosed branch: ' . $needle);
}

function g16b_artifact_contract(): void
{
	$artifact_path = '/tmp/wporg-datezero-g15.0zTh76/plugin-check.strict.json';
	g16b_assert(is_file($artifact_path), 'Authoritative date-zero/G15 artifact must be present.');
	g16b_same(
		'e0acd72b19d164c92958a99d9d1c58361fc90a8fcd1a0bf2c8d6f07b1ef9ef5a',
		hash_file('sha256', $artifact_path),
		'Authoritative artifact hash changed.'
	);
	$findings = json_decode((string) file_get_contents($artifact_path), true, 512, JSON_THROW_ON_ERROR);
	g16b_assert(is_array($findings), 'Authoritative artifact must decode to an array.');
	g16b_same(167, count($findings), 'Authoritative artifact total changed.');

	$expected_counts = array(
		'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent' => 1,
		'WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace' => 1,
		'WordPress.PHP.DevelopmentFunctions.error_log_error_log' => 41,
		'WordPress.Security.EscapeOutput.OutputNotEscaped' => 123,
		'WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet' => 1,
	);
	$owned_suffixes = array(
		'includes/admin/data-tools/actions-event-plan-import.php',
		'includes/modules/admissions/rest.php',
		'includes/taxonomies/vendor-type.php',
	);
	$expected_rows = array(
		'/privateincludes/admin/data-tools/actions-event-plan-import.php:167:4',
		'/privateincludes/admin/data-tools/actions-event-plan-import.php:262:4',
		'/privateincludes/admin/data-tools/actions-event-plan-import.php:357:4',
		'/privateincludes/modules/admissions/rest.php:413:4',
		'/privateincludes/modules/admissions/rest.php:545:4',
		'/privateincludes/modules/admissions/rest.php:623:4',
		'/privateincludes/modules/admissions/rest.php:721:4',
		'/privateincludes/taxonomies/vendor-type.php:336:6',
		'/privateincludes/taxonomies/vendor-type.php:419:7',
	);
	$counts = array();
	$owned_rows = array();
	$outside_logging = 0;
	foreach ($findings as $finding) {
		$code = (string) ($finding['code'] ?? '');
		$counts[$code] = ($counts[$code] ?? 0) + 1;
		if ($code !== 'WordPress.PHP.DevelopmentFunctions.error_log_error_log') {
			continue;
		}
		$file = (string) ($finding['file'] ?? '');
		$owned = false;
		foreach ($owned_suffixes as $suffix) {
			if (str_ends_with($file, $suffix)) {
				$owned = true;
				break;
			}
		}
		if ($owned) {
			$owned_rows[] = $file . ':' . ($finding['line'] ?? 0) . ':' . ($finding['column'] ?? 0);
		} else {
			$outside_logging++;
		}
	}
	ksort($counts);
	ksort($expected_counts);
	g16b_same($expected_counts, $counts, 'Authoritative code counts changed.');
	g16b_same($expected_rows, $owned_rows, 'Owned group-B artifact rows changed.');
	g16b_same(9, count($owned_rows), 'Group B must own exactly nine rows.');
	g16b_same(32, $outside_logging, 'Exactly 32 direct server-log rows must remain outside group B.');
	g16b_same(33, $outside_logging + ($counts['WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace'] ?? 0), 'Outside logging findings must remain 33 including debug_backtrace.');
}

function g16b_extract_adapter_call(string $source, string $event_code): string
{
	$event_at = strpos($source, "'{$event_code}'");
	g16b_assert($event_at !== false, 'Missing operational event: ' . $event_code);
	$prefix = substr($source, 0, (int) $event_at);
	$start = strrpos($prefix, 'bvmgr_record_operational_issue(');
	g16b_assert($start !== false, 'Missing adapter call for: ' . $event_code);
	$open = strpos($source, '(', (int) $start);
	$depth = 0;
	$length = strlen($source);
	for ($offset = (int) $open; $offset < $length; $offset++) {
		if ($source[$offset] === '(') {
			$depth++;
		} elseif ($source[$offset] === ')') {
			$depth--;
			if ($depth === 0) {
				$end = $offset + 1;
				if (($source[$end] ?? '') === ';') {
					$end++;
				}
				return substr($source, (int) $start, $end - (int) $start);
			}
		}
	}
	throw new RuntimeException('Unclosed adapter call: ' . $event_code);
}

function g16b_compact_php(string $source): string
{
	return (string) preg_replace('/\s+/', '', $source);
}

function g16b_source_contract(string $root, string $shadow_root): array
{
	$files = array(
		'import' => 'includes/admin/data-tools/actions-event-plan-import.php',
		'admissions' => 'includes/modules/admissions/rest.php',
		'vendor' => 'includes/taxonomies/vendor-type.php',
	);
	$sources = array('mirror' => array(), 'shadow' => array());
	foreach ($files as $key => $relative) {
		$sources['mirror'][$key] = g16b_source($root, $relative);
		$sources['shadow'][$key] = g16b_source($shadow_root, $relative);
	}

	$events = array(
		'import' => array(
			'event_plan_import_preview_failed' => "array('service'=>'event_plan_import','operation'=>'preview','stage'=>'build_preview','status'=>'failed',),\$preview",
			'event_plan_import_commit_failed' => "array('service'=>'event_plan_import','operation'=>'commit','stage'=>'run_commit','status'=>'failed',),\$result",
			'event_plan_import_revert_failed' => "array('service'=>'event_plan_import','operation'=>'revert','stage'=>'run_revert','status'=>'failed',),\$result",
		),
		'admissions' => array(
			'admission_create_failed' => "array('service'=>'admissions','entity_type'=>'admission','operation'=>'create','stage'=>'database_write','status'=>'failed','plan_id'=>\$event_plan_id,),null",
			'admission_update_failed' => "array('service'=>'admissions','entity_type'=>'admission','operation'=>'update','stage'=>'database_write','status'=>'failed','plan_id'=>absint(\$row['event_plan_id']??0),'entity_id'=>\$entry_id,),null",
			'admission_checkin_failed' => "array('service'=>'admissions','entity_type'=>'admission','operation'=>'checkin','stage'=>'database_write','status'=>'failed','plan_id'=>absint(\$row['event_plan_id']??0),'entity_id'=>\$entry_id,),null",
			'admission_uncheckin_failed' => "array('service'=>'admissions','entity_type'=>'admission','operation'=>'uncheckin','stage'=>'database_write','status'=>'failed','plan_id'=>absint(\$row['event_plan_id']??0),'entity_id'=>\$entry_id,),null",
		),
		'vendor' => array(
			'vendor_type_default_term_ensure_failed' => "array('service'=>'vendor_taxonomy','entity_type'=>'vendor_type','operation'=>'ensure_default','stage'=>'term_insert','status'=>'failed',),\$created",
			'vendor_type_duplicate_term_delete_failed' => "array('service'=>'vendor_taxonomy','entity_type'=>'vendor_type','operation'=>'delete_duplicate','stage'=>'canonicalization','status'=>'failed','entity_id'=>(int)\$term->term_id,),\$deleted",
		),
	);

	foreach ($sources as $tree => $tree_sources) {
		foreach ($tree_sources as $key => $source) {
			g16b_same(0, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $source), $tree . ' ' . $key . ' source must contain no direct server logging.');
			g16b_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*(?:DevelopmentFunctions|error_log)/i', $source), $tree . ' ' . $key . ' source must not suppress logging findings.');
			g16b_same(count($events[$key]), substr_count($source, 'bvmgr_record_operational_issue('), $tree . ' ' . $key . ' adapter count changed.');
			foreach ($events[$key] as $event => $arguments) {
				$call = g16b_extract_adapter_call($source, $event);
				$expected = "vms_record_operational_issue('{$event}',{$arguments});";
				g16b_same(g16b_compact_php($expected), g16b_compact_php($call), $tree . ' operational event contract changed: ' . $event);
			}
		}
	}

	foreach ($events as $key => $file_events) {
		foreach (array_keys($file_events) as $event) {
			g16b_same(g16b_extract_adapter_call($sources['mirror'][$key], $event), g16b_extract_adapter_call($sources['shadow'][$key], $event), 'Owned adapter boundary lost mirror/shadow parity: ' . $event);
		}
	}
	g16b_same($sources['mirror']['admissions'], $sources['shadow']['admissions'], 'Admissions source must retain full mirror/shadow parity.');
	g16b_assert($sources['mirror']['import'] !== $sources['shadow']['import'], 'Import source must retain established surrounding divergence.');
	g16b_assert($sources['mirror']['vendor'] !== $sources['shadow']['vendor'], 'Vendor source must retain established surrounding divergence.');

	return $sources;
}

function g16b_projection_contract(array $sources): void
{
	$pre_edit_hashes = array(
		'mirror' => array(
			'import' => 'ce207a147c802345ac5735b463e9e035a96e7c2ae6b2b2b75a6faa09c17d39a3',
			'admissions' => 'd15f1629c610ddc4429691019124e10ff517973bf524c2e05634788261984ae2',
			'vendor' => 'ac036bef295173d9d26b7165871a09797de2a61add12247ee985a547f3f74b4e',
		),
		'shadow' => array(
			'import' => '4fdfc4f2cb2629637e587408cb7df2645c4ef2d1b787ae1afd5860e9106654a0',
			'admissions' => 'd15f1629c610ddc4429691019124e10ff517973bf524c2e05634788261984ae2',
			'vendor' => '4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87',
		),
	);
	$historical = array(
		'import' => array(
			'event_plan_import_preview_failed' => "error_log('[VMS EPCSV] Preview build failed: ' . \$preview->get_error_message());",
			'event_plan_import_commit_failed' => "error_log('[VMS EPCSV] Commit failed: ' . \$result->get_error_message());",
			'event_plan_import_revert_failed' => "error_log('[VMS EPCSV] Revert failed: ' . \$result->get_error_message());",
		),
		'admissions' => array(
			'admission_create_failed' => "error_log('VMS Admission create failed: ' . (string) \$wpdb->last_error);",
			'admission_update_failed' => "error_log('VMS Admission update failed: ' . (string) \$wpdb->last_error);",
			'admission_checkin_failed' => "error_log('VMS Admission checkin failed: ' . (string) \$wpdb->last_error);",
			'admission_uncheckin_failed' => "error_log('VMS Admission uncheckin failed: ' . (string) \$wpdb->last_error);",
		),
		'vendor' => array(
			'vendor_type_default_term_ensure_failed' => "error_log('[VMS] vendor-type: failed to ensure default term ' . \$slug . ' (' . \$created->get_error_message() . ')');",
			'vendor_type_duplicate_term_delete_failed' => "error_log('[VMS] vendor-type: failed deleting duplicate term #' . (int) \$term->term_id . ' (' . \$deleted->get_error_message() . ')');",
		),
	);

	foreach ($sources as $tree => $tree_sources) {
		foreach ($tree_sources as $key => $source) {
			$projection = $source;
			foreach ($historical[$key] as $event => $old_statement) {
				$current_call = g16b_extract_adapter_call($projection, $event);
				$current_prefix = '';
				$historical_prefix = '';
				if ($key === 'vendor') {
					$current_prefix = $event === 'vendor_type_default_term_ensure_failed' ? "\t\t\t\t" : "\t\t\t\t\t";
					$historical_prefix = ' ' . $current_prefix;
				}
				$projection = g16b_replace_once(
					$current_prefix . $current_call,
					$historical_prefix . $old_statement,
					$projection,
					$tree . ' projection must restore exactly one historical log statement: ' . $event
				);
			}
			g16b_same($pre_edit_hashes[$tree][$key], hash('sha256', $projection), $tree . ' ' . $key . ' immutable pre-edit projection changed.');
		}
	}

	$mutated = g16b_replace_once(
		"'event_plan_import_preview_failed'",
		"'event_plan_import_preview_changed'",
		$sources['mirror']['import'],
		'Mutation control must alter exactly one event code.'
	);
	g16b_assert(
		hash('sha256', $mutated) !== hash('sha256', $sources['mirror']['import']),
		'Mutation control must prove source projection sensitivity.'
	);
	$failed_closed = false;
	try {
		g16b_extract_adapter_call($mutated, 'event_plan_import_preview_failed');
	} catch (RuntimeException $e) {
		$failed_closed = true;
	}
	g16b_assert($failed_closed, 'Mutation control must make the immutable event lookup fail closed.');
}

if (!defined('ABSPATH')) {
	define('ABSPATH', '/var/www/wordpress/');
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;

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

		public function get_error_messages(): array
		{
			return array($this->message);
		}
	}
}

if (!class_exists('WP_Term')) {
	class WP_Term
	{
		public int $term_id;
		public string $slug;
		public string $name;

		public function __construct(int $term_id, string $slug, string $name)
		{
			$this->term_id = $term_id;
			$this->slug = $slug;
			$this->name = $name;
		}
	}
}

if (!class_exists('WP_REST_Response')) {
	class WP_REST_Response
	{
		public array $data;
		public int $status;

		public function __construct(array $data, int $status = 200)
		{
			$this->data = $data;
			$this->status = $status;
		}
	}
}

function sanitize_key(string $key): string
{
	return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
}

function absint($value): int
{
	return abs((int) $value);
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function __($text, $domain = null): string
{
	unset($domain);
	return (string) $text;
}

function g16b_load_foundation_helpers(string $runtime_source): void
{
	foreach (array(
		'bvmgr_operational_issue_value_is_tainted',
		'bvmgr_operational_issue_request_path',
		'bvmgr_operational_issue_error_identity',
		'bvmgr_operational_issue_context',
	) as $helper) {
		eval(g16b_extract_guarded_function($runtime_source, $helper));
	}
}

function g16b_reset_capture(bool $adapter_result = true): void
{
	$GLOBALS['g16b_trace'] = array();
	$GLOBALS['g16b_records'] = array();
	$GLOBALS['g16b_adapter_result'] = $adapter_result;
}

function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$record = array(
		'event_code' => $event_code,
		'context' => bvmgr_operational_issue_context($context),
	);
	$error_identity = bvmgr_operational_issue_error_identity($error);
	if ($error_identity !== array()) {
		$record['error'] = $error_identity;
	}
	$GLOBALS['g16b_records'][] = $record;
	$GLOBALS['g16b_trace'][] = 'record:' . $event_code;
	return (bool) ($GLOBALS['g16b_adapter_result'] ?? true);
}

function g16b_assert_no_sentinels(array $records, array $sentinels, string $message): void
{
	$serialized = strtolower((string) json_encode($records));
	foreach ($sentinels as $sentinel) {
		g16b_assert(strpos($serialized, strtolower($sentinel)) === false, $message . ': ' . $sentinel);
	}
}

function bvmgr_event_plan_import_delete_stored_file(string $target_key): void
{
	$GLOBALS['g16b_trace'][] = 'delete:' . $target_key;
}

function bvmgr_event_plan_import_set_notice(string $type, string $message): void
{
	$GLOBALS['g16b_trace'][] = 'notice:' . $type;
	$GLOBALS['g16b_notice'] = array($type, $message);
}

function bvmgr_event_plan_import_admin_page_url(array $args = array()): string
{
	return '/wp-admin/tools.php?' . http_build_query($args);
}

function wp_safe_redirect(string $url): void
{
	$GLOBALS['g16b_trace'][] = 'redirect:' . $url;
	$GLOBALS['g16b_redirect'] = $url;
}

function g16b_eval_failure_branch(string $block, array $variables): void
{
	extract($variables, EXTR_SKIP);
	$exit_count = 0;
	$block = str_replace('exit;', 'return;', $block, $exit_count);
	g16b_assert($exit_count <= 1, 'Failure branch exit rewrite must remain bounded.');
	eval($block);
}

function g16b_import_behavior(string $source): void
{
	$raw_message = 'RAW_IMPORT alice@example.com token sk_live_ABCDEF1234567890 nonce SELECT * FROM wp_users /private/tmp/import.csv 203.0.113.9 UA_SENTINEL';
	$sentinels = array(
		'raw_import',
		'alice@example.com',
		'sk_live_abcdef1234567890',
		'nonce',
		'select * from wp_users',
		'/private/tmp/import.csv',
		'203.0.113.9',
		'ua_sentinel',
	);
	$preview_error = new WP_Error('preview_failed', $raw_message);
	$preview_function = g16b_extract_guarded_function($source, 'bvmgr_event_plan_import_handle_preview_action');
	$preview_block = g16b_extract_if_block($preview_function, 'if (is_wp_error($preview))');

	g16b_reset_capture(false);
	g16b_eval_failure_branch($preview_block, array(
		'preview' => $preview_error,
		'target_key' => 'retained-storage-key',
	));
	g16b_same(
		array('delete:retained-storage-key', 'record:event_plan_import_preview_failed', 'notice:error', 'redirect:/wp-admin/tools.php?'),
		$GLOBALS['g16b_trace'],
		'Preview failure order must remain delete -> record -> raw privileged notice -> redirect.'
	);
	g16b_same(array('error', $raw_message), $GLOBALS['g16b_notice'], 'Preview failure must retain the exact privileged raw notice.');
	$preview_record = $GLOBALS['g16b_records'][0] ?? array();
	g16b_same('wp_error', $preview_record['error']['error_class'] ?? '', 'Preview must retain only safe WP_Error class identity.');
	g16b_same('preview_failed', $preview_record['error']['error_code'] ?? '', 'Preview must retain only safe WP_Error code identity.');
	g16b_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($preview_record['error']['error_fingerprint'] ?? '')), 'Preview fingerprint must be deterministic truncated SHA-256.');
	g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, 'Preview storage leaked raw error data');

	g16b_reset_capture(true);
	g16b_eval_failure_branch($preview_block, array('preview' => $preview_error, 'target_key' => 'retained-storage-key'));
	g16b_same($preview_record['error'], $GLOBALS['g16b_records'][0]['error'] ?? array(), 'Identical WP_Error identity must produce a deterministic fingerprint.');
	g16b_same(array('error', $raw_message), $GLOBALS['g16b_notice'], 'Adapter return value must not change preview notice behavior.');

	$commit_function = g16b_extract_guarded_function($source, 'bvmgr_event_plan_import_handle_commit_action');
	$commit_block = g16b_extract_if_block($commit_function, 'if (is_wp_error($result))');
	g16b_reset_capture(false);
	g16b_eval_failure_branch($commit_block, array(
		'result' => new WP_Error('commit_failed', $raw_message),
		'token' => 'preview-token-retained',
	));
	g16b_same(
		array('record:event_plan_import_commit_failed', 'notice:error', 'redirect:/wp-admin/tools.php?preview_token=preview-token-retained'),
		$GLOBALS['g16b_trace'],
		'Commit failure must retain preview data and preserve record -> notice -> token redirect order.'
	);
	g16b_same(array('error', $raw_message), $GLOBALS['g16b_notice'], 'Commit failure must retain exact privileged raw notice.');
	g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, 'Commit storage leaked raw error data');

	$revert_function = g16b_extract_guarded_function($source, 'bvmgr_event_plan_import_handle_revert_last_action');
	$revert_block = g16b_extract_if_block($revert_function, 'if (is_wp_error($result))');
	g16b_reset_capture(false);
	g16b_eval_failure_branch($revert_block, array('result' => new WP_Error('revert_failed', $raw_message)));
	g16b_same(
		array('record:event_plan_import_revert_failed', 'notice:error', 'redirect:/wp-admin/tools.php?'),
		$GLOBALS['g16b_trace'],
		'Revert failure must preserve record -> raw privileged notice -> redirect order.'
	);
	g16b_same(array('error', $raw_message), $GLOBALS['g16b_notice'], 'Revert failure must retain exact privileged raw notice.');
	g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, 'Revert storage leaked raw error data');
}

function vms_admission_rest_error(string $code, string $message, int $status = 400, $data = null): WP_REST_Response
{
	$GLOBALS['g16b_trace'][] = 'response:' . $code;
	$response = new WP_REST_Response(array(
		'ok' => false,
		'data' => $data,
		'error' => array('code' => $code, 'message' => $message),
	), $status);
	$GLOBALS['g16b_response'] = $response;
	return $response;
}

function vms_admission_audit_log(...$args): void
{
	unset($args);
	$GLOBALS['g16b_trace'][] = 'audit';
}

function g16b_admissions_behavior(string $source): void
{
	$db_sentinel = 'RAW_DB SELECT * FROM wp_admissions WHERE email=guest@example.com token=pk_test_ABCDEF1234567890 /private/tmp/query.sql 198.51.100.7 UA_SENTINEL';
	$GLOBALS['wpdb'] = (object) array('last_error' => $db_sentinel);
	$sentinels = array(
		'raw_db',
		'select * from wp_admissions',
		'guest@example.com',
		'pk_test_abcdef1234567890',
		'/private/tmp/query.sql',
		'198.51.100.7',
		'ua_sentinel',
		'turnstile-secret',
		'private guest notes',
	);
	$cases = array(
		array(
			'function' => 'vms_admission_rest_create',
			'needle' => 'if ($insert === false)',
			'event' => 'admission_create_failed',
			'operation' => 'create',
			'message' => 'Could not create admission entry.',
			'variables' => array(
				'insert' => false,
				'event_plan_id' => 73,
				'guest_email' => 'guest@example.com',
				'notes' => 'private guest notes',
			),
			'ids' => array('plan_id' => 73),
		),
		array(
			'function' => 'vms_admission_rest_patch',
			'needle' => 'if ($ok === false)',
			'event' => 'admission_update_failed',
			'operation' => 'update',
			'message' => 'Could not update entry.',
			'variables' => array('ok' => false, 'entry_id' => 81, 'row' => array('event_plan_id' => '74', 'guest_email' => 'guest@example.com', 'notes' => 'private guest notes')),
			'ids' => array('plan_id' => 74, 'entity_id' => 81),
		),
		array(
			'function' => 'vms_admission_rest_checkin',
			'needle' => 'if ($updated === false)',
			'event' => 'admission_checkin_failed',
			'operation' => 'checkin',
			'message' => 'Could not check in guest.',
			'variables' => array('updated' => false, 'entry_id' => 82, 'row' => array('event_plan_id' => 75, 'guest_email' => 'guest@example.com', 'turnstile' => 'turnstile-secret')),
			'ids' => array('plan_id' => 75, 'entity_id' => 82),
		),
		array(
			'function' => 'vms_admission_rest_uncheckin',
			'needle' => 'if ($updated === false)',
			'event' => 'admission_uncheckin_failed',
			'operation' => 'uncheckin',
			'message' => 'Could not undo check-in.',
			'variables' => array('updated' => false, 'entry_id' => 83, 'row' => array('event_plan_id' => 76, 'guest_email' => 'guest@example.com', 'notes' => 'private guest notes')),
			'ids' => array('plan_id' => 76, 'entity_id' => 83),
		),
	);

	foreach ($cases as $case) {
		$function = g16b_extract_guarded_function($source, $case['function']);
		$block = g16b_extract_if_block($function, $case['needle']);
		g16b_reset_capture(false);
		g16b_eval_failure_branch($block, $case['variables']);
		g16b_same(array('record:' . $case['event'], 'response:db_error'), $GLOBALS['g16b_trace'], $case['event'] . ' must record before returning and must not audit on DB failure.');
		$response = $GLOBALS['g16b_response'] ?? null;
		g16b_assert($response instanceof WP_REST_Response, $case['event'] . ' must retain a REST response.');
		g16b_same(500, $response->status, $case['event'] . ' HTTP status changed.');
		g16b_same(array('ok' => false, 'data' => null, 'error' => array('code' => 'db_error', 'message' => $case['message'])), $response->data, $case['event'] . ' response body changed.');
		$expected_context = array(
			'service' => 'admissions',
			'entity_type' => 'admission',
			'operation' => $case['operation'],
			'stage' => 'database_write',
			'status' => 'failed',
		) + $case['ids'];
		g16b_same($expected_context, $GLOBALS['g16b_records'][0]['context'] ?? array(), $case['event'] . ' safe context changed.');
		g16b_assert(!isset($GLOBALS['g16b_records'][0]['error']), $case['event'] . ' must pass null rather than raw database error text.');
		g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, $case['event'] . ' stored a DB/PII/path/token sentinel');
	}
}

function taxonomy_exists(string $taxonomy): bool
{
	return $taxonomy === 'vms_vendor_type';
}

function vms_vendor_type_select_options(): array
{
	return ($GLOBALS['g16b_vendor_mode'] ?? '') === 'ensure'
		? array('band' => 'Music Vendor', 'food_truck' => 'Food Vendor')
		: array('band' => 'Music Vendor');
}

function get_term_by(string $field, string $value, string $taxonomy)
{
	unset($field, $value, $taxonomy);
	if (($GLOBALS['g16b_vendor_mode'] ?? '') === 'canonicalize') {
		return new WP_Term(10, 'band', 'Music Vendor');
	}
	return false;
}

function wp_update_term(int $term_id, string $taxonomy, array $args): array
{
	unset($term_id, $taxonomy, $args);
	return array();
}

function wp_insert_term(string $label, string $taxonomy, array $args)
{
	unset($label, $taxonomy);
	$slug = (string) ($args['slug'] ?? '');
	$GLOBALS['g16b_trace'][] = 'insert:' . $slug;
	if (($GLOBALS['g16b_vendor_mode'] ?? '') === 'ensure' && $slug === 'band') {
		return new WP_Error('term_insert_failed', (string) $GLOBALS['g16b_vendor_error']);
	}
	return array('term_id' => 10);
}

function get_term(int $term_id, string $taxonomy): WP_Term
{
	unset($taxonomy);
	return new WP_Term($term_id, 'band', 'Music Vendor');
}

function get_option(string $key, $default = false)
{
	unset($key);
	return $default;
}

function get_terms(array $args): array
{
	unset($args);
	return ($GLOBALS['g16b_vendor_mode'] ?? '') === 'canonicalize'
		? array(new WP_Term(99, 'band_artist', 'Legacy Band'))
		: array();
}

function vms_vendor_type_canonical_slug_for_term(WP_Term $term): string
{
	unset($term);
	return 'band';
}

function get_objects_in_term(int $term_id, string $taxonomy): array
{
	unset($term_id, $taxonomy);
	return array();
}

function wp_set_object_terms(...$args): void
{
	unset($args);
}

function wp_remove_object_terms(...$args): void
{
	unset($args);
}

function get_term_meta(int $term_id, string $key, bool $single = false): string
{
	unset($term_id, $key, $single);
	return '';
}

function update_term_meta(...$args): void
{
	unset($args);
}

function wp_delete_term(int $term_id, string $taxonomy): WP_Error
{
	unset($taxonomy);
	$GLOBALS['g16b_trace'][] = 'delete:' . $term_id;
	return new WP_Error('term_delete_failed', (string) $GLOBALS['g16b_vendor_error']);
}

function get_posts(array $args): array
{
	unset($args);
	return array();
}

function get_post_meta(...$args): string
{
	unset($args);
	return '';
}

function update_post_meta(...$args): void
{
	unset($args);
}

function update_option(string $key, $value, bool $autoload = false): bool
{
	$GLOBALS['g16b_trace'][] = 'option:' . $key;
	$GLOBALS['g16b_option_update'] = array($key, $value, $autoload);
	return true;
}

function g16b_vendor_behavior(string $source): void
{
	$ensure_block = g16b_extract_guarded_function($source, 'vms_vendor_type_ensure_default_terms');
	$canonicalize_block = g16b_extract_guarded_function($source, 'vms_vendor_type_maybe_canonicalize_terms');
	eval($ensure_block);
	eval($canonicalize_block);

	$raw_error = 'RAW_VENDOR vendor@example.com secret sk_test_ABCDEF1234567890 nonce SELECT * FROM wp_terms /private/tmp/vendor.log 192.0.2.44 UA_SENTINEL band_artist';
	$GLOBALS['g16b_vendor_error'] = $raw_error;
	$sentinels = array(
		'raw_vendor',
		'vendor@example.com',
		'sk_test_abcdef1234567890',
		'nonce',
		'select * from wp_terms',
		'/private/tmp/vendor.log',
		'192.0.2.44',
		'ua_sentinel',
		'band_artist',
	);

	$GLOBALS['g16b_vendor_mode'] = 'ensure';
	g16b_reset_capture(false);
	vms_vendor_type_ensure_default_terms();
	g16b_same(
		array('insert:band', 'record:vendor_type_default_term_ensure_failed', 'insert:food_truck'),
		$GLOBALS['g16b_trace'],
		'Ensure-default loop must continue after a failed insert even when the adapter returns false.'
	);
	$ensure_record = $GLOBALS['g16b_records'][0] ?? array();
	g16b_same(
		array(
			'service' => 'vendor_taxonomy',
			'entity_type' => 'vendor_type',
			'operation' => 'ensure_default',
			'stage' => 'term_insert',
			'status' => 'failed',
		),
		$ensure_record['context'] ?? array(),
		'Ensure-default context changed.'
	);
	g16b_same('term_insert_failed', $ensure_record['error']['error_code'] ?? '', 'Ensure-default safe error code changed.');
	g16b_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($ensure_record['error']['error_fingerprint'] ?? '')), 'Ensure-default error fingerprint must be truncated SHA-256.');
	g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, 'Ensure-default storage leaked slug/error/PII/token data');

	$GLOBALS['g16b_vendor_mode'] = 'canonicalize';
	g16b_reset_capture(false);
	unset($GLOBALS['g16b_option_update']);
	vms_vendor_type_maybe_canonicalize_terms();
	g16b_same(
		array('delete:99', 'record:vendor_type_duplicate_term_delete_failed', 'option:vms_vendor_type_canonicalized_v1'),
		$GLOBALS['g16b_trace'],
		'Canonicalization must continue to its one-shot completion marker after delete/adapter failure.'
	);
	g16b_same(array('vms_vendor_type_canonicalized_v1', '1', false), $GLOBALS['g16b_option_update'] ?? array(), 'Canonicalization completion option contract changed.');
	$delete_record = $GLOBALS['g16b_records'][0] ?? array();
	g16b_same(
		array(
			'service' => 'vendor_taxonomy',
			'entity_type' => 'vendor_type',
			'operation' => 'delete_duplicate',
			'stage' => 'canonicalization',
			'status' => 'failed',
			'entity_id' => 99,
		),
		$delete_record['context'] ?? array(),
		'Duplicate-delete safe context changed.'
	);
	g16b_same('term_delete_failed', $delete_record['error']['error_code'] ?? '', 'Duplicate-delete safe error code changed.');
	g16b_assert_no_sentinels($GLOBALS['g16b_records'], $sentinels, 'Duplicate-delete storage leaked slug/error/PII/token data');
}

g16b_artifact_contract();
$g16b_sources = g16b_source_contract($g16b_root, $g16b_shadow_root);
g16b_projection_contract($g16b_sources);
g16b_load_foundation_helpers(g16b_source($g16b_root, 'includes/runtime-guards.php'));
g16b_import_behavior($g16b_sources['mirror']['import']);
g16b_admissions_behavior($g16b_sources['mirror']['admissions']);
g16b_vendor_behavior($g16b_sources['mirror']['vendor']);

echo "G16 operational logging group-B contracts passed.\n";
