<?php
/** Focused Amenities configuration and visual-restoration contract. */
declare(strict_types=1);

$plugin_root = dirname(__DIR__);
$rules_source = (string) file_get_contents($plugin_root . '/includes/integrations/ticketing-rules-v2.php');
$helpers_source = (string) file_get_contents($plugin_root . '/includes/helpers.php');
$settings_source = (string) file_get_contents($plugin_root . '/includes/admin/settings-page.php');
$progressive_source = (string) file_get_contents($plugin_root . '/assets/vms-ticketing-progressive-ui.js');
$picker_source = (string) file_get_contents($plugin_root . '/assets/js/vms-settings-color-picker.js');

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$checks++;
};

$extract_function = static function (string $source, string $name): string {
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		throw new RuntimeException('Could not find function ' . $name);
	}
	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Could not find function body for ' . $name);
	}
	$depth = 0;
	$length = strlen($source);
	for ($offset = $brace; $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
		} elseif ($source[$offset] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $offset - $start + 1);
			}
		}
	}
	throw new RuntimeException('Unbalanced function body for ' . $name);
};

function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['amenities_post_meta'][(int) $post_id][(string) $key] ?? ''; }
function get_option($key, $default = false) { return $GLOBALS['amenities_options'][(string) $key] ?? $default; }
function sanitize_hex_color($value) {
	$value = trim((string) $value);
	return preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value) ? $value : null;
}
function __($text, $domain = null): string { return (string) $text; }
function esc_html($text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function wpautop($text): string { return '<p>' . trim((string) $text) . '</p>'; }
function wp_kses_post($text): string { return (string) $text; }
function wp_strip_all_tags($text): string { return strip_tags((string) $text); }

foreach (array(
	'bvmgr_ticketing_ui_help_default_text',
	'bvmgr_ticketing_ui_addons_section_heading_default',
	'bvmgr_ticketing_ui_addons_section_subtext_default',
	'bvmgr_ticketing_ui_addons_section_heading',
	'bvmgr_ticketing_ui_addons_section_subtext',
	'bvmgr_ticketing_ui_addons_heading_background_default',
	'bvmgr_ticketing_ui_addons_heading_background',
	'bvmgr_ticketing_ui_addons_section_heading_effective',
	'bvmgr_ticketing_ui_addons_section_subtext_effective',
	'bvmgr_ticketing_ui_help_settings_key',
	'bvmgr_ticketing_ui_help_meta_key',
	'bvmgr_ticketing_ui_help_global_text',
	'bvmgr_ticketing_ui_help_effective_text',
	'bvmgr_ticketing_ui_help_has_event_override',
	'bvmgr_ticketing_ui_help_global_enabled',
	'bvmgr_ticketing_ui_help_should_render',
) as $function_name) {
	eval($extract_function($helpers_source, $function_name));
}

$GLOBALS['amenities_options']['vms_settings'] = array(
	'ticket_ui_addons_heading' => 'Global Add-ons',
	'ticket_ui_addons_subtext' => 'Global add-on guidance.',
	'ticket_ui_help_addons_enabled' => 1,
	'ticket_ui_help_addons_default' => '<p>Global help text.</p>',
	'ticket_ui_addons_heading_background' => '#DdA6A6',
);
$GLOBALS['amenities_post_meta'][707] = array();
$assert(bvmgr_ticketing_ui_addons_section_heading_effective(707) === 'Global Add-ons', 'Global heading must be used when no Event Plan override exists.');
$assert(bvmgr_ticketing_ui_addons_section_subtext_effective(707) === 'Global add-on guidance.', 'Global subtext must be used when no Event Plan override exists.');
$assert(bvmgr_ticketing_ui_help_effective_text(707, 'addons') === '<p>Global help text.</p>', 'Saved global help text must be effective without an Event Plan override.');
$assert(bvmgr_ticketing_ui_help_should_render(707, 'addons'), 'Enabled global help text must be visible.');
$assert(bvmgr_ticketing_ui_addons_heading_background() === '#DdA6A6', 'Valid configured heading color must survive safe validation.');

$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_addons_heading_override'] = 'Event &amp; Extras';
$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_addons_subtext_override'] = 'Event-specific add-on guidance.';
$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_help_addons_override'] = '<p>Event-specific help.</p>';
$assert(bvmgr_ticketing_ui_addons_section_heading_effective(707) === 'Event & Extras', 'Event heading override must beat the configured global heading.');
$assert(bvmgr_ticketing_ui_addons_section_subtext_effective(707) === 'Event-specific add-on guidance.', 'Event subtext override must beat the configured global subtext.');
$assert(bvmgr_ticketing_ui_help_effective_text(707, 'addons') === '<p>Event-specific help.</p>', 'Event help override must beat the configured global help.');

$GLOBALS['amenities_options']['vms_settings']['ticket_ui_help_addons_enabled'] = 0;
$assert(bvmgr_ticketing_ui_help_should_render(707, 'addons'), 'An Event Plan help override must remain visible when global help is disabled.');
$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_help_addons_override'] = '';
$assert(!bvmgr_ticketing_ui_help_should_render(707, 'addons'), 'Disabled global help with no Event Plan override must remain hidden.');

$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_addons_heading_override'] = '   ';
$GLOBALS['amenities_post_meta'][707]['_vms_ticket_ui_addons_subtext_override'] = "\t";
$assert(bvmgr_ticketing_ui_addons_section_heading_effective(707) === 'Global Add-ons', 'Cleared Event Plan heading must fall back to the configured global heading.');
$assert(bvmgr_ticketing_ui_addons_section_subtext_effective(707) === 'Global add-on guidance.', 'Cleared Event Plan subtext must fall back to the configured global subtext.');

$GLOBALS['amenities_options']['vms_settings'] = array('ticket_ui_addons_heading_background' => 'not-a-color');
$assert(bvmgr_ticketing_ui_addons_heading_background() === '', 'Invalid heading color must fail closed to the CSS fallback.');
$GLOBALS['amenities_options']['vms_settings'] = array();
$assert(bvmgr_ticketing_ui_addons_section_heading_effective(707) === 'Fire Pits & Tables', 'Missing Event Plan and global heading values must use the built-in fallback.');
$assert(bvmgr_ticketing_ui_addons_section_subtext_effective(707) === 'Click here to add a fire pit or table to your order.', 'Missing Event Plan and global subtext values must use the built-in fallback.');
$assert(bvmgr_ticketing_ui_addons_heading_background_default() === '#f2f2f3', 'Color-picker default must match the public CSS fallback.');

foreach (array(
	"bvmgr_ticketing_ui_addons_section_heading_effective((int) \$plan_id_for_event)",
	"bvmgr_ticketing_ui_addons_section_subtext_effective((int) \$plan_id_for_event)",
	"bvmgr_ticketing_ui_help_effective_text((int) \$plan_id_for_event, 'addons')",
	"'addonSectionHeadingBackground'",
) as $needle) {
	$assert(strpos($rules_source, $needle) !== false, 'Public localized config must retain ' . $needle . '.');
}
$assert(strpos($rules_source, "'addonSectionHeading' => __('Amenities', 'backstage-venue-manager')") === false, 'Public localized config must not hardcode Amenities as the authoritative heading.');
$assert(strpos($rules_source, "'addonSectionSubtext' => __('Make your night more comfortable.', 'backstage-venue-manager')") === false, 'Public localized config must not hardcode the regressed Amenities subtext.');
$assert(strpos($settings_source, "sanitize_hex_color((string) \$input['ticket_ui_addons_heading_background'])") !== false, 'Settings must sanitize the saved heading color.');
$assert(strpos($settings_source, "array('wp-color-picker')") !== false && strpos($picker_source, 'wpColorPicker') !== false, 'Settings must load the external WordPress color-picker integration.');
$assert(strpos($progressive_source, 'readableTextColor') !== false && strpos($progressive_source, "setProperty('--vms-amenities-heading-fg'") !== false, 'Progressive Amenities must calculate and apply readable foreground contrast.');
$assert(strpos($progressive_source, "hasAddons && !addonsSection.hasAttribute('data-vms-user-toggled')") !== false, 'Eligible Amenities must default open without overriding a manual toggle.');

foreach (array(
	'assets/css/ticketing-front/90-ticket-progressive-ui.css',
	'assets/css/vms-ticketing-front.css',
	'assets/css/vms-entitlements-public.css',
) as $relative_path) {
	$css = (string) file_get_contents($plugin_root . '/' . $relative_path);
	$assert(strpos($css, '--vms-amenities-heading-bg: #f2f2f3;') !== false, $relative_path . ' must retain the safe default background.');
	$assert(strpos($css, 'background: var(--vms-amenities-heading-bg)') !== false, $relative_path . ' must scope the configured color to the Amenities heading.');
}

fwrite(STDOUT, $checks . " focused Amenities visual-restoration assertions passed.\n");
