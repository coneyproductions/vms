<?php
declare(strict_types=1);

if (!defined('DOING_AJAX')) {
	define('DOING_AJAX', true);
}
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

final class VMS_Comp_Options_Test_Ajax_Exit extends RuntimeException
{
}

function vms_comp_options_test_wp_die_handler($message = '', $title = '', $args = array()): void
{
	unset($title, $args);

	if (is_scalar($message)) {
		throw new VMS_Comp_Options_Test_Ajax_Exit((string) $message);
	}

	throw new VMS_Comp_Options_Test_Ajax_Exit('wp_die');
}

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$compensationPath = $pluginRoot . '/includes/cpt/event-plans/partials/compensation.php';
$compensationAssetPath = $pluginRoot . '/assets/js/vms-event-plan-compensation.js';

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

$createdPosts = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();
$originalUserId = get_current_user_id();
$originalHolidays = get_option('vms_holidays', '__vms_comp_options_missing__');

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$cleanup = static function () use (&$createdPosts, &$originalPost, &$originalGet, &$originalRequest, &$originalUserId, &$originalHolidays): void {
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}

	if ($originalHolidays === '__vms_comp_options_missing__') {
		delete_option('vms_holidays');
	} else {
		update_option('vms_holidays', $originalHolidays, false);
	}

	wp_set_current_user($originalUserId);
	$_POST = $originalPost;
	$_GET = $originalGet;
	$_REQUEST = $originalRequest;
};

