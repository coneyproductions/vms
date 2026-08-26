<?php
declare(strict_types=1);

if (!defined('DOING_AJAX')) {
	define('DOING_AJAX', true);
}
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}

require_once __DIR__ . '/bootstrap-wordpress.php';
vms_tests_require_wordpress(__DIR__);

if (!class_exists('BVMGR_Admin_Event_Plans')) {
	require_once dirname(__DIR__) . '/backstage-venue-manager.php';
}

final class VMS_Vendor_Availability_Ajax_Exit extends RuntimeException
{
}

function vms_vendor_availability_test_wp_die_handler($message = '', $title = '', $args = array()): void
{
	unset($title, $args);

	if (is_scalar($message)) {
		throw new VMS_Vendor_Availability_Ajax_Exit((string) $message);
	}

	throw new VMS_Vendor_Availability_Ajax_Exit('wp_die');
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$createdPosts = array();
$originalPost = $_POST ?? array();
$originalGet = $_GET ?? array();
$originalRequest = $_REQUEST ?? array();
$originalVendorMeta = get_user_meta(1, '_vms_vendor_id', true);
$originalUser = get_current_user_id();

$registerPost = static function (int $postId) use (&$createdPosts): int {
	$createdPosts[] = $postId;
	return $postId;
};

$cleanup = static function () use (&$createdPosts, &$originalPost, &$originalGet, &$originalRequest, $originalVendorMeta, $originalUser): void {
	foreach (array_reverse($createdPosts) as $postId) {
		wp_delete_post((int) $postId, true);
	}

	if ($originalVendorMeta === '' || $originalVendorMeta === null) {
		delete_user_meta(1, '_vms_vendor_id');
	} else {
		update_user_meta(1, '_vms_vendor_id', $originalVendorMeta);
	}

	$_POST = $originalPost;
	$_GET = $originalGet;
	$_REQUEST = $originalRequest;
	wp_set_current_user((int) $originalUser);
};

try {
	wp_set_current_user(1);

	$assert(false !== has_action('wp_ajax_vms_save_manual_availability_day', 'vms_save_manual_availability_day_ajax'), 'Expected the Vendor Portal manual availability AJAX action to remain registered.');
	$assert(function_exists('vms_save_manual_availability_day_ajax'), 'Expected the Vendor Portal manual availability AJAX callback to exist.');
	$assert(current_user_can('manage_options'), 'Expected test user 1 to remain an administrator for preview coverage.');

	$dieHandlerFilter = static function (): string {
		return 'vms_vendor_availability_test_wp_die_handler';
	};
	add_filter('wp_die_handler', $dieHandlerFilter);
	add_filter('wp_die_ajax_handler', $dieHandlerFilter);

	$createVendor = static function (string $title) use ($registerPost): int {
		$vendorId = wp_insert_post(array(
			'post_type' => 'vms_vendor',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($vendorId) || (int) $vendorId <= 0) {
			throw new RuntimeException('Failed to create AJAX vendor fixture: ' . $title);
		}
		return $registerPost((int) $vendorId);
	};

	$activeDates = vms_vendor_get_active_dates_or_rolling_window(1);
	$assert(!empty($activeDates), 'Expected active availability dates for AJAX coverage.');
	$activeDate = (string) $activeDates[0];

	$primaryVendorId = $createVendor('Vendor Portal Autosave Primary Vendor');
	$previewVendorId = $createVendor('Vendor Portal Autosave Preview Vendor');

	update_post_meta($primaryVendorId, '_vms_availability_manual', array());
	update_post_meta($previewVendorId, '_vms_availability_manual', array());
	delete_user_meta(1, '_vms_vendor_id');

	$invokeAjax = static function (array $request, bool $linkPrimaryVendor = false) use ($primaryVendorId): array {
		wp_set_current_user(1);
		if ($linkPrimaryVendor) {
			update_user_meta(1, '_vms_vendor_id', $primaryVendorId);
		} else {
			delete_user_meta(1, '_vms_vendor_id');
		}

		$_GET = array();
		$_POST = $request;
		$_REQUEST = $request;

		$body = '';
		$exitMessage = '';
		try {
			ob_start();
			vms_save_manual_availability_day_ajax();
			$body = (string) ob_get_clean();
		} catch (VMS_Vendor_Availability_Ajax_Exit $e) {
			$body = (string) ob_get_clean();
			$exitMessage = $e->getMessage();
		}

		$json = json_decode($body, true);
		return array(
			'body' => $body,
			'exit' => $exitMessage,
			'json' => is_array($json) ? $json : null,
		);
	};

	$vendorNonce = static function (): string {
		wp_set_current_user(1);
		return wp_create_nonce('vms_avail_ajax');
	};

	$previewNonce = static function (int $vendorId): string {
		wp_set_current_user(1);
		return wp_create_nonce('vms_preview_vendor_portal_' . $vendorId);
	};

	$validSave = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => $activeDate,
		'state' => 'available',
	), true);
	$assert(isset($validSave['json']['success']) && $validSave['json']['success'] === true, 'Valid vendor availability save should succeed.');
	$assert((string) ($validSave['json']['data']['date'] ?? '') === $activeDate, 'Valid save should echo the saved date.');
	$assert((string) ($validSave['json']['data']['state'] ?? '') === 'available', 'Valid save should echo the saved state.');
	$primaryManual = get_post_meta($primaryVendorId, '_vms_availability_manual', true);
	$assert(is_array($primaryManual) && (string) ($primaryManual[$activeDate] ?? '') === 'available', 'Valid save should persist the requested manual availability value.');
	$assert((string) get_post_meta($primaryVendorId, '_vms_availability_preferred_method', true) === 'manual', 'Valid save should preserve the preferred method update.');

	$invalidDate = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => 'not-a-date',
		'state' => 'unavailable',
	), true);
	$assert(isset($invalidDate['json']['success']) && $invalidDate['json']['success'] === false, 'Invalid date should be rejected.');
	$assert((string) ($invalidDate['json']['data']['message'] ?? '') === 'Invalid date.', 'Invalid date rejection message should remain stable.');
	$primaryManual = get_post_meta($primaryVendorId, '_vms_availability_manual', true);
	$assert(is_array($primaryManual) && (string) ($primaryManual[$activeDate] ?? '') === 'available', 'Invalid date should not persist a new manual state.');

	$invalidState = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => $activeDate,
		'state' => 'tentative',
	), true);
	$assert(isset($invalidState['json']['success']) && $invalidState['json']['success'] === false, 'Invalid state should be rejected.');
	$assert((string) ($invalidState['json']['data']['message'] ?? '') === 'Invalid state.', 'Invalid state rejection message should remain stable.');
	$primaryManual = get_post_meta($primaryVendorId, '_vms_availability_manual', true);
	$assert(is_array($primaryManual) && (string) ($primaryManual[$activeDate] ?? '') === 'available', 'Invalid state should not overwrite the stored manual value.');

	$missingNonce = $invokeAjax(array(
		'date' => $activeDate,
		'state' => 'unavailable',
	), true);
	$assert(trim((string) $missingNonce['body']) === '-1' || trim((string) $missingNonce['exit']) === '-1', 'Missing availability nonce should be rejected by the existing AJAX nonce check.');
	$primaryManual = get_post_meta($primaryVendorId, '_vms_availability_manual', true);
	$assert(is_array($primaryManual) && (string) ($primaryManual[$activeDate] ?? '') === 'available', 'Missing nonce should not change stored availability.');

	$invalidNonce = $invokeAjax(array(
		'nonce' => 'expired',
		'date' => $activeDate,
		'state' => 'unavailable',
	), true);
	$assert(trim((string) $invalidNonce['body']) === '-1' || trim((string) $invalidNonce['exit']) === '-1', 'Invalid availability nonce should be rejected by the existing AJAX nonce check.');
	$primaryManual = get_post_meta($primaryVendorId, '_vms_availability_manual', true);
	$assert(is_array($primaryManual) && (string) ($primaryManual[$activeDate] ?? '') === 'available', 'Invalid nonce should not change stored availability.');

	$vendorNotLinked = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => $activeDate,
		'state' => 'unavailable',
	), false);
	$assert(isset($vendorNotLinked['json']['success']) && $vendorNotLinked['json']['success'] === false, 'Unlinked vendor context should be rejected.');
	$assert((string) ($vendorNotLinked['json']['data']['message'] ?? '') === 'Vendor not linked.', 'Unlinked vendor rejection message should remain stable.');

	$validPreview = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => $activeDate,
		'state' => 'unavailable',
		'vms_preview_vendor' => $previewVendorId,
		'vms_preview_nonce' => $previewNonce($previewVendorId),
	), false);
	$assert(isset($validPreview['json']['success']) && $validPreview['json']['success'] === true, 'Valid preview-vendor autosave should succeed.');
	$previewManual = get_post_meta($previewVendorId, '_vms_availability_manual', true);
	$assert(is_array($previewManual) && (string) ($previewManual[$activeDate] ?? '') === 'unavailable', 'Valid preview-vendor autosave should persist to the preview vendor.');
	$assert((string) get_post_meta($previewVendorId, '_vms_availability_preferred_method', true) === 'manual', 'Valid preview-vendor autosave should preserve the manual preferred method update.');

	$invalidPreview = $invokeAjax(array(
		'nonce' => $vendorNonce(),
		'date' => $activeDate,
		'state' => 'available',
		'vms_preview_vendor' => $previewVendorId,
		'vms_preview_nonce' => 'expired-preview',
	), false);
	$assert(isset($invalidPreview['json']['success']) && $invalidPreview['json']['success'] === false, 'Invalid preview nonce should be rejected.');
	$assert((string) ($invalidPreview['json']['data']['message'] ?? '') === 'Vendor not linked.', 'Invalid preview nonce should fall through to the existing vendor-not-linked rejection.');
	$previewManual = get_post_meta($previewVendorId, '_vms_availability_manual', true);
	$assert(is_array($previewManual) && (string) ($previewManual[$activeDate] ?? '') === 'unavailable', 'Invalid preview nonce should not overwrite the preview vendor availability.');

	fwrite(STDOUT, "Vendor Portal availability autosave AJAX OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Vendor Portal availability autosave AJAX FAIL - ' . $e->getMessage() . "\n");
	exit(1);
} finally {
	$cleanup();
}
