<?php
defined('ABSPATH') || exit;

// add_action('admin_print_styles', function (): void {
// 	$page_raw = isset($_GET['page']) ? (string) $_GET['page'] : '';
// 	$page     = $page_raw !== '' ? sanitize_key($page_raw) : '';

// 	if ($page !== 'vms' && ($page === '' || strpos($page, 'vms-') !== 0)) {
// 		return;
// 	}

// 	// Attach as late as possible to whatever handle is actually enqueued.
// 	wp_add_inline_style('vms-admin', 'body.vms-admin #wpwrap{outline:6px solid magenta !important;}');

// 	if (defined('VMS_DEBUG_ADMIN_HOOKS') && VMS_DEBUG_ADMIN_HOOKS) {
// 		error_log('VMS INLINE TEST page=' . $page . ' enqueued=' . (wp_style_is('vms-admin', 'enqueued') ? '1' : '0') . ' done=' . (wp_style_is('vms-admin', 'done') ? '1' : '0'));
// 	}
// }, 999);

if (!function_exists('vms_core')) {
	function vms_core(): array
	{
		return [
			'slug'    => 'vms',
			'version' => defined('VMS_VERSION') ? VMS_VERSION : '0.2.24.456',
		];
	}
}

add_filter('admin_body_class', function (string $classes): string {
	// Ensure VMS admin styling applies on:
	// - VMS admin pages (page=vms, page=vms-*)
	// - VMS CPT edit screens (Event Plans, Vendors, Venues)
	$page_raw = isset($_GET['page']) ? (string) $_GET['page'] : '';
	$page     = $page_raw !== '' ? sanitize_key($page_raw) : '';

	$is_vms_page = false;
	if ($page === 'vms') {
		$is_vms_page = true;
	} elseif ($page !== '' && strpos($page, 'vms-') === 0) {
		$is_vms_page = true;
	}

	$is_vms_cpt = false;
	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen && !empty($screen->post_type)) {
			$post_type = sanitize_key((string) $screen->post_type);
			if ($post_type !== '' && strpos($post_type, 'vms_') === 0) {
				$is_vms_cpt = true;
			}
		}
	}

	if (!$is_vms_page && !$is_vms_cpt) {
		return $classes;
	}

	$classes .= ' vms-admin';
	if ($page !== '') {
		$classes .= ' vms-page-' . $page;
	}
	if ($is_vms_cpt && function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen && !empty($screen->post_type)) {
			$classes .= ' vms-cpt-' . sanitize_key((string) $screen->post_type);
		}
	}

	return $classes;
});


/**
 * Admin assets
 *
 * Rules:
 * - Load on VMS top-level page (page=vms) and all VMS subpages (page starts with vms-)
 * - Optional debug logging is gated by VMS_DEBUG_ADMIN_HOOKS to avoid spam
 */
add_action('admin_enqueue_scripts', function ($hook_suffix = ''): void {

	$page_raw = isset($_GET['page']) ? (string) $_GET['page'] : '';
	$page     = $page_raw !== '' ? sanitize_key($page_raw) : '';

	// Only load assets on VMS admin pages.
	$is_vms_page = false;
	if ($page === 'vms') {
		$is_vms_page = true;
	} elseif ($page !== '' && strpos($page, 'vms-') === 0) {
		$is_vms_page = true;
	}


	// Also load assets on VMS CPT screens (so list tables and edit screens get VMS styling).
	$is_vms_cpt = false;
	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if (is_object($screen)) {
			$pt = isset($screen->post_type) ? sanitize_key((string) $screen->post_type) : '';
			if ($pt !== '' && strpos($pt, 'vms_') === 0) {
				$is_vms_cpt = true;
			}
		}
	}
	if ($is_vms_cpt) {
		$is_vms_page = true;
	}


	if (!$is_vms_page) {
		if (defined('VMS_DEBUG_ADMIN_HOOKS') && VMS_DEBUG_ADMIN_HOOKS) {
			$screen_id = '';
			if (function_exists('get_current_screen')) {
				$screen = get_current_screen();
				if (is_object($screen) && isset($screen->id)) {
					$screen_id = (string) $screen->id;
				}
			}
			error_log('VMS ADMIN ASSETS skipped page=' . $page . ' hook=' . (string) $hook_suffix . ' screen=' . $screen_id);
		}
		return;
	}

	if (defined('VMS_DEBUG_ADMIN_HOOKS') && VMS_DEBUG_ADMIN_HOOKS) {
		$screen_id = '';
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if (is_object($screen) && isset($screen->id)) {
				$screen_id = (string) $screen->id;
			}
		}
		error_log('VMS ADMIN ASSETS enqueue page=' . $page . ' hook=' . (string) $hook_suffix . ' screen=' . $screen_id);
	}

	$ver = defined('VMS_VERSION') ? VMS_VERSION : null;
 
	// Shared foundation
	wp_enqueue_style(
		'vms-shared',
		VMS_PLUGIN_URL . 'assets/css/vms-shared.css',
		[],
		$ver
	);

	wp_enqueue_style(
		'vms-ui',
		VMS_PLUGIN_URL . 'assets/css/vms-ui.css',
		['vms-shared'],
		$ver
	);

	// Admin-specific
	wp_enqueue_style(
		'vms-admin',
		VMS_PLUGIN_URL . 'assets/css/vms-admin.css',
		['vms-ui'],
		$ver
	);

	// Prevent accidental mouse-wheel changes on number fields across VMS screens.
	wp_enqueue_script(
		'vms-number-input-guard',
		VMS_PLUGIN_URL . 'assets/vms-number-input-guard.js',
		[],
		$ver,
		true
	);

	// Help Mode tooltips (only when help is enabled).
	$opts = (array) get_option('vms_settings', array());
	$help_mode = isset($opts['help_mode']) ? sanitize_key((string) $opts['help_mode']) : 'basic';
	if (!in_array($help_mode, array('off', 'basic', 'guided'), true)) {
		$help_mode = 'basic';
	}
	if ($help_mode !== 'off') {
		wp_enqueue_script(
			'vms-help-tooltips',
			VMS_PLUGIN_URL . 'assets/admin-help-tooltips.js',
			[],
			$ver,
			true
		);
	}
}, 20);

