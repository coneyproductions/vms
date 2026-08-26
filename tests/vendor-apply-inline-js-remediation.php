<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

$GLOBALS['vms_test_options'] = array(
	'vms_turnstile_site_key' => 'site-key',
	'vms_turnstile_secret_key' => 'secret-key',
);
$GLOBALS['vms_test_scripts'] = array();

if (!class_exists('WP_User')) {
	class WP_User
	{
		public string $user_email = '';
	}
}

if (!class_exists('WP_Post')) {
	class WP_Post {}
}

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_attr__(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url(string $url): string { return $url; }
function wp_unslash($value) { return $value; }
function sanitize_email(string $email): string { return trim($email); }
function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9_-]/', '', $value) ?? ''); }
function sanitize_title(string $value): string { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''), '-'); }
function apply_filters(string $hook, $value) { unset($hook); return $value; }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_shortcode(string $tag, $callback): void { unset($tag, $callback); }
function get_option(string $option, $default = false) { return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default; }
function is_wp_error($thing): bool { return false; }
function taxonomy_exists(string $taxonomy): bool { unset($taxonomy); return false; }
function wp_json_encode($value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null'; }
function is_user_logged_in(): bool { return false; }
function current_user_can(string $capability): bool { unset($capability); return false; }
function wp_get_current_user(): WP_User { return new WP_User(); }
function vms_request_method(): string { return 'get'; }
function vms_request_read_key(array $source, string $key): string { return isset($source[$key]) && !is_array($source[$key]) ? (string) $source[$key] : ''; }
function vms_request_read_bool_flag(array $source, string $key): bool { return !empty($source[$key]); }
function vms_asset_url(string $asset_rel): string { return BVMGR_PLUGIN_URL . ltrim($asset_rel, '/'); }
function vms_asset_version_for(string $asset_rel): string { unset($asset_rel); return 'test-version'; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void { $GLOBALS['vms_test_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer'); }
function wp_nonce_field(string $action, string $name): void { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action) . '" />'; }
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
function home_url(string $path = '/'): string { return 'https://example.test' . $path; }
function get_page_by_path(string $path) { unset($path); return null; }
function get_permalink($post): string { unset($post); return 'https://example.test/permalink/'; }

require_once dirname(__DIR__) . '/includes/vendor-applications.php';

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$resetScripts = static function (): void {
	$GLOBALS['vms_test_scripts'] = array();
};

$pluginRoot = dirname(__DIR__);
$vendorApplicationsPath = $pluginRoot . '/includes/vendor-applications.php';
$assetPath = $pluginRoot . '/assets/js/vms-vendor-apply.js';
$vendorApplicationsSource = file_get_contents($vendorApplicationsPath);
$assetSource = file_get_contents($assetPath);

$assert(is_string($assetSource) && $assetSource !== '', 'Vendor Applications remediation asset should exist.');
$assert(is_string($vendorApplicationsSource) && strpos($vendorApplicationsSource, '<script>') === false, 'Vendor Applications renderer should not contain a raw executable <script> block.');
$assert(is_string($vendorApplicationsSource) && strpos($vendorApplicationsSource, 'type="application/json" id="vms-vendor-apply-variant-map"') !== false, 'Vendor Applications renderer should hand off variant config through an application/json payload.');

preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', (string) $vendorApplicationsSource, $scriptMatches, PREG_SET_ORDER);
foreach ($scriptMatches as $scriptMatch) {
	$type = '';
	if (preg_match('~\btype\s*=\s*(["\'])([^"\']+)\1~i', $scriptMatch[1], $typeMatch)) {
		$type = strtolower(trim((string) $typeMatch[2]));
	}
	$assert(in_array($type, array('application/json', 'application/ld+json'), true), 'Vendor Applications source should not emit executable inline script tags.');
}

$_GET = array();
$_POST = array();
$resetScripts();
$html = vms_vendor_apply_shortcode();

$assert(function_exists('vms_vendor_apply_turnstile_is_configured') && vms_vendor_apply_turnstile_is_configured(), 'Vendor Applications form should treat Turnstile as configured only when both keys exist.');
$assert(isset($GLOBALS['vms_test_scripts']['vms-vendor-apply']), 'Vendor Applications form should enqueue the migrated vms-vendor-apply asset.');
$assert(($GLOBALS['vms_test_scripts']['vms-vendor-apply']['src'] ?? '') === 'https://example.test/wp-content/plugins/backstage-venue-manager/assets/js/vms-vendor-apply.js', 'Vendor Applications asset should use the expected asset URL helper output.');
$assert(isset($GLOBALS['vms_test_scripts']['cf-turnstile']), 'Vendor Applications form should keep the existing Turnstile asset enqueued when both keys are configured.');
$assert(strpos($html, 'id="vms-vendor-apply-variant-map"') !== false, 'Vendor Applications form should render the JSON configuration payload.');
$assert(stripos($html, 'onchange=') === false && stripos($html, 'onclick=') === false && stripos($html, 'onsubmit=') === false, 'Vendor Applications form should not emit inline event-handler attributes.');
$assert(strpos($html, 'name="vms_vendor_apply_nonce"') !== false, 'Vendor Applications form should preserve its server-side nonce field.');
$assert(strpos($html, 'name="vms_app_vendor_type"') !== false, 'Vendor Applications form should preserve the vendor-type field.');
$assert(strpos($html, 'data-vms-band-required="1"') !== false, 'Vendor Applications form should preserve the band-specific required markers.');
$assert(strpos($html, 'class="cf-turnstile" data-sitekey="site-key"') !== false, 'Vendor Applications form should preserve the Turnstile widget markup when both keys are configured.');

preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $html, $renderedScripts, PREG_SET_ORDER);
$assert(count($renderedScripts) === 1, 'Vendor Applications form should only render the non-executable JSON script payload.');
$renderedType = '';
if (preg_match('~\btype\s*=\s*(["\'])([^"\']+)\1~i', $renderedScripts[0][1], $renderedTypeMatch)) {
	$renderedType = strtolower(trim((string) $renderedTypeMatch[2]));
}
$assert($renderedType === 'application/json', 'Vendor Applications rendered script payload should be application/json.');
$variantMap = json_decode(trim((string) $renderedScripts[0][2]), true);
$assert(is_array($variantMap) && isset($variantMap['default'], $variantMap['band']), 'Vendor Applications JSON payload should contain the expected variant-map shape.');

