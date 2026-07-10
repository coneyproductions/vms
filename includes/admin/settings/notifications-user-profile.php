<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_notify_render_user_profile_fields')) {
	function vms_notify_render_user_profile_fields(WP_User $user): void
	{
		if (!current_user_can('edit_user', $user->ID)) {
			return;
		}

		$locale_pref = sanitize_text_field((string) get_user_meta($user->ID, 'vms_locale_preference', true));
		$email_enabled = get_user_meta($user->ID, 'vms_notify_email_enabled', true);
		$sms_enabled = get_user_meta($user->ID, 'vms_notify_sms_enabled', true);
		$wa_enabled = get_user_meta($user->ID, 'vms_notify_whatsapp_enabled', true);
		$phone = sanitize_text_field((string) get_user_meta($user->ID, 'vms_phone_e164', true));
		$site_locale = get_locale();

		$email_checked = ($email_enabled === '') ? true : !empty($email_enabled);
		$sms_checked = !empty($sms_enabled);
		$wa_checked = !empty($wa_enabled);

		echo '<h2>' . esc_html__('VMS Notifications', 'backstage-venue-manager') . '</h2>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr>';
		echo '<th><label for="vms_locale_preference">' . esc_html__('Locale preference', 'backstage-venue-manager') . '</label></th>';
		echo '<td>';
		echo '<select id="vms_locale_preference" name="vms_locale_preference">';
		echo '<option value="" ' . selected($locale_pref, '', false) . '>' . esc_html(sprintf(__('Use site locale (%s)', 'backstage-venue-manager'), $site_locale)) . '</option>';
		echo '<option value="en_US" ' . selected($locale_pref, 'en_US', false) . '>English (US)</option>';
		echo '<option value="es_ES" ' . selected($locale_pref, 'es_ES', false) . '>Español (ES)</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th>' . esc_html__('Channels', 'backstage-venue-manager') . '</th>';
		echo '<td>';
		echo '<label><input type="checkbox" name="vms_notify_email_enabled" value="1" ' . checked($email_checked, true, false) . '> ' . esc_html__('Email', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="vms_notify_sms_enabled" value="1" ' . checked($sms_checked, true, false) . '> ' . esc_html__('SMS', 'backstage-venue-manager') . '</label><br>';
		echo '<label><input type="checkbox" name="vms_notify_whatsapp_enabled" value="1" ' . checked($wa_checked, true, false) . '> ' . esc_html__('WhatsApp', 'backstage-venue-manager') . '</label>';
		echo '<p class="description">' . esc_html__('SMS/WhatsApp require a provider add-on and valid E.164 phone number.', 'backstage-venue-manager') . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th><label for="vms_phone_e164">' . esc_html__('Phone (E.164)', 'backstage-venue-manager') . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="vms_phone_e164" name="vms_phone_e164" value="' . esc_attr($phone) . '" placeholder="+15551234567">';
		echo '<p class="description">' . esc_html__('Format: +{countrycode}{number}, e.g. +15551234567', 'backstage-venue-manager') . '</p></td>';
		echo '</tr>';
		echo '</table>';
	}
}
add_action('show_user_profile', 'vms_notify_render_user_profile_fields');
add_action('edit_user_profile', 'vms_notify_render_user_profile_fields');

if (!function_exists('vms_notify_save_user_profile_fields')) {
	function vms_notify_save_user_profile_fields(int $user_id): void
	{
		if (!current_user_can('edit_user', $user_id)) {
			return;
		}

		$locale = sanitize_text_field((string) ($_POST['vms_locale_preference'] ?? ''));
		if ($locale !== '' && !preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
			$locale = '';
		}

		$phone = sanitize_text_field((string) ($_POST['vms_phone_e164'] ?? ''));
		if ($phone !== '' && !preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
			$phone = '';
		}

		update_user_meta($user_id, 'vms_locale_preference', $locale);
		update_user_meta($user_id, 'vms_notify_email_enabled', !empty($_POST['vms_notify_email_enabled']) ? '1' : '0');
		update_user_meta($user_id, 'vms_notify_sms_enabled', !empty($_POST['vms_notify_sms_enabled']) ? '1' : '0');
		update_user_meta($user_id, 'vms_notify_whatsapp_enabled', !empty($_POST['vms_notify_whatsapp_enabled']) ? '1' : '0');
		update_user_meta($user_id, 'vms_phone_e164', $phone);
	}
}
add_action('personal_options_update', 'vms_notify_save_user_profile_fields');
add_action('edit_user_profile_update', 'vms_notify_save_user_profile_fields');
