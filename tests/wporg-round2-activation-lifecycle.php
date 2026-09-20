<?php
declare(strict_types=1);

/** Disposable WordPress activation/deactivation/reactivation integration proof. */

$wpLoad = (string) getenv('VMS_TEST_WP_LOAD');
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1'
	|| $wpLoad === ''
	|| strpos($wpLoad, '/private/tmp/bvm-authority-integration-20260906/runtime/source-wordpress/') !== 0) {
	throw new RuntimeException('Disposable WordPress supervisor required.');
}
require $wpLoad;
if (DB_NAME !== 'bvm_integration_source' || DB_HOST !== 'localhost:' . getenv('BVM_DISPOSABLE_DB_SOCKET')) {
	throw new RuntimeException('Disposable database identity mismatch.');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	$checks++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$plugin = 'backstage-venue-manager/backstage-venue-manager.php';
$pageOptions = array(
	'vms_page_vendor_application',
	'vms_page_vendor_portal',
	'vms_page_staff_portal',
	'vms_page_public_calendar',
);
$cronHooks = array(
	'vms_social_process_queue',
	'vms_tasks_notification_tick',
	'vms_tasks_nightly_generator',
	'vms_email_followups_cron',
	'vms_calendar_ticket_counts_nightly',
	'vms_vendor_booking_onboarding',
	'vms_notification_digest',
	'vms_ticket_integrity_scan',
	'vms_integrity_daily_scan',
	'vms_ticketing_v2_legacy_cleanup',
	'vms_ticketing_verification_cleanup',
);
$cronCounts = static function () use ($cronHooks): array {
	$counts = array_fill_keys($cronHooks, 0);
	foreach ((array) _get_cron_array() as $events) {
		foreach ((array) $events as $hook => $instances) {
			if (array_key_exists((string) $hook, $counts)) {
				$counts[(string) $hook] += count((array) $instances);
			}
		}
	}
	return $counts;
};

$assert(is_plugin_active($plugin), 'Supervisor fresh-install activation must leave canonical BVM active.');
$activePlugins = (array) get_option('active_plugins', array());
$assert(count(array_keys($activePlugins, $plugin, true)) === 1, 'Fresh activation must contain exactly one canonical BVM basename.');
$assert(!in_array('vms/vendor-management-system.php', $activePlugins, true), 'Fresh activation must not depend on the legacy VMS installation.');

$pageIds = array();
foreach ($pageOptions as $option) {
	$pageIds[$option] = absint(get_option($option, 0));
	$assert($pageIds[$option] > 0 && get_post($pageIds[$option]) instanceof WP_Post, 'Fresh activation must create or retain ' . $option . '.');
}
$pageCount = (int) wp_count_posts('page')->publish + (int) wp_count_posts('page')->draft + (int) wp_count_posts('page')->private;
$vendorPortalId = $pageIds['vms_page_vendor_portal'];
$customContent = 'Operator-customized activation-state canary ' . wp_generate_uuid4();
wp_update_post(array('ID' => $vendorPortalId, 'post_content' => $customContent));
update_option('bvm_step6_existing_state_canary', array('preserve' => true), false);
$schemaVersion = get_option('vms_db_schema_version', '');

deactivate_plugins($plugin, true, false);
$assert(!is_plugin_active($plugin), 'Deactivation must remain an explicit WordPress state transition.');
$assert(get_option('bvm_step6_existing_state_canary') === array('preserve' => true), 'Deactivation must retain existing durable option data.');
$assert((string) get_post_field('post_content', $vendorPortalId) === $customContent, 'Deactivation must retain operator-customized managed-page content.');

$activationResult = activate_plugin($plugin, '', false, true);
$assert(!is_wp_error($activationResult), 'Normal reactivation must succeed in the disposable site.');
$assert(is_plugin_active($plugin), 'Normal reactivation must restore the canonical active basename.');
$assert(get_option('vms_db_schema_version', '') === $schemaVersion, 'Reactivation must preserve the completed schema version marker.');
$assert(get_option('bvm_step6_existing_state_canary') === array('preserve' => true), 'Reactivation must preserve populated state.');
$assert((string) get_post_field('post_content', $vendorPortalId) === $customContent, 'Reactivation must not overwrite operator-customized managed-page content.');
foreach ($pageIds as $option => $pageId) {
	$assert(absint(get_option($option, 0)) === $pageId, 'Reactivation must retain the owned page ID for ' . $option . '.');
}
$assert(((int) wp_count_posts('page')->publish + (int) wp_count_posts('page')->draft + (int) wp_count_posts('page')->private) === $pageCount, 'Reactivation must not duplicate public pages.');

$cronAfterReactivation = $cronCounts();
$repeatResult = activate_plugin($plugin, '', false, true);
$assert(!is_wp_error($repeatResult), 'Repeated activation must complete without an error.');
$assert($cronCounts() === $cronAfterReactivation, 'Repeated activation must not duplicate owned WordPress cron events.');
$assert(get_option('vms_db_schema_version', '') === $schemaVersion, 'Repeated activation must leave the migration marker idempotent.');
$assert(get_option('bvm_step6_existing_state_canary') === array('preserve' => true), 'Repeated activation must retain populated state.');
$assert((string) get_post_field('post_content', $vendorPortalId) === $customContent, 'Repeated activation must preserve customized public resources.');

fwrite(STDOUT, 'PASS ' . $checks . " disposable activation lifecycle assertions.\n");