$_GET = array('vms_app' => 'success');
$_POST = array();
$resetScripts();
$successHtml = vms_vendor_apply_shortcode();
$assert(!isset($GLOBALS['vms_test_scripts']['vms-vendor-apply']), 'Vendor Applications asset should not be enqueued when the shortcode returns the confirmation screen instead of the form.');
$assert(strpos($successHtml, 'vms-vendor-apply-confirmation') !== false, 'Vendor Applications success path should still return the confirmation screen.');
$assert(strpos($successHtml, 'id="vms-vendor-apply-variant-map"') === false, 'Vendor Applications success path should not render form-only JSON configuration.');

$assert(strpos((string) $assetSource, "document.getElementById('vms-vendor-apply-variant-map')") !== false, 'Vendor Applications asset should read the JSON configuration payload.');
$assert(strpos((string) $assetSource, 'JSON.parse') !== false, 'Vendor Applications asset should parse JSON configuration rather than rely on executable PHP output.');
$assert(strpos((string) $assetSource, 'Array.isArray') !== false, 'Vendor Applications asset should validate configuration shape before use.');
$assert(strpos((string) $assetSource, 'vms-app-vendor-type') !== false, 'Vendor Applications asset should preserve the vendor-type selector behavior.');
$assert(strpos((string) $assetSource, '[data-vms-band-required]') !== false, 'Vendor Applications asset should preserve the band-required field toggles.');
$assert(strpos((string) $assetSource, '.vms-app-social-field') !== false, 'Vendor Applications asset should preserve the social-field toggles.');

$runtimeHarness = <<<'JS'
const fs = require('fs');
const vm = require('vm');

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

function createField() {
	return { disabled: false, required: false, placeholder: '' };
}

function createSection() {
	const fields = [createField(), createField()];
	return {
		hidden: true,
		querySelectorAll() {
			return fields;
		}
	};
}

function createSocialField(slug) {
	const fields = [createField()];
	return {
		hidden: true,
		getAttribute(name) {
			return name === 'data-vms-social-slug' ? slug : '';
		},
		querySelectorAll() {
			return fields;
		}
	};
}

function runScenario(assetSource, configText) {
	const listeners = {};
	const bandSection = createSection();
	const concessionSection = createSection();
	const bandRequiredFields = [createField(), createField()];
	const socialFacebook = createSocialField('facebook');
	const socialSpotify = createSocialField('spotify');
	const socialTikTok = createSocialField('tiktok');
	const socialGroup = { hidden: true };
	const socialHeading = { textContent: 'Social links (optional)' };
	const nameLabel = { textContent: 'Business / Vendor Name' };
	const websiteLabel = { textContent: 'Website URL (optional)' };
	const concessionLabel = { textContent: 'Cuisine / Food Type' };
	const concessionInput = { placeholder: 'Tacos, BBQ, Burgers, Coffee, etc.' };
	const concessionMenuLabel = { textContent: 'Menu Link (optional)' };
	const select = {
		value: 'band',
		addEventListener(type, handler) {
			listeners[type] = handler;
		}
	};
	const nodes = {
		'vms-vendor-apply-variant-map': configText === null ? null : { textContent: configText },
		'vms-app-vendor-type': select,
		'vms-app-social-group': socialGroup,
		'vms-app-social-heading': socialHeading,
		'vms-app-name-label': nameLabel,
		'vms-app-website-label': websiteLabel,
		'vms-app-concession-label': concessionLabel,
		'vms-app-concession-input': concessionInput,
		'vms-app-concession-menu-label': concessionMenuLabel
	};
	const document = {
		getElementById(id) {
			return Object.prototype.hasOwnProperty.call(nodes, id) ? nodes[id] : null;
		},
		querySelectorAll(selector) {
			switch (selector) {
				case '.vms-app-band':
					return [bandSection];
				case '.vms-app-concession':
					return [concessionSection];
				case '[data-vms-band-required]':
					return bandRequiredFields;
				case '.vms-app-social-field':
					return [socialFacebook, socialSpotify, socialTikTok];
				default:
					return [];
			}
		}
	};

	vm.runInNewContext(assetSource, { document, window: {}, console });

	return {
		bandRequiredFields,
		bandSection,
		concessionInput,
		concessionLabel,
		concessionMenuLabel,
		concessionSection,
		listeners,
		nameLabel,
		select,
		socialFacebook,
		socialGroup,
		socialHeading,
		socialSpotify,
		socialTikTok,
		websiteLabel
	};
}

