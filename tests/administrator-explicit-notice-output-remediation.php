<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
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

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
		$parts = parse_url($base);
		$query = array();

		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}

		foreach ((array) $args as $key => $value) {
			$query[(string) $key] = (string) $value;
		}

		$rebuilt = '';
		if (!empty($parts['scheme'])) {
			$rebuilt .= $parts['scheme'] . '://';
		}
		if (!empty($parts['host'])) {
			$rebuilt .= $parts['host'];
		}
		if (!empty($parts['path'])) {
			$rebuilt .= $parts['path'];
		}
		if ($query !== array()) {
			$rebuilt .= '?' . http_build_query($query);
		}

		return $rebuilt;
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
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
				$closing = $matches[1] === '/';
				$tag = strtolower((string) $matches[2]);
				if (!array_key_exists($tag, $allowed_html)) {
					return '';
				}

				if ($closing) {
					return '</' . $tag . '>';
				}

				$attrs = '';
				$allowed_attrs = is_array($allowed_html[$tag]) ? $allowed_html[$tag] : array();
				if ($allowed_attrs !== array()) {
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

						if ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) {
							continue;
						}

						$attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					}
				}

				return '<' . $tag . $attrs . '>';
			},
			(string) $html
		);
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/modules/status-notices/admin-ui.php';

$pluginRoot = dirname(__DIR__);
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');
$statusSource = file_get_contents($pluginRoot . '/includes/modules/status-notices/admin-ui.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($shellSource) && $shellSource !== '', 'Admin shell source should be readable.');
$assert(is_string($statusSource) && $statusSource !== '', 'Status Notices admin UI source should be readable.');

$normalizeAllowedHtml = static function (array $allowed_html): array {
	ksort($allowed_html);
	foreach ($allowed_html as $tag => $attrs) {
		if (is_array($attrs)) {
			ksort($attrs);
			$allowed_html[$tag] = $attrs;
		}
	}

	return $allowed_html;
};

$expectedAllowed = array(
	'div' => array(
		'class' => true,
	),
	'p' => array(),
);

$assert(function_exists('vms_admin_ui_explicit_notice_allowed_html'), 'Explicit notice allowlist helper should be defined.');
$assert(
	$normalizeAllowedHtml(vms_admin_ui_explicit_notice_allowed_html()) === $normalizeAllowedHtml($expectedAllowed),
	'Explicit notice allowlist should contain only div[class] and p.'
);
$assert(
	preg_match('~echo\s+wp_kses\s*\(\s*\$explicit_notices_html\s*,\s*vms_admin_ui_explicit_notice_allowed_html\s*\(\s*\)\s*\)\s*;~s', $shellSource) === 1,
	'Admin shell should apply the dedicated allowlist at the final explicit notice sink.'
);
$assert(strpos($shellSource, 'echo $explicit_notices_html;') === false, 'Admin shell should not leave a raw explicit notice echo sink.');
$assert(strpos($shellSource, 'esc_html($explicit_notices_html') === false, 'Admin shell should not text-escape the explicit notice fragment.');
$assert(strpos($shellSource, 'wp_kses_post($explicit_notices_html') === false, 'Admin shell should not use wp_kses_post() for the explicit notice sink.');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $shellSource), 'Admin shell should not use the post allowlist for the explicit notice sink.');
$assert(strpos($shellSource, 'echo \'<div class="vms-admin-shell__actions">\' . $actions_html . \'</div>\';') !== false, 'Actions sink should remain untouched.');
$assert(strpos($shellSource, 'echo $captured_notices_html;') !== false, 'Captured notice sink should remain untouched.');
$assert(strpos($shellSource, 'echo $content_html;') !== false, 'Shell content sink should remain untouched.');
$assert(strpos($shellSource, 'wp_kses($actions_html') === false, 'Dedicated explicit notice allowlist should not be applied to actions.');
$assert(strpos($shellSource, 'wp_kses($captured_notices_html') === false, 'Dedicated explicit notice allowlist should not be applied to captured notices.');
$assert(strpos($shellSource, 'wp_kses($content_html') === false, 'Dedicated explicit notice allowlist should not be applied to shell content.');

$allIncludeSource = '';
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($pluginRoot . '/includes', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
		continue;
	}

	$source = file_get_contents($file->getPathname());
	$assert(is_string($source), 'Production include source should be readable: ' . $file->getPathname());
	$allIncludeSource .= "\n" . $source;
}

$noticesCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>~', $allIncludeSource, $unusedMatches);
$statusNoticeCallbackCount = preg_match_all('~[\'"]notices_callback[\'"]\s*=>\s*[\'"]vms_status_notice_notice_bar[\'"]~', $allIncludeSource, $unusedStatusMatches);
$assert($noticesCallbackCount === 2, 'Only two production notices_callback assignments should exist.');
$assert($statusNoticeCallbackCount === 2, 'Status Notices list and edit screens should be the only production notices_callback callers.');
$assert(strpos($statusSource, 'function vms_status_notice_notice_bar(): void') !== false, 'Status Notices explicit fragment owner should keep a void callback signature.');
$assert(substr_count($statusSource, "'notices_callback' => 'vms_status_notice_notice_bar'") === 2, 'Status Notices list and edit screens should both supply the explicit notice callback.');
$noticeBarStart = strpos($statusSource, 'function vms_status_notice_notice_bar(): void');
$noticeBarEnd = strpos($statusSource, "if (!function_exists('vms_status_notice_render_list_screen'))");
$assert($noticeBarStart !== false && $noticeBarEnd !== false && $noticeBarEnd > $noticeBarStart, 'Status Notice callback body should be locatable.');
$noticeBarSource = substr($statusSource, (int) $noticeBarStart, (int) $noticeBarEnd - (int) $noticeBarStart);
$assert(strpos($noticeBarSource, 'apply_filters(') === false && strpos($noticeBarSource, 'do_action(') === false, 'Status Notice callback should not hand off explicit notice markup through hooks or filters.');
$assert(strpos($noticeBarSource, 'esc_html($message)') !== false, 'Status Notice callback should keep contextual escaping for notice text.');
$assert(strpos($noticeBarSource, '<div class="notice notice-success is-dismissible"><p>') !== false, 'Status Notice callback should keep the fixed explicit notice fragment shape.');

