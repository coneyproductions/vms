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

if (!function_exists('add_shortcode')) {
	function add_shortcode($tag, $callback): bool
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

if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = 'default'): string
	{
		return esc_html($text);
	}
}

if (!function_exists('_n')) {
	function _n($single, $plural, $number, $domain = 'default'): string
	{
		return (int) $number === 1 ? (string) $single : (string) $plural;
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

if (!function_exists('vms_portal_notice')) {
	function vms_portal_notice(string $type, string $msg): string
	{
		$type = ($type === 'success' || $type === 'warning') ? $type : 'error';
		return '<div class="vms-notice vms-notice-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
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

					if (($name === 'href' || $name === 'src') && !vms_staff_portal_test_safe_url($value)) {
						continue;
					}

					$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

function vms_staff_portal_test_safe_url(string $value): bool
{
	$value = trim($value);
	if ($value === '') {
		return true;
	}

	if (strpos($value, '//') === 0 || $value[0] === '/' || $value[0] === '#') {
		return true;
	}

	$scheme = (string) parse_url($value, PHP_URL_SCHEME);
	return $scheme === '' || in_array(strtolower($scheme), array('http', 'https', 'mailto'), true);
}

require_once dirname(__DIR__) . '/includes/portal/staff-portal.php';

$pluginRoot = dirname(__DIR__);
$staffPortalSource = file_get_contents($pluginRoot . '/includes/portal/staff-portal.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($staffPortalSource) && $staffPortalSource !== '', 'Staff Portal source should be readable.');

$expectedAllowedHtml = array(
	'a' => array(
		'class' => true,
		'href' => true,
		'loading' => true,
		'rel' => true,
		'target' => true,
	),
	'div' => array(
		'class' => true,
		'tabindex' => true,
	),
	'img' => array(
		'alt' => true,
		'class' => true,
		'loading' => true,
		'src' => true,
	),
	'p' => array(
		'class' => true,
	),
	'span' => array(
		'aria-hidden' => true,
		'class' => true,
	),
);
$assert(vms_staff_portal_safe_html_allowed_html() === $expectedAllowedHtml, 'Staff Portal safe-HTML allowlist should preserve the existing narrow element and attribute contract.');

$safeFragment = '<div class="vms-cal-pop" tabindex="0"><p class="vms-muted"><a class="vms-cal-pop-ticket" href="https://example.test/event" loading="lazy" rel="noopener" target="_blank">View</a><span class="vms-cal-vendor-icon" aria-hidden="true">T</span><img alt="Poster" class="poster" loading="lazy" src="https://example.test/poster.jpg"></p></div>';
$assert(vms_staff_portal_safe_html($safeFragment) === $safeFragment, 'Allowed staff portal elements and attributes should survive unchanged.');

$badge = vms_staff_portal_badge_html('complete');
$assert($badge === '<span class="vms-badge vms-badge-ok">Complete</span>', 'Tax status badge markup should keep its class and label.');
$assert(wp_kses(vms_staff_portal_safe_html($badge), vms_staff_portal_safe_html_allowed_html()) === $badge, 'Tax status badge should survive the explicit final-output contract.');

$certificationBadge = vms_staff_portal_certification_status_badge('pending_verification');
$assert($certificationBadge === '<span class="vms-badge vms-badge-warn">Pending Verification</span>', 'Certification status badge should keep its warning class and fallback label.');
$assert(wp_kses(vms_staff_portal_safe_html($certificationBadge), vms_staff_portal_safe_html_allowed_html()) === $certificationBadge, 'Certification status badge should survive the explicit final-output contract.');

$notice = vms_staff_portal_notice_html('warning', '<img src=x onerror=alert(1)>');
$assert($notice === '<div class="vms-notice vms-notice-warning">&lt;img src=x onerror=alert(1)&gt;</div>', 'Portal notices should keep escaped text inside the generated notice div.');
$assert(wp_kses($notice, vms_staff_portal_safe_html_allowed_html()) === $notice, 'Portal notices should survive the explicit final-output contract.');

$assignmentFragment = '<div class="vms-av-event-title vms-av-event-title--staff vms-public-cal"><div class="vms-av-meta-line is-trigger vms-cal-entry" tabindex="0"><div class="vms-cal-entry-vendors"><a class="vms-cal-vendor-row vms-cal-entry-vendor is-primary" href="https://example.test/show"><span class="vms-cal-vendor-icon" aria-hidden="true">S</span><span class="vms-cal-vendor-name">Show</span></a></div><div class="vms-cal-pop"><div class="vms-cal-pop-body"><a class="vms-cal-pop-media" href="https://example.test/show"><img src="https://example.test/show.jpg" alt="" loading="lazy"></a><div class="vms-cal-pop-actions"><a class="vms-cal-pop-ticket" href="https://example.test/show">View Event Page</a></div></div></div></div><span class="vms-av-meta-more">+1 more</span></div>';
$assert(vms_staff_portal_safe_html($assignmentFragment) === $assignmentFragment, 'Assignment calendar safe fragment should keep current div/a/img/span structure and attributes.');

$unsafeFragment = '<div class="ok" onclick="evil()" onload="evil()" style="color:red" id="bad" data-x="1"><a class="link" href="javascript:alert(1)" style="color:red">Bad</a><img src="javascript:alert(1)" alt="x" onerror="evil()"><span class="x" aria-hidden="true" onclick="evil()">Icon</span><script>alert(1)</script><iframe src="x"></iframe><object data="x"></object><form><input></form></div>';
$filtered = vms_staff_portal_safe_html($unsafeFragment);
foreach (array('<script', '<iframe', '<object', '<form', '<input', 'onclick=', 'onload=', 'onerror=', 'style=', ' id=', 'data-x=', 'javascript:') as $forbidden) {
	$assert(strpos($filtered, $forbidden) === false, 'Staff Portal safe-HTML contract should reject unsupported markup or attributes: ' . $forbidden);
}
$assert(strpos($filtered, '<div class="ok"><a class="link">Bad</a><img alt="x"><span class="x" aria-hidden="true">Icon</span>alert(1)</div>') !== false, 'Allowed tags should remain while unsafe attributes and protocols are stripped.');

$malformedBadge = vms_staff_portal_certification_status_badge('active" onclick="evil');
$assert(strpos($malformedBadge, 'onclick=') === false && strpos($malformedBadge, '<script') === false, 'Malformed certification statuses should not break out of the generated badge fragment.');

preg_match_all('~wp_kses\s*\(\s*vms_staff_portal_safe_html\s*\(~', $staffPortalSource, $wrappedSafeHtmlMatches);
$assert(count($wrappedSafeHtmlMatches[0]) === 5, 'Every Staff Portal safe-html final output sink should wrap the safe fragment with wp_kses().');

preg_match_all('~echo\s+wp_kses\s*\(\s*vms_staff_portal_notice_html\s*\(~', $staffPortalSource, $wrappedNoticeMatches);
$assert(count($wrappedNoticeMatches[0]) === 17, 'Every Staff Portal notice final output sink should wrap the notice fragment with wp_kses().');

foreach (array(
	'echo vms_staff_portal_safe_html(',
	'echo vms_staff_portal_notice_html(',
	'esc_html(vms_staff_portal_safe_html(',
	'esc_html(vms_staff_portal_notice_html(',
	'esc_html(vms_staff_portal_badge_html(',
	'esc_html(vms_staff_portal_certification_status_badge(',
) as $forbiddenSource) {
	$assert(strpos($staffPortalSource, $forbiddenSource) === false, 'Staff Portal source should not contain uncontracted or text-escaped safe fragment output: ' . $forbiddenSource);
}

$assert(strpos($staffPortalSource, 'wp_kses_post(') === false, 'Staff Portal safe-HTML output should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $staffPortalSource), 'Staff Portal safe-HTML output should not use the broad post allowlist.');
$assert(!preg_match('~wp_kses\s*\(\s*\$content_html\s*,\s*vms_staff_portal_safe_html_allowed_html\s*\(\s*\)\s*\)~', $staffPortalSource), 'Staff Portal shell content should not be passed through the fragment allowlist.');
$assert(!preg_match('~wp_kses\s*\(\s*ob_get_clean\s*\(\s*\)\s*,\s*vms_staff_portal_safe_html_allowed_html\s*\(\s*\)\s*\)~', $staffPortalSource), 'Complete Staff Portal buffered output should not be passed through the fragment allowlist.');

$sourceLines = preg_split('/\R/', $staffPortalSource);
foreach ($sourceLines as $line) {
	if (strpos($line, 'vms_staff_portal_safe_html_allowed_html()') === false || strpos($line, 'function vms_staff_portal_safe_html_allowed_html') !== false) {
		continue;
	}
	if (strpos($line, 'return wp_kses($html, vms_staff_portal_safe_html_allowed_html())') !== false) {
		continue;
	}
	$assert(
		preg_match('~wp_kses\s*\(\s*vms_staff_portal_(?:safe_html|notice_html)\s*\(~', $line) === 1,
		'The Staff Portal safe-HTML allowlist should only be applied directly to safe fragments, not larger portal markup.'
	);
}

fwrite(STDOUT, "Staff Portal safe HTML output remediation OK.\n");
