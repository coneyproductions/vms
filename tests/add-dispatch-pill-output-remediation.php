<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('add_action')) {
	function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
	{
		return true;
	}
}

if (!function_exists('__')) {
	function __($text, $domain = 'default'): string
	{
		return (string) $text;
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? '';
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html): string
	{
		return (string) preg_replace_callback(
			'~<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>~',
			static function (array $matches) use ($allowed_html): string {
				$is_closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}
				if ($is_closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				preg_match_all(
					'~\s+([a-zA-Z0-9:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~',
					(string) ($matches[3] ?? ''),
					$attr_matches,
					PREG_SET_ORDER
				);
				foreach ($attr_matches as $attr_match) {
					$name = strtolower((string) $attr_match[1]);
					if (!array_key_exists($name, $allowed_attrs)) {
						continue;
					}

					$value = '';
					if (array_key_exists(2, $attr_match) && $attr_match[2] !== '') {
						$value = (string) $attr_match[2];
					} elseif (array_key_exists(3, $attr_match) && $attr_match[3] !== '') {
						$value = (string) $attr_match[3];
					} elseif (array_key_exists(4, $attr_match) && $attr_match[4] !== '') {
						$value = (string) $attr_match[4];
					}

					$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/admin-ui.php';

$pluginRoot = dirname(__DIR__);
$adminUiSource = file_get_contents($pluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($adminUiSource) && $adminUiSource !== '', 'ADD admin UI source should be readable.');

$expectedAllowedHtml = array(
	'span' => array(
		'class' => true,
	),
);
$assert(vms_add_dispatch_pill_allowed_html() === $expectedAllowedHtml, 'ADD pill allowlist should contain only span[class].');

$successPill = vms_add_dispatch_status_pill('available');
$assert($successPill === '<span class="vms-add-pill vms-add-pill--success">Available</span>', 'Available status should keep the success pill classes and label.');
$assert(wp_kses($successPill, vms_add_dispatch_pill_allowed_html()) === $successPill, 'The narrow contract should preserve the expected success pill span and class.');

$warningPill = vms_add_dispatch_status_pill('requested');
$assert($warningPill === '<span class="vms-add-pill vms-add-pill--warning">Requested</span>', 'Requested status should keep the warning pill classes and label.');

$dangerPill = vms_add_dispatch_status_pill('over_capacity');
$assert($dangerPill === '<span class="vms-add-pill vms-add-pill--danger">Over capacity</span>', 'Over-capacity status should keep the danger pill classes and label.');

$neutralPill = vms_add_dispatch_status_pill('full');
$assert($neutralPill === '<span class="vms-add-pill vms-add-pill--neutral">Full</span>', 'Full status should keep the neutral pill classes and label.');

$sourcePill = vms_add_dispatch_source_pill('portal_interest');
$assert($sourcePill === '<span class="vms-add-pill vms-add-pill--neutral vms-add-pill--source">Vendor Portal</span>', 'Portal-interest source should keep the source pill classes and label.');

$unknownStatusPill = vms_add_dispatch_status_pill('"><script>alert(1)</script>');
$assert($unknownStatusPill === '<span class="vms-add-pill vms-add-pill--neutral">scriptalert1script</span>', 'Malformed statuses should keep the existing sanitized fallback behavior.');
$assert(strpos($unknownStatusPill, '<script') === false && strpos($unknownStatusPill, 'onclick') === false, 'Malformed status labels should not become executable markup.');

$unknownSourcePill = vms_add_dispatch_source_pill('javascript:alert(1)');
$assert($unknownSourcePill === '<span class="vms-add-pill vms-add-pill--neutral vms-add-pill--source">ADD</span>', 'Malformed sources should keep the existing ADD fallback label.');

$unsafeHtml = '<span class="ok" onclick="evil()" onmouseover="evil()" style="color:red" id="bad" data-x="1" aria-hidden="true">Label</span><script>alert(1)</script><a href="https://example.test">Link</a><img src="x" alt="x">';
$filtered = wp_kses($unsafeHtml, vms_add_dispatch_pill_allowed_html());
$assert(strpos($filtered, '<span class="ok">Label</span>') !== false, 'The narrow contract should preserve only span[class].');
foreach (array('<script', '<a ', '<img', 'onclick=', 'onmouseover=', 'style=', ' id=', 'data-x=', 'aria-hidden=') as $forbidden) {
	$assert(strpos($filtered, $forbidden) === false, 'The narrow contract should reject unsupported markup or attributes: ' . $forbidden);
}

preg_match_all('~vms_add_dispatch_status_pill\s*\(~', $adminUiSource, $statusCallMatches);
preg_match_all('~function\s+vms_add_dispatch_status_pill\s*\(~', $adminUiSource, $statusDefinitionMatches);
preg_match_all('~wp_kses\s*\(\s*vms_add_dispatch_status_pill\s*\(~', $adminUiSource, $wrappedStatusMatches);
$statusOutputCallCount = count($statusCallMatches[0]) - count($statusDefinitionMatches[0]);
$assert($statusOutputCallCount === 8, 'ADD admin UI should keep exactly eight status-pill production output calls.');
$assert(count($wrappedStatusMatches[0]) === $statusOutputCallCount, 'Every status-pill production output call should be wrapped by wp_kses() at the pill boundary.');

preg_match_all('~vms_add_dispatch_source_pill\s*\(~', $adminUiSource, $sourceCallMatches);
preg_match_all('~function\s+vms_add_dispatch_source_pill\s*\(~', $adminUiSource, $sourceDefinitionMatches);
preg_match_all('~wp_kses\s*\(\s*vms_add_dispatch_source_pill\s*\(~', $adminUiSource, $wrappedSourceMatches);
$sourceOutputCallCount = count($sourceCallMatches[0]) - count($sourceDefinitionMatches[0]);
$assert($sourceOutputCallCount === 5, 'ADD admin UI should keep exactly five source-pill production output calls.');
$assert(count($wrappedSourceMatches[0]) === $sourceOutputCallCount, 'Every source-pill production output call should be wrapped by wp_kses() at the pill boundary.');

$assert(strpos($adminUiSource, 'esc_html(vms_add_dispatch_status_pill(') === false, 'Completed status pill fragments should not be text-escaped.');
$assert(strpos($adminUiSource, 'esc_html(vms_add_dispatch_source_pill(') === false, 'Completed source pill fragments should not be text-escaped.');
$assert(strpos($adminUiSource, 'wp_kses_post(') === false, 'ADD pill output should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $adminUiSource), 'ADD pill output should not use the broad post allowlist.');

$sourceLines = preg_split('/\R/', $adminUiSource);
foreach ($sourceLines as $line) {
	if (strpos($line, 'vms_add_dispatch_pill_allowed_html()') === false || strpos($line, 'function vms_add_dispatch_pill_allowed_html') !== false) {
		continue;
	}
	$assert(
		preg_match('~wp_kses\s*\(\s*vms_add_dispatch_(?:status|source)_pill\s*\(~', $line) === 1,
		'The pill allowlist should only be applied directly to status/source pill fragments, not larger ADD markup blocks.'
	);
}

fwrite(STDOUT, "ADD dispatch pill output remediation OK.\n");
