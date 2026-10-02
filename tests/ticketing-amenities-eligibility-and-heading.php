<?php
/** Focused Amenities eligibility normalization and global heading-color contract. */
declare(strict_types=1);

$plugin_root = dirname(__DIR__);
$rules_source = (string) file_get_contents($plugin_root . '/includes/integrations/ticketing-rules-v2.php');
$helpers_source = (string) file_get_contents($plugin_root . '/includes/helpers.php');
$settings_source = (string) file_get_contents($plugin_root . '/includes/admin/settings-page.php');
$integrity_source = (string) file_get_contents($plugin_root . '/includes/ticketing/ticket-integrity-checks.php');
$progressive_source = (string) file_get_contents($plugin_root . '/assets/vms-ticketing-progressive-ui.js');

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
function get_the_title($post_id): string { return 'Fixture product ' . (int) $post_id; }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['amenities_post_meta'][(int) $post_id][(string) $key] ?? ''; }
function bvmgr_ticketing_v2_meta_get($post_id, $key) { return get_post_meta($post_id, $key, true); }
function get_option($key, $default = false) { return $GLOBALS['amenities_options'][(string) $key] ?? $default; }
function sanitize_hex_color($value) {
	$value = trim((string) $value);
	return preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value) ? $value : null;
}

eval($extract_function($rules_source, 'bvmgr_ticketing_v2_resolve_eligibility_for_product'));
eval($extract_function($helpers_source, 'bvmgr_ticketing_ui_addons_heading_background_default'));
eval($extract_function($helpers_source, 'bvmgr_ticketing_ui_addons_heading_background'));

$entitlement = static function (bool $allow_without_ga, int $minimum): array {
	return array(
		'label' => 'Fixture Amenity',
		'eligibility' => array(
			'pool_key' => 'amenities',
			'min_ga_per_unit' => $minimum,
			'allow_without_ga' => $allow_without_ga,
		),
	);
};

$GLOBALS['amenities_post_meta'] = array();
$restricted_zero = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, $entitlement(false, 0));
$assert(empty($restricted_zero['allow_without_ga']), 'Explicit false must remain restricted.');
$assert((int) $restricted_zero['min_ga_per_unit'] === 1, 'Explicit false plus zero must normalize to one qualifying admission per unit.');

$unrestricted_zero = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, $entitlement(true, 0));
$assert(!empty($unrestricted_zero['allow_without_ga']), 'Explicit true must remain unrestricted.');
$assert((int) $unrestricted_zero['min_ga_per_unit'] === 0, 'Unrestricted eligibility must expose no effective admission minimum.');

$restricted_positive = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, $entitlement(false, 3));
$assert(empty($restricted_positive['allow_without_ga']), 'Positive ratio must remain restricted.');
$assert((int) $restricted_positive['min_ga_per_unit'] === 3, 'Positive ratio must remain unchanged.');

$unrestricted_positive = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, $entitlement(true, 3));
$assert(!empty($unrestricted_positive['allow_without_ga']), 'Explicit true must override a stale positive minimum.');
$assert((int) $unrestricted_positive['min_ga_per_unit'] === 0, 'Explicit true must normalize the effective client minimum to zero.');

$legacy_unspecified = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, array('eligibility' => array('min_ga_per_unit' => 0)));
$assert(!empty($legacy_unspecified['allow_without_ga']), 'An unspecified legacy zero minimum must retain unrestricted compatibility behavior.');

$GLOBALS['amenities_post_meta'][500]['_sr_addon_qualifier'] = 'yes';
$explicit_restriction_beats_legacy = bvmgr_ticketing_v2_resolve_eligibility_for_product(500, 42, $entitlement(false, 0));
$assert(empty($explicit_restriction_beats_legacy['allow_without_ga']) && (int) $explicit_restriction_beats_legacy['min_ga_per_unit'] === 1, 'Explicit operator restriction must beat legacy qualifier metadata.');

$assert(strpos($rules_source, '$required = $pool_min * max(1, absint($quantity));') !== false, 'Non-pooled server add-to-cart validation must enforce the effective ratio.');
$assert(strpos($integrity_source, "array_key_exists('allow_without_ga', \$eligibility)") !== false, 'Ticket integrity must recognize an explicit false/zero eligibility rule as requiring admission.');

$GLOBALS['amenities_options']['vms_settings'] = array('ticket_ui_addons_heading_background' => '#1A2b3C');
$assert(bvmgr_ticketing_ui_addons_heading_background() === '#1A2b3C', 'Valid custom heading color must be returned.');
$GLOBALS['amenities_options']['vms_settings'] = array('ticket_ui_addons_heading_background' => 'not-a-color');
$assert(bvmgr_ticketing_ui_addons_heading_background() === '', 'Invalid heading color must fail closed to CSS fallback.');
$GLOBALS['amenities_options']['vms_settings'] = array();
$assert(bvmgr_ticketing_ui_addons_heading_background() === '', 'Unset heading color must leave the CSS fallback authoritative.');
$assert(bvmgr_ticketing_ui_addons_heading_background_default() === '#f2f2f3', 'Admin Default control must match the CSS fallback color.');

foreach (array(
	'ticket_ui_addons_heading_background',
	'Add-on heading background color',
	'sanitize_hex_color',
	'wp-color-picker',
	'data-default-color',
) as $needle) {
	$assert(strpos($settings_source, $needle) !== false, 'Settings must retain ' . $needle . '.');
}
$assert(strpos($rules_source, "'addonSectionHeadingBackground'") !== false, 'Public Ticketing config must expose the sanitized global heading color.');
$assert(strpos($progressive_source, "--vms-amenities-heading-bg") !== false && strpos($progressive_source, 'readableTextColor') !== false, 'Progressive Amenities must apply custom properties with automatic text contrast.');
$assert(strpos($settings_source . $helpers_source . $rules_source, '_vms_ticket_ui_addons_heading_background') === false, 'Amenities heading color must not gain a per-event override.');

foreach (array(
	'assets/css/ticketing-front/90-ticket-progressive-ui.css',
	'assets/css/vms-ticketing-front.css',
	'assets/css/vms-entitlements-public.css',
) as $relative_path) {
	$css = (string) file_get_contents($plugin_root . '/' . $relative_path);
	$assert(strpos($css, '--vms-amenities-heading-bg: #f2f2f3;') !== false, $relative_path . ' must retain the safe default background.');
	$assert(strpos($css, 'background: var(--vms-amenities-heading-bg)') !== false, $relative_path . ' must scope the configured color to the Amenities heading.');
}

fwrite(STDOUT, $checks . " focused Amenities eligibility and heading-color assertions passed.\n");
