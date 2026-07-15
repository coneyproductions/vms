<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$secondaryAssignmentsPath = $pluginRoot . '/includes/core/secondary-vendor-assignments.php';
$secondaryVendorsPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/secondary-vendors.php';
$secondaryVendorAssetPath = $pluginRoot . '/assets/js/vms-event-plan-secondary-vendors.js';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

try {
	$eventPlansSource = $readFile($eventPlansPath);
	$coreSource = $readFile($secondaryAssignmentsPath);
	$secondaryVendorsPartialSource = $readFile($secondaryVendorsPartialPath);
	$secondaryVendorAssetSource = $readFile($secondaryVendorAssetPath);
	$shellAssetSource = $readFile($shellAssetPath);

	$assert(strpos($eventPlansSource, "add_action('wp_ajax_vms_save_event_plan_secondary_vendors', array(\$this, 'ajax_save_event_plan_secondary_vendors'));") !== false, 'Secondary Vendors save response should retain the authenticated save AJAX hook.');
	$assert(strpos($eventPlansSource, 'wp_ajax_nopriv_vms_save_event_plan_secondary_vendors') === false, 'Secondary Vendors save AJAX endpoint should not expose an unauthenticated hook.');
	$assert(strpos($eventPlansSource, '$post_id = isset($_POST[\'post_id\']) ? absint($_POST[\'post_id\']) : 0;') !== false, 'Secondary Vendors save handler should continue to normalize post_id with absint().');
	$assert(strpos($eventPlansSource, "check_ajax_referer('vms_event_plan_secondary_vendors_save', 'nonce');") !== false, 'Secondary Vendors save handler should keep the exact nonce action and request field.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);") !== false, 'Secondary Vendors save handler should retain the exact invalid-plan response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Not allowed'), 403);") !== false, 'Secondary Vendors save handler should retain the exact capability error response.');
	$assert(strpos($eventPlansSource, '? vms_event_plan_save_secondary_vendors_module($post_id, (array) $_POST)') !== false, 'Secondary Vendors save handler should continue to route mutation through the existing module save helper.');
	$assert(strpos($eventPlansSource, 'build_event_plan_secondary_vendors_save_response_payload') !== false, 'Secondary Vendors save response should route through the save-specific payload builder.');
	$assert(strpos($eventPlansSource, 'render_event_plan_secondary_vendors_save_response_html') !== false, 'Secondary Vendors save response should route through the save-specific renderer.');
	$assert(strpos($eventPlansSource, "capture_event_plan_partial('secondary-vendors'") !== false, 'Legacy Secondary Vendors partial capture sink should remain source-visible for the old helper / initial render paths.');
	$assert(strpos($secondaryVendorsPartialSource, '<script type="application/json" data-vms-secondary-config><?php echo wp_json_encode($secondary_config); ?></script>') !== false, 'Legacy Secondary Vendors partial should retain the exact inert config script tag.');

	$saveMethodMatched = preg_match(
		'~public function ajax_save_event_plan_secondary_vendors\(\): void\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function event_plan_ticket_ui_override_meta_keys~sm',
		$eventPlansSource,
		$saveMethodMatch
	);
	$assert($saveMethodMatched === 1, 'Failed to isolate ajax_save_event_plan_secondary_vendors() source.');
	$saveMethodSource = (string) $saveMethodMatch['body'];
	preg_match_all('~\\$_POST\\[\'([^\']+)\'\\]~', $saveMethodSource, $saveRequestFieldMatches);
	$saveRequestFields = array_values(array_unique($saveRequestFieldMatches[1] ?? array()));
	sort($saveRequestFields);
	$assert($saveRequestFields === array('post_id'), 'Secondary Vendors save handler should continue to read only post_id directly from $_POST before delegating the full request to the mutation helper.');
	$assert(substr_count($saveMethodSource, 'current_user_can(') === 1, 'Secondary Vendors save handler should retain exactly one capability check.');
	$assert(substr_count($saveMethodSource, 'check_ajax_referer(') === 1, 'Secondary Vendors save handler should retain exactly one nonce check.');
	$assert(substr_count($saveMethodSource, 'wp_send_json_error(') === 3, 'Secondary Vendors save handler should retain the exact three explicit JSON error writes.');
	$assert(substr_count($saveMethodSource, 'wp_send_json_success(') === 1, 'Secondary Vendors save handler should write exactly one success response.');
	$assert(substr_count($saveMethodSource, 'build_event_plan_secondary_vendors_save_response_payload(') === 1, 'Secondary Vendors save handler should invoke the save-specific payload builder exactly once.');
	$assert(strpos($saveMethodSource, '$this->get_event_plan_secondary_vendors_module_payload(') === false, 'Secondary Vendors save handler should no longer obtain HTML from the old shared payload helper.');
	$assert(strpos($saveMethodSource, 'render_event_plan_secondary_vendors_lazy_load_html(') === false, 'Secondary Vendors save handler should not invoke the accepted lazy-load renderer.');
	foreach (array(
		"'html' => (string) (\$payload['html'] ?? '')",
		"'has_data' => !empty(\$payload['has_data']) ? 1 : 0",
		"'summary_meta' => (string) (\$payload['summary_meta'] ?? '')",
		"'module_owner' => (string) (\$payload['module_owner'] ?? \$this->get_event_plan_section_module_owner('secondary_vendors'))",
		"'changed' => !empty(\$result['changed']) ? 1 : 0",
		"'dirty_fields' => array_values(array_unique(array_map('sanitize_key', (array) (\$result['dirty_fields'] ?? array()))))",
		"'repair_reasons' => array_values(array_unique(array_map('sanitize_key', (array) (\$result['repair_reasons'] ?? array()))))",
		"'queued_calendar_maintenance' => !empty(\$result['queued_calendar_maintenance']) ? 1 : 0",
		"'message' => !empty(\$result['changed'])",
	) as $requiredSuccessMarker) {
		$assert(strpos($saveMethodSource, $requiredSuccessMarker) !== false, 'Secondary Vendors save handler should retain the exact success-response marker: ' . $requiredSuccessMarker);
	}
	$assert(strpos($saveMethodSource, "'code' => sanitize_key(\$error_code)") !== false, 'Secondary Vendors save handler should preserve the error-code response key.');
	$assert(strpos($saveMethodSource, "'message' => sanitize_text_field((string) \$result->get_error_message())") !== false, 'Secondary Vendors save handler should preserve the sanitized mutation-error message response key.');
	$assert(strpos($saveMethodSource, '$error_code === \'vms_secondary_vendor_over_capacity\' ? 400 : 500') !== false, 'Secondary Vendors save handler should preserve the over-capacity vs internal-error HTTP status split.');

	$oldHelperMatched = preg_match(
		'~private function get_event_plan_secondary_vendors_module_payload\(int \$post_id\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function build_event_plan_secondary_vendors_save_response_payload~sm',
		$eventPlansSource,
		$oldHelperMatch
	);
	$assert($oldHelperMatched === 1, 'Failed to isolate the legacy shared Secondary Vendors payload helper.');
	$oldHelperSource = (string) $oldHelperMatch['body'];
	$assert(strpos($oldHelperSource, "capture_event_plan_partial('secondary-vendors'") !== false, 'Legacy shared Secondary Vendors payload helper should retain the old captured-partial sink for evidence and non-save paths.');

	$saveBuilderMatched = preg_match(
		'~private function build_event_plan_secondary_vendors_save_response_payload\(int \$post_id\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function build_event_plan_secondary_vendors_save_response_context~sm',
		$eventPlansSource,
		$saveBuilderMatch
	);
	$assert($saveBuilderMatched === 1, 'Failed to isolate the save-specific Secondary Vendors response payload builder.');
	$saveBuilderSource = (string) $saveBuilderMatch['body'];
	$assert(substr_count($saveBuilderSource, 'get_event_plan_meta_bundle(') === 1, 'Secondary Vendors save response builder should resolve the Event Plan bundle exactly once.');
	$assert(substr_count($saveBuilderSource, 'get_posts(') === 1, 'Secondary Vendors save response builder should resolve the vendor list exactly once.');
	$assert(substr_count($saveBuilderSource, 'get_event_plan_secondary_vendor_boot_summary(') === 1, 'Secondary Vendors save response builder should resolve the secondary-vendor boot summary exactly once.');
	$assert(substr_count($saveBuilderSource, 'vms_event_plan_collect_vendor_category_snapshot(') === 1, 'Secondary Vendors save response builder should resolve the vendor-category snapshot exactly once.');
	$assert(substr_count($saveBuilderSource, 'build_event_plan_secondary_vendors_save_response_context(') === 1, 'Secondary Vendors save response builder should normalize the save-specific context exactly once.');
	$assert(substr_count($saveBuilderSource, 'render_event_plan_secondary_vendors_save_response_html(') === 1, 'Secondary Vendors save response builder should render the save-specific HTML exactly once.');
	$assert(strpos($saveBuilderSource, 'capture_event_plan_partial(') === false, 'Secondary Vendors save response builder should not route through a captured partial.');
	$assert(strpos($saveBuilderSource, 'render_event_plan_secondary_vendors_lazy_load_html(') === false, 'Secondary Vendors save response builder should remain separate from the accepted lazy-load renderer.');

	$saveRendererGroupMatched = preg_match(
		'~private function render_event_plan_secondary_vendors_save_response_html\(array \$context\): string\s*\{(?P<body>.*?private function render_event_plan_secondary_vendors_save_response_hidden_attr\(bool \$hidden\): string\s*\{.*?^\s*\})\s*\n\s*private function build_event_plan_secondary_vendors_lazy_load_response_payload~sm',
		$eventPlansSource,
		$saveRendererGroupMatch
	);
	$assert($saveRendererGroupMatched === 1, 'Failed to isolate the save-specific Secondary Vendors renderer group source.');
	$saveRendererGroupSource = (string) $saveRendererGroupMatch['body'];
	foreach (array(
		'render_event_plan_secondary_vendors_save_response_group_html',
		'render_event_plan_secondary_vendors_save_response_row_html',
		'render_event_plan_secondary_vendors_save_response_status_badges_html',
		'render_event_plan_secondary_vendors_save_response_group_template_html',
		'render_event_plan_secondary_vendors_save_response_row_template_html',
		'render_event_plan_secondary_vendors_save_response_vendor_category_notice_html',
	) as $requiredRendererHelper) {
		$assert(strpos($saveRendererGroupSource, $requiredRendererHelper) !== false, 'Secondary Vendors save renderer group should route through the dedicated helper: ' . $requiredRendererHelper);
	}
	foreach (array(
		'capture_event_plan_partial(',
		'get_post(',
		'get_post_meta(',
		'get_posts(',
		'update_post_meta(',
		'delete_post_meta(',
		'vms_event_plan_collect_vendor_category_snapshot(',
		'render_event_plan_secondary_vendors_lazy_load_html(',
		'wp_kses_post(',
		'wp_kses(',
	) as $forbiddenRendererToken) {
		$assert(strpos($saveRendererGroupSource, $forbiddenRendererToken) === false, 'Secondary Vendors save renderer group should not perform provider/database reads or broad safe-HTML filtering: ' . $forbiddenRendererToken);
	}
	$assert(strpos($saveRendererGroupSource, "wp_json_encode((array) (\$context['secondary_config'] ?? array()))") !== false, 'Secondary Vendors save renderer should preserve the safe wp_json_encode() config encoding call.');
	$assert(strpos($saveRendererGroupSource, '<SCRIPT data-vms-secondary-config type="application/json">') !== false, 'Secondary Vendors save renderer should emit the exact inert config element for the unchanged initializer contract.');

	$secondaryBranchMatched = preg_match(
		'~if \(\$section === \'secondary_vendors\'\) \{(?P<body>.*?)^\s*\}\s*\n\s*if \(\$section === \'readiness_details\'\) \{~sm',
		$eventPlansSource,
		$secondaryBranchMatch
	);
	$assert($secondaryBranchMatched === 1, 'Failed to isolate the accepted Secondary Vendors lazy-load branch.');
	$secondaryBranchSource = (string) $secondaryBranchMatch['body'];
	$assert(substr_count($secondaryBranchSource, 'build_event_plan_secondary_vendors_lazy_load_response_payload(') === 1, 'Accepted Secondary Vendors lazy-load branch should remain on the lazy-load-only payload builder.');
	$assert(strpos($secondaryBranchSource, 'build_event_plan_secondary_vendors_save_response_payload(') === false, 'Accepted Secondary Vendors lazy-load branch should not reuse the save response builder.');

	$resolverMatched = preg_match(
		'~function vms_event_plan_resolve_secondary_vendor_submission\(int \$post_id, array \$request\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*}\s*\n\s*if \(!function_exists\(\'vms_event_plan_get_secondary_vendor_state\'\)\)~sm',
		$coreSource,
		$resolverMatch
	);
	$assert($resolverMatched === 1, 'Failed to isolate vms_event_plan_resolve_secondary_vendor_submission() source.');
	$resolverSource = (string) $resolverMatch['body'];
	foreach (array(
		'vms_secondary_vendor_assignments',
		'vms_secondary_vendor_type',
		'vms_secondary_vendor_ids',
		'vms_clear_secondary_vendors',
	) as $requiredRequestField) {
		$assert(strpos($resolverSource, $requiredRequestField) !== false, 'Secondary Vendors submission resolver should continue to consume the request field: ' . $requiredRequestField);
	}
	$assert(strpos($resolverSource, 'wp_unslash($raw_assignments)') !== false, 'Secondary Vendors submission resolver should continue to unslash posted assignment arrays.');
	$assert(strpos($resolverSource, "wp_unslash((string) \$request['vms_secondary_vendor_type'])") !== false, 'Secondary Vendors submission resolver should continue to unslash and normalize the posted type field.');
	$assert(strpos($resolverSource, 'wp_unslash($raw_secondary)') !== false, 'Secondary Vendors submission resolver should continue to unslash posted secondary IDs.');
	$assert(strpos($resolverSource, 'vms_event_plan_normalize_secondary_vendor_assignment_map(') !== false, 'Secondary Vendors submission resolver should continue to normalize assignment arrays through the shared canonical normalizer.');
	$assert(strpos($resolverSource, 'vms_event_plan_normalize_secondary_vendor_type_slug(') !== false, 'Secondary Vendors submission resolver should continue to normalize posted type slugs.');
	$assert(strpos($resolverSource, "array_map('absint', (array) \$raw_secondary)") !== false, 'Secondary Vendors submission resolver should continue to normalize posted secondary IDs with absint().');

	$normalizeMapMatched = preg_match(
		'~function vms_event_plan_normalize_secondary_vendor_assignment_map\(int \$post_id, array \$assignments, int \$primary_vendor_id = 0, array \$args = array\(\)\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*}\s*\n\s*if \(!function_exists\(\'vms_event_plan_secondary_vendor_over_capacity_rows\'\)\)~sm',
		$coreSource,
		$normalizeMapMatch
	);
	$assert($normalizeMapMatched === 1, 'Failed to isolate vms_event_plan_normalize_secondary_vendor_assignment_map() source.');
	$normalizeMapSource = (string) $normalizeMapMatch['body'];
	foreach (array(
		"array('type_slug', 'vendor_type', 'type', 'slug')",
		"array('allow_over_capacity', 'over_capacity_override', 'capacity_override')",
		"array('needed_slots', 'target_slots', 'needed', 'target')",
		"row['vendor_ids'] ?? (\$row['secondary_ids'] ?? array())",
		"array_key_exists('open_for_dispatch', \$row)",
		'vms_event_plan_parse_secondary_vendor_over_capacity_override($row[\'open_for_dispatch\'])',
		'vms_event_plan_parse_secondary_vendor_over_capacity_override($row[$override_key])',
		"array_key_exists('slot_limit', \$row)",
		"array_key_exists('capacity', \$row)",
		"sanitize_key((string) (\$row['mode'] ?? ''))",
	) as $requiredNormalizerMarker) {
		$assert(strpos($normalizeMapSource, $requiredNormalizerMarker) !== false, 'Secondary Vendors assignment normalizer should preserve the marker: ' . $requiredNormalizerMarker);
	}

	$saveMutationMatched = preg_match(
		'~function vms_event_plan_save_secondary_vendors_module\(int \$post_id, array \$request\)\s*\{(?P<body>.*?)^\s*\}\s*\n\s*}\s*\n\s*if \(!function_exists\(\'vms_event_plan_secondary_vendor_group_available_slots\'\)\)~sm',
		$coreSource,
		$saveMutationMatch
	);
	$assert($saveMutationMatched === 1, 'Failed to isolate vms_event_plan_save_secondary_vendors_module() source.');
	$saveMutationSource = (string) $saveMutationMatch['body'];
	foreach (array(
		'vms_event_plan_get_secondary_vendor_state($post_id)',
		'vms_event_plan_resolve_secondary_vendor_submission($post_id, $request)',
		'vms_event_plan_validate_secondary_vendor_assignment_capacity($proposed_assignments)',
		'vms_event_plan_secondary_vendor_state_diff_fields(',
		'vms_event_plan_secondary_vendor_rebuild_repair_reasons($post_id, $current_vendor_state)',
		'vms_event_plan_write_secondary_vendor_assignments($post_id, $proposed_assignments)',
		'vms_event_plan_update_vendor_category_snapshot($post_id)',
		"vms_event_plan_schedule_calendar_maintenance(\$post_id, \$linked_tec_event_id, 'vendor_category_sync')",
		'clean_post_cache($post_id)',
		"'changed' => \$changed",
		"'dirty_fields' => array_values(array_unique(array_map('sanitize_key', (array) \$vendor_dirty_fields)))",
		"'repair_reasons' => array_values(array_unique(array_map('sanitize_key', (array) \$repair_reasons)))",
		"'queued_calendar_maintenance' => \$queued_calendar_maintenance",
	) as $requiredMutationMarker) {
		$assert(strpos($saveMutationSource, $requiredMutationMarker) !== false, 'Secondary Vendors mutation helper should preserve the marker: ' . $requiredMutationMarker);
	}

	$capacityValidationMatched = preg_match(
		'~function vms_event_plan_validate_secondary_vendor_assignment_capacity\(array \$assignments\)\s*\{(?P<body>.*?)^\s*\}\s*\n\s*}\s*\n\s*if \(!function_exists\(\'vms_event_plan_get_secondary_vendor_flat_ids_from_assignments\'\)\)~sm',
		$coreSource,
		$capacityValidationMatch
	);
	$assert($capacityValidationMatched === 1, 'Failed to isolate vms_event_plan_validate_secondary_vendor_assignment_capacity() source.');
	$capacityValidationSource = (string) $capacityValidationMatch['body'];
	$assert(strpos($capacityValidationSource, "'vms_secondary_vendor_over_capacity'") !== false, 'Secondary Vendors capacity validator should retain the over-capacity error code.');
	$assert(strpos($capacityValidationSource, 'Additional Vendors cannot be saved over capacity unless you check the over-capacity override for each affected group: %s.') !== false, 'Secondary Vendors capacity validator should retain the over-capacity error message.');

	foreach (array(
		"params.set('action', 'vms_save_event_plan_secondary_vendors');",
		"params.set('post_id', String(postId));",
		"params.set('nonce', saveNonce);",
		"params.set('vms_clear_secondary_vendors', clearInput ? String(clearInput.value || '0') : '0');",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][type_slug]`, group.typeSlug);",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][mode]`, group.mode);",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][slot_limit]`, group.slotLimit);",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][allow_over_capacity]`, '1');",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][needed_slots]`, group.neededSlots);",
		"params.set(`vms_secondary_vendor_assignments[\${groupIndex}][open_for_dispatch]`, group.openForDispatch ? '1' : '0');",
		"params.append(`vms_secondary_vendor_assignments[\${groupIndex}][vendor_ids][]`, vendorId);",
		'body.innerHTML = payload.data.html;',
		"collapsibleSection.dataset.hasData = payload.data.has_data ? '1' : '0';",
		'meta.textContent = payload.data.summary_meta;',
		'payload.data.message',
		'payload.data.changed',
		'window.vmsEventPlanInitSecondaryVendors(body);',
	) as $requiredSaveConsumerMarker) {
		$assert(strpos($secondaryVendorAssetSource, $requiredSaveConsumerMarker) !== false, 'Dedicated Secondary Vendors asset should retain the save-response consumer marker: ' . $requiredSaveConsumerMarker);
	}
	$assert(strpos($secondaryVendorAssetSource, 'payload.data.module_owner') === false, 'Dedicated Secondary Vendors asset should not start consuming module_owner from the save response.');
	$assert(strpos($secondaryVendorAssetSource, 'payload.data.dirty_fields') === false, 'Dedicated Secondary Vendors asset should not start consuming dirty_fields from the save response.');
	$assert(strpos($secondaryVendorAssetSource, 'payload.data.repair_reasons') === false, 'Dedicated Secondary Vendors asset should not start consuming repair_reasons from the save response.');
	$assert(strpos($secondaryVendorAssetSource, 'payload.data.queued_calendar_maintenance') === false, 'Dedicated Secondary Vendors asset should not start consuming queued_calendar_maintenance from the save response.');
	$assert(strpos($secondaryVendorAssetSource, 'vms_secondary_vendors_module_detached') === false, 'Dedicated Secondary Vendors asset should not start sending the detached-module marker in the AJAX save request.');
	$assert(strpos($shellAssetSource, 'window.vmsEventPlanInitSecondaryVendors(body);') !== false, 'Accepted shell lazy-load consumer should still reinitialize Secondary Vendors after lazy replacement.');

	if (!defined('ABSPATH')) {
		define('ABSPATH', $pluginRoot . '/');
	}
	if (!function_exists('add_action')) {
		function add_action(...$args): void
		{
			unset($args);
		}
	}
	if (!function_exists('add_filter')) {
		function add_filter(...$args): void
		{
			unset($args);
		}
	}
	if (!function_exists('is_admin')) {
		function is_admin(): bool
		{
			return false;
		}
	}
	if (!function_exists('__')) {
		function __(string $text, string $domain = ''): string
		{
			unset($domain);
			return $text;
		}
	}
	if (!function_exists('_n')) {
		function _n(string $single, string $plural, int $number, string $domain = ''): string
		{
			unset($domain);
			return $number === 1 ? $single : $plural;
		}
	}
	if (!function_exists('esc_html__')) {
		function esc_html__(string $text, string $domain = ''): string
		{
			unset($domain);
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_html_e')) {
		function esc_html_e(string $text, string $domain = ''): void
		{
			unset($domain);
			echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_html')) {
		function esc_html(string $text): string
		{
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_attr')) {
		function esc_attr(string $text): string
		{
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_attr__')) {
		function esc_attr__(string $text, string $domain = ''): string
		{
			unset($domain);
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_attr_e')) {
		function esc_attr_e(string $text, string $domain = ''): void
		{
			unset($domain);
			echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('esc_url')) {
		function esc_url(string $url): string
		{
			return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
	}
	if (!function_exists('absint')) {
		function absint($value): int
		{
			return max(0, (int) $value);
		}
	}
	if (!function_exists('selected')) {
		function selected($selected, $current = true, bool $display = true): string
		{
			$result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
			if ($display) {
				echo $result;
			}
			return $result;
		}
	}
	if (!function_exists('checked')) {
		function checked($checked, $current = true, bool $display = true): string
		{
			$result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
			if ($display) {
				echo $result;
			}
			return $result;
		}
	}
	if (!function_exists('disabled')) {
		function disabled($disabled, $current = true, bool $display = true): string
		{
			$result = ((bool) $disabled === (bool) $current) ? ' disabled="disabled"' : '';
			if ($display) {
				echo $result;
			}
			return $result;
		}
	}
	if (!function_exists('sanitize_key')) {
		function sanitize_key(string $key): string
		{
			$key = strtolower($key);
			return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
		}
	}
	if (!function_exists('sanitize_html_class')) {
		function sanitize_html_class(string $class): string
		{
			return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $class);
		}
	}
	if (!function_exists('sanitize_title')) {
		function sanitize_title(string $title): string
		{
			$title = strtolower(trim($title));
			$title = (string) preg_replace('/[^a-z0-9_\-]+/', '-', $title);
			return trim($title, '-');
		}
	}
	if (!function_exists('admin_url')) {
		function admin_url(string $path = ''): string
		{
			return 'https://example.com/wp-admin/' . ltrim($path, '/');
		}
	}
	if (!function_exists('add_query_arg')) {
		function add_query_arg(array $args, string $url): string
		{
			$query = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
			if ($query === '') {
				return $url;
			}

			return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
		}
	}
	if (!function_exists('wp_create_nonce')) {
		function wp_create_nonce(string $action = ''): string
		{
			return 'nonce-' . $action;
		}
	}
	if (!function_exists('wp_json_encode')) {
		function wp_json_encode($value, int $flags = 0, int $depth = 512): string
		{
			return (string) json_encode($value, $flags, $depth);
		}
	}
	if (!function_exists('vms_event_plan_parse_secondary_vendor_over_capacity_override')) {
		function vms_event_plan_parse_secondary_vendor_over_capacity_override($value): bool
		{
			if (is_bool($value)) {
				return $value;
			}
			if (is_int($value) || is_float($value)) {
				return (int) $value === 1;
			}

			$value = strtolower(trim((string) $value));
			return in_array($value, array('1', 'true', 'yes', 'on'), true);
		}
	}
	if (!function_exists('vms_event_plan_additional_vendor_type_options')) {
		function vms_event_plan_additional_vendor_type_options(): array
		{
			return array(
				'food_vendor' => 'Food Vendor',
				'market_vendor' => 'Market Vendor',
			);
		}
	}
	if (!function_exists('vms_event_plan_secondary_vendor_default_mode')) {
		function vms_event_plan_secondary_vendor_default_mode(string $type_slug): string
		{
			return $type_slug === 'market_vendor' ? 'market' : 'standard';
		}
	}
	if (!function_exists('vms_event_plan_secondary_vendor_default_slot_limit')) {
		function vms_event_plan_secondary_vendor_default_slot_limit(string $type_slug, string $mode): int
		{
			unset($mode);
			return $type_slug === 'market_vendor' ? 4 : 1;
		}
	}
	if (!function_exists('vms_help_is_enabled')) {
		function vms_help_is_enabled(): bool
		{
			return true;
		}
	}
	if (!function_exists('vms_help_icon')) {
		function vms_help_icon(string $text): void
		{
			unset($text);
		}
	}
	if (!class_exists('WP_Term')) {
		class WP_Term
		{
			public $term_id;
			public $name;
			public $slug;

			public function __construct(int $term_id = 0, string $name = '', string $slug = '')
			{
				$this->term_id = $term_id;
				$this->name = $name;
				$this->slug = $slug;
			}
		}
	}
	if (!function_exists('get_post_meta')) {
		function get_post_meta(int $post_id, string $meta_key, bool $single = false)
		{
			unset($single);
			$bandIds = isset($GLOBALS['vms_secondary_vendor_test_band_ids']) && is_array($GLOBALS['vms_secondary_vendor_test_band_ids'])
				? $GLOBALS['vms_secondary_vendor_test_band_ids']
				: array();
			if ($meta_key === '_vms_band_vendor_id') {
				return $bandIds[$post_id] ?? 0;
			}

			return '';
		}
	}
	if (!function_exists('vms_event_plan_get_secondary_vendor_assignments')) {
		function vms_event_plan_get_secondary_vendor_assignments(int $post_id, array $args = array()): array
		{
			unset($args);
			$assignments = isset($GLOBALS['vms_secondary_vendor_test_secondary_assignments']) && is_array($GLOBALS['vms_secondary_vendor_test_secondary_assignments'])
				? $GLOBALS['vms_secondary_vendor_test_secondary_assignments']
				: array();
			return isset($assignments[$post_id]) && is_array($assignments[$post_id]) ? $assignments[$post_id] : array();
		}
	}
	if (!function_exists('get_post_type')) {
		function get_post_type(int $post_id): string
		{
			$postTypes = isset($GLOBALS['vms_secondary_vendor_test_post_types']) && is_array($GLOBALS['vms_secondary_vendor_test_post_types'])
				? $GLOBALS['vms_secondary_vendor_test_post_types']
				: array();
			return (string) ($postTypes[$post_id] ?? '');
		}
	}
	if (!function_exists('vms_vendor_primary_type_slug')) {
		function vms_vendor_primary_type_slug(int $vendor_id): string
		{
			$typeSlugs = isset($GLOBALS['vms_secondary_vendor_test_primary_type_slugs']) && is_array($GLOBALS['vms_secondary_vendor_test_primary_type_slugs'])
				? $GLOBALS['vms_secondary_vendor_test_primary_type_slugs']
				: array();
			return (string) ($typeSlugs[$vendor_id] ?? '');
		}
	}
	if (!function_exists('vms_vendor_type_label')) {
		function vms_vendor_type_label(string $type_slug): string
		{
			$labels = array(
				'food_vendor' => 'Food Vendor',
				'market_vendor' => 'Market Vendor',
				'band' => 'Primary Vendor',
			);
			return (string) ($labels[$type_slug] ?? ucwords(str_replace(array('_', '-'), ' ', $type_slug)));
		}
	}
	if (!function_exists('vms_vendor_category_label_for_type')) {
		function vms_vendor_category_label_for_type(string $type_slug): string
		{
			$labels = isset($GLOBALS['vms_secondary_vendor_test_category_labels']) && is_array($GLOBALS['vms_secondary_vendor_test_category_labels'])
				? $GLOBALS['vms_secondary_vendor_test_category_labels']
				: array();
			return (string) ($labels[$type_slug] ?? 'Category');
		}
	}
	if (!function_exists('vms_vendor_get_category_terms')) {
		function vms_vendor_get_category_terms(int $vendor_id): array
		{
			$terms = isset($GLOBALS['vms_secondary_vendor_test_terms']) && is_array($GLOBALS['vms_secondary_vendor_test_terms'])
				? $GLOBALS['vms_secondary_vendor_test_terms']
				: array();
			return isset($terms[$vendor_id]) && is_array($terms[$vendor_id]) ? $terms[$vendor_id] : array();
		}
	}
	if (!function_exists('is_wp_error')) {
		function is_wp_error($value): bool
		{
			return false;
		}
	}
	if (!function_exists('get_the_title')) {
		function get_the_title(int $post_id): string
		{
			$titles = isset($GLOBALS['vms_secondary_vendor_test_titles']) && is_array($GLOBALS['vms_secondary_vendor_test_titles'])
				? $GLOBALS['vms_secondary_vendor_test_titles']
				: array();
			return (string) ($titles[$post_id] ?? '');
		}
	}

	require_once $eventPlansPath;

	$controller = new VMS_Admin_Event_Plans();
	$invokePrivate = static function (object $object, string $method, array $args = array()) {
		$reflection = new ReflectionMethod($object, $method);
		return $reflection->invokeArgs($object, $args);
	};

	$loadFragment = static function (string $html, string $context) use ($assert): array {
		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$loaded = $doc->loadHTML('<!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>');
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$assert($loaded, 'Failed to parse ' . $context . ' fragment.');
		$xpath = new DOMXPath($doc);
		$root = $xpath->query('//*[@id="root"]')->item(0);
		$assert($root instanceof DOMElement, 'Failed to load ' . $context . ' root node.');
		return array($doc, $xpath, $root);
	};

	$describeFragment = static function (string $html, string $context) use ($loadFragment): array {
		list($doc, $xpath) = $loadFragment($html, $context);
		unset($doc);
		$signature = array();
		$elements = $xpath->query('//*[@id="root"]//*');
		foreach ($elements as $element) {
			if (!$element instanceof DOMElement) {
				continue;
			}

			$attrs = array();
			foreach ($element->attributes as $attribute) {
				if (!$attribute instanceof DOMAttr) {
					continue;
				}
				$attrs[$attribute->name] = $attribute->value;
			}
			ksort($attrs);

			$directTextParts = array();
			$childTags = array();
			foreach ($element->childNodes as $childNode) {
				if ($childNode instanceof DOMElement) {
					$childTags[] = strtolower($childNode->tagName);
					continue;
				}
				if ($childNode instanceof DOMText) {
					$directTextParts[] = preg_replace('/\s+/', ' ', $childNode->nodeValue ?? '');
				}
			}
			$directText = preg_replace('/\s+/', ' ', implode(' ', $directTextParts));
			$signature[] = array(
				'tag' => strtolower($element->tagName),
				'attrs' => $attrs,
				'direct_text' => trim((string) $directText),
				'child_tags' => $childTags,
			);
		}

		return $signature;
	};

	$assertFiniteMarkupContract = static function (DOMNodeList $elements) use ($assert): void {
		$allowedTags = array('a', 'button', 'div', 'input', 'label', 'li', 'option', 'p', 'script', 'select', 'span', 'strong', 'template', 'ul');
		$allowedAttrs = array('aria-hidden', 'aria-live', 'checked', 'class', 'disabled', 'hidden', 'href', 'id', 'min', 'name', 'placeholder', 'rel', 'selected', 'step', 'target', 'type', 'value');
		foreach ($elements as $element) {
			if (!$element instanceof DOMElement) {
				continue;
			}

			$tagName = strtolower($element->tagName);
			$assert(in_array($tagName, $allowedTags, true), 'Secondary Vendors save renderer should emit only the finite allowed elements. Unexpected tag: ' . $tagName);

			foreach ($element->attributes as $attribute) {
				if (!$attribute instanceof DOMAttr) {
					continue;
				}

				$attributeName = $attribute->name;
				$assert(strpos($attributeName, 'on') !== 0, 'Secondary Vendors save fragment should not allow inline event-handler attributes.');
				$assert($attributeName !== 'style', 'Secondary Vendors save fragment should not allow inline styles.');
				$assert(
					in_array($attributeName, $allowedAttrs, true)
					|| $attributeName === 'data-selected-id'
					|| strpos($attributeName, 'data-vms-') === 0,
					'Unexpected attribute inventory on <' . $tagName . '>: ' . $attributeName
				);
			}
		}
	};

	$renderLegacyPartial = static function (
		int $post_id,
		array $secondary_vendor_assignments,
		string $secondary_vendor_type,
		array $secondary_vendor_ids,
		array $secondary_vendor_boot_summary,
		string $module_owner
	) use ($secondaryVendorsPartialPath): string {
		$post = (object) array('ID' => $post_id);
		$vms_module_owner = $module_owner;
		ob_start();
		include $secondaryVendorsPartialPath;
		return (string) ob_get_clean();
	};

	$postId = 444;
	$moduleOwner = 'secondary_vendors';
	$richBootSummary = array(
		'assignment_groups' => array(
			array(
				'type_slug' => 'food_vendor',
				'type_name' => 'Food Vendor',
				'mode' => 'standard',
				'slot_limit' => 1,
				'slot_limit_display' => '1',
				'vendor_ids' => array(201, 202),
				'secondary_missing' => array(202),
				'secondary_mismatch' => array(201),
				'secondary_unqualified' => array(201),
				'selected_vendor_titles' => array(
					201 => 'Alpha <strong>Food</strong>',
					202 => 'Ghost <Vendor>',
				),
				'selected_missing_map' => array(
					201 => array('Contact info', 'Insurance'),
				),
				'pool_option_rows' => array(
					array(
						'vendor_id' => 201,
						'label' => 'Alpha <strong>Food</strong> [Q⚠]',
						'vendor_title' => 'Alpha <strong>Food</strong>',
						'availability_state' => 'available',
					),
					array(
						'vendor_id' => 202,
						'label' => 'Ghost <Vendor>',
						'vendor_title' => 'Ghost <Vendor>',
						'availability_state' => 'unavailable',
					),
				),
				'allow_over_capacity' => '1',
				'open_for_dispatch' => '1',
			),
			array(
				'type_slug' => 'market_vendor',
				'type_name' => 'Market Vendor',
				'mode' => 'market',
				'slot_limit' => 3,
				'slot_limit_display' => '3',
				'vendor_ids' => array(301, 0),
				'secondary_missing' => array(),
				'secondary_mismatch' => array(),
				'secondary_unqualified' => array(),
				'selected_vendor_titles' => array(
					301 => 'Market <One>',
				),
				'selected_missing_map' => array(),
				'pool_option_rows' => array(
					array(
						'vendor_id' => 301,
						'label' => 'Market <One>',
						'vendor_title' => 'Market <One>',
						'availability_state' => '',
					),
					array(
						'vendor_id' => 302,
						'label' => 'Market Two',
						'vendor_title' => 'Market Two',
						'availability_state' => 'available',
					),
				),
				'allow_over_capacity' => '0',
				'needed_slots' => 4,
				'open_for_dispatch' => '0',
			),
		),
		'secondary_type_options' => array(
			'food_vendor' => 'Food Vendor',
			'market_vendor' => 'Market Vendor',
		),
		'secondary_mode_options' => array(
			'standard' => 'Standard',
			'market' => 'Market',
		),
		'type_pool_map' => array(
			'food_vendor' => array(
				array(
					'vendor_id' => 201,
					'label' => 'Alpha </script><script>alert(1)</script> [Q⚠]',
					'vendor_title' => 'Alpha </script><script>alert(1)</script>',
					'availability_state' => 'available',
				),
				array(
					'vendor_id' => 202,
					'label' => 'Ghost <Vendor>',
					'vendor_title' => 'Ghost <Vendor>',
					'availability_state' => 'unavailable',
				),
			),
			'market_vendor' => array(
				array(
					'vendor_id' => 301,
					'label' => 'Market <One>',
					'vendor_title' => 'Market <One>',
					'availability_state' => '',
				),
				array(
					'vendor_id' => 302,
					'label' => 'Market Two',
					'vendor_title' => 'Market Two',
					'availability_state' => 'available',
				),
			),
		),
		'secondary_missing' => array(202),
		'secondary_mismatch' => array(201),
		'secondary_unqualified' => array(201),
	);
	$richSnapshot = array(
		'vendors' => array(
			array(
				'source_label' => 'Primary Vendor',
				'vendor_title' => 'Main <Vendor>',
				'category_label' => 'Category',
				'term_names' => array('Rock', 'Food <Fest>'),
			),
			array(
				'source_label' => 'Food Vendor',
				'vendor_title' => 'Alpha </script><script>alert(1)</script>',
				'category_label' => 'Category',
				'term_names' => array(),
			),
		),
		'term_names' => array('Rock', 'Food <Fest>'),
	);
	$GLOBALS['vms_secondary_vendor_test_band_ids'] = array(
		$postId => 901,
	);
	$GLOBALS['vms_secondary_vendor_test_secondary_assignments'] = array(
		$postId => array(
			'food_vendor' => array(
				'vendor_ids' => array(201),
			),
		),
	);
	$GLOBALS['vms_secondary_vendor_test_post_types'] = array(
		901 => 'vms_vendor',
		201 => 'vms_vendor',
	);
	$GLOBALS['vms_secondary_vendor_test_primary_type_slugs'] = array(
		901 => 'band',
	);
	$GLOBALS['vms_secondary_vendor_test_category_labels'] = array(
		'band' => 'Category',
		'food_vendor' => 'Category',
	);
	$GLOBALS['vms_secondary_vendor_test_titles'] = array(
		901 => 'Main <Vendor>',
		201 => 'Alpha </script><script>alert(1)</script>',
	);
	$GLOBALS['vms_secondary_vendor_test_terms'] = array(
		901 => array(
			new WP_Term(11, 'Rock', 'rock'),
			new WP_Term(12, 'Food <Fest>', 'food-fest'),
		),
		201 => array(),
	);

	$richContext = (array) $invokePrivate($controller, 'build_event_plan_secondary_vendors_save_response_context', array(
		$postId,
		$richBootSummary,
		$moduleOwner,
		$richSnapshot,
	));
	$richHtml = (string) $invokePrivate($controller, 'render_event_plan_secondary_vendors_save_response_html', array($richContext));
	$legacyRichHtml = $renderLegacyPartial(
		$postId,
		$richBootSummary['assignment_groups'],
		'food_vendor',
		array(201, 202, 301),
		$richBootSummary,
		$moduleOwner
	);

	$richSignature = $describeFragment($richHtml, 'rich Secondary Vendors save renderer output');
	$legacyRichSignature = $describeFragment($legacyRichHtml, 'rich Secondary Vendors legacy partial output');
	if ($richSignature !== $legacyRichSignature) {
		$firstDiff = null;
		$maxIndex = max(count($richSignature), count($legacyRichSignature));
		for ($index = 0; $index < $maxIndex; $index++) {
			$actualNode = $richSignature[$index] ?? null;
			$legacyNode = $legacyRichSignature[$index] ?? null;
			if ($actualNode !== $legacyNode) {
				$firstDiff = array(
					'index' => $index,
					'actual' => $actualNode,
					'legacy' => $legacyNode,
				);
				break;
			}
		}
		throw new RuntimeException('Secondary Vendors rich save renderer should preserve the legacy partial markup contract. First diff: ' . wp_json_encode($firstDiff));
	}
	$assert(strpos($richHtml, '<script>alert(1)</script>') === false, 'Secondary Vendors rich save renderer should keep script-like stored values inert.');

	list($richDoc, $richXpath) = $loadFragment($richHtml, 'rich Secondary Vendors save renderer output');
	unset($richDoc);
	$richElements = $richXpath->query('//*[@id="root"]//*');
	$assertFiniteMarkupContract($richElements);

	$richWrapper = $richXpath->query('//*[@id="vms-secondary-vendors-section"]')->item(0);
	$assert($richWrapper instanceof DOMElement, 'Secondary Vendors rich save renderer should preserve the section wrapper.');
	$assert($richWrapper->getAttribute('data-vms-module-owner') === 'secondary_vendors', 'Secondary Vendors rich save renderer should preserve the module owner data attribute.');
	$assert($richWrapper->getAttribute('data-vms-save-url') === 'https://example.com/wp-admin/admin-ajax.php', 'Secondary Vendors rich save renderer should preserve the save URL contract.');
	$assert($richWrapper->getAttribute('data-vms-save-nonce') === 'nonce-vms_event_plan_secondary_vendors_save', 'Secondary Vendors rich save renderer should preserve the save nonce contract.');
	$assert($richWrapper->getAttribute('data-vms-save-post-id') === (string) $postId, 'Secondary Vendors rich save renderer should preserve the save post ID contract.');

	$configScript = $richXpath->query('//*[@id="root"]//script[@type="application/json" and @data-vms-secondary-config]')->item(0);
	$assert($configScript instanceof DOMElement, 'Secondary Vendors rich save renderer should preserve the scoped JSON config tag.');
	$config = json_decode((string) $configScript->textContent, true);
	$assert(is_array($config), 'Secondary Vendors rich save renderer should emit valid JSON config.');
	$assert(count((array) ($config['typeOptions'] ?? array())) === 2, 'Secondary Vendors rich save renderer should preserve the type-options config inventory.');
	$assert(($config['labels']['saveUnavailable'] ?? '') === 'Additional Vendors save is not available right now.', 'Secondary Vendors rich save renderer should preserve the localized config label inventory.');

	$scriptTags = $richXpath->query('//*[@id="root"]//script');
	$assert($scriptTags instanceof DOMNodeList && $scriptTags->length === 1, 'Secondary Vendors rich save renderer should emit exactly one script tag.');
	$groupNodes = $richXpath->query('//*[@id="vms-secondary-vendor-groups"]/div[contains(concat(" ", normalize-space(@class), " "), " vms-secondary-vendor-group ")]');
	$assert($groupNodes instanceof DOMNodeList && $groupNodes->length === 2, 'Secondary Vendors rich save renderer should preserve the group count.');
	$rowNodes = $richXpath->query('//*[@id="vms-secondary-vendor-groups"]//div[contains(concat(" ", normalize-space(@class), " "), " vms-secondary-vendor-row ")]');
	$assert($rowNodes instanceof DOMNodeList && $rowNodes->length === 4, 'Secondary Vendors rich save renderer should preserve the row count.');
	$helpRows = $richXpath->query('//*[@id="root"]//ul[@class="vms-help-missing-list"]/li');
	$assert($helpRows instanceof DOMNodeList && $helpRows->length === 1, 'Secondary Vendors rich save renderer should preserve the unqualified-help list.');
	$categoryRows = $richXpath->query('//*[@id="root"]//div[contains(@class, "vms-notice--info")]/ul/li');
	$assert($categoryRows instanceof DOMNodeList && $categoryRows->length === 2, 'Secondary Vendors rich save renderer should preserve the vendor-category notice rows.');
	$summaryNodes = $richXpath->query('//*[@id="vms-secondary-vendor-groups"]//p[contains(concat(" ", normalize-space(@class), " "), " vms-secondary-vendor-group__summary ")]');
	$assert($summaryNodes instanceof DOMNodeList && $summaryNodes->length === 2, 'Secondary Vendors rich save renderer should preserve the group summary count.');
	$assert(strpos((string) $summaryNodes->item(0)->textContent, 'Over capacity by 1') !== false, 'Secondary Vendors rich save renderer should preserve the over-capacity warning summary.');
	$assert(strpos((string) $summaryNodes->item(1)->textContent, 'Hidden from ADD') !== false, 'Secondary Vendors rich save renderer should preserve the parsed hidden-from-dispatch summary.');
	$clearButton = $richXpath->query('//*[@id="vms-secondary-vendor-clear"]')->item(0);
	$assert($clearButton instanceof DOMElement && !$clearButton->hasAttribute('disabled'), 'Secondary Vendors rich save renderer should keep the clear button enabled when saved groups exist.');
	$foodOption = $richXpath->query('//*[@id="root"]//select[contains(@class, "vms-secondary-vendor-select")]/option[@value="201"]')->item(0);
	$assert($foodOption instanceof DOMElement && strpos(trim((string) $foodOption->textContent), 'Alpha </script><script>alert(1)</script>') !== false, 'Secondary Vendors rich save renderer should keep hostile vendor labels inert as literal text.');
	$assert($richXpath->query('//*[@id="root"]//table | //*[@id="root"]//img | //*[@id="root"]//style')->length === 0, 'Secondary Vendors rich save renderer should not emit tables, media, or style tags.');

	$emptyBootSummary = array(
		'assignment_groups' => array(),
		'secondary_type_options' => array(
			'food_vendor' => 'Food Vendor',
			'market_vendor' => 'Market Vendor',
		),
		'secondary_mode_options' => array(
			'standard' => 'Standard',
			'market' => 'Market',
		),
		'type_pool_map' => array(),
		'secondary_missing' => array(),
		'secondary_mismatch' => array(),
		'secondary_unqualified' => array(),
	);
	$emptySnapshot = array(
		'vendors' => array(),
		'term_names' => array(),
	);
	$GLOBALS['vms_secondary_vendor_test_band_ids'] = array(
		$postId => 0,
	);
	$GLOBALS['vms_secondary_vendor_test_secondary_assignments'] = array(
		$postId => array(),
	);
	$GLOBALS['vms_secondary_vendor_test_post_types'] = array();
	$GLOBALS['vms_secondary_vendor_test_primary_type_slugs'] = array();
	$GLOBALS['vms_secondary_vendor_test_category_labels'] = array();
	$GLOBALS['vms_secondary_vendor_test_titles'] = array();
	$GLOBALS['vms_secondary_vendor_test_terms'] = array();
	$emptyContext = (array) $invokePrivate($controller, 'build_event_plan_secondary_vendors_save_response_context', array(
		$postId,
		$emptyBootSummary,
		$moduleOwner,
		$emptySnapshot
	));
	$emptyHtml = (string) $invokePrivate($controller, 'render_event_plan_secondary_vendors_save_response_html', array($emptyContext));
	$legacyEmptyHtml = $renderLegacyPartial(
		$postId,
		array(),
		'',
		array(),
		$emptyBootSummary,
		$moduleOwner
	);

	$assert($describeFragment($emptyHtml, 'empty Secondary Vendors save renderer output') === $describeFragment($legacyEmptyHtml, 'empty Secondary Vendors legacy partial output'), 'Secondary Vendors empty save renderer should preserve the legacy partial empty-state contract.');
	list($emptyDoc, $emptyXpath) = $loadFragment($emptyHtml, 'empty Secondary Vendors save renderer output');
	unset($emptyDoc);
	$assert($emptyXpath->query('//*[@id="vms-secondary-vendor-groups"]/div[contains(concat(" ", normalize-space(@class), " "), " vms-secondary-vendor-group ")]')->length === 0, 'Secondary Vendors empty save renderer should omit saved groups.');
	$emptyClearButton = $emptyXpath->query('//*[@id="vms-secondary-vendor-clear"]')->item(0);
	$assert($emptyClearButton instanceof DOMElement && $emptyClearButton->hasAttribute('disabled'), 'Secondary Vendors empty save renderer should disable the clear button when no saved groups exist.');
	$emptyInfoNotice = $emptyXpath->query('//*[@id="root"]//div[@class="notice notice-info inline vms-notice vms-notice--info"]/p')->item(0);
	$assert($emptyInfoNotice instanceof DOMElement && trim((string) $emptyInfoNotice->textContent) === 'Add a vendor group, then save this section to store your additional vendor assignments.', 'Secondary Vendors empty save renderer should preserve the empty-state info notice.');
	$assert($emptyXpath->query('//*[@id="root"]//script[@type="application/json" and @data-vms-secondary-config]')->length === 1, 'Secondary Vendors empty save renderer should preserve the scoped JSON config tag.');

	$hostileValue = '</script><script>alert(1)</script>';
	$assert(strpos($richHtml, $hostileValue) === false, 'Secondary Vendors save renderer should not emit a literal hostile </script><script> breakout sequence.');
	$assert(strpos($richHtml, '<\\/script><script>alert(1)<\\/script>') !== false, 'Secondary Vendors save renderer should preserve the hostile value only as JSON-escaped inert text.');
	list($richScriptDoc, $richScriptXpath) = $loadFragment($richHtml, 'rich Secondary Vendors hostile-config save renderer output');
	unset($richScriptDoc);
	$hostileScripts = $richScriptXpath->query('//*[@id="root"]//script');
	$assert($hostileScripts instanceof DOMNodeList && $hostileScripts->length === 1, 'Secondary Vendors save renderer should still produce exactly one script element when hostile config text includes a closing-tag payload.');
	$hostileConfigScript = $richScriptXpath->query('//*[@id="root"]//script[@type="application/json" and @data-vms-secondary-config]')->item(0);
	$assert($hostileConfigScript instanceof DOMElement, 'Secondary Vendors save renderer should keep the hostile config payload inside the inert application/json script tag.');
	$decodedHostileConfig = json_decode((string) $hostileConfigScript->textContent, true);
	$assert(is_array($decodedHostileConfig), 'Secondary Vendors save renderer should emit valid JSON even when hostile config text is present.');
	$hostilePoolLabel = $decodedHostileConfig['pools']['food_vendor'][0]['label'] ?? '';
	$assert($hostilePoolLabel === 'Alpha </script><script>alert(1)</script> [Q⚠]', 'Secondary Vendors save renderer should preserve hostile pool labels as inert decoded JSON text.');
	$assert($richScriptXpath->query('//*[@id="root"]//script[not(@type="application/json")]')->length === 0, 'Secondary Vendors save renderer should not create an executable script element from hostile config text.');

	$assert(strpos($secondaryVendorsPartialSource, "vms_event_plan_parse_secondary_vendor_over_capacity_override(\$group['open_for_dispatch'] ?? true)") !== false, 'Legacy Secondary Vendors partial should interpret open_for_dispatch through the shared parser.');
	$assert(strpos($secondaryVendorsPartialSource, "vms_event_plan_parse_secondary_vendor_over_capacity_override(\$group['allow_over_capacity'] ?? false)") !== false, 'Legacy Secondary Vendors partial should interpret allow_over_capacity through the shared parser.');
	$assert(strpos($saveRendererGroupSource, "vms_event_plan_parse_secondary_vendor_over_capacity_override(\$group['open_for_dispatch'] ?? true)") !== false, 'Secondary Vendors save renderer should interpret open_for_dispatch through the shared parser.');
	$assert(strpos($saveRendererGroupSource, "vms_event_plan_parse_secondary_vendor_over_capacity_override(\$group['allow_over_capacity'] ?? false)") !== false, 'Secondary Vendors save renderer should interpret allow_over_capacity through the shared parser.');

	$booleanCases = array(
		'bool_true' => array('value' => true, 'expected' => true),
		'bool_false' => array('value' => false, 'expected' => false),
		'int_one' => array('value' => 1, 'expected' => true),
		'int_zero' => array('value' => 0, 'expected' => false),
		'string_one' => array('value' => '1', 'expected' => true),
		'string_zero' => array('value' => '0', 'expected' => false),
		'string_true' => array('value' => 'true', 'expected' => true),
		'string_false' => array('value' => 'false', 'expected' => false),
		'empty_string' => array('value' => '', 'expected' => false),
		'missing' => array('missing' => true, 'expected' => true),
	);
	foreach ($booleanCases as $caseName => $case) {
		$group = array(
			'type_slug' => 'market_vendor',
			'mode' => 'market',
			'vendor_ids' => array(301),
			'needed_slots' => 4,
		);
		if (empty($case['missing'])) {
			$group['open_for_dispatch'] = $case['value'];
		}
		$summary = (array) $invokePrivate($controller, 'build_event_plan_secondary_vendors_save_response_group_summary_context', array($group));
		$text = (string) ($summary['text'] ?? '');
		$shouldShowNeeded = !empty($case['expected']);
		$assert(
			$shouldShowNeeded ? strpos($text, '3 needed') !== false : strpos($text, 'Hidden from ADD') !== false,
			'open_for_dispatch parser parity should preserve the market summary branch for save-renderer case: ' . $caseName
		);
	}

	foreach (array(
		'bool_true' => array('value' => true, 'expected' => true),
		'bool_false' => array('value' => false, 'expected' => false),
		'int_one' => array('value' => 1, 'expected' => true),
		'int_zero' => array('value' => 0, 'expected' => false),
		'string_one' => array('value' => '1', 'expected' => true),
		'string_zero' => array('value' => '0', 'expected' => false),
		'string_true' => array('value' => 'true', 'expected' => true),
		'string_false' => array('value' => 'false', 'expected' => false),
		'empty_string' => array('value' => '', 'expected' => false),
		'missing' => array('missing' => true, 'expected' => false),
	) as $caseName => $case) {
		$group = array(
			'type_slug' => 'food_vendor',
			'type_name' => 'Food Vendor',
			'mode' => 'standard',
			'slot_limit' => 1,
			'slot_limit_display' => '1',
			'vendor_ids' => array(201, 202),
			'secondary_missing' => array(),
			'secondary_mismatch' => array(),
			'secondary_unqualified' => array(),
			'selected_vendor_titles' => array(),
			'selected_missing_map' => array(),
		);
		if (empty($case['missing'])) {
			$group['allow_over_capacity'] = $case['value'];
		}
		$groupHtml = (string) $invokePrivate($controller, 'render_event_plan_secondary_vendors_save_response_group_html', array(
			$group,
			0,
			array(
				'post_id' => $postId,
				'secondary_group_type_options' => array('food_vendor' => 'Food Vendor'),
				'secondary_mode_options' => array('standard' => 'Standard'),
				'type_pool_map' => array('food_vendor' => array()),
				'help_enabled' => true,
			),
		));
		list($groupDoc, $groupXpath) = $loadFragment($groupHtml, 'allow_over_capacity save group case ' . $caseName);
		unset($groupDoc);
		$overrideInput = $groupXpath->query('//*[@id="root"]//input[contains(concat(" ", normalize-space(@class), " "), " vms-secondary-vendor-group-over-capacity-override ")]')->item(0);
		$assert(
			$overrideInput instanceof DOMElement && ($overrideInput->hasAttribute('checked') === !empty($case['expected'])),
			'allow_over_capacity parser parity should preserve the override checked state for save-renderer case: ' . $caseName
		);
	}

	fwrite(STDOUT, "event plan secondary vendors save output remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan secondary vendors save output remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