const assetSource = fs.readFileSync(process.argv[2], 'utf8');
const validConfig = JSON.stringify({
	default: {
		name_label: 'Business / Vendor Name',
		website_label: 'Website URL (optional)',
		social_heading: 'Social links (optional)',
		show_concession: false,
		concession_label: 'Cuisine / Food Type',
		concession_placeholder: 'Tacos, BBQ, Burgers, Coffee, etc.',
		concession_menu_label: 'Menu Link (optional)',
		visible_socials: ['facebook', 'instagram']
	},
	band: {
		name_label: 'Music Vendor / Artist Name',
		website_label: 'Website URL (optional)',
		social_heading: 'Social & music links (optional)',
		show_concession: false,
		concession_label: 'Cuisine / Food Type',
		concession_placeholder: 'Tacos, BBQ, Burgers, Coffee, etc.',
		concession_menu_label: 'Menu Link (optional)',
		visible_socials: ['facebook', 'spotify']
	},
	food_truck: {
		name_label: 'Business Name',
		website_label: 'Website URL (optional)',
		social_heading: 'Social links (optional)',
		show_concession: true,
		concession_label: 'Cuisine / Food Type',
		concession_placeholder: 'Tacos, BBQ, Burgers, Coffee, etc.',
		concession_menu_label: 'Menu Link (optional)',
		visible_socials: ['facebook', 'tiktok']
	}
});

const valid = runScenario(assetSource, validConfig);
assert(typeof valid.listeners.change === 'function', 'Valid config should attach the change handler.');
assert(valid.nameLabel.textContent === 'Music Vendor / Artist Name', 'Valid config should update the business-name label.');
assert(valid.socialHeading.textContent === 'Social & music links (optional)', 'Valid config should update the social heading.');
assert(valid.bandSection.hidden === false, 'Valid config should reveal band sections on initial load.');
assert(valid.concessionSection.hidden === true, 'Valid config should keep concession sections hidden for bands.');
assert(valid.bandRequiredFields.every((field) => field.required === true), 'Valid config should mark band fields required.');
assert(valid.socialFacebook.hidden === false && valid.socialSpotify.hidden === false, 'Valid config should reveal configured social fields.');
assert(valid.socialTikTok.hidden === true, 'Valid config should keep unrelated social fields hidden.');
assert(valid.socialGroup.hidden === false, 'Valid config should reveal the social group when fields are visible.');

valid.select.value = 'food_truck';
valid.listeners.change();
assert(valid.concessionSection.hidden === false, 'Change handling should reveal concession sections for food trucks.');
assert(valid.bandSection.hidden === true, 'Change handling should hide band sections when switching away from bands.');
assert(valid.bandRequiredFields.every((field) => field.required === false), 'Change handling should clear band-only required flags when switching away from bands.');
assert(valid.socialTikTok.hidden === false, 'Change handling should reveal the configured food-truck social field.');

const malformed = runScenario(assetSource, '{bad json');
assert(typeof malformed.listeners.change === 'function', 'Malformed config should fail safely and still attach the change handler.');
assert(malformed.nameLabel.textContent === 'Business / Vendor Name', 'Malformed config should fall back to the default static label.');
assert(malformed.socialGroup.hidden === true, 'Malformed config should not reveal social fields by accident.');

const missing = runScenario(assetSource, null);
assert(typeof missing.listeners.change === 'function', 'Missing config should fail safely and still attach the change handler.');
assert(missing.websiteLabel.textContent === 'Website URL (optional)', 'Missing config should preserve the fallback website label.');

console.log('Vendor apply runtime OK.');
JS;

$runtimePath = tempnam(sys_get_temp_dir(), 'vms-vendor-apply-runtime-');
if ($runtimePath === false) {
	throw new RuntimeException('Failed to create temporary runtime harness for Vendor Applications JS.');
}
file_put_contents($runtimePath, $runtimeHarness);
$runtimeOutput = array();
$runtimeStatus = 0;
exec('node ' . escapeshellarg($runtimePath) . ' ' . escapeshellarg($assetPath) . ' 2>&1', $runtimeOutput, $runtimeStatus);
unlink($runtimePath);
$assert($runtimeStatus === 0, 'Vendor Applications JS runtime harness failed: ' . implode("\n", $runtimeOutput));
$assert(in_array('Vendor apply runtime OK.', $runtimeOutput, true), 'Vendor Applications JS runtime harness did not report success.');

fwrite(STDOUT, "Vendor Applications inline JS remediation OK.\n");
