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

if (!function_exists('add_rewrite_tag')) {
	function add_rewrite_tag($tag, $regex): bool
	{
		return true;
	}
}

if (!function_exists('add_rewrite_rule')) {
	function add_rewrite_rule($regex, $query, $position = 'bottom'): bool
	{
		return true;
	}
}

if (!function_exists('get_option')) {
	function get_option($option, $default = false)
	{
		return $default;
	}
}

if (!function_exists('flush_rewrite_rules')) {
	function flush_rewrite_rules($hard = true): bool
	{
		return true;
	}
}

if (!function_exists('update_option')) {
	function update_option($option, $value, $autoload = null): bool
	{
		return true;
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

require_once dirname(__DIR__) . '/includes/modules/availability-date-dispatch/public.php';

$pluginRoot = dirname(__DIR__);
$publicSource = file_get_contents($pluginRoot . '/includes/modules/availability-date-dispatch/public.php');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(is_string($publicSource) && $publicSource !== '', 'ADD public shell source should be readable.');
$assert(strpos($publicSource, 'function vms_add_dispatch_render_public_shell(string $headline, string $content_html): void') !== false, 'ADD public shell renderer should remain the isolated browser output owner.');
$assert(strpos($publicSource, "echo '<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">';") !== false, 'ADD public shell should continue to emit the standalone document wrapper directly.');
$assert(strpos($publicSource, "echo '</div></div></body></html>';") !== false, 'ADD public shell should continue to close the standalone document wrapper directly.');
$assert(strpos($publicSource, 'echo $content_html;') === false, 'ADD public shell should not echo the response fragment raw.');
$assert(
	preg_match('~echo\s+wp_kses\s*\(\s*\$content_html\s*,\s*vms_add_dispatch_public_response_allowed_html\s*\(\s*\)\s*\)\s*;~', $publicSource) === 1,
	'ADD public shell should apply the dedicated allowlist at the final response-fragment sink.'
);
$assert(strpos($publicSource, 'esc_html($content_html') === false, 'Completed ADD public response HTML should not be text-escaped.');
$assert(strpos($publicSource, 'wp_kses_post(') === false, 'ADD public shell output should not use wp_kses_post().');
$assert(!preg_match('~wp_kses_allowed_html\s*\(\s*[\'"]post[\'"]\s*\)~', $publicSource), 'ADD public shell output should not use the broad post allowlist.');
$assert(strpos($publicSource, 'wp_kses($html, vms_add_dispatch_public_response_allowed_html())') === false, 'ADD response assembly should not sanitize the complete fragment before the isolated final sink.');
$assert(strpos($publicSource, 'wp_kses(ob_get_clean(), vms_add_dispatch_public_response_allowed_html())') === false, 'ADD public shell contract should not be applied to buffered page output.');

foreach (array(
	"echo '<title>' . esc_html(\$headline) . '</title>';",
	"esc_html((string) (\$context['event_title'] ?? ''))",
	"esc_html(vms_add_dispatch_format_date((string) (\$context['event_date'] ?? '')))",
	"esc_html((string) \$context['venue_name'])",
	"nl2br(esc_html((string) \$request['message']))",
	"esc_url(vms_add_dispatch_build_response_url(\$response, 'available'))",
	"esc_url(vms_add_dispatch_build_response_url(\$response, 'unavailable'))",
) as $requiredEscaping) {
	$assert(strpos($publicSource, $requiredEscaping) !== false, 'ADD public shell should retain contextual escaping marker: ' . $requiredEscaping);
}

$expectedAllowedHtml = array(
	'a' => array(
		'class' => true,
		'href' => true,
	),
	'br' => array(),
	'div' => array(
		'class' => true,
	),
	'h1' => array(),
	'p' => array(
		'class' => true,
	),
	'strong' => array(),
);
$assert(vms_add_dispatch_public_response_allowed_html() === $expectedAllowedHtml, 'ADD public shell allowlist should contain only the response fragment tags and attributes.');

$unsafeHtml = '<h1>Availability Request</h1>'
	. '<div class="vms-add-meta" style="color:red" aria-label="bad"><strong>Event:</strong> Summer Kickoff</div>'
	. '<p class="vms-add-note" onclick="evil()" data-note="x">Line 1<br /><script>alert(1)</script><span>bad span</span></p>'
	. '<a class="vms-add-btn" href="https://example.test/respond?choice=available" target="_blank" rel="noopener" onclick="evil()">Yes</a>'
	. '<a class="vms-add-btn" href="javascript:alert(1)" data-action="no">No</a>'
	. '<iframe src="https://example.test/embed"></iframe><object data="https://example.test/object"></object><embed src="https://example.test/embed"></embed>'
	. '<img src="https://example.test/image.png" alt="x">';
$filtered = wp_kses($unsafeHtml, vms_add_dispatch_public_response_allowed_html());

$assert(strpos($filtered, '<h1>Availability Request</h1>') !== false, 'ADD public shell contract should preserve the heading fragment.');
$assert(strpos($filtered, '<div class="vms-add-meta"><strong>Event:</strong> Summer Kickoff</div>') !== false, 'ADD public shell contract should preserve the event summary fragment.');
$assert(strpos($filtered, '<p class="vms-add-note">Line 1<br>') !== false, 'ADD public shell contract should preserve paragraph class and line breaks.');
$assert(strpos($filtered, '<a class="vms-add-btn" href="https://example.test/respond?choice=available">Yes</a>') !== false, 'ADD public shell contract should preserve the response action link without unsupported attributes.');
$assert(strpos($filtered, '<a class="vms-add-btn">No</a>') !== false, 'ADD public shell contract should drop unsafe href protocols while preserving the safe action tag.');
foreach (array('<script', '<span', '<img', '<iframe', '<object', '<embed', 'onclick=', 'target=', 'rel=', 'style=', 'data-note=', 'data-action=', 'aria-label=') as $forbidden) {
	$assert(strpos($filtered, $forbidden) === false, 'ADD public shell contract should reject unsupported markup or attributes: ' . $forbidden);
}

$documentEscapeAttempt = '<html lang="en"><body><p class="vms-add-note">Still here</p><script>alert(1)</script><iframe src="https://example.test/embed"></iframe></body></html>';
$documentFiltered = wp_kses($documentEscapeAttempt, vms_add_dispatch_public_response_allowed_html());
$assert(strpos($documentFiltered, '<p class="vms-add-note">Still here</p>') !== false, 'The ADD public shell contract should keep only the intended inner fragment when a standalone document wrapper is attempted.');
foreach (array('<html', '<body', '<script', '<iframe') as $forbidden) {
	$assert(strpos($documentFiltered, $forbidden) === false, 'The ADD public shell contract should not permit standalone document-shell markup: ' . $forbidden);
}

$dynamicMessage = nl2br(esc_html("<strong>Vendor</strong>\n<script>alert(1)</script>"));
$assert(strpos($dynamicMessage, '&lt;strong&gt;Vendor&lt;/strong&gt;') !== false, 'Dynamic request text should remain escaped before entering the ADD public fragment.');
$assert(strpos($dynamicMessage, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Dynamic request text should stay inert even when it contains script-like input.');
$assert(strpos($dynamicMessage, '<script') === false, 'Dynamic request text should not become executable markup before the final sink.');

$sourceLines = preg_split('/\R/', $publicSource);
$allowlistUseLines = array();
foreach ($sourceLines as $line) {
	if (strpos($line, 'vms_add_dispatch_public_response_allowed_html()') === false || strpos($line, 'function vms_add_dispatch_public_response_allowed_html') !== false) {
		continue;
	}
	$allowlistUseLines[] = $line;
	$assert(
		preg_match('~wp_kses\s*\(\s*\$content_html\s*,\s*vms_add_dispatch_public_response_allowed_html\s*\(\s*\)\s*\)~', $line) === 1,
		'The ADD public response allowlist should only be applied directly to the final response fragment sink.'
	);
}
$assert(count($allowlistUseLines) === 1, 'ADD public shell should use the response allowlist exactly once outside its definition.');

fwrite(STDOUT, "ADD public shell output remediation OK.\n");