try {
	wp_set_current_user(1);
	$adminUserIds = get_users(array(
		'role' => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	));
	$adminUserId = !empty($adminUserIds) ? (int) $adminUserIds[0] : 1;
	wp_set_current_user($adminUserId);
	$assert(current_user_can('manage_options'), 'Expected test user to have manage_options.');

	foreach (array('vms_venue', 'vms_vendor', 'vms_comp_package') as $postType) {
		if (!post_type_exists($postType)) {
			register_post_type($postType, array(
				'public' => false,
				'show_ui' => true,
				'label' => $postType,
			));
		}
	}

	$dieHandlerFilter = static function (): string {
		return 'vms_comp_options_test_wp_die_handler';
	};
	add_filter('wp_die_handler', $dieHandlerFilter);
	add_filter('wp_die_ajax_handler', $dieHandlerFilter);

	$eventPlansSource = $readFile($eventPlansPath);
	$compensationSource = $readFile($compensationPath);
	$compensationAssetSource = $readFile($compensationAssetPath);

	$assert(false !== has_action('wp_ajax_vms_get_event_plan_comp_options'), 'Expected compensation-options AJAX hook to remain registered.');
	$assert(false === has_action('wp_ajax_nopriv_vms_get_event_plan_comp_options'), 'Compensation-options AJAX endpoint should not expose an unauthenticated hook.');
	$assert(strpos($eventPlansSource, "add_action('wp_ajax_vms_get_event_plan_comp_options', array(\$this, 'ajax_get_event_plan_comp_options'));") !== false, 'Event Plans should register the exact authenticated compensation-options AJAX hook.');
	$assert(strpos($eventPlansSource, 'wp_ajax_nopriv_vms_get_event_plan_comp_options') === false, 'Event Plans should not register a nopriv compensation-options AJAX hook.');
	$assert(strpos($eventPlansSource, "check_ajax_referer('vms_comp_options', 'nonce');") !== false, 'Compensation-options AJAX handler should keep the exact nonce action and request field.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Not allowed'), 403);") !== false, 'Compensation-options AJAX handler should retain the exact capability failure response.');
	$assert(strpos($eventPlansSource, "wp_send_json_error(array('message' => 'Comp options helper not loaded'), 500);") !== false, 'Compensation-options AJAX handler should retain the exact helper-missing response.');
	$assert(strpos($eventPlansSource, '$selected_opt = isset($_POST[\'selected_opt\']) ? sanitize_text_field(wp_unslash($_POST[\'selected_opt\'])) : \'\';') !== false, 'Compensation-options AJAX handler should continue to sanitize selected_opt with wp_unslash() + sanitize_text_field().');
	$assert(strpos($eventPlansSource, '$event_date = isset($_POST[\'event_date\']) ? sanitize_text_field(wp_unslash($_POST[\'event_date\'])) : \'\';') !== false, 'Compensation-options AJAX handler should continue to sanitize event_date with wp_unslash() + sanitize_text_field().');
	$assert(strpos($eventPlansSource, '$venue_id   = isset($_POST[\'venue_id\']) ? absint($_POST[\'venue_id\']) : 0;') !== false, 'Compensation-options AJAX handler should continue to normalize venue_id with absint().');
	$assert(strpos($eventPlansSource, '$vendor_id  = isset($_POST[\'vendor_id\']) ? absint($_POST[\'vendor_id\']) : 0;') !== false, 'Compensation-options AJAX handler should continue to normalize vendor_id with absint().');
	$assert(strpos($eventPlansSource, '$html = $this->render_event_plan_compensation_options_response_html($opts, 0, $selected_opt);') !== false, 'Compensation-options AJAX handler should route the html response field through the family-specific renderer.');
	$assert(strpos($eventPlansSource, 'render_comp_option_tiles_html') === false, 'The old generic compensation-options renderer should be removed.');
	$assert(strpos($compensationSource, 'id="vms-comp-options" data-nonce=') !== false, 'Compensation partial should retain the comp-options wrapper and nonce attribute.');
	$assert(strpos($compensationSource, 'render_event_plan_compensation_options_response_html') !== false, 'Compensation partial should use the family-specific response renderer.');

	$methodMatched = preg_match(
		'~public function ajax_get_event_plan_comp_options\(\): void\s*\{(?P<body>.*?)^\s*\}\s*\n\s*public function ajax_load_event_plan_admin_section~sm',
		$eventPlansSource,
		$methodMatch
	);
	$assert($methodMatched === 1, 'Failed to isolate ajax_get_event_plan_comp_options() source.');
	$ajaxMethodSource = (string) $methodMatch['body'];
	$assert(substr_count($ajaxMethodSource, 'current_user_can(') === 1, 'Compensation-options AJAX handler should retain exactly one capability check.');
	$assert(substr_count($ajaxMethodSource, 'check_ajax_referer(') === 1, 'Compensation-options AJAX handler should retain exactly one nonce check.');
	$assert(substr_count($ajaxMethodSource, 'vms_get_event_plan_comp_options(') === 1, 'Compensation-options AJAX handler should resolve the provider exactly once.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_success(') === 1, 'Compensation-options AJAX handler should write exactly one success response.');
	$assert(substr_count($ajaxMethodSource, 'wp_send_json_error(') === 2, 'Compensation-options AJAX handler should keep the exact two explicit JSON error branches.');
	$assert(strpos($ajaxMethodSource, 'update_post_meta(') === false && strpos($ajaxMethodSource, 'delete_post_meta(') === false, 'Compensation-options AJAX handler should not mutate database state.');
	$assert(strpos($ajaxMethodSource, 'wp_kses_post(') === false, 'Compensation-options AJAX handler should not use wp_kses_post().');
	$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $ajaxMethodSource), 'Compensation-options AJAX handler should not use the broad post allowlist.');

	$rendererMatched = preg_match(
		'~private function render_event_plan_compensation_options_response_html\(array \$opts, int \$current_pkg_id = 0, string \$selected_opt = ""\): string\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function render_event_plan_compensation_package_empty_state_html~sm',
		$eventPlansSource,
		$rendererMatch
	);
	$assert($rendererMatched === 1, 'Failed to isolate the compensation-options response renderer source.');
	$rendererSource = (string) $rendererMatch['body'];
	$assert(strpos($rendererSource, 'render_event_plan_compensation_default_option_tile_html') !== false, 'Compensation-options response renderer should fan out to the dedicated default-tile renderer.');
	$assert(strpos($rendererSource, 'render_event_plan_compensation_package_option_tile_html') !== false, 'Compensation-options response renderer should fan out to the dedicated package-tile renderer.');
	$assert(strpos($rendererSource, 'render_event_plan_compensation_package_empty_state_html') !== false, 'Compensation-options response renderer should route the package empty-state through its dedicated helper.');
	$assert(strpos($rendererSource, 'get_post_meta(') === false && strpos($rendererSource, 'get_posts(') === false && strpos($rendererSource, 'update_post_meta(') === false, 'Compensation-options response renderer should accept only already resolved data and should not perform provider reads or mutations.');
	$assert(strpos($rendererSource, 'wp_kses_post(') === false && strpos($rendererSource, 'wp_kses(') === false, 'Compensation-options response renderer should rely on direct escaping rather than a local KSES contract.');

	foreach (array(
		"form.append('action', 'vms_get_event_plan_comp_options');",
		"form.append('nonce', wrap.getAttribute('data-nonce') || '');",
		"form.append('venue_id', venueSel.value || '');",
		"form.append('event_date', dateInp.value || '');",
		"form.append('vendor_id', bandSel ? (bandSel.value || '') : '');",
		"form.append('selected_opt', selInp ? (selInp.value || '') : '');",
		"if (!data || !data.success || !data.data || typeof data.data.html !== 'string') return;",
		'wrap.innerHTML = data.data.html;',
		"if (maxInp && typeof data.data.max_guarantee !== 'undefined') {",
		"document.dispatchEvent(new Event('vms_comp_options_updated'));",
		"btn = target.closest('.vms-comp-opt-tile');",
		"wrap.querySelectorAll('.vms-comp-opt-tile').forEach(function (tile) {",
		"structure: btn.getAttribute('data-structure') || 'flat_fee',",
		"flat: btn.getAttribute('data-flat') || '',",
		"split: btn.getAttribute('data-split') || '',",
		"attendance_bonus_mode: btn.getAttribute('data-bonus-mode') || '',",
		"attendance_bonus_start_count: btn.getAttribute('data-bonus-start-count') || '',",
		"attendance_bonus_step_size: btn.getAttribute('data-bonus-step-size') || '',",
		"attendance_bonus_step_bonus: btn.getAttribute('data-bonus-step-bonus') || '',",
		"attendance_bonus_per_ticket_rate: btn.getAttribute('data-bonus-per-ticket-rate') || '',",
		"attendance_bonus_max_bonus: btn.getAttribute('data-bonus-max-bonus') || '',",
		"commission_percent: btn.getAttribute('data-commission-percent') || '',",
		"commission_mode: btn.getAttribute('data-commission-mode') || 'artist_fee'",
	) as $jsMarker) {
		$assert(strpos($compensationAssetSource, $jsMarker) !== false, 'Compensation asset should retain the JS consumer contract marker: ' . $jsMarker);
	}

	$assert(strpos($compensationAssetSource, 'data.data.markup') === false, 'Compensation asset should not look for a renamed HTML response key.');
	$assert(strpos($compensationAssetSource, 'insertAdjacentHTML(') === false, 'Compensation asset should not switch to a different DOM insertion method for this response family.');
	$assert(strpos($compensationAssetSource, '.html(') === false, 'Compensation asset should not switch to a jQuery html() insertion path for this response family.');

	$createPost = static function (string $postType, string $title) use ($registerPost): int {
		$postId = wp_insert_post(array(
			'post_type' => $postType,
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($postId) || (int) $postId <= 0) {
			throw new RuntimeException('Failed to create post: ' . $postType . ' / ' . $title);
		}

		return $registerPost((int) $postId);
	};

	$dispatchAjax = static function (array $payload, ?int $userId = null, bool $expectJson = true) use ($assert, $adminUserId): array {
		$prevPost = $_POST ?? array();
		$prevGet = $_GET ?? array();
		$prevRequest = $_REQUEST ?? array();
		$prevUserId = get_current_user_id();
		$effectiveUserId = $userId === null ? $adminUserId : $userId;

		$payload['action'] = 'vms_get_event_plan_comp_options';
		$_POST = $payload;
		$_GET = array();
		$_REQUEST = $payload;
		wp_set_current_user($effectiveUserId);

		$bufferLevel = ob_get_level();
		$raw = '';
		$dieMessage = '';

		try {
			ob_start();
			try {
				do_action('wp_ajax_vms_get_event_plan_comp_options');
			} catch (VMS_Comp_Options_Test_Ajax_Exit $e) {
				$dieMessage = $e->getMessage();
			}
			$raw = (string) ob_get_clean();
		} finally {
			while (ob_get_level() > $bufferLevel) {
				ob_end_clean();
			}

			wp_set_current_user($prevUserId);
			$_POST = $prevPost;
			$_GET = $prevGet;
			$_REQUEST = $prevRequest;
		}

		$decoded = null;
		if ($expectJson) {
			$decoded = json_decode(trim($raw), true);
			$assert(is_array($decoded), 'Failed to decode AJAX JSON. Raw output: ' . substr(trim($raw), 0, 200));
		}

		return array(
			'raw' => $raw,
			'decoded' => $decoded,
			'die_message' => $dieMessage,
		);
	};

	$loadHtml = static function (string $html) use ($assert): array {
		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$loaded = $doc->loadHTML('<!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>');
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$assert($loaded, 'Failed to parse compensation-options HTML fragment.');
		$assert(count($errors) === 0, 'Compensation-options HTML fragment should parse cleanly.');
		return array($doc, new DOMXPath($doc));
	};

	$getTileByOpt = static function (DOMXPath $xpath, string $opt) use ($assert): DOMElement {
		$query = sprintf('//button[@data-opt="%s"]', $opt);
		$nodes = $xpath->query($query);
		$assert($nodes instanceof DOMNodeList && $nodes->length === 1, 'Expected exactly one tile for option ' . $opt . '.');
		$node = $nodes->item(0);
		$assert($node instanceof DOMElement, 'Expected a DOMElement tile for option ' . $opt . '.');
		return $node;
	};

	$eventDate = '2026-07-12';
	$timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$dow = (int) (new DateTimeImmutable($eventDate, $timezone))->format('w');

	$venueId = $createPost('vms_venue', 'Comp Venue Alpha');
	$emptyVenueId = $createPost('vms_venue', 'Comp Venue Beta');
	$vendorId = $createPost('vms_vendor', 'Comp Vendor Prime');
	$packageId = $createPost('vms_comp_package', 'Package <svg onload=alert(1)>');
	$disabledPackageId = $createPost('vms_comp_package', 'Disabled <img src=x onerror=alert(1)>');

	$vendorMetaKey = static function (string $field, string $fallback): string {
		return function_exists('vms_meta_key')
			? (string) (vms_meta_key('vendor', $field) ?: $fallback)
			: $fallback;
	};

	update_post_meta($venueId, '_vms_default_comp_by_dow', array(
		$dow => array(
			'structure' => 'flat_fee',
			'flat_fee_amount' => '100',
			'commission_percent' => '5',
			'commission_mode' => 'artist_fee',
		),
	));
	update_post_meta($emptyVenueId, '_vms_default_comp_by_dow', array(
		$dow => array(
			'structure' => 'flat_fee',
			'flat_fee_amount' => '80',
		),
	));

	update_post_meta($vendorId, $vendorMetaKey('default_comp_structure', '_vms_default_comp_structure'), 'flat_fee');
	update_post_meta($vendorId, $vendorMetaKey('default_flat_fee_amount', '_vms_default_flat_fee_amount'), '150');
	update_post_meta($vendorId, $vendorMetaKey('default_commission_percent', '_vms_default_commission_percent'), '7');
	update_post_meta($vendorId, $vendorMetaKey('default_commission_mode', '_vms_default_commission_mode'), 'artist_fee');
	update_post_meta($vendorId, $vendorMetaKey('default_comp_by_venue_dow', '_vms_vendor_default_comp_by_venue_dow'), array(
		$venueId => array(
			$dow => array(
				'structure' => 'flat_fee',
				'flat_fee_amount' => '275',
				'commission_percent' => '12',
				'commission_mode' => 'gross',
			),
		),
	));

	update_option('vms_holidays', array(
		$venueId => array(
			$eventDate => array(
				'name' => 'Holiday <script>alert(1)</script>',
				'status' => 'open',
				'rules' => array(
					'vendor' => array(
						'structure' => 'attendance_bonus',
						'flat_fee_amount' => '325',
						'attendance_bonus_mode' => 'step',
						'attendance_bonus_start_count' => '50',
						'attendance_bonus_step_size' => '25',
						'attendance_bonus_step_bonus' => '40',
						'attendance_bonus_max_bonus' => '200',
						'commission_percent' => '15',
						'commission_mode' => 'artist_fee',
					),
				),
			),
		),
	), false);

	update_post_meta($packageId, '_vms_venue_id', $venueId);
	update_post_meta($packageId, '_vms_comp_type', 'flat_plus_split');
	update_post_meta($packageId, '_vms_flat_fee', '225');
	update_post_meta($packageId, '_vms_split_percent_artist', '70');
	update_post_meta($packageId, '_vms_commission_percent', '9');
	update_post_meta($packageId, '_vms_commission_mode', 'gross');

	update_post_meta($disabledPackageId, '_vms_venue_id', $venueId);
	update_post_meta($disabledPackageId, '_vms_comp_type', 'attendance_bonus');
	update_post_meta($disabledPackageId, '_vms_attendance_bonus_mode', 'step');

	$noVenueResponse = $dispatchAjax(array(
		'nonce' => wp_create_nonce('vms_comp_options'),
		'venue_id' => '',
		'vendor_id' => (string) $vendorId,
		'event_date' => '',
		'selected_opt' => " default:vendor \n",
	));
	$assert(!empty($noVenueResponse['decoded']['success']), 'Compensation-options AJAX should succeed without a venue selection.');
	$assert(array_keys((array) $noVenueResponse['decoded']['data']) === array('html', 'max_guarantee'), 'Compensation-options success payload should retain only html and max_guarantee.');
	$assert((float) ($noVenueResponse['decoded']['data']['max_guarantee'] ?? 0.0) === 150.0, 'Compensation-options max_guarantee should reflect the enabled global vendor default when venue/date are missing.');
	$noVenueHtml = (string) ($noVenueResponse['decoded']['data']['html'] ?? '');
	$assert(strpos($noVenueHtml, 'Select a Venue above to load packages.') !== false, 'Compensation-options HTML should retain the no-venue package prompt branch.');
	$assert(strpos($noVenueHtml, 'No Comp Packages are available for the selected venue yet.') === false, 'Compensation-options HTML should not show the venue-selected empty-state notice when no venue is selected.');
	list($noVenueDoc, $noVenueXPath) = $loadHtml($noVenueHtml);
	unset($noVenueDoc);
	$selectedVendorTile = $getTileByOpt($noVenueXPath, 'default:vendor');
	$assert(strpos((string) $selectedVendorTile->getAttribute('class'), 'is-selected') !== false, 'Compensation-options should preserve selected_opt after sanitize_text_field() normalization.');
	$assert($selectedVendorTile->getAttribute('data-commission-percent') === '7', 'Compensation-options should preserve the vendor default commission percent attribute.');
	$assert($selectedVendorTile->getAttribute('data-commission-mode') === 'artist_fee', 'Compensation-options should preserve the vendor default commission mode attribute.');

	$holidayResponse = $dispatchAjax(array(
		'nonce' => wp_create_nonce('vms_comp_options'),
		'venue_id' => (string) $venueId,
		'vendor_id' => (string) $vendorId,
		'event_date' => $eventDate,
		'selected_opt' => 'default:holiday',
	));
	$assert(!empty($holidayResponse['decoded']['success']), 'Compensation-options AJAX should succeed for the complete venue/date/vendor branch.');
	$assert((float) ($holidayResponse['decoded']['data']['max_guarantee'] ?? 0.0) === 325.0, 'Compensation-options max_guarantee should equal the highest enabled guaranteed amount.');
	$holidayHtml = (string) ($holidayResponse['decoded']['data']['html'] ?? '');
	$assert(strpos($holidayHtml, 'Color scale: lower guaranteed pay -&gt; higher guaranteed pay.') !== false, 'Compensation-options HTML should retain the scale legend when multiple guarantees exist.');
	$assert(strpos($holidayHtml, 'Package &lt;svg onload=alert(1)&gt;') !== false, 'Compensation-options HTML should escape package titles before emitting them.');
	$assert(strpos($holidayHtml, 'Holiday &lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Compensation-options HTML should escape holiday labels before emitting them.');

	list($holidayDoc, $holidayXPath) = $loadHtml($holidayHtml);
	$allowedTags = array(
		'div' => array('class'),
		'strong' => array(),
		'button' => array(
			'type',
			'class',
			'data-opt-kind',
			'data-opt-key',
			'data-opt-id',
			'data-opt',
			'data-structure',
			'data-flat',
			'data-split',
			'data-bonus-mode',
			'data-bonus-start-count',
			'data-bonus-step-size',
			'data-bonus-step-bonus',
			'data-bonus-per-ticket-rate',
			'data-bonus-max-bonus',
			'data-commission-percent',
			'data-commission-mode',
			'data-package-id',
			'disabled',
		),
		'p' => array(),
		'em' => array(),
	);

	$nodes = $holidayXPath->query('//*');
	$assert($nodes instanceof DOMNodeList, 'Expected a DOMNodeList for fragment inspection.');
	foreach ($nodes as $node) {
		if (!$node instanceof DOMElement || $node->tagName === 'html' || $node->tagName === 'body') {
			continue;
		}
		if ($node->tagName === 'div' && $node->getAttribute('id') === 'root') {
			continue;
		}

		$allowedAttrs = $allowedTags[$node->tagName] ?? null;
		$assert(is_array($allowedAttrs), 'Unexpected element in compensation-options fragment: ' . $node->tagName);
		$allowedAttrMap = array_fill_keys($allowedAttrs, true);
		if ($node->hasAttributes()) {
			foreach ($node->attributes as $attribute) {
				$assert(isset($allowedAttrMap[$attribute->nodeName]), 'Unexpected attribute ' . $attribute->nodeName . ' on ' . $node->tagName . ' in compensation-options fragment.');
			}
		}
	}

	$assert($holidayXPath->query('//script')->length === 0, 'Compensation-options fragment should not emit script elements.');
	$assert($holidayXPath->query('//svg')->length === 0, 'Compensation-options fragment should not emit svg elements.');
	$assert($holidayXPath->query('//img')->length === 0, 'Compensation-options fragment should not emit img elements.');
	$assert($holidayXPath->query('//button')->length === 5, 'Compensation-options fragment should render three default tiles plus two package tiles for the complete venue/date/vendor branch.');

	$holidayTile = $getTileByOpt($holidayXPath, 'default:holiday');
	$assert(strpos((string) $holidayTile->getAttribute('class'), 'is-selected') !== false, 'Compensation-options fragment should preserve default-tile selected state.');
	$assert($holidayTile->getAttribute('data-structure') === 'attendance_bonus', 'Holiday tile should preserve the attendance-bonus structure attribute.');
	$assert($holidayTile->getAttribute('data-flat') === '325', 'Holiday tile should preserve the normalized guaranteed amount.');
	$assert($holidayTile->getAttribute('data-bonus-mode') === 'step', 'Holiday tile should preserve the attendance bonus mode attribute.');
	$assert($holidayTile->getAttribute('data-bonus-start-count') === '50', 'Holiday tile should preserve the attendance bonus start-count attribute.');
	$assert($holidayTile->getAttribute('data-bonus-step-size') === '25', 'Holiday tile should preserve the attendance bonus step-size attribute.');
	$assert($holidayTile->getAttribute('data-bonus-step-bonus') === '40', 'Holiday tile should preserve the attendance bonus step-bonus attribute.');
	$assert($holidayTile->getAttribute('data-bonus-per-ticket-rate') === '', 'Holiday tile should preserve the empty per-ticket attribute for step-mode attendance bonuses.');
	$assert($holidayTile->getAttribute('data-bonus-max-bonus') === '200', 'Holiday tile should preserve the attendance bonus max-bonus attribute.');
	$assert($holidayTile->getAttribute('data-commission-percent') === '15', 'Holiday tile should preserve the commission percent attribute.');
	$assert($holidayTile->getAttribute('data-commission-mode') === 'artist_fee', 'Holiday tile should preserve the commission mode attribute.');
	$assert($holidayTile->getAttribute('data-package-id') === '0', 'Holiday tile should preserve package_id=0 for defaults.');

	$holidayChildren = array();
	foreach ($holidayTile->childNodes as $childNode) {
		if ($childNode instanceof DOMElement) {
			$holidayChildren[] = $childNode->getAttribute('class');
		}
	}
	$assert($holidayChildren === array(
		'vms-comp-opt-tile__title',
		'vms-comp-opt-tile__value',
		'vms-comp-opt-tile__sub',
		'vms-comp-opt-tile__badge',
	), 'Holiday tile child ordering should remain title, value, subtitle, badge.');

	$disabledTile = $getTileByOpt($holidayXPath, 'package:' . $disabledPackageId);
	$assert($disabledTile->hasAttribute('disabled'), 'Compensation-options fragment should keep disabled package tiles disabled.');
	$assert(strpos((string) $disabledTile->getAttribute('class'), 'is-disabled') !== false, 'Compensation-options fragment should keep disabled package tiles marked as disabled.');
	$assert(
		preg_match('~data-opt="package:' . preg_quote((string) $disabledPackageId, '~') . '".*?<div class="vms-comp-opt-tile__value">—</div>~s', $holidayHtml) === 1,
		'Disabled package tiles should preserve the inert dash placeholder value.'
	);

	$packageResponse = $dispatchAjax(array(
		'nonce' => wp_create_nonce('vms_comp_options'),
		'venue_id' => (string) $venueId,
		'vendor_id' => (string) $vendorId,
		'event_date' => $eventDate,
		'selected_opt' => 'package:' . $packageId,
	));
	$assert(!empty($packageResponse['decoded']['success']), 'Compensation-options AJAX should succeed when selecting a package tile.');
	list($packageDoc, $packageXPath) = $loadHtml((string) ($packageResponse['decoded']['data']['html'] ?? ''));
	unset($packageDoc);
	$packageTile = $getTileByOpt($packageXPath, 'package:' . $packageId);
	$assert(strpos((string) $packageTile->getAttribute('class'), 'is-selected') !== false, 'Compensation-options fragment should preserve package selected state.');
	$assert($packageTile->getAttribute('data-opt-kind') === 'package', 'Package tile should preserve the exact package opt kind.');
	$assert($packageTile->getAttribute('data-opt-id') === (string) $packageId, 'Package tile should preserve the exact package ID attribute.');
	$assert($packageTile->getAttribute('data-flat') === '225', 'Package tile should preserve the package guaranteed amount attribute.');
	$assert($packageTile->getAttribute('data-split') === '70', 'Package tile should preserve the package door split attribute.');
	$assert($packageTile->getAttribute('data-commission-percent') === '9', 'Package tile should preserve the package commission percent attribute.');
	$assert($packageTile->getAttribute('data-commission-mode') === 'gross', 'Package tile should preserve the package commission mode attribute.');

	$emptyVenueResponse = $dispatchAjax(array(
		'nonce' => wp_create_nonce('vms_comp_options'),
		'venue_id' => (string) $emptyVenueId,
		'vendor_id' => '',
		'event_date' => $eventDate,
		'selected_opt' => '',
	));
	$assert(!empty($emptyVenueResponse['decoded']['success']), 'Compensation-options AJAX should succeed when a venue has no packages.');
	$emptyVenueHtml = (string) ($emptyVenueResponse['decoded']['data']['html'] ?? '');
	$assert(strpos($emptyVenueHtml, 'No Comp Packages are available for the selected venue yet.') !== false, 'Compensation-options fragment should retain the venue-selected package empty-state notice.');
	$assert(strpos($emptyVenueHtml, 'Select a Venue above to load packages.') === false, 'Compensation-options fragment should not show the no-venue prompt when a venue is selected.');

	$unauthorizedResponse = $dispatchAjax(array(
		'nonce' => wp_create_nonce('vms_comp_options'),
		'venue_id' => (string) $venueId,
		'vendor_id' => (string) $vendorId,
		'event_date' => $eventDate,
	), 0);
	$assert(empty($unauthorizedResponse['decoded']['success']), 'Compensation-options AJAX should reject unauthorized requests.');
	$assert(array_keys((array) $unauthorizedResponse['decoded']['data']) === array('message'), 'Unauthorized compensation-options AJAX response should retain the exact message-only payload.');
	$assert((string) ($unauthorizedResponse['decoded']['data']['message'] ?? '') === 'Not allowed', 'Compensation-options AJAX should retain the exact unauthorized message text.');

	$invalidNonceResponse = $dispatchAjax(array(
		'nonce' => 'invalid-comp-options-nonce',
		'venue_id' => (string) $venueId,
		'vendor_id' => (string) $vendorId,
		'event_date' => $eventDate,
	), $adminUserId, false);
	$assert(
		trim((string) $invalidNonceResponse['raw']) === '-1' || (string) ($invalidNonceResponse['die_message'] ?? '') === '-1',
		'Compensation-options AJAX should retain the raw invalid-nonce AJAX failure output.'
	);

	fwrite(STDOUT, "event plan compensation-options output remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'event plan compensation-options output remediation: FAIL - ' . $e->getMessage() . "\n");
	$cleanup();
	exit(1);
}

$cleanup();
