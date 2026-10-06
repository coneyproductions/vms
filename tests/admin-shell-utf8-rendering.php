<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($value): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('esc_html')) {
	function esc_html($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('esc_textarea')) {
	function esc_textarea($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($markup, array $allowed_html): string
	{
		unset($allowed_html);
		return (string) $markup;
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';

function bvmgr_admin_shell_utf8_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function bvmgr_admin_shell_utf8_document(string $markup): DOMDocument
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$loaded = $document->loadHTML(
		'<?xml encoding="UTF-8">' . $markup,
		LIBXML_NOERROR | LIBXML_NOWARNING
	);
	bvmgr_admin_shell_utf8_assert($loaded, 'Rendered shell output could not be parsed.');
	return $document;
}

function bvmgr_admin_shell_utf8_first(DOMXPath $xpath, string $query): DOMElement
{
	$nodes = $xpath->query($query);
	$node = $nodes instanceof DOMNodeList ? $nodes->item(0) : null;
	bvmgr_admin_shell_utf8_assert($node instanceof DOMElement, 'Expected rendered element was not found: ' . $query);
	return $node;
}

function bvmgr_admin_shell_utf8_render(string $content, string $title): string
{
	$level = ob_get_level();
	ob_start();
	bvmgr_admin_ui_render_shell(
		array(
			'title' => $title,
			'subtitle' => $title,
			'shell_id' => 'utf8-shell',
		),
		static function () use ($content): void {
			echo $content;
		}
	);
	$output = (string) ob_get_clean();
	bvmgr_admin_shell_utf8_assert(ob_get_level() === $level, 'Shell rendering leaked an output buffer.');
	return $output;
}

function bvmgr_admin_shell_utf8_content(string $unicode, string $unsafe): string
{
	return '<p class="utf8-display">' . esc_html($unicode . ' ' . $unsafe) . '</p>'
		. '<input class="utf8-input" value="' . esc_attr($unicode . ' ' . $unsafe) . '">'
		. '<textarea class="utf8-textarea">' . esc_textarea($unicode . "\n" . $unsafe) . '</textarea>'
		. '<p class="utf8-entities">Existing &amp; &#8212; &#x1F600;</p>';
}

function bvmgr_admin_shell_utf8_verify_fields(string $output, string $unicode, string $unsafe): DOMXPath
{
	bvmgr_admin_shell_utf8_assert(!str_contains($output, 'CafÃ©'), 'Café was double encoded.');
	bvmgr_admin_shell_utf8_assert(!str_contains($output, 'â'), 'Smart punctuation was double encoded.');
	bvmgr_admin_shell_utf8_assert(!str_contains($output, 'ð'), 'Emoji was double encoded.');
	bvmgr_admin_shell_utf8_assert(str_contains($output, '&lt;script&gt;'), 'Escaped HTML was not retained as text.');
	bvmgr_admin_shell_utf8_assert(!str_contains($output, '&amp;amp;'), 'An existing entity was double escaped.');

	$document = bvmgr_admin_shell_utf8_document($output);
	$xpath = new DOMXPath($document);
	bvmgr_admin_shell_utf8_assert($xpath->query('//script')->length === 0, 'Escaped script text became executable markup.');
	bvmgr_admin_shell_utf8_assert(
		bvmgr_admin_shell_utf8_first($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " utf8-display ")]')->textContent === $unicode . ' ' . $unsafe,
		'Displayed UTF-8 text changed.'
	);
	bvmgr_admin_shell_utf8_assert(
		bvmgr_admin_shell_utf8_first($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " utf8-input ")]')->getAttribute('value') === $unicode . ' ' . $unsafe,
		'UTF-8 input attribute changed.'
	);
	bvmgr_admin_shell_utf8_assert(
		bvmgr_admin_shell_utf8_first($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " utf8-textarea ")]')->textContent === $unicode . "\n" . $unsafe,
		'UTF-8 textarea content changed.'
	);
	bvmgr_admin_shell_utf8_assert(
		bvmgr_admin_shell_utf8_first($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " utf8-entities ")]')->textContent === 'Existing & — 😀',
		'Existing entities changed meaning.'
	);

	return $xpath;
}

$unicode = "Café — You’ve… 😀 中文 e\u{0301}";
$unsafe = '<script>alert("x")</script> & "quoted"';
$content = bvmgr_admin_shell_utf8_content($unicode, $unsafe);

$without_notice = bvmgr_admin_shell_utf8_render($content, $unicode);
$without_notice_xpath = bvmgr_admin_shell_utf8_verify_fields($without_notice, $unicode, $unsafe);
bvmgr_admin_shell_utf8_assert(
	$without_notice_xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " vms-admin-shell__notices ")]/*')->length === 0,
	'The no-notice shell unexpectedly extracted content.'
);

$notice_text = $unicode . ' Notice & <safe>';
$notice = '<div class="notice notice-warning"><p>' . esc_html($notice_text) . '</p></div>';
$with_notice = bvmgr_admin_shell_utf8_render($notice . $content, $unicode);
$with_notice_xpath = bvmgr_admin_shell_utf8_verify_fields($with_notice, $unicode, $unsafe);
$notice_node = bvmgr_admin_shell_utf8_first(
	$with_notice_xpath,
	'//*[contains(concat(" ", normalize-space(@class), " "), " vms-admin-shell__notices ")]/*[contains(concat(" ", normalize-space(@class), " "), " notice ")]'
);
bvmgr_admin_shell_utf8_assert($notice_node->textContent === $notice_text, 'Extracted notice UTF-8 text changed.');
bvmgr_admin_shell_utf8_assert(str_contains($notice_node->getAttribute('class'), 'below-h2'), 'Extracted notice lost below-h2 normalization.');
bvmgr_admin_shell_utf8_assert(str_contains($notice_node->getAttribute('class'), 'vms-shell-notice'), 'Extracted notice lost shell normalization.');
$content_section = bvmgr_admin_shell_utf8_first(
	$with_notice_xpath,
	'//*[contains(concat(" ", normalize-space(@class), " "), " vms-admin-shell__content ")]'
);
bvmgr_admin_shell_utf8_assert(
	(new DOMXPath($content_section->ownerDocument))->query('.//*[contains(concat(" ", normalize-space(@class), " "), " notice ")]', $content_section)->length === 0,
	'Extracted notice remained in page content.'
);

echo "PASS: BVM admin shell preserves UTF-8, entities, escaped HTML, notice extraction, and output buffers.\n";
