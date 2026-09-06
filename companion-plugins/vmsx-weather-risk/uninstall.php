<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

wp_clear_scheduled_hook('vmsx_weather_risk_refresh_cron');

if (!get_option('vmsx_weather_risk_remove_on_uninstall', false)) {
	return;
}

delete_option('vmsx_weather_risk_settings');
delete_option('vmsx_weather_risk_log');

$plan_ids = get_posts(array(
	'post_type' => 'vms_event_plan',
	'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
	'posts_per_page' => -1,
	'fields' => 'ids',
	'suppress_filters' => false,
));

foreach ((array) $plan_ids as $plan_id) {
	delete_post_meta((int) $plan_id, 'vmsx_weather_risk_snapshot');
}
