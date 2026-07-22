<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class WP_Error
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
}

final class VMS_Test_WPDB
{
	/** @var array<int,array<string,mixed>> */
	public array $rows = array();

	public function prepare(string $query, ...$args): string
	{
		unset($args);
		return $query;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results(string $query, $output = ARRAY_A): array
	{
		unset($query, $output);
		return $this->rows;
	}
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function update_post_meta(...$args): bool
{
	$GLOBALS['vms_test_post_meta_updates'][] = $args;
	return true;
}

function is_wp_error($thing): bool
{
	return $thing instanceof WP_Error;
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	throw new RuntimeException(
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name;
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$inSingleQuote = false;
	$inDoubleQuote = false;
	$inLineComment = false;
	$inBlockComment = false;
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$nextChar = $i + 1 < $length ? $source[$i + 1] : '';
		$previousChar = $i > 0 ? $source[$i - 1] : '';

		if ($inLineComment) {
			if ($char === "\n") {
				$inLineComment = false;
			}
			continue;
		}
		if ($inBlockComment) {
			if ($char === '*' && $nextChar === '/') {
				$inBlockComment = false;
				$i++;
			}
			continue;
		}
		if ($inSingleQuote) {
			if ($char === "'" && $previousChar !== '\\') {
				$inSingleQuote = false;
			}
			continue;
		}
		if ($inDoubleQuote) {
			if ($char === '"' && $previousChar !== '\\') {
				$inDoubleQuote = false;
			}
			continue;
		}

		if ($char === '/' && $nextChar === '/') {
			$inLineComment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $nextChar === '*') {
			$inBlockComment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$inSingleQuote = true;
			continue;
		}
		if ($char === '"') {
			$inDoubleQuote = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_call_without_warnings(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $errno, string $errstr, string $errfile, int $errline) use (&$warnings): bool {
			$warnings[] = array(
				'errno' => $errno,
				'errstr' => $errstr,
				'errfile' => $errfile,
				'errline' => $errline,
			);
			return true;
		}
	);

	try {
		$result = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'result' => $result,
		'warnings' => $warnings,
	);
}

function vms_test_compile_generate_wrapper(string $source): string
{
	$body = vms_test_extract_function($source, 'vms_tasks_generate_for_event');

	return strtr(
		$body,
		array(
			'function vms_tasks_generate_for_event' => 'function vms_test_generate_for_event',
			'vms_tasks_db_ready(' => 'vms_test_db_ready(',
			'vms_tasks_get_event_context(' => 'vms_test_get_event_context(',
			'vms_tasks_get_settings(' => 'vms_test_get_settings(',
			'vms_tasks_should_allow_supersede(' => 'vms_test_should_allow_supersede(',
			'vms_tasks_signature_meta_key(' => 'vms_test_signature_meta_key(',
			'vms_tasks_build_event_signature(' => 'vms_test_build_event_signature(',
			'vms_tasks_get_applicable_checklists(' => 'vms_test_get_applicable_checklists(',
			'vms_tasks_get_checklist_items(' => 'vms_test_get_checklist_items(',
			'vms_tasks_get_task_template(' => 'vms_test_get_task_template(',
			'vms_tasks_merge_template_with_overrides(' => 'vms_test_merge_template_with_overrides(',
			'vms_tasks_compute_due_at_local(' => 'vms_test_compute_due_at_local(',
			'vms_tasks_resolve_assignment_for_instance(' => 'vms_test_resolve_assignment_for_instance(',
			'vms_tasks_select_existing_open_instance(' => 'vms_test_select_existing_open_instance(',
			'vms_tasks_update_instance_assignment(' => 'vms_test_update_instance_assignment(',
			'vms_tasks_log_task_action(' => 'vms_test_log_task_action(',
			'vms_tasks_insert_instance(' => 'vms_test_insert_instance(',
			'vms_tasks_supersede_open_instances(' => 'vms_test_supersede_open_instances(',
		)
	);
}

function vms_test_template(int $templateId, array $overrides = array()): array
{
	return array_merge(
		array(
			'id' => $templateId,
			'is_active' => 1,
			'title' => 'Load In',
			'instructions' => 'Baseline instructions',
			'priority' => 'high',
			'required_default' => 1,
			'due_mode' => 'event_offset',
			'due_offset_minutes' => 90,
			'due_time_local' => '08:30',
			'assignment_mode' => 'person',
			'role_key' => 'default-role',
			'assignee_user_id' => 42,
		),
		$overrides
	);
}

function vms_test_item(int $rowId, int $templateId, string $state, array $overrides = array(), string $reason = ''): array
{
	if ($reason === '') {
		if ($state === 'invalid') {
			$reason = 'invalid_test_reason';
		} elseif ($state === 'missing') {
			$reason = 'missing_value';
		} else {
			$reason = 'valid';
		}
	}

	return array(
		'id' => $rowId,
		'task_template_id' => $templateId,
		'overrides' => $overrides,
		'overrides_state' => $state,
		'overrides_reason' => $reason,
	);
}

function vms_test_reset_generate_env(array $itemsByChecklist, array $templates = array(), array $options = array()): void
{
	$checklists = array();
	foreach (array_keys($itemsByChecklist) as $checklistId) {
		$checklists[] = array('id' => $checklistId);
	}

	$templateMap = array();
	foreach ($templates as $templateId => $template) {
		$templateMap[$templateId] = $template;
	}

	$GLOBALS['vms_test_generate_env'] = array(
		'event_context' => $options['event_context'] ?? array(
			'venue_id' => 11,
			'event_type' => 'concert',
			'event_start_local' => '2026-08-01 18:00:00',
			'date_ymd' => '2026-08-01',
		),
		'settings' => $options['settings'] ?? array(),
		'allow_supersede' => $options['allow_supersede'] ?? false,
		'checklists' => $checklists,
		'items' => $itemsByChecklist,
		'templates' => $templateMap,
		'existing' => $options['existing'] ?? null,
		'insert_error' => $options['insert_error'] ?? null,
		'supersede_result' => $options['supersede_result'] ?? 0,
		'next_insert_id' => 900,
	);
	$GLOBALS['vms_test_generate_trace'] = array(
		'merge_calls' => array(),
		'due_calls' => array(),
		'assignment_calls' => array(),
		'existing_calls' => array(),
		'insert_calls' => array(),
		'update_calls' => array(),
		'supersede_calls' => array(),
		'log_calls' => array(),
		'scheduled_role_calls' => array(),
	);
	$GLOBALS['vms_test_post_meta_updates'] = array();
}

function vms_test_db_ready(): bool
{
	return true;
}

function vms_test_get_event_context(int $eventId): ?array
{
	unset($eventId);
	return $GLOBALS['vms_test_generate_env']['event_context'];
}

function vms_test_get_settings(): array
{
	return $GLOBALS['vms_test_generate_env']['settings'];
}

function vms_test_should_allow_supersede(int $eventId, array $eventContext, array $settings): bool
{
	unset($eventId, $eventContext, $settings);
	return !empty($GLOBALS['vms_test_generate_env']['allow_supersede']);
}

function vms_test_signature_meta_key(): string
{
	return '_vms_test_signature';
}

function vms_test_build_event_signature(array $eventContext): array
{
	return array(
		'date_ymd' => (string) ($eventContext['date_ymd'] ?? ''),
		'venue_id' => absint($eventContext['venue_id'] ?? 0),
		'event_type' => sanitize_key((string) ($eventContext['event_type'] ?? '')),
	);
}

function vms_test_get_applicable_checklists(int $venueId, string $eventType): array
{
	unset($venueId, $eventType);
	return $GLOBALS['vms_test_generate_env']['checklists'];
}

function vms_test_get_checklist_items(int $checklistId): array
{
	return $GLOBALS['vms_test_generate_env']['items'][$checklistId] ?? array();
}

function vms_test_get_task_template(int $templateId): ?array
{
	return $GLOBALS['vms_test_generate_env']['templates'][$templateId] ?? null;
}

function vms_test_merge_template_with_overrides(array $template, array $overrides): array
{
	$GLOBALS['vms_test_generate_trace']['merge_calls'][] = array(
		'template' => $template,
		'overrides' => $overrides,
	);
	return vms_tasks_merge_template_with_overrides($template, $overrides);
}

function vms_test_compute_due_at_local(array $eventContext, string $dueMode, ?int $dueOffsetMinutes, string $dueTimeLocal = ''): ?string
{
	$GLOBALS['vms_test_generate_trace']['due_calls'][] = array(
		'event_context' => $eventContext,
		'due_mode' => $dueMode,
		'due_offset_minutes' => $dueOffsetMinutes,
		'due_time_local' => $dueTimeLocal,
	);
	return vms_tasks_compute_due_at_local($eventContext, $dueMode, $dueOffsetMinutes, $dueTimeLocal);
}

function vms_test_resolve_assignment_for_instance(int $eventId, array $effective): array
{
	$GLOBALS['vms_test_generate_trace']['assignment_calls'][] = array(
		'event_id' => $eventId,
		'effective' => $effective,
	);
	return vms_tasks_resolve_assignment_for_instance($eventId, $effective);
}

function vms_test_select_existing_open_instance(int $eventId, int $templateId, int $originChecklistId, ?string $dueAtLocal, bool $strictDue = true): ?array
{
	$GLOBALS['vms_test_generate_trace']['existing_calls'][] = array(
		'event_id' => $eventId,
		'template_id' => $templateId,
		'origin_checklist_id' => $originChecklistId,
		'due_at_local' => $dueAtLocal,
		'strict_due' => $strictDue,
	);
	return $GLOBALS['vms_test_generate_env']['existing'];
}

function vms_test_update_instance_assignment(int $instanceId, int $assigneeUserId, bool $assignmentLocked = false, ?int $actorUserId = null): bool
{
	$GLOBALS['vms_test_generate_trace']['update_calls'][] = array(
		'instance_id' => $instanceId,
		'assignee_user_id' => $assigneeUserId,
		'assignment_locked' => $assignmentLocked,
		'actor_user_id' => $actorUserId,
	);
	return true;
}

function vms_test_log_task_action(int $instanceId, string $action, ?int $actorUserId = null, $details = null): bool
{
	$GLOBALS['vms_test_generate_trace']['log_calls'][] = array(
		'instance_id' => $instanceId,
		'action' => $action,
		'actor_user_id' => $actorUserId,
		'details' => $details,
	);
	return true;
}

function vms_test_insert_instance(array $payload)
{
	$GLOBALS['vms_test_generate_trace']['insert_calls'][] = $payload;
	if ($GLOBALS['vms_test_generate_env']['insert_error'] instanceof WP_Error) {
		return $GLOBALS['vms_test_generate_env']['insert_error'];
	}

	$nextInsertId = (int) $GLOBALS['vms_test_generate_env']['next_insert_id'];
	$GLOBALS['vms_test_generate_env']['next_insert_id'] = $nextInsertId + 1;
	return $nextInsertId;
}

function vms_test_supersede_open_instances(int $eventId, int $templateId, int $originChecklistId, int $newInstanceId, ?int $actorUserId = null): int
{
	$GLOBALS['vms_test_generate_trace']['supersede_calls'][] = array(
		'event_id' => $eventId,
		'template_id' => $templateId,
		'origin_checklist_id' => $originChecklistId,
		'new_instance_id' => $newInstanceId,
		'actor_user_id' => $actorUserId,
	);
	return (int) $GLOBALS['vms_test_generate_env']['supersede_result'];
}

function vms_tasks_table_name(string $kind): string
{
	return 'wp_' . $kind;
}

function vms_tasks_resolve_scheduled_role_user_id(int $eventId, string $roleKey): array
{
	$GLOBALS['vms_test_generate_trace']['scheduled_role_calls'][] = array(
		'event_id' => $eventId,
		'role_key' => $roleKey,
	);

	if ($roleKey === 'lead-tech') {
		return array(
			'status' => 'single',
			'assignee_user_id' => 77,
			'staff_ids' => array(501),
		);
	}

	return array(
		'status' => 'none',
		'assignee_user_id' => 0,
		'staff_ids' => array(),
	);
}

$pluginRoot = dirname(__DIR__);
$livePluginRoot = dirname($pluginRoot, 2) . '/vms';
$storePath = $pluginRoot . '/includes/modules/staff-tasks/store.php';
$liveStorePath = $livePluginRoot . '/includes/modules/staff-tasks/store.php';
$generatorPath = $pluginRoot . '/includes/modules/staff-tasks/generator.php';
$liveGeneratorPath = $livePluginRoot . '/includes/modules/staff-tasks/generator.php';
$dbPath = $pluginRoot . '/includes/modules/staff-tasks/db.php';
$adminUiPath = $pluginRoot . '/includes/modules/staff-tasks/admin-ui.php';

$storeSource = (string) file_get_contents($storePath);
$liveStoreSource = (string) file_get_contents($liveStorePath);
$generatorSource = (string) file_get_contents($generatorPath);
$liveGeneratorSource = (string) file_get_contents($liveGeneratorPath);
$dbSource = (string) file_get_contents($dbPath);
$adminUiSource = (string) file_get_contents($adminUiPath);

$decoderBody = vms_test_extract_function($storeSource, 'vms_tasks_decode_checklist_overrides');
$getChecklistBody = vms_test_extract_function($storeSource, 'vms_tasks_get_checklist_items');
$writerBody = vms_test_extract_function($storeSource, 'vms_tasks_replace_checklist_items');
$dueBody = vms_test_extract_function($generatorSource, 'vms_tasks_compute_due_at_local');
$mergeBody = vms_test_extract_function($generatorSource, 'vms_tasks_merge_template_with_overrides');
$resolveBody = vms_test_extract_function($generatorSource, 'vms_tasks_resolve_assignment_for_instance');
$generateBody = vms_test_extract_function($generatorSource, 'vms_tasks_generate_for_event');
$signatureHelperBody = vms_test_extract_function($generatorSource, 'vms_tasks_decode_stored_event_signature');

vms_test_assert_contains('function vms_tasks_decode_checklist_overrides', $storeSource, 'Specialized checklist-overrides decoder should exist.');
vms_test_assert_not_contains('json_decode(', $getChecklistBody, 'vms_tasks_get_checklist_items() should no longer decode JSON directly.');
vms_test_assert_same(1, substr_count($storeSource, 'json_decode('), 'store.php should retain exactly one raw json_decode() call.');
vms_test_assert_same(1, substr_count($decoderBody, 'json_decode('), 'Checklist-overrides decoder should own the single raw json_decode() call.');
vms_test_assert_contains("'overrides_state'", $getChecklistBody, 'Checklist items should expose overrides_state.');
vms_test_assert_contains("'overrides_reason'", $getChecklistBody, 'Checklist items should expose overrides_reason.');
vms_test_assert_contains("\$payload['required_default'] = !empty(\$overrides['required_default']) ? 1 : 0;", $writerBody, 'Checklist writer should preserve required_default normalization.');
vms_test_assert_contains("\$payload['priority'] = vms_tasks_sanitize_priority((string) \$overrides['priority']);", $writerBody, 'Checklist writer should preserve priority normalization.');
vms_test_assert_contains("\$payload['assignment_mode'] = vms_tasks_sanitize_assignment_mode((string) \$overrides['assignment_mode']);", $writerBody, 'Checklist writer should preserve assignment_mode normalization.');
vms_test_assert_contains("\$payload['role_key'] = sanitize_key((string) \$overrides['role_key']);", $writerBody, 'Checklist writer should preserve role_key normalization.');
vms_test_assert_contains("\$payload['assignee_user_id'] = absint(\$overrides['assignee_user_id']);", $writerBody, 'Checklist writer should preserve assignee_user_id normalization.');
vms_test_assert_contains("\$payload['due_offset_minutes'] = (int) \$overrides['due_offset_minutes'];", $writerBody, 'Checklist writer should preserve due_offset_minutes normalization.');
vms_test_assert_contains('overrides_json LONGTEXT NULL,', $dbSource, 'Checklist storage schema should remain overrides_json LONGTEXT NULL.');
vms_test_assert_contains("if (!vms_tasks_current_user_can_manage_checklists()) {", $adminUiSource, 'Checklist capability guard should remain unchanged.');
vms_test_assert_contains("if (vms_tasks_admin_is_exact_post_request() && isset(\$_POST['vms_tasks_checklist_action'])) {", $adminUiSource, 'Checklist exact POST gate should remain unchanged.');
vms_test_assert_contains("check_admin_referer('vms_tasks_save_checklist');", $adminUiSource, 'Checklist nonce check should remain unchanged.');
vms_test_assert_contains("if (!vms_tasks_current_user_can_manage_all()) {", $adminUiSource, 'One-off capability guard should remain unchanged.');
vms_test_assert_contains("!wp_verify_nonce(\$nonce, 'vms_tasks_create_one_off')", $adminUiSource, 'One-off nonce boundary should remain unchanged.');
vms_test_assert_same(hash('sha256', $storeSource), hash('sha256', $liveStoreSource), 'Mirror and live store files should be byte-identical.');
vms_test_assert_same(hash('sha256', $generatorSource), hash('sha256', $liveGeneratorSource), 'Mirror and live generator files should be byte-identical.');
vms_test_assert_same(1, substr_count($generatorSource, 'json_decode('), 'Generator file should retain exactly one raw json_decode() call for the signature helper.');
vms_test_assert_same(1, substr_count($signatureHelperBody, 'json_decode('), 'Stored-signature helper should remain the only raw decoder in generator.php.');
vms_test_assert_true(strpos($storeSource, 'vms_tasks_decode_stored_event_signature') === false, 'store.php should remain outside the stored-signature slice.');
vms_test_assert_true(strpos($generatorSource, 'overrides_json') === false, 'Generator source should continue consuming decoded overrides instead of raw overrides_json.');

$invalidCheckPos = strpos($generateBody, "if (\$overrides_state === 'invalid')");
$dedupCheckPos = strpos($generateBody, 'if (isset($seen_templates[$template_id]))');
$seenMarkPos = strpos($generateBody, '$seen_templates[$template_id] = true;');
vms_test_assert_true($invalidCheckPos !== false, 'Generator should explicitly inspect invalid override state.');
vms_test_assert_true($dedupCheckPos !== false && $invalidCheckPos < $dedupCheckPos, 'Invalid override state check should run before duplicate suppression.');
vms_test_assert_true($seenMarkPos !== false && $invalidCheckPos < $seenMarkPos, 'Invalid override state check should run before template IDs are marked seen.');
vms_test_assert_contains("\$summary['warnings'][] = sprintf(", $generateBody, 'Generator should route invalid rows through the existing warnings array.');
vms_test_assert_contains("absint(\$item['id'] ?? 0)", $generateBody, 'Invalid-row warning should include the safe checklist row id.');
vms_test_assert_contains("\$template_id,", $generateBody, 'Invalid-row warning should include the task template id.');

eval(vms_test_extract_function($storeSource, 'vms_tasks_allowed_priorities'));
eval(vms_test_extract_function($storeSource, 'vms_tasks_sanitize_priority'));
eval(vms_test_extract_function($storeSource, 'vms_tasks_sanitize_due_mode'));
eval(vms_test_extract_function($storeSource, 'vms_tasks_sanitize_assignment_mode'));
eval($decoderBody);
eval($getChecklistBody);
eval($dueBody);
eval($mergeBody);
eval($resolveBody);
eval(vms_test_compile_generate_wrapper($generatorSource));

$largeObject = array();
for ($i = 0; $i < 50; $i++) {
	$largeObject['unknown_' . $i] = $i;
}

$excessiveDepth = '0';
for ($i = 0; $i < 20; $i++) {
	$excessiveDepth = '{"nested":' . $excessiveDepth . '}';
}

$decoderCases = array(
	'db_null' => array('raw' => null, 'state' => 'missing', 'reason' => 'missing_value', 'overrides' => array()),
	'empty_string' => array('raw' => '', 'state' => 'missing', 'reason' => 'blank_value', 'overrides' => array()),
	'whitespace_only' => array('raw' => " \n\t ", 'state' => 'missing', 'reason' => 'blank_value', 'overrides' => array()),
	'valid_empty_object' => array('raw' => '{}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array()),
	'valid_full_object' => array(
		'raw' => '{"required_default":0,"priority":"low","assignment_mode":"person","role_key":"","assignee_user_id":88,"due_offset_minutes":-30}',
		'state' => 'valid',
		'reason' => 'valid',
		'overrides' => array(
			'required_default' => 0,
			'priority' => 'low',
			'assignment_mode' => 'person',
			'role_key' => '',
			'assignee_user_id' => 88,
			'due_offset_minutes' => -30,
		),
	),
	'valid_subset_object' => array('raw' => '{"priority":"low"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'low')),
	'required_default_zero' => array('raw' => '{"required_default":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('required_default' => 0)),
	'required_default_one' => array('raw' => '{"required_default":1}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('required_default' => 1)),
	'priority_low' => array('raw' => '{"priority":"low"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'low')),
	'priority_normal' => array('raw' => '{"priority":"normal"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'normal')),
	'priority_high' => array('raw' => '{"priority":"high"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'high')),
	'invalid_priority' => array('raw' => '{"priority":"urgent"}', 'state' => 'invalid', 'reason' => 'priority_value', 'overrides' => array()),
	'assignment_role' => array('raw' => '{"assignment_mode":"role"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'role')),
	'assignment_person' => array('raw' => '{"assignment_mode":"person"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'person')),
	'assignment_scheduled_role' => array('raw' => '{"assignment_mode":"scheduled_role"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignment_mode' => 'scheduled_role')),
	'invalid_assignment_mode' => array('raw' => '{"assignment_mode":"bogus"}', 'state' => 'invalid', 'reason' => 'assignment_mode_value', 'overrides' => array()),
	'blank_role_key' => array('raw' => '{"role_key":""}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('role_key' => '')),
	'canonical_role_key' => array('raw' => '{"role_key":"lead-tech"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('role_key' => 'lead-tech')),
	'material_role_key_sanitization' => array('raw' => '{"role_key":"Lead Tech"}', 'state' => 'invalid', 'reason' => 'role_key_value', 'overrides' => array()),
	'assignee_zero' => array('raw' => '{"assignee_user_id":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 0)),
	'assignee_positive' => array('raw' => '{"assignee_user_id":88}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 88)),
	'assignee_numeric_string' => array('raw' => '{"assignee_user_id":"88"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('assignee_user_id' => 88)),
	'assignee_negative' => array('raw' => '{"assignee_user_id":-3}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'assignee_float' => array('raw' => '{"assignee_user_id":12.5}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'assignee_boolean' => array('raw' => '{"assignee_user_id":true}', 'state' => 'invalid', 'reason' => 'assignee_user_id_value', 'overrides' => array()),
	'due_offset_negative' => array('raw' => '{"due_offset_minutes":-30}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => -30)),
	'due_offset_zero' => array('raw' => '{"due_offset_minutes":0}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => 0)),
	'due_offset_positive' => array('raw' => '{"due_offset_minutes":45}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('due_offset_minutes' => 45)),
	'invalid_due_offset' => array('raw' => '{"due_offset_minutes":"1.5"}', 'state' => 'invalid', 'reason' => 'due_offset_minutes_value', 'overrides' => array()),
	'unknown_key' => array('raw' => '{"legacy_priority":"low"}', 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
	'numeric_key' => array('raw' => '{"0":"low"}', 'state' => 'invalid', 'reason' => 'numeric_key', 'overrides' => array()),
	'nested_value' => array('raw' => '{"priority":["low"]}', 'state' => 'invalid', 'reason' => 'nested_priority', 'overrides' => array()),
	'list_json' => array('raw' => '["priority","low"]', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'empty_list_json' => array('raw' => '[]', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'scalar_string_json' => array('raw' => '"hello"', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'number_json' => array('raw' => '7', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'boolean_true_json' => array('raw' => 'true', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'boolean_false_json' => array('raw' => 'false', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'json_null' => array('raw' => 'null', 'state' => 'invalid', 'reason' => 'non_object_json', 'overrides' => array()),
	'malformed_json' => array('raw' => '{"priority":', 'state' => 'invalid', 'reason' => 'json_syntax', 'overrides' => array()),
	'truncated_json' => array('raw' => '{"priority":"low"', 'state' => 'invalid', 'reason' => 'json_syntax', 'overrides' => array()),
	'invalid_utf8' => array('raw' => "{\"priority\":\"bad\xB1\x31\"}", 'state' => 'invalid', 'reason' => 'json_utf8', 'overrides' => array()),
	'excessive_depth' => array('raw' => $excessiveDepth, 'state' => 'invalid', 'reason' => 'json_depth', 'overrides' => array()),
	'duplicate_keys' => array('raw' => '{"priority":"low","priority":"high"}', 'state' => 'valid', 'reason' => 'valid', 'overrides' => array('priority' => 'high')),
	'large_object' => array('raw' => (string) wp_json_encode($largeObject), 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
	'unsupported_legacy_value' => array('raw' => '{"required":0}', 'state' => 'invalid', 'reason' => 'unknown_key', 'overrides' => array()),
);

$seenStates = array();
foreach ($decoderCases as $name => $case) {
	$call = vms_test_call_without_warnings(
		static function () use ($case) {
			return vms_tasks_decode_checklist_overrides($case['raw']);
		}
	);
	$result = $call['result'];

	vms_test_assert_same(array(), $call['warnings'], 'Decoder case ' . $name . ' should not emit warnings.');
	vms_test_assert_same($case['state'], $result['state'] ?? null, 'Decoder case ' . $name . ' should return the expected state.');
	vms_test_assert_same($case['reason'], $result['reason'] ?? null, 'Decoder case ' . $name . ' should return the expected reason.');
	vms_test_assert_same($case['overrides'], $result['overrides'] ?? null, 'Decoder case ' . $name . ' should return the expected normalized overrides.');
	vms_test_assert_true(is_string($result['reason'] ?? null), 'Decoder case ' . $name . ' should return a string reason.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '{') === false, 'Decoder case ' . $name . ' reason should not include raw object JSON.');
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '[') === false, 'Decoder case ' . $name . ' reason should not include raw list JSON.');
	$seenStates[(string) ($result['state'] ?? '')] = true;
}
ksort($seenStates);
vms_test_assert_same(array('invalid' => true, 'missing' => true, 'valid' => true), $seenStates, 'Decoder should distinguish missing, valid, and invalid states.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->rows = array(
	array('id' => 1, 'task_template_id' => 10, 'overrides_json' => null),
	array('id' => 2, 'task_template_id' => 11, 'overrides_json' => '{}'),
	array('id' => 3, 'task_template_id' => 12, 'overrides_json' => '{"priority":"low"}'),
	array('id' => 4, 'task_template_id' => 13, 'overrides_json' => '{"priority":"urgent"}'),
);

$checklistRowsCall = vms_test_call_without_warnings(
	static function () {
		return vms_tasks_get_checklist_items(55);
	}
);
$checklistRows = $checklistRowsCall['result'];
vms_test_assert_same(array(), $checklistRowsCall['warnings'], 'Checklist item reader should not emit warnings.');
vms_test_assert_same('missing', $checklistRows[0]['overrides_state'] ?? null, 'Missing overrides_json should surface missing state.');
vms_test_assert_same('valid', $checklistRows[1]['overrides_state'] ?? null, 'Empty object overrides_json should surface valid state.');
vms_test_assert_same('valid', $checklistRows[2]['overrides_state'] ?? null, 'Valid overrides_json should surface valid state.');
vms_test_assert_same('invalid', $checklistRows[3]['overrides_state'] ?? null, 'Invalid overrides_json should surface invalid state.');
vms_test_assert_same(array(), $checklistRows[0]['overrides'] ?? null, 'Missing overrides_json should expose empty overrides.');
vms_test_assert_same(array(), $checklistRows[1]['overrides'] ?? null, 'Valid empty object should expose empty overrides.');
vms_test_assert_same(array('priority' => 'low'), $checklistRows[2]['overrides'] ?? null, 'Valid overrides_json should expose normalized overrides.');
vms_test_assert_same(array(), $checklistRows[3]['overrides'] ?? null, 'Invalid overrides_json should not expose usable overrides.');
vms_test_assert_same('priority_value', $checklistRows[3]['overrides_reason'] ?? null, 'Invalid overrides_json should expose a concise reason code.');

$missingCaseItems = array(
	501 => array(vms_test_item(1, 10, 'missing')),
);
$validEmptyCaseItems = array(
	501 => array(vms_test_item(2, 10, 'valid', array())),
);
$validSubsetItems = array(
	501 => array(vms_test_item(3, 10, 'valid', array('priority' => 'low'))),
);
$validFullItems = array(
	501 => array(vms_test_item(4, 10, 'valid', array(
		'required_default' => 0,
		'priority' => 'low',
		'assignment_mode' => 'person',
		'role_key' => '',
		'assignee_user_id' => 88,
		'due_offset_minutes' => -30,
	))),
);
$scheduledRoleItems = array(
	501 => array(vms_test_item(5, 10, 'valid', array(
		'required_default' => 0,
		'priority' => 'low',
		'assignment_mode' => 'scheduled_role',
		'role_key' => 'lead-tech',
		'assignee_user_id' => 88,
		'due_offset_minutes' => -30,
	))),
);
$invalidOnlyItems = array(
	501 => array(vms_test_item(6, 10, 'invalid', array(), 'priority_value')),
);
$invalidThenValidSameTemplateItems = array(
	501 => array(
		vms_test_item(7, 10, 'invalid', array(), 'priority_value'),
		vms_test_item(8, 10, 'valid', array('priority' => 'low')),
	),
);
$invalidThenValidOtherTemplateItems = array(
	501 => array(
		vms_test_item(9, 10, 'invalid', array(), 'priority_value'),
		vms_test_item(10, 11, 'valid', array('priority' => 'low')),
	),
);
$missingThenValidDuplicateItems = array(
	501 => array(
		vms_test_item(11, 10, 'missing'),
		vms_test_item(12, 10, 'valid', array('priority' => 'low')),
	),
);

$runGenerateCase = static function (array $itemsByChecklist, array $templates, array $options = array()): array {
	vms_test_reset_generate_env($itemsByChecklist, $templates, $options);

	$call = vms_test_call_without_warnings(
		static function () use ($options) {
			return vms_test_generate_for_event(77, array(
				'actor_user_id' => 5,
				'allow_supersede' => $options['allow_supersede'] ?? false,
			));
		}
	);

	return array(
		'call' => $call,
		'trace' => $GLOBALS['vms_test_generate_trace'],
		'post_meta_updates' => $GLOBALS['vms_test_post_meta_updates'],
	);
};

$missingCase = $runGenerateCase($missingCaseItems, array(10 => vms_test_template(10)));
vms_test_assert_same(array(), $missingCase['call']['warnings'], 'Missing-overrides generation should not emit PHP warnings.');
vms_test_assert_same(1, $missingCase['call']['result']['instances_created'] ?? null, 'Missing overrides should still create a task instance.');
vms_test_assert_same('high', $missingCase['trace']['insert_calls'][0]['priority'] ?? null, 'Missing overrides should preserve template priority.');
vms_test_assert_same(1, $missingCase['trace']['insert_calls'][0]['is_required'] ?? null, 'Missing overrides should preserve template requiredness.');
vms_test_assert_same('2026-08-01 19:30:00', $missingCase['trace']['insert_calls'][0]['due_at_local'] ?? null, 'Missing overrides should preserve the template due offset.');
vms_test_assert_same(42, $missingCase['trace']['insert_calls'][0]['assignee_user_id'] ?? null, 'Missing overrides should preserve the template assignee.');

$validEmptyCase = $runGenerateCase($validEmptyCaseItems, array(10 => vms_test_template(10)));
vms_test_assert_same(array(), $validEmptyCase['call']['warnings'], 'Valid empty overrides should not emit PHP warnings.');
vms_test_assert_same(1, $validEmptyCase['call']['result']['instances_created'] ?? null, 'Valid empty overrides should still create a task instance.');
vms_test_assert_same('high', $validEmptyCase['trace']['insert_calls'][0]['priority'] ?? null, 'Valid empty overrides should preserve template priority.');
vms_test_assert_same(1, $validEmptyCase['trace']['insert_calls'][0]['is_required'] ?? null, 'Valid empty overrides should preserve template requiredness.');

$validSubsetCase = $runGenerateCase($validSubsetItems, array(10 => vms_test_template(10)));
vms_test_assert_same('low', $validSubsetCase['trace']['insert_calls'][0]['priority'] ?? null, 'Valid subset overrides should change only the supplied priority.');
vms_test_assert_same(1, $validSubsetCase['trace']['insert_calls'][0]['is_required'] ?? null, 'Valid subset overrides should retain template requiredness.');
vms_test_assert_same(42, $validSubsetCase['trace']['insert_calls'][0]['assignee_user_id'] ?? null, 'Valid subset overrides should retain template assignee.');

$validFullCase = $runGenerateCase($validFullItems, array(10 => vms_test_template(10)));
vms_test_assert_same('low', $validFullCase['trace']['insert_calls'][0]['priority'] ?? null, 'Valid full overrides should preserve priority changes.');
vms_test_assert_same(0, $validFullCase['trace']['insert_calls'][0]['is_required'] ?? null, 'Valid full overrides should preserve requiredness changes.');
vms_test_assert_same('2026-08-01 17:30:00', $validFullCase['trace']['insert_calls'][0]['due_at_local'] ?? null, 'Valid full overrides should preserve due-offset changes.');
vms_test_assert_same(88, $validFullCase['trace']['insert_calls'][0]['assignee_user_id'] ?? null, 'Valid full overrides should preserve person-assignee changes.');
vms_test_assert_same('2026-08-01 17:30:00', $validFullCase['trace']['existing_calls'][0]['due_at_local'] ?? null, 'Valid due offsets should continue participating in existing-instance identity.');

$scheduledRoleCase = $runGenerateCase($scheduledRoleItems, array(10 => vms_test_template(10)));
vms_test_assert_same(1, $scheduledRoleCase['call']['result']['assignment_resolutions_applied'] ?? null, 'Scheduled-role overrides should still resolve assignments.');
vms_test_assert_same('scheduled_role', $scheduledRoleCase['trace']['insert_calls'][0]['assignment_mode'] ?? null, 'Scheduled-role assignment mode should be preserved.');
vms_test_assert_same(77, $scheduledRoleCase['trace']['insert_calls'][0]['assignee_user_id'] ?? null, 'Scheduled-role overrides should preserve resolved assignee behavior.');

$invalidOnlyCase = $runGenerateCase($invalidOnlyItems, array(10 => vms_test_template(10)));
vms_test_assert_same(array(), $invalidOnlyCase['call']['warnings'], 'Invalid-overrides generation should not emit PHP warnings.');
vms_test_assert_same(0, $invalidOnlyCase['call']['result']['instances_created'] ?? null, 'Invalid overrides should skip the affected checklist row.');
vms_test_assert_same(0, $invalidOnlyCase['call']['result']['duplicate_suppressed'] ?? null, 'Invalid overrides should not be counted as duplicates.');
vms_test_assert_same(1, count($invalidOnlyCase['call']['result']['warnings'] ?? array()), 'Invalid overrides should emit one bounded warning.');
vms_test_assert_true(strpos((string) ($invalidOnlyCase['call']['result']['warnings'][0] ?? ''), 'priority_value') !== false, 'Invalid-overrides warning should include the concise reason code.');
vms_test_assert_true(strpos((string) ($invalidOnlyCase['call']['result']['warnings'][0] ?? ''), '{') === false, 'Invalid-overrides warning should not include raw object JSON.');
vms_test_assert_true(strpos((string) ($invalidOnlyCase['call']['result']['warnings'][0] ?? ''), '[') === false, 'Invalid-overrides warning should not include raw list JSON.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['merge_calls'], 'Invalid overrides should not reach template merging.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['due_calls'], 'Invalid overrides should not reach due-date calculation.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['assignment_calls'], 'Invalid overrides should not reach assignment resolution.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['existing_calls'], 'Invalid overrides should not reach existing-instance lookup.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['insert_calls'], 'Invalid overrides should not reach task insertion.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['update_calls'], 'Invalid overrides should not reach assignment updates.');
vms_test_assert_same(array(), $invalidOnlyCase['trace']['supersede_calls'], 'Invalid overrides should not reach supersede logic.');

$invalidThenValidSameTemplateCase = $runGenerateCase(
	$invalidThenValidSameTemplateItems,
	array(10 => vms_test_template(10))
);
vms_test_assert_same(1, $invalidThenValidSameTemplateCase['call']['result']['instances_created'] ?? null, 'A later valid duplicate-template row should remain eligible after an invalid row.');
vms_test_assert_same(0, $invalidThenValidSameTemplateCase['call']['result']['duplicate_suppressed'] ?? null, 'Invalid rows should not mark template IDs as seen.');
vms_test_assert_same(1, count($invalidThenValidSameTemplateCase['call']['result']['warnings'] ?? array()), 'Only the invalid row should emit a warning.');
vms_test_assert_same(1, count($invalidThenValidSameTemplateCase['trace']['insert_calls']), 'A later valid duplicate-template row should still insert one task.');

$invalidThenValidOtherTemplateCase = $runGenerateCase(
	$invalidThenValidOtherTemplateItems,
	array(
		10 => vms_test_template(10),
		11 => vms_test_template(11, array('priority' => 'normal', 'assignee_user_id' => 33)),
	)
);
vms_test_assert_same(1, $invalidThenValidOtherTemplateCase['call']['result']['instances_created'] ?? null, 'One invalid row should not stop later unrelated rows.');
vms_test_assert_same(11, $invalidThenValidOtherTemplateCase['trace']['insert_calls'][0]['task_template_id'] ?? null, 'Later unrelated valid rows should still generate.');

$missingThenValidDuplicateCase = $runGenerateCase(
	$missingThenValidDuplicateItems,
	array(10 => vms_test_template(10))
);
vms_test_assert_same(1, $missingThenValidDuplicateCase['call']['result']['instances_created'] ?? null, 'Missing overrides should continue generating exactly one task instance.');
vms_test_assert_same(1, $missingThenValidDuplicateCase['call']['result']['duplicate_suppressed'] ?? null, 'Valid and missing duplicate suppression should remain unchanged.');

$sequentialInvalid = $runGenerateCase($invalidOnlyItems, array(10 => vms_test_template(10)));
$sequentialMissing = $runGenerateCase($missingCaseItems, array(10 => vms_test_template(10)));
$sequentialValid = $runGenerateCase($validFullItems, array(10 => vms_test_template(10)));
vms_test_assert_same(0, $sequentialInvalid['call']['result']['instances_created'] ?? null, 'Sequential invalid case should remain isolated.');
vms_test_assert_same(1, $sequentialMissing['call']['result']['instances_created'] ?? null, 'Sequential missing case should not inherit invalid state.');
vms_test_assert_same('2026-08-01 17:30:00', $sequentialValid['trace']['insert_calls'][0]['due_at_local'] ?? null, 'Sequential valid case should not lose override state.');

fwrite(STDOUT, "staff tasks overrides json remediation: PASS\n");
