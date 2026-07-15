<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$timeLineupPath = $pluginRoot . '/includes/cpt/event-plans/partials/time-lineup.php';
$lineupAssetPath = $pluginRoot . '/assets/js/vms-lineup-schedule-admin.js';

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

$failure = null;

try {
	$eventPlansSource = $readFile($eventPlansPath);
	$timeLineupSource = $readFile($timeLineupPath);
	$lineupAssetSource = $readFile($lineupAssetPath);

	$assert(strpos($eventPlansSource, "add_action('wp_ajax_vms_load_event_plan_supporting_vendor_options', array(\$this, 'ajax_load_event_plan_supporting_vendor_options'));") !== false, 'Event Plans should register the exact authenticated supporting-vendor options AJAX hook.');
	$assert(strpos($eventPlansSource, 'wp_ajax_nopriv_vms_load_event_plan_supporting_vendor_options') === false, 'Event Plans should not register a nopriv supporting-vendor options AJAX hook.');
	$assert(strpos($eventPlansSource, '$post_id = isset($_POST[\'post_id\']) ? absint($_POST[\'post_id\']) : 0;') !== false, 'Supporting-vendor options AJAX handler should continue to normalize post_id with absint().');
	$assert(strpos($eventPlansSource, "check_ajax_referer('vms_event_plan_admin_section', 'nonce');") !== false, 'Supporting-vendor options AJAX handler should keep the exact nonce action and request field.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);") !== false, 'Supporting-vendor options AJAX handler should retain the exact invalid-plan response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Not allowed'), 403);") !== false, 'Supporting-vendor options AJAX handler should retain the exact capability failure response.');
	$assert(strpos($eventPlansSource, 'build_event_plan_supporting_vendor_options_response_payload') !== false, 'Supporting-vendor options AJAX handler should route the response through the family-specific payload builder.');
	$assert(strpos($eventPlansSource, 'render_event_plan_supporting_vendor_options_primary_html') !== false, 'Supporting-vendor options response should expose a family-specific primary fragment renderer.');
	$assert(strpos($eventPlansSource, 'render_event_plan_supporting_vendor_options_supporting_html') !== false, 'Supporting-vendor options response should expose a family-specific supporting fragment renderer.');

	$methodMatched = preg_match(
		'~public function ajax_load_event_plan_supporting_vendor_options\(\): void\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function build_event_plan_supporting_vendor_options_response_payload~sm',
		$eventPlansSource,
		$methodMatch
	);
	$assert($methodMatched === 1, 'Failed to isolate ajax_load_event_plan_supporting_vendor_options() source.');
	$ajaxMethodSource = (string) $methodMatch['body'];
	preg_match_all('~\\$_POST\\[\'([^\']+)\'\\]~', $ajaxMethodSource, $requestFieldMatches);
	$requestFields = array_values(array_unique($requestFieldMatches[1] ?? array()));
	sort($requestFields);
	$assert($requestFields === array('post_id'), 'Supporting-vendor options AJAX handler should read only the post_id request field directly.');
	$assert(substr_count($ajaxMethodSource, 'current_user_can(') === 1, 'Supporting-vendor options AJAX handler should retain exactly one capability check.');
	$assert(substr_count($ajaxMethodSource, 'check_ajax_referer(') === 1, 'Supporting-vendor options AJAX handler should retain exactly one nonce check.');
	$assert(substr_count($ajaxMethodSource, 'get_event_plan_meta_bundle(') === 1, 'Supporting-vendor options AJAX handler should resolve the Event Plan bundle exactly once.');
	$assert(substr_count($ajaxMethodSource, 'get_posts(') === 1, 'Supporting-vendor options AJAX handler should resolve the vendor list exactly once.');
	$assert(substr_count($ajaxMethodSource, 'build_event_plan_supporting_vendor_options_response_payload(') === 1, 'Supporting-vendor options AJAX handler should invoke the local response payload builder exactly once.');
	$assert(strpos($ajaxMethodSource, 'build_event_plan_vendor_option_context(') === false, 'Supporting-vendor options AJAX handler should no longer relay pre-rendered HTML from the shared vendor-option context.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_success(') === 1, 'Supporting-vendor options AJAX handler should write exactly one success response.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_error(') === 2, 'Supporting-vendor options AJAX handler should keep the exact two explicit JSON error branches.');
	$assert(strpos($ajaxMethodSource, "'primary_html' => \$primary_html") !== false, 'Supporting-vendor options AJAX success payload should retain the primary_html response key.');
	$assert(strpos($ajaxMethodSource, "'supporting_html' => \$supporting_html") !== false, 'Supporting-vendor options AJAX success payload should retain the supporting_html response key.');
	$assert(strpos($ajaxMethodSource, 'update_post_meta(') === false && strpos($ajaxMethodSource, 'delete_post_meta(') === false, 'Supporting-vendor options AJAX handler should not mutate database state.');
	$assert(strpos($ajaxMethodSource, 'wp_kses_post(') === false && strpos($ajaxMethodSource, 'wp_kses(') === false, 'Supporting-vendor options AJAX handler should not use KSES for this response family.');

	$builderMatched = preg_match(
		'~private function build_event_plan_supporting_vendor_options_response_payload\(int \$post_id, array \$bands, string \$event_date, int \$venue_id_effective, int \$selected_primary_vendor_id = 0\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function render_event_plan_supporting_vendor_options_primary_html~sm',
		$eventPlansSource,
		$builderMatch
	);
	$assert($builderMatched === 1, 'Failed to isolate the supporting-vendor options response payload builder source.');
	$builderSource = (string) $builderMatch['body'];
	$assert(substr_count($builderSource, 'get_event_plan_vendor_boot_summary(') === 1, 'Supporting-vendor options response payload builder should resolve the vendor boot summary exactly once.');
	$assert(strpos($builderSource, 'render_event_plan_supporting_vendor_options_primary_html') !== false, 'Supporting-vendor options response payload builder should route the primary fragment through the local primary renderer.');
	$assert(strpos($builderSource, 'render_event_plan_supporting_vendor_options_supporting_html') !== false, 'Supporting-vendor options response payload builder should route the supporting fragment through the local supporting renderer.');
	$assert(strpos($builderSource, 'build_event_plan_vendor_option_context(') === false, 'Supporting-vendor options response payload builder should stay independent from the shared vendor-option context helper.');
	$assert(strpos($builderSource, 'get_posts(') === false && strpos($builderSource, 'get_post_meta(') === false && strpos($builderSource, 'update_post_meta(') === false, 'Supporting-vendor options response payload builder should accept only already resolved data and should not perform extra provider reads or mutations.');
	$assert(strpos($builderSource, 'wp_kses_post(') === false && strpos($builderSource, 'wp_kses(') === false, 'Supporting-vendor options response payload builder should rely on direct escaping rather than a KSES contract.');

	$primaryRendererMatched = preg_match(
		'~private function render_event_plan_supporting_vendor_options_primary_html\(array \$rows, int \$selected_id\): string\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function render_event_plan_supporting_vendor_options_supporting_html~sm',
		$eventPlansSource,
		$primaryRendererMatch
	);
	$assert($primaryRendererMatched === 1, 'Failed to isolate the supporting-vendor primary fragment renderer source.');
	$primaryRendererSource = (string) $primaryRendererMatch['body'];
	$assert(strpos($primaryRendererSource, 'render_event_plan_primary_vendor_option_html($rows, $selected_id)') !== false, 'Supporting-vendor primary fragment renderer should preserve the existing primary option markup contract.');

	$supportingRendererMatched = preg_match(
		'~private function render_event_plan_supporting_vendor_options_supporting_html\(array \$rows\): string\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function event_plan_admin_section_supports_lazy_load~sm',
		$eventPlansSource,
		$supportingRendererMatch
	);
	$assert($supportingRendererMatched === 1, 'Failed to isolate the supporting-vendor supporting fragment renderer source.');
	$supportingRendererSource = (string) $supportingRendererMatch['body'];
	$assert(strpos($supportingRendererSource, 'render_event_plan_supporting_vendor_option_html($rows, 0)') !== false, 'Supporting-vendor supporting fragment renderer should preserve the existing supporting option markup contract.');

	foreach (array(
		'data-lineup-vendor-options-url="<?php echo esc_url(admin_url(\'admin-ajax.php\')); ?>"',
		'data-lineup-vendor-options-nonce="<?php echo esc_attr(wp_create_nonce(\'vms_event_plan_admin_section\')); ?>"',
		'<template id="vms-lineup-supporting-vendor-options-template">',
		'<select id="vms_band_vendor_id" name="vms_band_vendor_id" class="vms-ep-select-md" data-lineup-primary-vendor-select>',
		'data-lineup-vendor-select',
	) as $requiredMarkupMarker) {
		$assert(strpos($timeLineupSource, $requiredMarkupMarker) !== false, 'Time/lineup partial should retain the supporting-vendor options markup contract: ' . $requiredMarkupMarker);
	}

	foreach (array(
		"params.set('action', 'vms_load_event_plan_supporting_vendor_options');",
		"params.set('post_id', String(supportingVendorOptionsPostId));",
		"params.set('nonce', String(supportingVendorOptionsNonce));",
		'payload && payload.success && payload.data && payload.data.primary_html',
		'payload && payload.success && payload.data && payload.data.supporting_html',
		'supportingVendorOptionsTemplate.innerHTML = supportingHtml;',
		'selectEl.innerHTML = optionsHtml;',
		"selectEl.getAttribute('data-lineup-selected-vendor-id')",
		"fallbackOption.setAttribute('data-vendor-title', fallbackVendorTitle || fallbackOption.textContent);",
		"fallbackOption.setAttribute('data-tax-ok', '0');",
		"fallbackOption.setAttribute('data-tax-bypass-active', '0');",
		"fallbackOption.setAttribute('data-tax-bypass-until', '');",
		"fallbackOption.setAttribute('data-tax-bypass-reason', '');",
		"fallbackOption.setAttribute('data-tax-missing', '');",
		"fallbackOption.setAttribute('data-lineup-support-default-fee', fallbackDefaultFee);",
		"selectEl.setAttribute('data-lineup-vendor-options-hydrated', '1');",
	) as $requiredAssetMarker) {
		$assert(strpos($lineupAssetSource, $requiredAssetMarker) !== false, 'Lineup asset should retain the supporting-vendor options JS consumer contract marker: ' . $requiredAssetMarker);
	}

	$assert(strpos($lineupAssetSource, 'payload.data.markup') === false, 'Lineup asset should not look for a renamed supporting-vendor HTML response key.');
	$assert(strpos($lineupAssetSource, 'insertAdjacentHTML(') === false, 'Lineup asset should not switch to a different DOM insertion method for this response family.');
	$assert(strpos($lineupAssetSource, '.html(') === false, 'Lineup asset should not switch to a jQuery html() insertion path for this response family.');

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
	if (!function_exists('absint')) {
		function absint($value): int
		{
			return max(0, (int) $value);
		}
	}

	require_once $eventPlansPath;

	$controller = new VMS_Admin_Event_Plans();
	$invokePrivate = static function (object $object, string $method, array $args = array()) {
		$reflection = new ReflectionMethod($object, $method);
		return $reflection->invokeArgs($object, $args);
	};

	$loadOptionsHtml = static function (string $html, string $context) use ($assert): array {
		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$loaded = $doc->loadHTML('<!DOCTYPE html><html><body><select id="root">' . $html . '</select></body></html>');
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$assert($loaded, 'Failed to parse ' . $context . ' HTML fragment.');
		$assert(count($errors) === 0, $context . ' HTML fragment should parse cleanly.');
		return array($doc, new DOMXPath($doc));
	};

	$optionAttributes = static function (DOMElement $option): array {
		$names = array();
		foreach ($option->attributes as $attribute) {
			if ($attribute instanceof DOMAttr) {
				$names[] = $attribute->name;
			}
		}
		sort($names);
		return $names;
	};

	$primaryFragmentHtml = (string) $invokePrivate($controller, 'render_event_plan_supporting_vendor_options_primary_html', array(
		array(
			array(
				'vendor_id' => 0,
				'vendor_title' => 'Ignore me',
				'label' => 'Ignore me',
			),
			array(
				'vendor_id' => 201,
				'vendor_title' => 'Alpha <em>Lead</em>',
				'label' => 'Alpha <em>Lead</em> [T⚠]',
				'tax_ok' => '0',
				'tax_bypass_active' => '1',
				'tax_bypass_until' => '2030-01-02',
				'tax_bypass_reason' => '<script>alert(1)</script>',
				'tax_missing' => 'W-9 | EIN',
			),
			array(
				'vendor_id' => 202,
				'vendor_title' => 'Bravo & Co',
				'label' => 'Bravo & Co [T✓]',
				'tax_ok' => '1',
				'tax_bypass_active' => '0',
				'tax_bypass_until' => '',
				'tax_bypass_reason' => '',
				'tax_missing' => '',
			),
		),
		201,
	));
	$assert(strpos($primaryFragmentHtml, '<script>') === false, 'Primary supporting-vendor fragment should escape HTML-like tax bypass reasons.');

	list($primaryDoc, $primaryXpath) = $loadOptionsHtml($primaryFragmentHtml, 'supporting-vendor primary fragment');
	unset($primaryDoc);
	$primaryOptions = $primaryXpath->query('//*[@id="root"]/option');
	$assert($primaryOptions instanceof DOMNodeList && $primaryOptions->length === 3, 'Supporting-vendor primary fragment should render the placeholder plus the two valid vendor options.');
	$primaryExtraNodes = $primaryXpath->query('//*[@id="root"]/*[not(self::option)]');
	$assert($primaryExtraNodes instanceof DOMNodeList && $primaryExtraNodes->length === 0, 'Supporting-vendor primary fragment should contain only option elements.');

	$primaryPlaceholder = $primaryOptions->item(0);
	$assert($primaryPlaceholder instanceof DOMElement, 'Missing primary placeholder option.');
	$assert($primaryPlaceholder->getAttribute('value') === '', 'Primary placeholder option should use an empty value.');
	$assert(trim((string) $primaryPlaceholder->textContent) === '-- Select Primary Vendor --', 'Primary placeholder option text changed unexpectedly.');

	$primarySelected = $primaryOptions->item(1);
	$assert($primarySelected instanceof DOMElement, 'Missing rendered primary vendor option.');
	$assert($primarySelected->getAttribute('value') === '201', 'Primary fragment should preserve the vendor ID value.');
	$assert($primarySelected->getAttribute('selected') === 'selected', 'Primary fragment should preserve the selected state.');
	$assert($primarySelected->getAttribute('data-vendor-title') === 'Alpha <em>Lead</em>', 'Primary fragment should preserve the escaped vendor title attribute.');
	$assert($primarySelected->getAttribute('data-tax-ok') === '0', 'Primary fragment should preserve the tax-ok attribute.');
	$assert($primarySelected->getAttribute('data-tax-bypass-active') === '1', 'Primary fragment should preserve the tax-bypass-active attribute.');
	$assert($primarySelected->getAttribute('data-tax-bypass-until') === '2030-01-02', 'Primary fragment should preserve the tax-bypass-until attribute.');
	$assert($primarySelected->getAttribute('data-tax-bypass-reason') === '<script>alert(1)</script>', 'Primary fragment should preserve the inert tax-bypass-reason text.');
	$assert($primarySelected->getAttribute('data-tax-missing') === 'W-9 | EIN', 'Primary fragment should preserve the tax-missing attribute.');
	$assert($optionAttributes($primarySelected) === array('data-tax-bypass-active', 'data-tax-bypass-reason', 'data-tax-bypass-until', 'data-tax-missing', 'data-tax-ok', 'data-vendor-title', 'selected', 'value'), 'Primary fragment should retain the exact finite attribute inventory.');
	$assert(strpos(trim((string) $primarySelected->textContent), 'Alpha <em>Lead</em> [') === 0, 'Primary fragment should keep HTML-like label text inert.');

	$primaryUnselected = $primaryOptions->item(2);
	$assert($primaryUnselected instanceof DOMElement, 'Missing unselected primary vendor option.');
	$assert(!$primaryUnselected->hasAttribute('selected'), 'Only the chosen primary vendor should be selected.');
	$assert($primaryUnselected->getAttribute('data-vendor-title') === 'Bravo & Co', 'Primary fragment should preserve the second vendor title.');

	$supportingFragmentHtml = (string) $invokePrivate($controller, 'render_event_plan_supporting_vendor_options_supporting_html', array(
		array(
			array(
				'vendor_id' => 0,
				'vendor_title' => 'Ignore me',
				'label' => 'Ignore me',
			),
			array(
				'vendor_id' => 301,
				'vendor_title' => 'Charlie <strong>Support</strong>',
				'label' => 'Charlie <strong>Support</strong>',
				'default_fee' => '175.50',
			),
			array(
				'vendor_id' => 302,
				'vendor_title' => 'Delta Duo',
				'label' => 'Delta Duo',
				'default_fee' => '',
			),
		),
	));
	$assert(strpos($supportingFragmentHtml, '<strong>') === false, 'Supporting supporting-vendor fragment should escape HTML-like vendor labels.');

	list($supportingDoc, $supportingXpath) = $loadOptionsHtml($supportingFragmentHtml, 'supporting-vendor supporting fragment');
	unset($supportingDoc);
	$supportingOptions = $supportingXpath->query('//*[@id="root"]/option');
	$assert($supportingOptions instanceof DOMNodeList && $supportingOptions->length === 3, 'Supporting fragment should render the placeholder plus the two valid vendor options.');
	$supportingExtraNodes = $supportingXpath->query('//*[@id="root"]/*[not(self::option)]');
	$assert($supportingExtraNodes instanceof DOMNodeList && $supportingExtraNodes->length === 0, 'Supporting fragment should contain only option elements.');

	$supportingPlaceholder = $supportingOptions->item(0);
	$assert($supportingPlaceholder instanceof DOMElement, 'Missing supporting placeholder option.');
	$assert($supportingPlaceholder->getAttribute('value') === '', 'Supporting placeholder option should use an empty value.');
	$assert(trim((string) $supportingPlaceholder->textContent) === '-- Select a Vendor --', 'Supporting placeholder option text changed unexpectedly.');

	$supportingFeeOption = $supportingOptions->item(1);
	$assert($supportingFeeOption instanceof DOMElement, 'Missing rendered supporting vendor option with default fee.');
	$assert($supportingFeeOption->getAttribute('value') === '301', 'Supporting fragment should preserve the vendor ID value.');
	$assert($supportingFeeOption->getAttribute('data-vendor-title') === 'Charlie <strong>Support</strong>', 'Supporting fragment should preserve the escaped vendor title attribute.');
	$assert($supportingFeeOption->getAttribute('data-lineup-support-default-fee') === '175.50', 'Supporting fragment should preserve the default-fee attribute.');
	$assert(!$supportingFeeOption->hasAttribute('selected'), 'Supporting options response should not preselect a supporting vendor.');
	$assert($optionAttributes($supportingFeeOption) === array('data-lineup-support-default-fee', 'data-vendor-title', 'value'), 'Supporting fragment should retain the exact default-fee attribute inventory.');
	$assert(trim((string) $supportingFeeOption->textContent) === 'Charlie <strong>Support</strong>', 'Supporting fragment should keep HTML-like label text inert.');

	$supportingPlainOption = $supportingOptions->item(2);
	$assert($supportingPlainOption instanceof DOMElement, 'Missing rendered supporting vendor option without a default fee.');
	$assert(!$supportingPlainOption->hasAttribute('data-lineup-support-default-fee'), 'Supporting fragment should omit the default-fee attribute when no default exists.');
	$assert($optionAttributes($supportingPlainOption) === array('data-vendor-title', 'value'), 'Supporting fragment should retain the exact plain attribute inventory.');
} catch (Throwable $e) {
	$failure = $e;
}

if ($failure instanceof Throwable) {
	fwrite(STDERR, 'event plan supporting vendor options output remediation: FAIL - ' . $failure->getMessage() . "\n");
	exit(1);
}

fwrite(STDOUT, "event plan supporting vendor options output remediation: PASS\n");
