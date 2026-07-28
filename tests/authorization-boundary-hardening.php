<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VmsTestDieException extends RuntimeException
{
}

final class VmsTestRedirectException extends RuntimeException
{
	public string $location;

	public function __construct(string $location)
	{
		parent::__construct($location);
		$this->location = $location;
	}
}

final class WP_Post
{
	public int $ID = 0;
	public string $post_type = '';
	public string $post_status = 'draft';
	public string $post_title = '';
	public string $post_name = '';
}

if (!defined('VMS_VENDOR_APP_CPT')) {
	define('VMS_VENDOR_APP_CPT', 'vms_vendor_app');
}
if (!defined('VMS_VENDOR_CPT')) {
	define('VMS_VENDOR_CPT', 'vms_vendor');
}
if (!defined('VMS_VENUE_TEMPLATE_META_KEY')) {
	define('VMS_VENUE_TEMPLATE_META_KEY', '_vms_is_template');
}

$GLOBALS['vms_test_posts'] = array();
$GLOBALS['vms_test_meta'] = array();
$GLOBALS['vms_test_caps'] = array();
$GLOBALS['vms_test_current_user_id'] = 0;
$GLOBALS['vms_test_redirects'] = array();
$GLOBALS['vms_test_notices'] = array();
$GLOBALS['vms_test_transition_log'] = array();
$GLOBALS['vms_test_season_save_calls'] = 0;
$GLOBALS['vms_test_next_post_id'] = 1000;