$_GET = array(
	'vms_status_notice_result' => 'saved',
);
ob_start();
vms_status_notice_notice_bar();
$savedNotice = (string) ob_get_clean();
$assert(
	$savedNotice === '<div class="notice notice-success is-dismissible"><p>Status Notice saved.</p></div>',
	'Status Notice callback should preserve the saved notice fragment.'
);
$assert(
	wp_kses($savedNotice, vms_admin_ui_explicit_notice_allowed_html()) === $savedNotice,
	'The explicit notice allowlist should admit the current saved notice fragment unchanged.'
);

$_GET = array(
	'vms_status_notice_result' => 'bulk_updated',
	'bulk_count' => '2',
);
ob_start();
vms_status_notice_notice_bar();
$bulkNotice = (string) ob_get_clean();
$assert(
	$bulkNotice === '<div class="notice notice-success is-dismissible"><p>2 notices updated.</p></div>',
	'Status Notice callback should preserve the bulk-updated notice fragment with inert dynamic text.'
);

$unsafeHtml = '<div class="notice notice-success is-dismissible" style="color:red" data-track="1" role="alert" onclick="alert(1)"><p class="bad" aria-live="assertive" style="font-weight:bold">Saved<script>alert(1)</script><iframe src="https://example.test"></iframe><object data="bad"></object><embed src="bad"><form action="#"><input type="text" value="x"></form><a href="https://example.test">link</a><button type="button">button</button></p></div>';
$sanitizedHtml = wp_kses($unsafeHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedHtml, '<div class="notice notice-success is-dismissible">') !== false, 'Allowed div tag and class attribute should survive.');
$assert(strpos($sanitizedHtml, '<p>') !== false, 'Allowed p tag should survive.');
$assert(strpos($sanitizedHtml, 'Saved') !== false, 'Notice text should survive sanitization.');
$assert(strpos($sanitizedHtml, '<script') === false && strpos($sanitizedHtml, '</script>') === false, 'Script tags should not survive.');
$assert(strpos($sanitizedHtml, '<iframe') === false && strpos($sanitizedHtml, '</iframe>') === false, 'Iframe tags should not survive.');
$assert(strpos($sanitizedHtml, '<object') === false && strpos($sanitizedHtml, '</object>') === false, 'Object tags should not survive.');
$assert(strpos($sanitizedHtml, '<embed') === false, 'Embed tags should not survive.');
$assert(strpos($sanitizedHtml, '<form') === false && strpos($sanitizedHtml, '</form>') === false, 'Form tags should not survive.');
$assert(strpos($sanitizedHtml, '<input') === false, 'Input tags should not survive.');
$assert(strpos($sanitizedHtml, '<a ') === false && strpos($sanitizedHtml, '</a>') === false, 'Anchor tags should not survive.');
$assert(strpos($sanitizedHtml, '<button') === false && strpos($sanitizedHtml, '</button>') === false, 'Button tags should not survive.');
$assert(preg_match('~<[^>]+\son[a-z]+\s*=~i', $sanitizedHtml) === 0, 'Inline event-handler attributes should not survive.');
$assert(stripos($sanitizedHtml, 'style=') === false, 'Style attributes should not survive.');
$assert(stripos($sanitizedHtml, 'data-track=') === false, 'Data attributes should not survive.');
$assert(stripos($sanitizedHtml, 'role=') === false, 'Role attributes should not survive.');
$assert(stripos($sanitizedHtml, 'aria-live=') === false, 'ARIA attributes should not survive.');
$assert(preg_match('~<p[^>]+\s(?:class|id|role|aria-[a-z-]+)=~i', $sanitizedHtml) === 0, 'Unapproved p attributes should not survive.');

$malformedHtml = '<div class="notice notice-success is-dismissible" data-bad="1"><p title="&quot; onmouseover=&quot;alert(1)">Broken</p></div>';
$sanitizedMalformedHtml = wp_kses($malformedHtml, vms_admin_ui_explicit_notice_allowed_html());
$assert(strpos($sanitizedMalformedHtml, 'onmouseover=') === false, 'Malformed attributes should not escape into executable attributes.');
$assert(strpos($sanitizedMalformedHtml, 'title=') === false, 'Malformed p attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, 'data-bad=') === false, 'Malformed div data attributes should not survive.');
$assert(strpos($sanitizedMalformedHtml, '<div class="notice notice-success is-dismissible"><p>Broken</p></div>') !== false, 'Malformed markup should remain inside the intended fragment contract.');

fwrite(STDOUT, "Administrator explicit notice output remediation OK.\n");
