<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_door_checkin_shortcode')) {
	function vms_door_checkin_shortcode($atts = array()): string
	{
		if (!is_user_logged_in() || !vms_admission_current_user_can_checkin()) {
			return '<div class="vms-door-checkin-denied">' . esc_html__('Access denied.', 'backstage-venue-manager') . '</div>';
		}

		$settings = vms_admission_settings();
		$ver = defined('BVMGR_VERSION') ? BVMGR_VERSION : null;
		wp_enqueue_style(
			'vms-door-checkin',
			BVMGR_PLUGIN_URL . 'assets/css/vms-door-checkin.css',
			array('vms-ui'),
			$ver
		);
		wp_enqueue_script(
			'vms-door-checkin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-door-checkin.js',
			array(),
			$ver,
			true
		);

		wp_localize_script('vms-door-checkin', 'vmsDoorCheckin', array(
			'restUrl' => esc_url_raw(rest_url('vms/v1')),
			'nonce' => wp_create_nonce('wp_rest'),
			'canManage' => current_user_can(vms_admission_manage_capability()) ? 1 : 0,
			'canDoor' => current_user_can(vms_admission_door_capability()) ? 1 : 0,
			'settings' => array(
				'allowUncheckin' => !empty($settings['allow_uncheckin']) ? 1 : 0,
				'allowUncheckinForDoor' => !empty($settings['allow_uncheckin_for_door']) ? 1 : 0,
			),
		));

		ob_start();
		?>
		<div class="vms-door-checkin" id="vms-door-checkin-root">
			<div class="vms-door-toolbar">
				<label class="vms-door-field">
					<span><?php esc_html_e('Select Event Plan', 'backstage-venue-manager'); ?></span>
					<select id="vms-door-event-plan"></select>
				</label>
				<input type="search" id="vms-door-search" placeholder="<?php echo esc_attr__('Scan QR or search name/phone', 'backstage-venue-manager'); ?>" autocomplete="off">
				<button type="button" class="vms-door-scan-submit" id="vms-door-scan-submit"><?php esc_html_e('Scan / Check In', 'backstage-venue-manager'); ?></button>
			</div>
			<div class="vms-door-filters" id="vms-door-filters">
				<button type="button" data-status="active" class="is-active"><?php esc_html_e('Active', 'backstage-venue-manager'); ?></button>
				<button type="button" data-status="checked_in"><?php esc_html_e('Checked In', 'backstage-venue-manager'); ?></button>
				<button type="button" data-status="all"><?php esc_html_e('All', 'backstage-venue-manager'); ?></button>
			</div>
			<div class="vms-door-summary" id="vms-door-summary"></div>
			<p class="vms-door-feedback" id="vms-door-feedback" aria-live="polite"></p>
			<div class="vms-door-results" id="vms-door-results"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
add_shortcode('vms_door_checkin', 'vms_door_checkin_shortcode');