function __(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html__(string $text, string $domain = ''): string
{
	return $text;
}

function esc_attr__(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html(string $text): string
{
	return $text;
}

function esc_attr(string $text): string
{
	return $text;
}

function esc_url(string $url): string
{
	return $url;
}

function esc_textarea(string $text): string
{
	return $text;
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return trim((string) $value);
}

function sanitize_textarea_field($value): string
{
	return sanitize_text_field($value);
}

function sanitize_email($value): string
{
	return sanitize_text_field($value);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function sanitize_title(string $value): string
{
	$value = strtolower(trim($value));
	$value = preg_replace('/[^a-z0-9]+/', '-', $value);
	return trim((string) $value, '-');
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function absint($value): int
{
	return abs((int) $value);
}

function vms_request_read_scalar(array $source, string $key): string
{
	if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
		return '';
	}

	$value = wp_unslash($source[$key]);
	if (!is_scalar($value)) {
		return '';
	}

	return trim((string) $value);
}

function vms_request_read_text_field(array $source, string $key): string
{
	$value = vms_request_read_scalar($source, $key);
	return $value === '' ? '' : sanitize_text_field($value);
}

function vms_request_read_textarea_field(array $source, string $key): string
{
	$value = vms_request_read_scalar($source, $key);
	return $value === '' ? '' : sanitize_textarea_field($value);
}

function vms_request_read_email(array $source, string $key): string
{
	$value = vms_request_read_scalar($source, $key);
	return $value === '' ? '' : sanitize_email($value);
}

function vms_request_read_key(array $source, string $key): string
{
	$value = vms_request_read_scalar($source, $key);
	return $value === '' ? '' : sanitize_key($value);
}

function vms_request_read_absint(array $source, string $key): int
{
	$value = vms_request_read_scalar($source, $key);
	return $value === '' ? 0 : absint($value);
}

function vms_request_read_bool_flag(array $source, string $key): bool
{
	if (!array_key_exists($key, $source)) {
		return false;
	}

	$value = $source[$key];
	if (is_array($value) || is_object($value)) {
		return false;
	}

	$value = wp_unslash($value);
	if (is_bool($value)) {
		return $value;
	}
	if (!is_scalar($value)) {
		return false;
	}

	$value = strtolower(trim((string) $value));
	if ($value === '') {
		return false;
	}

	return !in_array($value, array('0', 'false', 'off', 'no'), true);
}

function get_current_user_id(): int
{
	return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
}

function current_time(string $type): string
{
	return $type === 'mysql' ? '2026-07-10 12:00:00' : '0';
}

function current_user_can(string $capability, ...$args): bool
{
	$caps = (array) ($GLOBALS['vms_test_caps'] ?? array());

	if ($capability === 'edit_post') {
		$postId = (int) ($args[0] ?? 0);
		return !empty($caps['edit_post'][$postId]);
	}

	return !empty($caps[$capability]);
}

function vms_test_nonce(string $action): string
{
	return 'good:' . $action;
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	return $nonce === vms_test_nonce($action);
}

function check_admin_referer(string $action): bool
{
	$nonce = '';
	if (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) {
		$nonce = sanitize_text_field((string) $_REQUEST['_wpnonce']);
	}

	if (!wp_verify_nonce($nonce, $action)) {
		wp_die('Invalid nonce.');
	}

	return true;
}

function wp_nonce_field(string $action, string $name): void
{
	echo '<input type="hidden" name="' . $name . '" value="' . vms_test_nonce($action) . '">';
}

function wp_nonce_url(string $url, string $action): string
{
	$glue = strpos($url, '?') === false ? '?' : '&';
	return $url . $glue . '_wpnonce=' . rawurlencode(vms_test_nonce($action));
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function get_edit_post_link(int $post_id, string $context = ''): string
{
	return admin_url('post.php?post=' . $post_id . '&action=edit');
}

function get_the_title(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_title : '';
}

function wp_safe_redirect(string $url): bool
{
	$GLOBALS['vms_test_redirects'][] = $url;
	throw new VmsTestRedirectException($url);
}

function wp_die($message = ''): void
{
	if (!is_scalar($message)) {
		$message = '';
	}

	throw new VmsTestDieException((string) $message);
}

function wp_is_post_revision(int $post_id): bool
{
	return false;
}

function wp_is_post_autosave(int $post_id): bool
{
	return false;
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function apply_filters(string $hook, $value)
{
	$overrides = (array) ($GLOBALS['vms_test_filter_values'] ?? array());
	return $overrides[$hook] ?? $value;
}

function get_post(int $post_id)
{
	return $GLOBALS['vms_test_posts'][$post_id] ?? null;
}

function get_post_type(int $post_id): string
{
	$post = get_post($post_id);
	return $post instanceof WP_Post ? $post->post_type : '';
}

function get_post_meta(int $post_id, string $meta_key, bool $single = true)
{
	$store = (array) ($GLOBALS['vms_test_meta'][$post_id] ?? array());
	if (!array_key_exists($meta_key, $store)) {
		return $single ? '' : array();
	}

	$value = $store[$meta_key];
	if ($single) {
		if (is_array($value) && array_key_exists(0, $value)) {
			return $value[0];
		}
		return $value;
	}

	return is_array($value) ? array_values($value) : array($value);
}

function update_post_meta(int $post_id, string $meta_key, $value): void
{
	$GLOBALS['vms_test_meta'][$post_id][$meta_key] = $value;
}

function delete_post_meta(int $post_id, string $meta_key): void
{
	unset($GLOBALS['vms_test_meta'][$post_id][$meta_key]);
}

function add_post_meta(int $post_id, string $meta_key, $value, bool $unique = false): void
{
	$existing = get_post_meta($post_id, $meta_key, false);
	$existing[] = $value;
	$GLOBALS['vms_test_meta'][$post_id][$meta_key] = $existing;
}

function vms_vendor_app_cpt_slugs(): array
{
	return array(VMS_VENDOR_APP_CPT, 'vms_vendor_application');
}

function vms_vendor_app_statuses(): array
{
	return array(
		'pending' => 'Pending',
		'holding' => 'Holding',
		'approved' => 'Approved',
		'rejected' => 'Rejected',
	);
}

function vms_vendor_app_status_pill_class(string $status): string
{
	return 'vms-pill-' . sanitize_key($status);
}

function vms_vendor_app_get_status(int $app_id): string
{
	$status = (string) get_post_meta($app_id, '_vms_app_status', true);
	return $status !== '' ? $status : 'pending';
}

function vms_vendor_app_set_status(int $app_id, string $status): void
{
	update_post_meta($app_id, '_vms_app_status', sanitize_key($status));
}

function vms_vendor_app_default_response_message(int $app_id, string $status): string
{
	return 'Default response for ' . $status;
}

function vms_vendor_app_get_confirmation_state(int $app_id): string
{
	$state = (string) get_post_meta($app_id, '_vms_app_confirmation_state', true);
	return $state !== '' ? $state : 'confirmed';
}

function vms_vendor_app_confirmation_state_label(string $state): string
{
	return ucfirst($state);
}

function vms_approvals_queue_record_transition(string $queue, int $object_id, string $from_status, string $to_status): void
{
	$GLOBALS['vms_test_transition_log'][] = array(
		'queue' => $queue,
		'object_id' => $object_id,
		'from' => $from_status,
		'to' => $to_status,
	);
}

function vms_vendor_mark_profile_reviewed(int $vendor_id, int $user_id): void
{
	update_post_meta($vendor_id, '_vms_test_reviewed_by', $user_id);
}

function vms_set_admin_notice(string $message, string $type = 'success'): void
{
	$GLOBALS['vms_test_notices'][] = array('message' => $message, 'type' => $type);
}

function wp_update_post(array $data): int
{
	$postId = (int) ($data['ID'] ?? 0);
	$post = get_post($postId);
	if (!$post instanceof WP_Post) {
		return 0;
	}

	foreach (array('post_title', 'post_name', 'post_status') as $field) {
		if (array_key_exists($field, $data)) {
			$post->{$field} = (string) $data[$field];
		}
	}

	$GLOBALS['vms_test_posts'][$postId] = $post;
	return $postId;
}

function vms_duplicate_venue_as_draft(int $template_id): int
{
	$template = get_post($template_id);
	if (!$template instanceof WP_Post) {
		return 0;
	}

	$newId = vms_test_register_post('vms_venue', 'Draft copy of ' . $template->post_title, 'draft');
	$templateMeta = (array) ($GLOBALS['vms_test_meta'][$template_id] ?? array());
	foreach ($templateMeta as $metaKey => $value) {
		$GLOBALS['vms_test_meta'][$newId][$metaKey] = $value;
	}

	return $newId;
}

function vms_meta_key(string $object, string $field): string
{
	$map = array(
		'event_plan' => array(
			'band_vendor_id' => '_vms_band_vendor_id',
			'status' => '_vms_event_plan_status',
			'integrity_issue' => '_vms_integrity_issue',
			'secondary_vendor_ids' => '_vms_secondary_vendor_ids',
			'secondary_vendor_id' => '_vms_secondary_vendor_id',
		),
	);

	return $map[$object][$field] ?? '';
}

function vms_event_plan_vendor_exists(int $vendor_id): bool
{
	$post = get_post($vendor_id);
	return $post instanceof WP_Post && $post->post_type === VMS_VENDOR_CPT && $post->post_status !== 'trash';
}

function vms_event_plan_flag_missing_vendor(int $post_id, int $vendor_id, string $title): void
{
	update_post_meta($post_id, '_vms_integrity_issue', 'missing_vendor');
}

function vms_event_plan_perf_wp_update_post(array $data, string $context, int $post_id): void
{
	wp_update_post(array(
		'ID' => $post_id,
		'post_status' => (string) ($data['post_status'] ?? ''),
	));
}

function vms_add_admin_notice(string $message, string $type = 'success'): void
{
	$GLOBALS['vms_test_notices'][] = array('message' => $message, 'type' => $type);
}

function vms_sd_require_engine(): void
{
}

function vms_sch_season_get_rules(int $venue_id): array
{
	return array();
}

function vms_sch_season_save_rules(int $venue_id, array $rules): bool
{
	$GLOBALS['vms_test_season_save_calls']++;
	return true;
}

function vms_sd_base_url(string $page_slug, int $venue_id): string
{
	return admin_url('admin.php?page=' . $page_slug . '&venue_id=' . $venue_id);
}

function vms_sd_redirect(string $url): void
{
	wp_safe_redirect($url);
}

function vms_test_register_post(string $postType, string $title, string $status = 'publish'): int
{
	$postId = (int) $GLOBALS['vms_test_next_post_id'];
	$GLOBALS['vms_test_next_post_id'] = $postId + 1;

	$post = new WP_Post();
	$post->ID = $postId;
	$post->post_type = $postType;
	$post->post_status = $status;
	$post->post_title = $title;
	$post->post_name = sanitize_title($title);

	$GLOBALS['vms_test_posts'][$postId] = $post;
	return $postId;
}

function vms_test_reset_runtime_state(): void
{
	$GLOBALS['vms_test_posts'] = array();
	$GLOBALS['vms_test_meta'] = array();
	$GLOBALS['vms_test_caps'] = array();
	$GLOBALS['vms_test_current_user_id'] = 0;
	$GLOBALS['vms_test_redirects'] = array();
	$GLOBALS['vms_test_notices'] = array();
	$GLOBALS['vms_test_transition_log'] = array();
	$GLOBALS['vms_test_season_save_calls'] = 0;
	$GLOBALS['vms_test_is_admin'] = true;
	$GLOBALS['vms_test_filter_values'] = array();
	$GLOBALS['vms_test_next_post_id'] = 1000;
	$_GET = array();
	$_POST = array();
	$_REQUEST = array();
	$_SERVER = array();
}

function vms_test_find_matching_brace(string $code, int $braceOffset): int
{
	$length = strlen($code);
	$depth = 0;
	$inSingle = false;
	$inDouble = false;
	$inLineComment = false;
	$inBlockComment = false;

	for ($i = $braceOffset; $i < $length; $i++) {
		$char = $code[$i];
		$next = $i + 1 < $length ? $code[$i + 1] : '';
		$prev = $i > 0 ? $code[$i - 1] : '';

		if ($inLineComment) {
			if ($char === "\n") {
				$inLineComment = false;
			}
			continue;
		}

		if ($inBlockComment) {
			if ($char === '*' && $next === '/') {
				$inBlockComment = false;
				$i++;
			}
			continue;
		}

		if (!$inSingle && !$inDouble) {
			if ($char === '/' && $next === '/') {
				$inLineComment = true;
				$i++;
				continue;
			}
			if ($char === '/' && $next === '*') {
				$inBlockComment = true;
				$i++;
				continue;
			}
		}

		if (!$inDouble && $char === "'" && $prev !== '\\') {
			$inSingle = !$inSingle;
			continue;
		}

		if (!$inSingle && $char === '"' && $prev !== '\\') {
			$inDouble = !$inDouble;
			continue;
		}

		if ($inSingle || $inDouble) {
			continue;
		}

		if ($char === '{') {
			$depth++;
			continue;
		}

		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return $i;
			}
		}
	}

	throw new RuntimeException('Could not find matching brace.');
}

function vms_test_extract_named_function(string $path, string $functionName): string
{
	$code = (string) file_get_contents($path);
	$pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(/';
	if (!preg_match($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('Function not found: ' . $functionName);
	}

	$start = (int) $matches[0][1];
	$brace = strpos($code, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Opening brace not found: ' . $functionName);
	}

	$end = vms_test_find_matching_brace($code, $brace);
	return substr($code, $start, $end - $start + 1);
}

function vms_test_extract_inline_closure(string $path, string $marker): string
{
	$code = (string) file_get_contents($path);
	$markerPos = strpos($code, $marker);
	if ($markerPos === false) {
		throw new RuntimeException('Marker not found: ' . $marker);
	}

	$functionPos = strpos($code, 'function', $markerPos);
	if ($functionPos === false) {
		throw new RuntimeException('Closure not found for marker: ' . $marker);
	}

	$brace = strpos($code, '{', $functionPos);
	if ($brace === false) {
		throw new RuntimeException('Closure brace not found for marker: ' . $marker);
	}

	$end = vms_test_find_matching_brace($code, $brace);
	return substr($code, $functionPos, $end - $functionPos + 1);
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$vendorApplicationsPath = dirname(__DIR__) . '/includes/vendor-applications.php';
$helpersPath = dirname(__DIR__) . '/includes/helpers.php';
$venueTemplatesPath = dirname(__DIR__) . '/includes/admin/venue-duplicate-templates.php';
$seasonDatesPath = dirname(__DIR__) . '/includes/admin/season-dates.php';
$eventPlansPath = dirname(__DIR__) . '/includes/cpt/event-plans.php';

eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_row_actions'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_metabox_actions'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_handle_edit_screen_decision'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_handle_approve'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_handle_reject'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_handle_repair_vendor'));
eval(vms_test_extract_named_function($vendorApplicationsPath, 'vms_vendor_applications_handle_resync_vendor'));
eval(vms_test_extract_named_function($helpersPath, 'vms_vendor_handle_mark_reviewed'));
eval(vms_test_extract_named_function($venueTemplatesPath, 'vms_handle_create_venue_from_template'));
eval(vms_test_extract_named_function($seasonDatesPath, 'vms_sd_query_arg'));
eval(vms_test_extract_named_function($seasonDatesPath, 'vms_sd_maybe_handle_post'));
eval(vms_test_extract_named_function($eventPlansPath, 'vms_event_plan_current_get_request'));
$GLOBALS['vms_test_event_plan_admin_guard'] = eval(
	'return ' . vms_test_extract_inline_closure($eventPlansPath, "add_action('admin_init', function () {") . ';'
);

$expectDie = static function (callable $callback, string $expectedMessage) use ($assert): void {
	try {
		$callback();
		throw new RuntimeException('Expected wp_die with message: ' . $expectedMessage);
	} catch (VmsTestDieException $e) {
		$assert($e->getMessage() === $expectedMessage, 'Expected wp_die "' . $expectedMessage . '" but got "' . $e->getMessage() . '".');
	}
};

$expectRedirect = static function (callable $callback, string $expectedLocation) use ($assert): void {
	try {
		$callback();
		throw new RuntimeException('Expected redirect to: ' . $expectedLocation);
	} catch (VmsTestRedirectException $e) {
		$assert($e->location === $expectedLocation, 'Expected redirect to "' . $expectedLocation . '" but got "' . $e->location . '".');
	}
};

try {
	vms_test_reset_runtime_state();

	$appId = vms_test_register_post(VMS_VENDOR_APP_CPT, 'Vendor Application Fixture');
	update_post_meta($appId, '_vms_app_status', 'pending');
	update_post_meta($appId, '_vms_app_confirmation_state', 'confirmed');
	update_post_meta($appId, '_vms_app_operator_internal_note', 'Baseline note');

	$vendorPost = get_post($appId);
	$assert($vendorPost instanceof WP_Post, 'Vendor application fixture should exist.');

	$GLOBALS['vms_test_caps'] = array(
		'edit_posts' => true,
		'edit_post' => array(),
	);
	$assert(current_user_can('edit_posts') === true, 'Broad edit_posts capability should be available in the limited-user fixture.');
	$assert(current_user_can('edit_post', $appId) === false, 'Limited-user fixture should not have edit_post on the target application.');

	$rowActions = vms_vendor_applications_row_actions(array('edit' => 'Edit'), $vendorPost);
	$assert(!isset($rowActions['vms_review_response']), 'Row actions should hide review controls without object-level access.');

	ob_start();
	vms_vendor_applications_metabox_actions($vendorPost);
	$limitedMetabox = (string) ob_get_clean();
	$assert(strpos($limitedMetabox, 'You do not have permission to update applications.') !== false, 'Metabox should render the permission warning when object-level access is missing.');
	$assert(strpos($limitedMetabox, 'vms_vendor_app_decision_nonce') === false, 'Metabox should not render decision controls without object-level access.');

	$_POST = array(
		'vms_vendor_app_admin_fields_present' => '1',
		'vms_vendor_app_decision_nonce' => vms_test_nonce('vms_vendor_app_decision_' . $appId),
		'vms_vendor_app_operator_internal_note' => 'Unauthorized change',
		'vms_vendor_app_decision_message' => 'Unauthorized response',
		'vms_vendor_app_decision' => 'holding',
	);
	vms_vendor_applications_handle_edit_screen_decision($appId, $vendorPost, true);
	$assert(vms_vendor_app_get_status($appId) === 'pending', 'Edit-screen decision should not mutate status before object authorization passes.');
	$assert((string) get_post_meta($appId, '_vms_app_operator_internal_note', true) === 'Baseline note', 'Edit-screen decision should not update notes before object authorization passes.');

	$_GET = array('app_id' => $appId);
	$_REQUEST = $_GET;
	$expectDie(static function () {
		vms_vendor_applications_handle_approve();
	}, 'Forbidden');
	$expectDie(static function () {
		vms_vendor_applications_handle_repair_vendor();
	}, 'Forbidden');
	$expectDie(static function () {
		vms_vendor_applications_handle_resync_vendor();
	}, 'Forbidden');

	$vendorId = vms_test_register_post(VMS_VENDOR_CPT, 'Vendor Fixture');
	$_GET = array('vendor_id' => $vendorId);
	$_REQUEST = $_GET;
	$expectDie(static function () {
		vms_vendor_handle_mark_reviewed();
	}, 'Permission denied.');
	$assert((string) get_post_meta($vendorId, '_vms_test_reviewed_by', true) === '', 'Mark reviewed should not mutate vendor review state before object authorization passes.');

	$templateId = vms_test_register_post('vms_venue', 'Template Venue Fixture');
	update_post_meta($templateId, VMS_VENUE_TEMPLATE_META_KEY, '1');
	$_POST = array(
		'vms_create_venue_from_template_nonce' => vms_test_nonce('vms_create_venue_from_template'),
		'vms_template_id' => $templateId,
	);
	$_REQUEST = $_POST;
	$expectDie(static function () {
		vms_handle_create_venue_from_template();
	}, 'Not allowed.');
	$assert(count((array) $GLOBALS['vms_test_posts']) === 3, 'Create-from-template should not create a new venue when the user lacks edit_post on the template.');

	$GLOBALS['vms_test_caps'] = array(
		'edit_posts' => true,
		'edit_post' => array($appId => true, $vendorId => true, $templateId => true),
	);
	$GLOBALS['vms_test_current_user_id'] = 88;

	$authorizedActions = vms_vendor_applications_row_actions(array('edit' => 'Edit'), $vendorPost);
	$assert(isset($authorizedActions['vms_review_response']), 'Row actions should show review controls for users with object-level access.');

	ob_start();
	vms_vendor_applications_metabox_actions($vendorPost);
	$authorizedMetabox = (string) ob_get_clean();
	$assert(strpos($authorizedMetabox, 'vms_vendor_app_decision_nonce') !== false, 'Metabox should render the decision nonce for authorized users.');
	$assert(strpos($authorizedMetabox, 'Approve') !== false, 'Metabox should still render decision buttons for authorized users.');

	$_POST = array(
		'vms_vendor_app_admin_fields_present' => '1',
		'vms_vendor_app_decision_nonce' => vms_test_nonce('vms_vendor_app_decision_' . $appId),
		'vms_vendor_app_operator_internal_note' => 'Authorized note',
		'vms_vendor_app_decision_message' => 'Hold this for later review.',
		'vms_vendor_app_decision' => 'holding',
	);
	$GLOBALS['vms_test_transition_log'] = array();
	vms_vendor_applications_handle_edit_screen_decision($appId, $vendorPost, true);
	$assert(vms_vendor_app_get_status($appId) === 'holding', 'Edit-screen decision should update status for authorized users.');
	$assert((string) get_post_meta($appId, '_vms_app_operator_internal_note', true) === 'Authorized note', 'Edit-screen decision should update the internal note for authorized users.');
	$assert((string) get_post_meta($appId, '_vms_app_last_response_status', true) === 'holding', 'Edit-screen decision should preserve the stored response status on success.');
	$assert((string) get_post_meta($appId, '_vms_app_last_response_message', true) === 'Hold this for later review.', 'Edit-screen decision should preserve the stored response message on success.');
	$assert(count((array) $GLOBALS['vms_test_transition_log']) === 1, 'Edit-screen decision should still record review-queue transitions on success.');

	vms_vendor_app_set_status($appId, 'pending');
	update_post_meta($appId, '_vms_app_operator_internal_note', 'Baseline note');
	delete_post_meta($appId, '_vms_app_last_response_status');
	delete_post_meta($appId, '_vms_app_last_response_message');

	$_POST = array(
		'vms_vendor_app_admin_fields_present' => '1',
		'vms_vendor_app_operator_internal_note' => 'Missing nonce change',
		'vms_vendor_app_decision_message' => 'Missing nonce',
		'vms_vendor_app_decision' => 'holding',
	);
	vms_vendor_applications_handle_edit_screen_decision($appId, $vendorPost, true);
	$assert(vms_vendor_app_get_status($appId) === 'pending', 'Missing nonce should block edit-screen status mutations.');
	$assert((string) get_post_meta($appId, '_vms_app_operator_internal_note', true) === 'Baseline note', 'Missing nonce should block edit-screen note updates.');

	$_POST = array(
		'vms_vendor_app_admin_fields_present' => '1',
		'vms_vendor_app_decision_nonce' => 'expired',
		'vms_vendor_app_operator_internal_note' => 'Invalid nonce change',
		'vms_vendor_app_decision_message' => 'Invalid nonce',
		'vms_vendor_app_decision' => 'holding',
	);
	vms_vendor_applications_handle_edit_screen_decision($appId, $vendorPost, true);
	$assert(vms_vendor_app_get_status($appId) === 'pending', 'Invalid nonce should block edit-screen status mutations.');
	$assert((string) get_post_meta($appId, '_vms_app_operator_internal_note', true) === 'Baseline note', 'Invalid nonce should block edit-screen note updates.');

	$_POST = array(
		'vms_vendor_app_admin_fields_present' => '1',
		'vms_vendor_app_decision_nonce' => array('expired'),
		'vms_vendor_app_operator_internal_note' => 'Malformed nonce change',
		'vms_vendor_app_decision_message' => 'Malformed nonce',
		'vms_vendor_app_decision' => 'holding',
	);
	$warningRaised = false;
	set_error_handler(static function () use (&$warningRaised): bool {
		$warningRaised = true;
		return false;
	});
	try {
		vms_vendor_applications_handle_edit_screen_decision($appId, $vendorPost, true);
	} finally {
		restore_error_handler();
	}
	$assert($warningRaised === false, 'Malformed nonce inputs should fail without warnings.');
	$assert(vms_vendor_app_get_status($appId) === 'pending', 'Malformed nonce should block edit-screen status mutations.');
	$assert((string) get_post_meta($appId, '_vms_app_operator_internal_note', true) === 'Baseline note', 'Malformed nonce should block edit-screen note updates.');

	$_GET = array(
		'app_id' => $appId,
		'_wpnonce' => vms_test_nonce('vms_vendor_app_reject_' . $appId),
	);
	$_REQUEST = $_GET;
	$GLOBALS['vms_test_transition_log'] = array();
	$expectRedirect(static function () {
		vms_vendor_applications_handle_reject();
	}, admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
	$assert(vms_vendor_app_get_status($appId) === 'rejected', 'Reject admin-post should still update the application status for authorized users.');
	$assert(count((array) $GLOBALS['vms_test_transition_log']) === 1, 'Reject admin-post should still record the status transition.');

	$_GET = array(
		'vendor_id' => $vendorId,
		'_wpnonce' => vms_test_nonce('vms_vendor_mark_reviewed_' . $vendorId),
	);
	$_REQUEST = $_GET;
	$expectRedirect(static function () {
		vms_vendor_handle_mark_reviewed();
	}, admin_url('post.php?post=' . $vendorId . '&action=edit'));
	$assert((int) get_post_meta($vendorId, '_vms_test_reviewed_by', true) === 88, 'Mark reviewed should still stamp the acting user on success.');

	$expectRedirect(static function () use ($templateId) {
		$_POST = array(
			'vms_create_venue_from_template_nonce' => vms_test_nonce('vms_create_venue_from_template'),
			'vms_template_id' => $templateId,
		);
		$_REQUEST = $_POST;
		vms_handle_create_venue_from_template();
	}, admin_url('post.php?post=1003&action=edit'));
	$newVenue = get_post(1003);
	$assert($newVenue instanceof WP_Post, 'Create-from-template should still create a new venue for authorized users.');
	$assert($newVenue->post_title === 'New Venue — Template Venue Fixture', 'Create-from-template should preserve the success title update.');
	$assert($newVenue->post_name === sanitize_title('New Venue — Template Venue Fixture'), 'Create-from-template should preserve the generated slug update.');
	$assert((string) get_post_meta(1003, VMS_VENUE_TEMPLATE_META_KEY, true) === '', 'Create-from-template should still clear the template marker on the new venue.');

	vms_test_reset_runtime_state();
	$eventPlanId = vms_test_register_post('vms_event_plan', 'Event Plan Fixture', 'publish');
	update_post_meta($eventPlanId, '_vms_band_vendor_id', 99999);
	update_post_meta($eventPlanId, '_vms_event_plan_status', 'published');

	$GLOBALS['vms_test_caps'] = array(
		'edit_posts' => true,
		'edit_post' => array(),
	);
	$_GET = array('post' => $eventPlanId, 'action' => 'edit');
	$guard = $GLOBALS['vms_test_event_plan_admin_guard'];
	$assert($guard instanceof Closure, 'Event Plan admin guard closure should be extractable.');
	$guard();
	$assert((int) get_post_meta($eventPlanId, '_vms_band_vendor_id', true) === 99999, 'Event Plan admin guard should not clear vendor links before object authorization passes.');
	$assert((string) get_post_meta($eventPlanId, '_vms_event_plan_status', true) === 'published', 'Event Plan admin guard should not change workflow status before object authorization passes.');
	$assert((get_post($eventPlanId) instanceof WP_Post ? get_post($eventPlanId)->post_status : '') === 'publish', 'Event Plan admin guard should not change post_status before object authorization passes.');

	$GLOBALS['vms_test_caps'] = array(
		'edit_posts' => true,
		'edit_post' => array($eventPlanId => true),
	);
	$guard();
	$assert((int) get_post_meta($eventPlanId, '_vms_band_vendor_id', true) === 0, 'Event Plan admin guard should clear broken primary-vendor links for authorized users.');
	$assert((string) get_post_meta($eventPlanId, '_vms_event_plan_status', true) === 'draft', 'Event Plan admin guard should still revert workflow status for authorized users.');
	$assert((get_post($eventPlanId) instanceof WP_Post ? get_post($eventPlanId)->post_status : '') === 'draft', 'Event Plan admin guard should still revert post_status for authorized users.');

	vms_test_reset_runtime_state();
	$GLOBALS['vms_test_caps'] = array(
		'manage_options' => false,
	);
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_GET = array('page' => 'vms-season-dates');
	$_POST = array(
		'vms_season_dates_nonce' => vms_test_nonce('vms_season_dates_55'),
		'vms_action' => 'save_rules',
		'venue_id' => 55,
	);
	$_REQUEST = array_merge($_GET, $_POST);
	vms_sd_maybe_handle_post();
	$assert((int) $GLOBALS['vms_test_season_save_calls'] === 0, 'Season Dates POST handler should not reach mutation paths without the admin capability.');

	fwrite(STDOUT, "Authorization boundary hardening OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Authorization boundary hardening FAIL: ' . $e->getMessage() . "\n");
	exit(1);
}
