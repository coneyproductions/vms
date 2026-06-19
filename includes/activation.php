<?php
if (!defined('ABSPATH')) exit;

/**
 * Runs on plugin activation (hooked from the main plugin file).
 */
function vms_activate_plugin(): void
{
	if (function_exists('vms_resource_fingerprint_flag')) {
		vms_resource_fingerprint_flag('plugin_activation', 'vms/vendor-management-system.php');
	}

	if (function_exists('vms_cleanup_legacy_square_nightly_sync_hook')) {
		vms_cleanup_legacy_square_nightly_sync_hook();
	}

	if (function_exists('vms_require_internal_file') && vms_require_internal_file('includes/db/migrations.php', 'missing_db_migrations_activation', 'Database migrations')) {
		if (function_exists('vms_db_migrate_vendor_core_v7')) {
			vms_db_migrate_vendor_core_v7();
		} elseif (function_exists('vms_db_migrate_vendor_core_v6')) {
			vms_db_migrate_vendor_core_v6();
		} elseif (function_exists('vms_db_migrate_vendor_core_v5')) {
			vms_db_migrate_vendor_core_v5();
		} elseif (function_exists('vms_db_migrate_vendor_core_v4')) {
			vms_db_migrate_vendor_core_v4();
		} elseif (function_exists('vms_db_migrate_vendor_core_v3')) {
			vms_db_migrate_vendor_core_v3();
		} elseif (function_exists('vms_db_migrate_vendor_core_v2')) {
			vms_db_migrate_vendor_core_v2();
		} elseif (function_exists('vms_db_migrate_vendor_core_v1')) {
			vms_db_migrate_vendor_core_v1();
		}
	}

	// Create/ensure public pages
	vms_install_public_pages();

	$recurring_bootstraps = array(
		'vms_social_schedule_cron',
		'vms_tasks_notifications_ensure_cron',
		'vms_tasks_schedule_nightly_generator',
		'vms_email_followups_schedule_cron',
		'vms_calendar_ticket_counts_schedule_cron',
		'vms_vendor_booking_onboarding_schedule_event',
		'vms_notify_ensure_digest_cron',
		'vms_ticket_integrity_maybe_schedule_cron',
		'vms_integrity_schedule_daily_scan',
		'vms_ticketing_v2_legacy_cleanup_cron_init',
		'vms_ticketing_verification_schedule_cleanup',
	);
	foreach ($recurring_bootstraps as $bootstrap) {
		if (function_exists($bootstrap)) {
			$bootstrap();
		}
	}

	// One-time notice flag
	update_option('vms_show_first_run_notice', '1', false);

	// If your plugin registers rewrite rules, flush once on activation
	flush_rewrite_rules();
}

function vms_deactivate_plugin(): void
{
	if (function_exists('vms_resource_fingerprint_flag')) {
		vms_resource_fingerprint_flag('plugin_deactivation', 'vms/vendor-management-system.php');
	}

	if (function_exists('vms_cleanup_legacy_square_nightly_sync_hook')) {
		vms_cleanup_legacy_square_nightly_sync_hook();
	}

	if (function_exists('vms_unschedule_all_owned_cron_hooks')) {
		vms_unschedule_all_owned_cron_hooks();
	}
	flush_rewrite_rules();
}

if (!function_exists('vms_cleanup_legacy_square_nightly_sync_hook')) {
	function vms_cleanup_legacy_square_nightly_sync_hook(): void
	{
		$hook = 'vms_square_nightly_sync';
		if (function_exists('wp_clear_scheduled_hook')) {
			wp_clear_scheduled_hook($hook);
		}

		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions($hook);
			return;
		}

		if (!class_exists('ActionScheduler') || !class_exists('ActionScheduler_Store') || !method_exists('ActionScheduler', 'store')) {
			return;
		}

		try {
			$store = ActionScheduler::store();
			if (!is_object($store) || !method_exists($store, 'query_actions')) {
				return;
			}

			$action_ids = (array) $store->query_actions(array(
				'hook' => $hook,
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 100,
				'orderby' => 'none',
			));
			foreach ($action_ids as $action_id) {
				$action_id = (int) $action_id;
				if ($action_id <= 0) {
					continue;
				}

				if (method_exists($store, 'cancel_action')) {
					$store->cancel_action($action_id);
				} elseif (method_exists($store, 'delete_action')) {
					$store->delete_action($action_id);
				}
			}
		} catch (Throwable $e) {
			return;
		}
	}
}

if (!function_exists('vms_maybe_cleanup_legacy_square_nightly_sync_hook')) {
	function vms_maybe_cleanup_legacy_square_nightly_sync_hook(): void
	{
		if (!function_exists('get_option') || !function_exists('update_option')) {
			return;
		}

		$option_key = 'vms_cleanup_legacy_square_nightly_sync_0_2_24_748';
		if (get_option($option_key, '') === '1') {
			return;
		}

		vms_cleanup_legacy_square_nightly_sync_hook();
		update_option($option_key, '1', false);
	}
}
add_action('init', 'vms_maybe_cleanup_legacy_square_nightly_sync_hook', 5);

/**
 * Ensure a WP Page exists by slug. Creates it if missing.
 * If a page exists in trash, restores it.
 *
 * @return int Page ID (0 on failure)
 */
function vms_ensure_page_exists(array $args): int
{
	$slug    = isset($args['slug']) ? sanitize_title((string) $args['slug']) : '';
	$title   = isset($args['title']) ? sanitize_text_field((string) $args['title']) : '';
	$content = isset($args['content']) ? (string) $args['content'] : '';

	if ($slug === '' || $title === '') {
		return 0;
	}

	$existing = get_page_by_path($slug, OBJECT, 'page');

	if ($existing instanceof WP_Post) {
		$update = [
			'ID'           => $existing->ID,
			'post_title'   => $title,
			'post_content' => $content,
		];

		if ($existing->post_status === 'trash') {
			$update['post_status'] = 'draft';
		}

		wp_update_post($update);
		return (int) $existing->ID;
	}

	$new_id = wp_insert_post([
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
	], true);

	if (is_wp_error($new_id) || !$new_id) {
		return 0;
	}

	return (int) $new_id;
}

/**
 * Create/ensure VMS public pages (Vendor Portal, Staff Portal, etc.)
 * Called from activation.
 */
function vms_install_public_pages(): void
{
	$pages = function_exists('vms_required_public_pages')
		? (array) vms_required_public_pages()
		: array(
			'vendor_application' => array(
				'slug'    => 'vendor-application',
				'title'   => 'Vendor Application',
				'content' => "[vms_vendor_apply]\n",
			),
			'vendor_portal' => array(
				'slug'    => 'vendor-portal',
				'title'   => 'Vendor Portal',
				'content' => "[vms_vendor_portal]\n",
			),
			'staff_portal' => array(
				'slug'    => 'staff-portal',
				'title'   => 'Staff Portal',
				'content' => "[vms_staff_portal]\n",
			),
			'public_calendar' => array(
				'slug'    => 'events-calendar',
				'title'   => 'Public Calendar',
				'content' => "[vms_public_calendar]\n",
			),
		);

	foreach ($pages as $key => $p) {
		$page_id = vms_ensure_page_exists($p);
		if ($page_id > 0) {
			update_option('vms_page_' . $key, $page_id, false);
		}
	}
}
