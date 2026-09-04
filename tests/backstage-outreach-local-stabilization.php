<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function sanitize_html_class($value): string
{
	return (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
}

function esc_attr($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function esc_html($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function wp_kses($value, $allowed_html): string
{
	unset($allowed_html);
	return (string) $value;
}

function bvmgr_admin_ui_active_cluster(): string
{
	return 'marketing_social';
}

function bvmgr_admin_ui_render_top_nav(): void
{
	echo '<nav aria-label="Backstage Venue Manager top navigation">Backstage</nav>';
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$root = dirname(__DIR__);
$outreach_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/outreach.php');
$integration_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');
$css_source = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/css/outreach-admin.css');
$registry_source = (string) file_get_contents($root . '/includes/core/registry/admin-menu.php');
$shell_source = (string) file_get_contents($root . '/includes/admin-ui/shell.php');

$assert(strpos($outreach_source, "'shell' => true") !== false, 'Outreach must declare that its callback renders the canonical shell.');
$assert(substr_count($outreach_source, 'vms_admin_ui_render_shell(') === 1, 'The Outreach callback must retain exactly one shell-rendering path.');
$assert(strpos($registry_source, "'shell' => array_key_exists('shell', \$args) ? (bool) \$args['shell'] : false") !== false, 'The global registry shell default must remain unchanged.');

$assert(strpos($shell_source, '<?xml encoding="UTF-8" ?>') !== false, 'DOMDocument input must declare UTF-8 before parsing captured markup.');
$assert(substr_count($shell_source, 'loadHTML(') === 1, 'The shared shell must retain one audited DOMDocument parsing path.');

$notice_text = 'LOCAL ACCEPTANCE TEST — DO NOT SEND · Café & Résumé’s 🎟️';
$translated_text = 'Éxito: invitación preparada';
$markup = '<div class="notice notice-success"><p>'
	. htmlspecialchars($notice_text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
	. '</p></div><section class="payload"><p>'
	. htmlspecialchars($translated_text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
	. '</p></section>';
$notice_markup = '';
$content_markup = bvmgr_admin_ui_extract_notice_markup($markup, $notice_markup);

$rendered_text = static function (string $html): string {
	return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
};

$assert($rendered_text($notice_markup) === $notice_text, 'Extracted notices must round-trip smart punctuation, accents, ampersands, entities, and emoji.');
$assert($rendered_text($content_markup) === $translated_text, 'Non-notice translated-style content must round-trip through DOM extraction.');
$assert(strpos($notice_markup . $content_markup, '&amp;amp;') === false, 'DOM extraction must not double-encode HTML entities.');
$assert(strpos($notice_markup . $content_markup, 'â') === false && strpos($notice_markup . $content_markup, 'Ã') === false && strpos($notice_markup . $content_markup, 'Â') === false, 'DOM extraction must not introduce mojibake.');

$validation_doc = new DOMDocument('1.0', 'UTF-8');
$previous_libxml_errors = libxml_use_internal_errors(true);
$valid_markup = $validation_doc->loadHTML('<?xml encoding="UTF-8" ?><!doctype html><html><body>' . $notice_markup . $content_markup . '</body></html>');
libxml_clear_errors();
libxml_use_internal_errors($previous_libxml_errors);
$assert($valid_markup === true, 'Extracted notice and content markup must remain parseable HTML.');

foreach (array('Guest Passes', 'Data Tools', 'Marketing & Social', 'Outreach') as $page_title) {
	ob_start();
	bvmgr_admin_ui_render_shell(
		array(
			'title' => $page_title,
			'subtitle' => 'Résumé — ready',
			'shell_id' => 'test-shell',
		),
		static function () use ($page_title): void {
			echo '<div class="notice notice-warning"><p>Café &amp; crème</p></div>';
			echo '<div class="page-content">' . esc_html($page_title) . ' content</div>';
		}
	);
	$rendered_shell = (string) ob_get_clean();
	$assert(substr_count($rendered_shell, 'class="wrap vms-admin-shell"') === 1, $page_title . ' must render exactly one shell wrapper.');
	$assert(substr_count($rendered_shell, 'aria-label="Backstage Venue Manager top navigation"') === 1, $page_title . ' must render exactly one canonical navigation.');
	$assert(strpos($rendered_shell, esc_html($page_title)) !== false && strpos($rendered_text($rendered_shell), $page_title . ' content') !== false, $page_title . ' heading and content must remain present.');
	$assert(strpos($rendered_shell, 'notice-warning below-h2 vms-shell-notice') !== false, $page_title . ' notices must remain in the shell notice zone.');
	$assert(strpos($rendered_shell, 'Résumé — ready') !== false && $rendered_text($rendered_shell) !== '', $page_title . ' shell text must preserve UTF-8.');
}

$assert(strpos($css_source, "#vms-pass-claims-wrap .vms-pass-help__popover {\n  position: absolute;") !== false, 'Help popover styling must remain scoped to Outreach.');
$assert(strpos($css_source, "max-width: calc(100vw - 32px);\n  display: none;") !== false, 'Closed help popovers must not participate in document overflow.');
$assert(strpos($css_source, ".vms-pass-help.is-open .vms-pass-help__popover {\n  display: block;") !== false, 'Open help popovers must remain available to keyboard/click interaction.');
$assert(strpos($css_source, '@media (max-width: 782px)') !== false && strpos($css_source, 'position: fixed;') !== false, 'Narrow help popovers must stay viewport-contained.');
$assert(strpos($css_source, 'overflow-wrap: anywhere;') !== false && strpos($css_source, 'max-height: calc(100vh - 24px);') !== false, 'Open help content must wrap and remain vertically usable.');

$assert(strpos($integration_source, 'if (!vms_outreach_is_admin_page())') !== false, 'Outreach assets must remain gated to actual Outreach screens.');
$assert(strpos($integration_source, "filemtime(\$css_path)") !== false && strpos($integration_source, "filemtime(\$js_path)") !== false, 'Changed Outreach assets must use file fingerprints to avoid stale browser caches.');

echo 'Backstage Outreach local stabilization OK (' . $assertions . " assertions).\n";
