<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('MB_IN_BYTES', 1048576);

final class WP_Error
{
	private string $code;
	private string $message;

	public function __construct(string $code = '', string $message = '')
	{
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code(): string
	{
		return $this->code;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

$GLOBALS['business_import_transients'] = array();
$GLOBALS['business_import_upload_result'] = null;

function add_action(...$args): void { unset($args); }
function add_filter(...$args): void { unset($args); }
function __($text, $domain = ''): string { unset($domain); return (string) $text; }
function esc_html__($text, $domain = ''): string { return esc_html(__($text, $domain)); }
function esc_attr__($text, $domain = ''): string { return esc_attr(__($text, $domain)); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value): string { return esc_html($value); }
function esc_url($value): string { return esc_attr($value); }
function sanitize_key($value): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_email($value): string
{
	$value = trim((string) $value);
	return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : '';
}
function esc_url_raw($value): string
{
	$value = trim(strip_tags((string) $value));
	return preg_match('~^https?://~i', $value) ? $value : '';
}
function absint($value): int { return abs((int) $value); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field(string $action): void { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($action) . '">'; }
function wp_unslash($value) { return $value; }
function wp_json_encode($value): string { return (string) json_encode($value); }
function set_transient(string $key, $value, int $expiration): bool
{
	$GLOBALS['business_import_transients'][$key] = array('value' => $value, 'expiration' => $expiration);
	return true;
}
function get_transient(string $key)
{
	return $GLOBALS['business_import_transients'][$key]['value'] ?? false;
}
function delete_transient(string $key): bool
{
	$existed = isset($GLOBALS['business_import_transients'][$key]);
	unset($GLOBALS['business_import_transients'][$key]);
	return $existed;
}
function bvmgr_upload_read_file(array $files, string $key)
{
	unset($files, $key);
	return $GLOBALS['business_import_upload_result'];
}
function bvmgr_validate_uploaded_file($upload, array $rules)
{
	unset($rules);
	return $upload;
}

require dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/business-distribution.php';

function business_import_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/** @param list<string> $headers @param list<list<string>> $rows */
function business_import_csv(array $headers, array $rows): string
{
	$path = tempnam(sys_get_temp_dir(), 'bvm-business-import-');
	if (!is_string($path)) {
		throw new RuntimeException('Could not allocate CSV fixture.');
	}
	$handle = fopen($path, 'wb');
	if ($handle === false) {
		throw new RuntimeException('Could not open CSV fixture.');
	}
	fputcsv($handle, $headers, ',', '"', '\\');
	foreach ($rows as $row) {
		fputcsv($handle, $row, ',', '"', '\\');
	}
	fclose($handle);
	return $path;
}

$headers = array('external_id', 'business_name', 'contact_name', 'email', 'phone', 'website', 'address', 'city', 'state', 'postal_code', 'notes');
$rows = array();
for ($index = 1; $index <= 35; $index++) {
	$rows[] = array(
		'fitness-' . $index,
		$index === 35 ? '<b>Fitness & Recovery 35</b>' : 'Fitness & Recovery ' . $index,
		'Coach ' . $index,
		(($index - 1) % 10 === 0) ? '' : 'coach' . $index . '@example.test',
		'555-01' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
		'https://fitness' . $index . '.example.test/path?ref=csv',
		$index . ' Wellness Avenue',
		'River City',
		'IL',
		'600' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
		$index === 35 ? "Research: mobility & recovery <script>alert('x')</script>" : 'Research metadata ' . $index,
	);
}
$fitness_path = business_import_csv($headers, $rows);
$parsed = backstage_outreach_csv_rows($fitness_path);
unlink($fitness_path);
business_import_assert(is_array($parsed), 'The corrected fitness CSV should parse.');
business_import_assert(count($parsed['rows']) === 35, 'All 35 fitness businesses must remain in the preview.');
business_import_assert(($parsed['rows'][0]['row_number'] ?? null) === 2 && ($parsed['rows'][34]['row_number'] ?? null) === 36, 'Physical CSV row numbers must be retained.');
business_import_assert(($parsed['rows'][0]['values']['address_line'] ?? '') === '1 Wellness Avenue', 'The explicit address alias must map to address_line.');
business_import_assert(empty($parsed['header_errors']), 'The corrected supported-header CSV must have no header errors.');

$preview = backstage_outreach_build_business_import_preview(17, $parsed);
business_import_assert($preview['valid_count'] === 35, 'All 35 corrected fitness records should be valid.');
business_import_assert($preview['invalid_count'] === 0, 'The corrected fitness CSV should have no invalid records.');
business_import_assert($preview['missing_email_count'] === 4, 'Blank optional emails must be counted without invalidating records.');
business_import_assert(count($preview['records']) === 35 && count($preview['rows']) === 35, 'Preview records and committable rows must both retain all 35 businesses.');
business_import_assert(($preview['rows'][34]['payload']['business_name'] ?? '') === 'Fitness & Recovery 35', 'The committable payload must contain the sanitized business name.');
business_import_assert(strpos((string) ($preview['rows'][34]['payload']['notes'] ?? ''), '<script>') === false, 'Research metadata in Notes must be sanitized before storage.');
business_import_assert(backstage_outreach_business_import_commit_error($preview, 17) === null, 'A clean preview with valid rows should be committable.');

ob_start();
backstage_outreach_render_business_import_preview($preview, 17);
$fitness_html = (string) ob_get_clean();
business_import_assert(substr_count($fitness_html, 'class="vms-business-import-record vms-business-import-record--valid"') === 35, 'The review table must render every one of the 35 records.');
business_import_assert(substr_count($fitness_html, '<details>') === 35, 'Each business must expose Notes in expandable details.');
business_import_assert(strpos($fitness_html, '1 Wellness Avenue<br>River City IL 60001') !== false, 'The review must render the complete sanitized address.');
business_import_assert(strpos($fitness_html, '4 missing email') !== false && strpos($fitness_html, 'Email delivery unavailable') !== false, 'Missing-email count and delivery limitation must be explicit.');
business_import_assert(strpos($fitness_html, '<script>') === false && strpos($fitness_html, 'alert(&#039;x&#039;)') !== false, 'Rendered Notes must escape hostile HTML while retaining text.');
business_import_assert(strpos($fitness_html, '<button class="button button-primary" disabled') === false, 'A clean preview commit button must be enabled.');
business_import_assert(strpos($fitness_html, '</table>') < strpos($fitness_html, 'vms-business-import-review__commit'), 'Commit must render below the complete review table.');

$unsupported_path = business_import_csv(
	array('business_name', 'email', '<img src=x onerror=alert(1)>research_score'),
	array(array('Supported Business', '', 'A+'))
);
$unsupported = backstage_outreach_csv_rows($unsupported_path);
unlink($unsupported_path);
business_import_assert(is_array($unsupported) && count($unsupported['header_errors']) === 1, 'Unsupported columns must be reported, not silently ignored.');
$unsupported_preview = backstage_outreach_build_business_import_preview(17, $unsupported);
$unsupported_error = backstage_outreach_business_import_commit_error($unsupported_preview, 17);
business_import_assert(is_wp_error($unsupported_error) && $unsupported_error->get_error_code() === 'business_import_blocked', 'Unsupported columns must block commit server-side.');
ob_start();
backstage_outreach_render_business_import_preview($unsupported_preview, 17);
$unsupported_html = (string) ob_get_clean();
business_import_assert(strpos($unsupported_html, 'Unsupported CSV column') !== false && strpos($unsupported_html, 'disabled aria-disabled="true"') !== false, 'Unsupported columns must be visible and disable commit.');
business_import_assert(strpos($unsupported_html, '<img') === false && strpos($unsupported_html, 'onerror=') === false, 'Unsupported header labels must be escaped and sanitized.');

$duplicate_path = business_import_csv(
	array('business_name', 'address', 'address_line'),
	array(array('Duplicate Address Gym', 'First address', 'Second address'))
);
$duplicate = backstage_outreach_csv_rows($duplicate_path);
unlink($duplicate_path);
business_import_assert(is_array($duplicate) && count($duplicate['header_errors']) === 1, 'Duplicate canonical/alias mappings must be rejected as ambiguous.');
business_import_assert(($duplicate['rows'][0]['values']['address_line'] ?? '') === 'First address', 'Ambiguous mappings must never overwrite the first deterministic value.');
business_import_assert(strpos($duplicate['header_errors'][0], 'both map to "address_line"') !== false, 'The duplicate-mapping error must identify the canonical field.');

$invalid_path = business_import_csv(
	array('business_name', 'email', 'notes'),
	array(
		array('Valid Studio', '', 'Allowed without email'),
		array('', 'owner@example.test', 'Missing business name'),
		array('Bad Email Gym', 'not-an-email', 'Invalid email'),
	)
);
$invalid = backstage_outreach_csv_rows($invalid_path);
unlink($invalid_path);
$invalid_preview = backstage_outreach_build_business_import_preview(17, $invalid);
business_import_assert($invalid_preview['valid_count'] === 1 && $invalid_preview['invalid_count'] === 2, 'Valid and invalid record counts must remain distinct.');
business_import_assert(count($invalid_preview['records']) === 3, 'Invalid records must remain visible in the review.');
business_import_assert(strpos(implode(' ', $invalid_preview['errors']), 'Row 3: Business name is required.') !== false, 'Missing-name rows must retain their physical row and specific error.');
business_import_assert(strpos(implode(' ', $invalid_preview['errors']), 'Row 4: Enter a valid email address') !== false, 'Invalid-email rows must retain their physical row and specific error.');
business_import_assert(is_wp_error(backstage_outreach_business_import_commit_error($invalid_preview, 17)), 'Any invalid row must block commit server-side.');
ob_start();
backstage_outreach_render_business_import_preview($invalid_preview, 17);
$invalid_html = (string) ob_get_clean();
business_import_assert(substr_count($invalid_html, 'vms-business-import-record--invalid') === 2, 'Both invalid records must render in the table.');
business_import_assert(strpos($invalid_html, '1 valid · 2 invalid · 1 missing email') !== false, 'The review summary must include valid, invalid, and missing-email counts.');
business_import_assert(strpos($invalid_html, 'disabled aria-disabled="true"') !== false, 'Row errors must disable the commit control.');

$empty_preview = backstage_outreach_build_business_import_preview(17, array('rows' => array(), 'header_errors' => array()));
$empty_error = backstage_outreach_business_import_commit_error($empty_preview, 17);
business_import_assert(is_wp_error($empty_error) && $empty_error->get_error_code() === 'business_import_no_valid_rows', 'A preview with no valid rows must be rejected server-side.');

$key = 'backstage_outreach_business_import_99';
set_transient($key, $preview, 600);
$GLOBALS['business_import_upload_result'] = new WP_Error('upload_failed', 'Replacement upload failed.');
$_FILES = array();
$replacement = backstage_outreach_prepare_business_import_preview(17, $key);
business_import_assert(is_wp_error($replacement), 'The failed replacement upload must return its error.');
business_import_assert(get_transient($key) === false, 'A failed replacement upload must invalidate the older preview.');

$legacy_error = backstage_outreach_business_import_commit_error(array('source_id' => 17, 'rows' => $preview['rows']), 17);
business_import_assert(is_wp_error($legacy_error) && $legacy_error->get_error_code() === 'business_import_preview_expired', 'Legacy/stale preview shapes must not be committable after the review-model change.');

$source = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$css = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/assets/css/outreach-admin.css');
$plugin = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$integration = (string) file_get_contents(dirname(__DIR__) . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');
business_import_assert(strpos($source, 'UNIQUE KEY email') === false, 'CSV review changes must not merge business identities by email.');
business_import_assert(strpos($source, "provenance = hash('sha256', 'csv:'") !== false, 'CSV provenance/idempotency must remain intact.');
business_import_assert(strpos($source, "\$wpdb->query('START TRANSACTION')") !== false && strpos($source, "\$wpdb->query('ROLLBACK')") !== false, 'Atomic CSV import behavior must remain intact.');
business_import_assert(strpos($css, '.vms-business-import-review__table-wrap') !== false && strpos($css, 'overflow-wrap: anywhere') !== false, 'Desktop review styles must wrap long values inside a bounded table.');
business_import_assert(strpos($css, '@media (max-width: 782px)') !== false && strpos($css, 'content: attr(data-label)') !== false, 'Narrow-screen review styles must expose labels in the stacked record layout.');
business_import_assert(strpos($plugin, 'Version: 1.2.5') !== false && strpos($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.5'") !== false, 'The standalone Outreach release metadata must identify version 1.2.5.');
business_import_assert(strpos($integration, "return \$page === 'vms-passes' && \$tab === 'sources';") !== false, 'The standalone Outreach assets must load on the business Source import screen.');
business_import_assert(substr_count($integration, 'BACKSTAGE_OUTREACH_VERSION') >= 2 && strpos($integration, 'filemtime(') === false, 'Outreach admin asset cache keys must follow the reproducible plugin release version.');

$html_output = getenv('BVM_BUSINESS_IMPORT_HTML');
if (is_string($html_output) && $html_output !== '') {
	$document = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . $css . '</style></head><body style="margin:0;background:#f0f0f1;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif"><main id="vms-pass-claims-wrap" style="box-sizing:border-box;max-width:1440px;margin:0 auto;padding:20px"><section class="vms-pass-card"><h3>Import Businesses</h3>' . $fitness_html . '</section></main></body></html>';
	if (file_put_contents($html_output, $document) === false) {
		throw new RuntimeException('Could not write the optional browser-inspection fixture.');
	}
}

fwrite(STDOUT, "business import preview: PASS (35-row review, headers, validation, stale upload, escaping)\n");