/**
 * Public / portal assets
 *
 * Rules:
 * - Load shared styles everywhere on the public site
 * - Load portal styles only when the portal shortcode is present (or allow override via filter)
 */
add_action('wp_enqueue_scripts', function (): void {

	$ver = defined('VMS_VERSION') ? VMS_VERSION : null;

	// Shared foundation
	wp_enqueue_style(
		'vms-shared',
		VMS_PLUGIN_URL . 'assets/css/vms-shared.css',
		[],
		$ver
	);

	wp_enqueue_style(
		'vms-ui',
		VMS_PLUGIN_URL . 'assets/css/vms-ui.css',
		['vms-shared'],
		$ver
	);

	// Prevent accidental mouse-wheel changes on number fields across VMS public forms.
	wp_enqueue_script(
		'vms-number-input-guard',
		VMS_PLUGIN_URL . 'assets/vms-number-input-guard.js',
		[],
		$ver,
		true
	);

	// Register portal stylesheet so the shortcode can enqueue it even when the page builder
	// does not store the shortcode in post_content (the has_shortcode() guard would miss).
	$portal_ver = $ver;
	if (defined('VMS_PLUGIN_PATH')) {
		$portal_file = VMS_PLUGIN_PATH . 'assets/css/vms-portal.css';
		if (file_exists($portal_file)) {
			$portal_ver = (string) @filemtime($portal_file);
		}
	}

	wp_register_style(
		'vms-portal',
		VMS_PLUGIN_URL . 'assets/css/vms-portal.css',
		['vms-ui'],
		$portal_ver
	);
	// Portal stylesheet
	//
	// We enqueue this globally on the public site because page builders and template injections
	// can render the [vms_vendor_portal] shortcode without it existing in post_content, which
	// makes has_shortcode() based gating unreliable. The CSS is scoped under .vms-portal so it
	// will not affect other public pages.
	$should = apply_filters('vms_should_enqueue_portal_assets', true);
	if ($should !== false) {
		wp_enqueue_style('vms-portal');
	}
}, 20);

/**
 * Ensure DB schema migrations run on update as well as initial install.
 *
 * Notes:
 * - Activation hooks are intentionally not relied upon here (they may be disabled).
 * - Migrations are idempotent; once at the latest version, this is a quick no-op.
 */
add_action('plugins_loaded', function (): void {
	if (defined('WP_INSTALLING') && WP_INSTALLING) return;
	if (!defined('VMS_PLUGIN_PATH')) return;
	if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) return;

	if (function_exists('vms_require_internal_file') && !vms_require_internal_file('includes/db/migrations.php', 'missing_db_migrations_runtime', 'Database migration checks')) {
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v7')) {
		vms_db_migrate_vendor_core_v7();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v6')) {
		vms_db_migrate_vendor_core_v6();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v5')) {
		vms_db_migrate_vendor_core_v5();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v4')) {
		vms_db_migrate_vendor_core_v4();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v3')) {
		vms_db_migrate_vendor_core_v3();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v2')) {
		vms_db_migrate_vendor_core_v2();
		return;
	}

	if (function_exists('vms_db_migrate_vendor_core_v1')) {
		vms_db_migrate_vendor_core_v1();
	}
}, 5);
