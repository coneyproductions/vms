<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$shellAssetPath = $pluginRoot . '/assets/js/vms-event-plan-shell.js';
$staffPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/staff.php';

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
	$shellAssetSource = $readFile($shellAssetPath);
	$staffPartialSource = $readFile($staffPartialPath);

	$assert(strpos($eventPlansSource, "add_action('wp_ajax_vms_load_event_plan_admin_section', array(\$this, 'ajax_load_event_plan_admin_section'));") !== false, 'Event Plans should retain the authenticated shared lazy-section AJAX hook.');
	$assert(strpos($eventPlansSource, 'wp_ajax_nopriv_vms_load_event_plan_admin_section') === false, 'The shared lazy-section AJAX endpoint should not expose an unauthenticated hook.');
	$assert(strpos($eventPlansSource, '$post_id = isset($_POST[\'post_id\']) ? absint($_POST[\'post_id\']) : 0;') !== false, 'Lazy-section AJAX handler should continue to normalize post_id with absint().');
	$assert(strpos($eventPlansSource, '$section = isset($_POST[\'section\']) ? sanitize_key((string) wp_unslash($_POST[\'section\'])) : \'\';') !== false, 'Lazy-section AJAX handler should continue to sanitize the section field with wp_unslash() + sanitize_key().');
	$assert(strpos($eventPlansSource, "check_ajax_referer('vms_event_plan_admin_section', 'nonce');") !== false, 'Lazy-section AJAX handler should keep the exact nonce action and request field.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);") !== false, 'Lazy-section AJAX handler should retain the exact invalid-plan response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Not allowed'), 403);") !== false, 'Lazy-section AJAX handler should retain the exact capability error response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Section not supported.'), 400);") !== false, 'Lazy-section AJAX handler should retain the exact unsupported-section response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Event Plan not found.'), 404);") !== false, 'Lazy-section AJAX handler should retain the exact missing-post response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Section not implemented.'), 400);") !== false, 'Lazy-section AJAX handler should retain the exact fallback response.');
	$assert(strpos($eventPlansSource, 'build_event_plan_staff_response_payload') !== false, 'Staff lazy-load branch should route the fragment through the family-specific payload builder.');
	$assert(substr_count($eventPlansSource, "capture_event_plan_partial('staff'") === 1, 'Only the live Event Plan render path should retain the captured staff partial after this slice.');
	$assert(strpos($eventPlansSource, "capture_event_plan_partial('secondary-vendors'") !== false, 'Secondary Vendors should remain outside the staff renderer slice.');
	$assert(strpos($eventPlansSource, 'build_event_plan_readiness_details_response_payload') !== false, 'Readiness-details should remain outside the staff renderer slice.');
	$assert(strpos($staffPartialSource, 'data-vms-staff-wrap="1"') !== false, 'The live staff partial should remain available for the non-AJAX Event Plan render path.');

	$ajaxMethodMatched = preg_match(
		'~public function ajax_load_event_plan_admin_section\(\): void\s*\{(?P<body>.*?)^\s*\}\s*\n\s*public function ajax_save_event_plan_secondary_vendors~sm',
		$eventPlansSource,
		$ajaxMethodMatch
	);
	$assert($ajaxMethodMatched === 1, 'Failed to isolate ajax_load_event_plan_admin_section() source.');
	$ajaxMethodSource = (string) $ajaxMethodMatch['body'];
	preg_match_all('~\\$_POST\\[\'([^\']+)\'\\]~', $ajaxMethodSource, $requestFieldMatches);
	$requestFields = array_values(array_unique($requestFieldMatches[1] ?? array()));
	sort($requestFields);
	$assert($requestFields === array('post_id', 'section'), 'Lazy-section AJAX handler should read only post_id and section directly from $_POST.');
	$assert(substr_count($ajaxMethodSource, 'current_user_can(') === 1, 'Lazy-section AJAX handler should retain exactly one capability check.');
	$assert(substr_count($ajaxMethodSource, 'check_ajax_referer(') === 1, 'Lazy-section AJAX handler should retain exactly one nonce check.');
	$assert(substr_count($ajaxMethodSource, 'get_post(') === 1, 'Lazy-section AJAX handler should retain exactly one Event Plan post lookup.');
	$assert(substr_count($ajaxMethodSource, 'event_plan_admin_section_supports_lazy_load(') === 1, 'Lazy-section AJAX handler should retain exactly one lazy-section support check.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_success(') === 3, 'Lazy-section AJAX handler should retain the exact three success writes for staff, secondary vendors, and readiness details.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_error(') === 5, 'Lazy-section AJAX handler should retain the exact five explicit JSON error branches.');
	$assert(strpos($ajaxMethodSource, 'update_post_meta(') === false && strpos($ajaxMethodSource, 'delete_post_meta(') === false, 'Lazy-section AJAX handler should not mutate database state.');

	$staffBranchMatched = preg_match(
		'~if \(\$section === \'staff\'\) \{(?P<body>.*?)^\s*\}\s*\n\s*if \(\$section === \'secondary_vendors\'\) \{~sm',
		$ajaxMethodSource,
		$staffBranchMatch
	);
	$assert($staffBranchMatched === 1, 'Failed to isolate the staff branch from ajax_load_event_plan_admin_section().');
	$staffBranchSource = (string) $staffBranchMatch['body'];
	$assert(substr_count($staffBranchSource, "get_post_meta(\$post_id, '_vms_staff_assignments', true)") === 1, 'Staff branch should continue to load the saved staff assignments once.');
	$assert(substr_count($staffBranchSource, 'build_event_plan_staff_response_payload(') === 1, 'Staff branch should invoke the local response payload builder exactly once.');
	$assert(substr_count($staffBranchSource, 'wp_send_json_success(') === 1, 'Staff branch should write exactly one success response.');
	$assert(strpos($staffBranchSource, "'html' => \$html") !== false, 'Staff branch should preserve the html response key.');
	$assert(strpos($staffBranchSource, "'section' => \$section") !== false, 'Staff branch should preserve the section response key.');
	$assert(strpos($staffBranchSource, "capture_event_plan_partial('staff'") === false, 'Staff branch should remove the ambiguous captured-partial handoff.');

	$payloadBuilderMatched = preg_match(
		'~private function build_event_plan_staff_response_payload\(int \$post_id, array \$staff_assignments\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function build_event_plan_staff_response_context~sm',
		$eventPlansSource,
		$payloadBuilderMatch
	);
	$assert($payloadBuilderMatched === 1, 'Failed to isolate the staff response payload builder source.');
	$payloadBuilderSource = (string) $payloadBuilderMatch['body'];
	$assert(substr_count($payloadBuilderSource, 'get_event_plan_staff_render_context(') === 1, 'Staff response payload builder should resolve the staff render context exactly once.');
	$assert(substr_count($payloadBuilderSource, 'build_event_plan_staff_response_context(') === 1, 'Staff response payload builder should normalize the staff data exactly once.');
	$assert(substr_count($payloadBuilderSource, 'render_event_plan_staff_response_html(') === 1, 'Staff response payload builder should render the fragment exactly once.');
	$assert(strpos($payloadBuilderSource, 'capture_event_plan_partial(') === false, 'Staff response payload builder should not route through a captured partial.');

	$rendererGroupMatched = preg_match(
		'~private function render_event_plan_staff_response_html\(array \$staff_context\): string\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function get_event_plan_compensation_render_context~sm',
		$eventPlansSource,
		$rendererGroupMatch
	);
	$assert($rendererGroupMatched === 1, 'Failed to isolate the staff response renderer group source.');
	$rendererGroupSource = (string) $rendererGroupMatch['body'];
	foreach (array(
		'render_event_plan_staff_response_template_alert_notice_html',
		'render_event_plan_staff_response_template_option_rows_html',
		'render_event_plan_staff_response_role_cards_html',
		'render_event_plan_staff_response_role_card_html',
		'render_event_plan_staff_response_candidate_rows_html',
		'render_event_plan_staff_response_badges_html',
	) as $requiredRendererHelper) {
		$assert(strpos($rendererGroupSource, $requiredRendererHelper) !== false, 'Staff response renderer group should route through the dedicated helper: ' . $requiredRendererHelper);
	}
	foreach (array(
		'capture_event_plan_partial(',
		'get_post_meta(',
		'get_posts(',
		'get_terms(',
		'bvmgr_staffing_staff_candidate_status_for_role(',
		'bvmgr_vendor_tax_profile_missing_items(',
		'bvmgr_get_tax_bypass_status(',
		'get_the_title(',
		'wp_kses_post(',
		'wp_kses(',
	) as $forbiddenRendererToken) {
		$assert(strpos($rendererGroupSource, $forbiddenRendererToken) === false, 'Staff response renderer group should not perform provider/database reads or broad safe-HTML filtering: ' . $forbiddenRendererToken);
	}
	foreach (array(
		'secondary_vendors',
		'readiness_details',
		'comp_options',
	) as $unexpectedFamilyToken) {
		$assert(strpos($rendererGroupSource, $unexpectedFamilyToken) === false, 'Staff response renderer group should stay scoped to the staff family: ' . $unexpectedFamilyToken);
	}

	foreach (array(
		"params.set('action', 'vms_load_event_plan_admin_section');",
		"params.set('post_id', String(lazyPostId));",
		"params.set('section', lazySection);",
		"params.set('nonce', lazyNonce);",
		"if (!response.ok || !payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {",
		'body.innerHTML = payload.data.html;',
		'window.vmsEventPlanInitStaff(body);',
	) as $requiredShellMarker) {
		$assert(strpos($shellAssetSource, $requiredShellMarker) !== false, 'Shell asset should retain the staff lazy-load consumer marker: ' . $requiredShellMarker);
	}
	$assert(strpos($shellAssetSource, 'payload.data.markup') === false, 'Shell asset should not look for a renamed staff HTML response key.');
	$assert(strpos($shellAssetSource, 'insertAdjacentHTML(') === false, 'Shell asset should not switch to a different DOM insertion method for staff.');

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
	if (!function_exists('absint')) {
		function absint($value): int
		{
			return max(0, (int) $value);
		}
	}

	require_once $eventPlansPath;

	$controller = new BVMGR_Admin_Event_Plans();
	$invokePrivate = static function (object $object, string $method, array $args = array()) {
		$reflection = new ReflectionMethod($object, $method);
		return $reflection->invokeArgs($object, $args);
	};

	$loadFragment = static function (string $html, string $context) use ($assert): array {
		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$loaded = $doc->loadHTML('<!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>');
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$assert($loaded, 'Failed to parse ' . $context . ' fragment.');
		$assert(count($errors) === 0, $context . ' fragment should parse cleanly.');
		$xpath = new DOMXPath($doc);
		return array($doc, $xpath);
	};

	$allowedAttributesByTag = array(
		'button' => array('class', 'name', 'type', 'value'),
		'div' => array('aria-label', 'class', 'data-role-critical', 'data-role-id', 'data-role-name', 'data-vms-current-headcount', 'data-vms-headcount-label', 'data-vms-headcount-wired', 'data-vms-role-absolute-warning', 'data-vms-role-required-warning', 'data-vms-section-has-data', 'data-vms-staff-role', 'data-vms-staff-wrap', 'role'),
		'input' => array('checked', 'data-vms-role-assignment-input', 'data-vms-role-duration-input', 'data-vms-role-end-offset-input', 'data-vms-role-headcount-input', 'data-vms-role-shift-end-input', 'data-vms-role-shift-start-input', 'data-vms-role-start-offset-input', 'data-vms-role-threshold-input', 'disabled', 'min', 'name', 'step', 'type', 'value'),
		'label' => array('class', 'data-vms-role-absolute-field', 'data-vms-role-duration-field', 'data-vms-role-end-field', 'data-vms-role-relative-field'),
		'li' => array(),
		'option' => array('selected', 'value'),
		'p' => array('class', 'data-vms-role-threshold-copy', 'id'),
		'select' => array('data-vms-role-end-anchor-input', 'data-vms-role-start-anchor-input', 'data-vms-role-time-mode-input', 'name'),
		'span' => array('aria-label', 'class', 'data-vms-role-base-summary', 'data-vms-role-state-pill'),
		'strong' => array(),
		'ul' => array('style'),
	);

	$fullContext = array(
		'has_data' => true,
		'headcount_wired' => false,
		'headcount_summary_text' => 'Anticipated guests: 51. Staffing highlights below update against this number. <unsafe>',
		'current_headcount' => 51,
		'headcount_label' => 'Attendance <pending>',
		'template_alerts' => array(
			'Alert <b>one</b>',
			'Alert "two"',
		),
		'applied_template_summary' => 'Applied <template> · Recommended "Now"',
		'applied_template_id' => 5,
		'template_options' => array(
			array('template_id' => 5, 'label' => 'Late <Set>'),
			array('template_id' => 6, 'label' => 'Rush & Go'),
		),
		'role_rows' => array(
			array(
				'role_id' => 7,
				'role_name' => 'Door <Lead>',
				'is_critical' => true,
				'card_class_name' => 'vms-ep-staff-role is-required-now has-inline-warning has-required-gap',
				'state_pill' => 'Needed now',
				'state_class' => 'is-required',
				'headcount' => 3,
				'filled' => 1,
				'open' => 2,
				'base_summary' => 'Need 3 · Filled 1 · Open 2 · Critical',
				'activation_threshold' => 50,
				'time_mode' => 'absolute',
				'shift_start' => '',
				'shift_end' => '',
				'start_anchor_key' => 'event_start',
				'end_anchor_key' => 'event_end',
				'start_offset_minutes' => 0,
				'end_offset_minutes' => 15,
				'duration_minutes' => '',
				'threshold_copy' => 'This role is needed now <unsafe>',
				'qualification_summary' => 'Required qualifications: TABC (Warn).',
				'absolute_time_missing' => true,
				'missing_staff_now' => true,
				'role_eligible_count' => 1,
				'no_eligible_staff_text' => 'No door-eligible staff found.',
				'show_assigned_ineligible_copy' => false,
				'candidate_rows' => array(
					array(
						'staff_id' => 11,
						'title' => 'Alex <script>alert(1)</script>',
						'checked' => true,
						'disabled' => false,
						'badge_rows' => array(
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--ok', 'text' => 'T✓', 'aria_label' => 'Tax profile ok'),
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--ok', 'text' => 'Q✓', 'aria_label' => ''),
						),
					),
					array(
						'staff_id' => 12,
						'title' => 'Jordan & Co',
						'checked' => false,
						'disabled' => true,
						'badge_rows' => array(
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--missing', 'text' => 'T⚠', 'aria_label' => 'Tax profile missing items'),
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--missing', 'text' => 'Q✕', 'aria_label' => ''),
							array('kind' => 'note', 'class_name' => 'vms-ep-tax-badge-note', 'text' => 'missing Alcohol Cert; expired Food Card', 'aria_label' => ''),
						),
					),
				),
			),
			array(
				'role_id' => 8,
				'role_name' => 'Cleanup',
				'is_critical' => false,
				'card_class_name' => 'vms-ep-staff-role',
				'state_pill' => 'Guests pending',
				'state_class' => 'is-unwired',
				'headcount' => 1,
				'filled' => 1,
				'open' => 0,
				'base_summary' => 'Need 1 · Filled 1 · Open 0',
				'activation_threshold' => 0,
				'time_mode' => 'relative',
				'shift_start' => '',
				'shift_end' => '',
				'start_anchor_key' => 'a1',
				'end_anchor_key' => 'a2',
				'start_offset_minutes' => -15,
				'end_offset_minutes' => 45,
				'duration_minutes' => 30,
				'threshold_copy' => 'Guest count is not available yet.',
				'qualification_summary' => '',
				'absolute_time_missing' => false,
				'missing_staff_now' => false,
				'role_eligible_count' => 0,
				'no_eligible_staff_text' => 'No cleanup-eligible staff found.',
				'show_assigned_ineligible_copy' => true,
				'candidate_rows' => array(
					array(
						'staff_id' => 13,
						'title' => 'Taylor "Assigned"',
						'checked' => true,
						'disabled' => false,
						'badge_rows' => array(
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--bypass', 'text' => 'TB', 'aria_label' => 'Tax profile bypass active'),
							array('kind' => 'note', 'class_name' => 'vms-ep-tax-badge-note', 'text' => 'until <tomorrow>', 'aria_label' => ''),
							array('kind' => 'badge', 'class_name' => 'vms-ep-tax-badge vms-ep-tax-badge--missing', 'text' => 'Role⚠', 'aria_label' => ''),
							array('kind' => 'note', 'class_name' => 'vms-ep-tax-badge-note', 'text' => 'No longer eligible <now>', 'aria_label' => ''),
						),
					),
				),
			),
			array(
				'role_id' => 9,
				'role_name' => 'Parking',
				'is_critical' => false,
				'card_class_name' => 'vms-ep-staff-role is-waiting-threshold',
				'state_pill' => 'Needed at 100+ guests',
				'state_class' => 'is-waiting',
				'headcount' => 0,
				'filled' => 0,
				'open' => 0,
				'base_summary' => 'Need 0 · Filled 0 · Open 0',
				'activation_threshold' => 100,
				'time_mode' => 'relative',
				'shift_start' => '',
				'shift_end' => '',
				'start_anchor_key' => 'event_start',
				'end_anchor_key' => 'event_end',
				'start_offset_minutes' => 5,
				'end_offset_minutes' => 10,
				'duration_minutes' => 15,
				'threshold_copy' => 'This role becomes needed at 100 anticipated guests.',
				'qualification_summary' => '',
				'absolute_time_missing' => false,
				'missing_staff_now' => false,
				'role_eligible_count' => 0,
				'no_eligible_staff_text' => 'No parking-eligible staff found.',
				'show_assigned_ineligible_copy' => false,
				'candidate_rows' => array(),
			),
		),
	);

	$emptyContext = array(
		'has_data' => false,
		'headcount_wired' => false,
		'headcount_summary_text' => 'Anticipated guest count is not available yet.',
		'current_headcount' => 0,
		'headcount_label' => 'Attendance not wired yet',
		'template_alerts' => array(),
		'applied_template_summary' => 'Applied: None recorded · Recommended now: No match',
		'applied_template_id' => 0,
		'template_options' => array(),
		'role_rows' => array(),
	);

	$fullHtml = (string) $invokePrivate($controller, 'render_event_plan_staff_response_html', array($fullContext));
	$emptyHtml = (string) $invokePrivate($controller, 'render_event_plan_staff_response_html', array($emptyContext));

	foreach (array(
		'<script',
		'<style',
		'<table',
		'<img',
		'<a ',
		'onclick=',
		'javascript:',
		'<h4',
	) as $forbiddenMarkup) {
		$assert(stripos($fullHtml, $forbiddenMarkup) === false, 'Staff response fragment should not emit disallowed markup or handler text: ' . $forbiddenMarkup);
	}

	$assert(strpos($fullHtml, '&lt;unsafe&gt;') !== false, 'Dynamic threshold/headcount text should be escaped in the staff response fragment.');
	$assert(strpos($fullHtml, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Dynamic staff titles should be escaped in the staff response fragment.');
	$assert(strpos($fullHtml, '&lt;tomorrow&gt;') !== false, 'Dynamic badge-note text should be escaped in the staff response fragment.');
	$assert(strpos($fullHtml, '<b>one</b>') === false, 'Template alerts should not allow raw HTML.');
	$assert(strpos($fullHtml, 'data-vms-staff-wrap="1"') !== false, 'Staff response fragment should retain the root staff-wrap contract.');
	$assert(strpos($fullHtml, 'name="vms_staff_assignments_present" value="1"') !== false, 'Staff response fragment should retain the hidden assignments-present contract.');
	$assert(strpos($fullHtml, 'name="vms_staffing_roles_present" value="1"') !== false, 'Staff response fragment should retain the hidden roles-present contract.');
	$assert(strpos($fullHtml, 'Staffing alert:') !== false, 'Staff response fragment should cover the template-alert branch.');
	$assert(strpos($fullHtml, 'Currently assigned but now-ineligible staff are shown below so this plan does not silently lose them.') !== false, 'Staff response fragment should cover the assigned-ineligible explanatory branch.');
	$assert(strpos($fullHtml, 'No staff candidates are available for this role yet.') !== false, 'Staff response fragment should cover the empty-candidate branch.');
	$assert(strpos($fullHtml, 'checked="checked"') !== false, 'Staff response fragment should preserve checked assignments.');
	$assert(strpos($fullHtml, 'disabled="disabled"') !== false, 'Staff response fragment should preserve disabled hard-blocked assignments.');
	$assert(strpos($emptyHtml, 'No staff roles are configured yet. Create roles in Staff Roles first.') !== false, 'Staff response fragment should cover the no-roles branch.');
	$assert(strpos($emptyHtml, 'Staffing alert:') === false, 'No-roles staff response fragment should omit the alert notice when no alerts are present.');

	list($fullDoc, $fullXpath) = $loadFragment($fullHtml, 'staff');
	unset($fullDoc);
	$elements = $fullXpath->query('//*[@id="root"]//*');
	$assert($elements instanceof DOMNodeList && $elements->length > 0, 'Expected the staff response fragment to render DOM elements.');
	foreach ($elements as $element) {
		$assert($element instanceof DOMElement, 'Expected DOM elements in the staff response fragment.');
		$tag = strtolower($element->tagName);
		$assert(array_key_exists($tag, $allowedAttributesByTag), 'Unexpected element in the staff response fragment: ' . $tag);
		$allowedAttributes = $allowedAttributesByTag[$tag];
		foreach ($element->attributes as $attribute) {
			$assert($attribute instanceof DOMAttr, 'Expected DOM attributes in the staff response fragment.');
			$assert(in_array($attribute->name, $allowedAttributes, true), 'Unexpected attribute on <' . $tag . '> in the staff response fragment: ' . $attribute->name);
		}
	}

	$templateOptions = $fullXpath->query('//select[@name="vms_staffing_template_id"]/option');
	$assert($templateOptions instanceof DOMNodeList && $templateOptions->length === 3, 'Staff response fragment should retain the placeholder plus both normalized template options.');
	$selectedOptions = $fullXpath->query('//select[@name="vms_staffing_template_id"]/option[@selected]');
	$assert($selectedOptions instanceof DOMNodeList && $selectedOptions->length === 1, 'Staff response fragment should keep exactly one selected template option.');
	$assert($selectedOptions->item(0) instanceof DOMElement && $selectedOptions->item(0)->getAttribute('value') === '5', 'Staff response fragment should keep the selected applied-template ID.');
	$badgeNotes = $fullXpath->query('//span[contains(@class,"vms-ep-tax-badge-note")]');
	$assert($badgeNotes instanceof DOMNodeList && $badgeNotes->length >= 2, 'Staff response fragment should retain badge-note spans for bypass and ineligibility notes.');
	$checkboxes = $fullXpath->query('//input[@type="checkbox" and @data-vms-role-assignment-input="1"]');
	$assert($checkboxes instanceof DOMNodeList && $checkboxes->length === 3, 'Staff response fragment should retain every normalized staff assignment checkbox.');

	fwrite(STDOUT, "event plan staff output remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan staff output remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
