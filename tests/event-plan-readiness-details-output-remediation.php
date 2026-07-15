<?php
declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$eventPlansPath = $pluginRoot . '/includes/cpt/event-plans.php';
$readinessPartialPath = $pluginRoot . '/includes/cpt/event-plans/partials/readiness-details.php';
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

$failure = null;

try {
	$eventPlansSource = $readFile($eventPlansPath);
	$readinessPartialSource = $readFile($readinessPartialPath);
	$shellAssetSource = $readFile($shellAssetPath);

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
	$assert(strpos($eventPlansSource, 'build_event_plan_readiness_details_response_payload') !== false, 'Readiness-details AJAX branch should route the fragment through the family-specific payload builder.');
	$assert(strpos($eventPlansSource, "capture_event_plan_partial('readiness-details'") === false, 'Readiness-details AJAX branch should no longer relay the captured readiness-details partial directly.');

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

	$readinessBranchMatched = preg_match(
		'~if \(\$section === \'readiness_details\'\) \{(?P<body>.*?)^\s*\}\s*\n\s*wp_send_json_error\(array\(\'message\' => \'Section not implemented\.\'\), 400\);~sm',
		$ajaxMethodSource,
		$readinessBranchMatch
	);
	$assert($readinessBranchMatched === 1, 'Failed to isolate the readiness_details branch from ajax_load_event_plan_admin_section().');
	$readinessBranchSource = (string) $readinessBranchMatch['body'];
	$assert(substr_count($readinessBranchSource, 'build_event_plan_readiness_details_response_payload(') === 1, 'Readiness-details AJAX branch should invoke the local response payload builder exactly once.');
	$assert(substr_count($readinessBranchSource, 'wp_send_json_success(') === 1, 'Readiness-details AJAX branch should write exactly one success response.');
	$assert(strpos($readinessBranchSource, "'html' => \$html") !== false, 'Readiness-details AJAX branch should preserve the html response key.');
	$assert(strpos($readinessBranchSource, "'section' => \$section") !== false, 'Readiness-details AJAX branch should preserve the section response key.');
	$assert(strpos($readinessBranchSource, "capture_event_plan_partial('readiness-details'") === false, 'Readiness-details AJAX branch should remove the ambiguous captured-partial handoff.');

	$builderMatched = preg_match(
		'~private function build_event_plan_readiness_details_response_payload\(int \$post_id\): array\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function render_event_plan_readiness_details_response_html~sm',
		$eventPlansSource,
		$builderMatch
	);
	$assert($builderMatched === 1, 'Failed to isolate the readiness-details response payload builder source.');
	$builderSource = (string) $builderMatch['body'];
	$assert(substr_count($builderSource, 'get_event_plan_readiness_detail_context(') === 1, 'Readiness-details response payload builder should resolve readiness detail context exactly once.');
	$assert(substr_count($builderSource, 'render_event_plan_readiness_details_response_html(') === 1, 'Readiness-details response payload builder should render the fragment exactly once.');
	$assert(strpos($builderSource, 'capture_event_plan_partial(') === false, 'Readiness-details response payload builder should not route through a captured partial.');
	$assert(strpos($builderSource, 'get_post_meta(') === false && strpos($builderSource, 'get_posts(') === false && strpos($builderSource, 'update_post_meta(') === false, 'Readiness-details response payload builder should accept only normalized readiness data and should not perform extra reads or mutations.');

	$rendererMatched = preg_match(
		'~private function render_event_plan_readiness_details_response_html\(array \$detail_context\): string\s*\{(?P<body>.*?)^\s*\}\s*\n\s*private function render_event_plan_readiness_details_summary_rows_html~sm',
		$eventPlansSource,
		$rendererMatch
	);
	$assert($rendererMatched === 1, 'Failed to isolate the readiness-details response renderer source.');
	$rendererSource = (string) $rendererMatch['body'];
	$assert(strpos($rendererSource, 'render_event_plan_readiness_details_summary_rows_html') !== false, 'Readiness-details response renderer should route summary rows through the dedicated summary-list helper.');
	$assert(strpos($rendererSource, 'render_event_plan_readiness_details_warning_notice_html') !== false, 'Readiness-details response renderer should route warning states through the dedicated notice helper.');
	$assert(strpos($rendererSource, 'render_event_plan_readiness_details_linked_tec_text') !== false, 'Readiness-details response renderer should route linked TEC text through the dedicated helper.');
	$assert(strpos($rendererSource, 'render_event_plan_readiness_details_ticketing_text') !== false, 'Readiness-details response renderer should route ticketing text through the dedicated helper.');
	$assert(strpos($rendererSource, 'render_event_plan_readiness_details_secondary_vendor_text') !== false, 'Readiness-details response renderer should route secondary-vendor text through the dedicated helper.');
	$assert(strpos($rendererSource, 'capture_event_plan_partial(') === false, 'Readiness-details response renderer should not route through a captured partial.');
	$assert(strpos($rendererSource, 'wp_kses_post(') === false && strpos($rendererSource, 'wp_kses(') === false, 'Readiness-details response renderer should rely on direct escaping rather than a KSES contract.');
	$assert(strpos($rendererSource, 'get_post_meta(') === false && strpos($rendererSource, 'get_posts(') === false, 'Readiness-details response renderer should not perform provider or database reads.');

	foreach (array(
		'id="vms-readiness-details"',
		'data-section-key="readiness_details"',
		'data-vms-lazy-section="readiness_details"',
		'data-vms-lazy-post-id="<?php echo (int) $post->ID; ?>"',
		'data-vms-lazy-url="<?php echo esc_url(admin_url(\'admin-ajax.php\')); ?>"',
		'data-vms-lazy-nonce="<?php echo esc_attr(wp_create_nonce(\'vms_event_plan_admin_section\')); ?>"',
	) as $requiredPhpMarker) {
		$assert(strpos($eventPlansSource, $requiredPhpMarker) !== false, 'Event Plan PHP should retain the readiness lazy-section shell marker: ' . $requiredPhpMarker);
	}

	foreach (array(
		"params.set('action', 'vms_load_event_plan_admin_section');",
		"params.set('post_id', String(lazyPostId));",
		"params.set('section', lazySection);",
		"params.set('nonce', lazyNonce);",
		"if (!response.ok || !payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {",
		'body.innerHTML = payload.data.html;',
		'section.dataset.vmsLazyLoaded = \'1\';',
		'body.innerHTML =',
		'window.vmsEventPlanInitSecondaryVendors(body);',
		'window.vmsEventPlanInitStaff(body);',
	) as $requiredShellMarker) {
		$assert(strpos($shellAssetSource, $requiredShellMarker) !== false, 'Shell asset should retain the readiness lazy-load consumer marker: ' . $requiredShellMarker);
	}

	$assert(strpos($shellAssetSource, 'payload.data.markup') === false, 'Shell asset should not look for a renamed readiness HTML response key.');
	$assert(strpos($shellAssetSource, 'insertAdjacentHTML(') === false, 'Shell asset should not switch to a different DOM insertion method for readiness details.');
	$assert(strpos($shellAssetSource, '.html(') === false, 'Shell asset should not switch to a jQuery html() insertion path for readiness details.');

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

	$renderLegacyPartial = static function (array $detailContext) use ($readinessPartialPath): string {
		$vms_readiness_detail_context = $detailContext;
		ob_start();
		include $readinessPartialPath;
		return (string) ob_get_clean();
	};

	$assertNoUnexpectedAttributes = static function (DOMNodeList $nodes, array $allowedByTag) use ($assert): void {
		foreach ($nodes as $node) {
			if (!$node instanceof DOMElement) {
				continue;
			}

			$tagName = strtolower($node->tagName);
			$allowed = $allowedByTag[$tagName] ?? array();
			$attrNames = array();
			foreach ($node->attributes as $attribute) {
				if (!$attribute instanceof DOMAttr) {
					continue;
				}
				$attrNames[] = $attribute->name;
				$assert(strpos($attribute->name, 'on') !== 0, 'Readiness fragment should not allow inline event-handler attributes.');
				$assert($attribute->name !== 'style', 'Readiness fragment should not allow inline styles.');
				$assert(in_array($attribute->name, $allowed, true), 'Unexpected attribute inventory on <' . $tagName . '>: ' . $attribute->name);
			}
		}
	};

	$warningContext = array(
		'status_label' => 'Blocking <b>warnings</b> present',
		'summary_rows' => array(
			array('label' => 'Publish-blocking warnings', 'value' => '2', 'state' => 'warning'),
			array('label' => 'Linked TEC status', 'value' => 'Linked (PUBLISH)', 'state' => 'ok'),
			'ignore me',
		),
		'warning_items' => array(
			'Vendor <em>profile</em> issue',
			'<script>alert(1)</script>',
		),
		'linked_tec_summary' => array(
			'linked_tec_id' => 77,
			'linked_tec_title' => 'Summer <span>Jam</span>',
			'linked_tec_status' => 'publish',
		),
		'ticketing_summary' => array(
			'effective_ticket_count' => '7',
		),
		'add_on_summary' => array(
			'enabled_add_on_count' => '2',
		),
		'secondary_vendor_boot_summary' => array(
			'secondary_missing' => array('a'),
			'secondary_mismatch' => array('b', 'c'),
			'secondary_unqualified' => array('d'),
		),
		'readiness_boot_summary' => array(
			'secondary_vendor_count' => '5',
		),
	);

	$warningHtml = (string) $invokePrivate($controller, 'render_event_plan_readiness_details_response_html', array($warningContext));
	$legacyWarningHtml = $renderLegacyPartial($warningContext);
	$assert($describeFragment($warningHtml, 'warning renderer output') === $describeFragment($legacyWarningHtml, 'warning legacy partial output'), 'Readiness warning renderer should preserve the legacy partial markup contract.');
	$assert(strpos($warningHtml, '<script>') === false, 'Readiness warning renderer should keep HTML-like warning text inert.');

	list($warningDoc, $warningXpath, $warningRoot) = $loadFragment($warningHtml, 'warning renderer output');
	unset($warningDoc);
	$warningWrapper = $warningXpath->query('//*[@id="root"]/div')->item(0);
	$assert($warningWrapper instanceof DOMElement, 'Readiness warning renderer should return one top-level wrapper div.');
	$assert($warningWrapper->getAttribute('class') === 'vms-ep-card vms-ep-card--white vms-ep-card--readiness-details', 'Readiness warning renderer should preserve the wrapper class contract.');

	$elements = $warningXpath->query('//*[@id="root"]//*');
	$allowedTags = array('div', 'p', 'ul', 'li', 'strong');
	foreach ($elements as $element) {
		$assert($element instanceof DOMElement && in_array(strtolower($element->tagName), $allowedTags, true), 'Readiness warning renderer should emit only the finite allowed elements.');
	}
	$assertNoUnexpectedAttributes($elements, array(
		'div' => array('class'),
		'p' => array('class'),
		'ul' => array('class'),
		'li' => array(),
		'strong' => array(),
	));

	$warningParagraphs = $warningXpath->query('//*[@id="root"]//p');
	$assert($warningParagraphs instanceof DOMNodeList && $warningParagraphs->length === 5, 'Readiness warning renderer should preserve the exact paragraph count.');
	$warningDescriptionParagraphs = $warningXpath->query('//*[@id="root"]//p[@class="description"]');
	$assert($warningDescriptionParagraphs instanceof DOMNodeList && $warningDescriptionParagraphs->length === 4, 'Readiness warning renderer should preserve the exact description-paragraph inventory.');
	$summaryList = $warningXpath->query('//*[@id="root"]/div/ul[@class="vms-ep-inline-list"]')->item(0);
	$assert($summaryList instanceof DOMElement, 'Readiness warning renderer should preserve the summary list wrapper.');
	$summaryItems = $warningXpath->query('//*[@id="root"]/div/ul[@class="vms-ep-inline-list"]/li');
	$assert($summaryItems instanceof DOMNodeList && $summaryItems->length === 2, 'Readiness warning renderer should preserve only valid summary rows.');
	$warningNotice = $warningXpath->query('//*[@id="root"]/div/div[@class="notice notice-warning inline vms-notice vms-notice--warning"]')->item(0);
	$assert($warningNotice instanceof DOMElement, 'Readiness warning renderer should preserve the warning notice wrapper.');
	$warningNoticeItems = $warningXpath->query('//*[@id="root"]/div/div[@class="notice notice-warning inline vms-notice vms-notice--warning"]/ul/li');
	$assert($warningNoticeItems instanceof DOMNodeList && $warningNoticeItems->length === 2, 'Readiness warning renderer should preserve the warning-item list.');
	$assert(trim((string) $warningNoticeItems->item(0)->textContent) === 'Vendor <em>profile</em> issue', 'Readiness warning renderer should keep HTML-like warning text inert.');
	$assert(trim((string) $warningNoticeItems->item(1)->textContent) === '<script>alert(1)</script>', 'Readiness warning renderer should keep script-like warning text inert.');
	$assert(trim((string) $warningParagraphs->item(0)->textContent) === 'Blocking <b>warnings</b> present', 'Readiness warning renderer should keep HTML-like status labels inert.');
	$assert(trim((string) $warningParagraphs->item(2)->textContent) === 'Linked TEC event: Summer <span>Jam</span> (PUBLISH).', 'Readiness warning renderer should preserve the linked TEC text branch.');
	$assert(trim((string) $warningParagraphs->item(3)->textContent) === 'Configured tickets: 7. Configured add-ons: 2.', 'Readiness warning renderer should preserve the ticketing summary branch.');
	$assert(trim((string) $warningParagraphs->item(4)->textContent) === 'Secondary vendor warnings: 4. Selected secondary vendors: 5.', 'Readiness warning renderer should preserve the secondary-vendor summary branch.');
	$assert($warningXpath->query('//*[@id="root"]//a | //*[@id="root"]//button | //*[@id="root"]//table | //*[@id="root"]//img | //*[@id="root"]//script | //*[@id="root"]//style')->length === 0, 'Readiness warning renderer should not emit links, buttons, tables, media, or executable tags.');

	$allReadyContext = array(
		'status_label' => 'No blocking publish warnings',
		'summary_rows' => array(
			array('label' => 'Publish-blocking warnings', 'value' => '0', 'state' => 'ok'),
			array('label' => 'Vendor warnings', 'value' => '0', 'state' => 'ok'),
			array('label' => 'Linked TEC status', 'value' => 'Not linked', 'state' => 'info'),
			array('label' => 'Configured tickets', 'value' => '0', 'state' => 'info'),
		),
		'warning_items' => array(),
		'linked_tec_summary' => array(),
		'ticketing_summary' => array('effective_ticket_count' => 0),
		'add_on_summary' => array('enabled_add_on_count' => 0),
		'secondary_vendor_boot_summary' => array(
			'secondary_missing' => array(),
			'secondary_mismatch' => array(),
			'secondary_unqualified' => array(),
		),
		'readiness_boot_summary' => array('secondary_vendor_count' => 0),
	);

	$allReadyHtml = (string) $invokePrivate($controller, 'render_event_plan_readiness_details_response_html', array($allReadyContext));
	$legacyAllReadyHtml = $renderLegacyPartial($allReadyContext);
	$assert($describeFragment($allReadyHtml, 'all-ready renderer output') === $describeFragment($legacyAllReadyHtml, 'all-ready legacy partial output'), 'Readiness all-ready renderer should preserve the legacy partial markup contract.');
	list($allReadyDoc, $allReadyXpath) = $loadFragment($allReadyHtml, 'all-ready renderer output');
	unset($allReadyDoc);
	$allReadySuccessNotice = $allReadyXpath->query('//*[@id="root"]/div/div[@class="notice notice-success inline vms-notice"]')->item(0);
	$assert($allReadySuccessNotice instanceof DOMElement, 'Readiness all-ready renderer should preserve the success notice wrapper.');
	$assert(trim((string) $allReadySuccessNotice->textContent) === 'No blocking or vendor-warning details are currently flagged in this summary view.', 'Readiness all-ready renderer should preserve the exact all-ready notice text.');
	$assert($allReadyXpath->query('//*[@id="root"]/div/div[@class="notice notice-success inline vms-notice"]/ul')->length === 0, 'Readiness all-ready renderer should not emit a warning-item list.');
	$allReadyParagraphs = $allReadyXpath->query('//*[@id="root"]//p[@class="description"]');
	$assert($allReadyParagraphs instanceof DOMNodeList && $allReadyParagraphs->length === 4, 'Readiness all-ready renderer should preserve the description-paragraph inventory.');
	$assert(trim((string) $allReadyParagraphs->item(1)->textContent) === 'Linked TEC event: not linked.', 'Readiness all-ready renderer should preserve the not-linked branch.');

	$emptyContext = array(
		'warning_items' => array(),
		'secondary_vendor_boot_summary' => array(),
		'readiness_boot_summary' => array(),
	);
	$emptyHtml = (string) $invokePrivate($controller, 'render_event_plan_readiness_details_response_html', array($emptyContext));
	$legacyEmptyHtml = $renderLegacyPartial($emptyContext);
	$assert($describeFragment($emptyHtml, 'empty renderer output') === $describeFragment($legacyEmptyHtml, 'empty legacy partial output'), 'Readiness empty renderer should preserve the legacy partial empty-state contract.');
	list($emptyDoc, $emptyXpath) = $loadFragment($emptyHtml, 'empty renderer output');
	unset($emptyDoc);
	$assert($emptyXpath->query('//*[@id="root"]/div/ul[@class="vms-ep-inline-list"]')->length === 0, 'Readiness empty renderer should omit the summary list when no summary rows exist.');
	$assert($emptyXpath->query('//*[@id="root"]/div/div[@class="notice notice-success inline vms-notice"]')->length === 1, 'Readiness empty renderer should preserve the success notice branch.');

	$assert(strpos($readinessPartialSource, '<div class="vms-ep-card vms-ep-card--white vms-ep-card--readiness-details">') !== false, 'Legacy readiness partial should retain the original wrapper contract used for comparison.');
} catch (Throwable $e) {
	$failure = $e;
}

if ($failure instanceof Throwable) {
	fwrite(STDERR, 'event plan readiness details output remediation: FAIL - ' . $failure->getMessage() . "\n");
	exit(1);
}

fwrite(STDOUT, "event plan readiness details output remediation: PASS\n");
