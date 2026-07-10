<?php
if (!defined('ABSPATH')) exit;

/**
 * ⚠️ ARCHITECTURE RULE (DO NOT VIOLATE)
 *
 * This file MUST NOT reference raw meta keys (e.g. '_vms_*').
 * All meta keys MUST come from meta-keys.php via vms_meta_key().
 * If a required key is missing, STOP and add it to meta-keys.php first.
 *
 * Security rule:
 * - This page does NOT collect/store SSN/EIN in VMS.
 */

function vms_tax_settings_get_provider(): string
{
	$settings = get_option('vms_settings', array());
	$settings = is_array($settings) ? $settings : array();

	$provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : '';
	if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
		$provider = 'upload';
	}
	return $provider;
}

function vms_tax_provider_label(string $provider): string
{
	if ($provider === 'quickbooks_email') return 'QuickBooks Online';
	if ($provider === 'tax1099_email') return 'Tax1099';
	return 'Upload';
}

function vms_tax_provider_instructions(string $provider): string
{
	if ($provider === 'quickbooks_email') {
		return "We will email you a secure request from QuickBooks Online to complete your W-9/tax information.\n\nPlease complete it using the secure link in that email.\n\nDo not email SSN or tax documents to us.\n\nAfter completion, return here and confirm below.";
	}

	if ($provider === 'tax1099_email') {
		return "We will email you a secure W-9 request from Tax1099.\n\nPlease complete it using the secure link in that email.\n\nDo not email SSN or tax documents to us.\n\nAfter completion, return here and confirm below.";
	}

	return "Upload your completed and signed W-9 as a PDF or image.\n\nDo not email SSN or tax documents to us.";
}

/**
 * Completion rules are implemented centrally in helpers.php (single source of truth).
 * This portal view calls vms_vendor_tax_profile_is_complete() from that helper.
 */

function vms_vendor_portal_render_tax_profile($vendor_id)
{
	$vendor_id = (int) $vendor_id;
	if ($vendor_id <= 0) {
		echo '<p>' . esc_html__('Invalid vendor.', 'backstage-venue-manager') . '</p>';
		return;
	}

	$provider = vms_tax_settings_get_provider();

	if ($provider === 'upload') {
		if (!function_exists('media_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	// Resolve keys once
	$k_legal   = vms_meta_key('vendor', 'payee_legal_name');
	$k_dba     = vms_meta_key('vendor', 'payee_dba');
	$k_entity  = vms_meta_key('vendor', 'entity_type');

	$k_addr1   = vms_meta_key('vendor', 'addr1');
	$k_addr2   = vms_meta_key('vendor', 'addr2');
	$k_city    = vms_meta_key('vendor', 'city');
	$k_state   = vms_meta_key('vendor', 'state');
	$k_zip     = vms_meta_key('vendor', 'zip');

	$k_upload  = vms_meta_key('vendor', 'w9_upload_id');
	$k_recv    = vms_meta_key('vendor', 'w9_received_date');

	$k_attest  = vms_meta_key('vendor', 'w9_attested_at');
	$k_prov    = vms_meta_key('vendor', 'w9_provider');

	$k_done    = vms_meta_key('vendor', 'tax_profile_completed_at');

	// Save handler
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_vendor_tax_save'])) {

		if (empty($_POST['vms_vendor_tax_nonce']) || !wp_verify_nonce($_POST['vms_vendor_tax_nonce'], 'vms_vendor_tax_save')) {
			echo vms_portal_notice('error', __('Security check failed.', 'backstage-venue-manager'));
		} else {

			$t = function ($key) {
				return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
			};

			update_post_meta($vendor_id, $k_legal,  $t('vms_payee_legal_name'));
			update_post_meta($vendor_id, $k_dba,    $t('vms_payee_dba'));
			update_post_meta($vendor_id, $k_entity, $t('vms_entity_type'));

			$state = strtoupper($t('vms_state'));
			if (strlen($state) > 2) $state = substr($state, 0, 2);

			update_post_meta($vendor_id, $k_addr1, $t('vms_addr1'));
			update_post_meta($vendor_id, $k_addr2, $t('vms_addr2'));
			update_post_meta($vendor_id, $k_city,  $t('vms_city'));
			update_post_meta($vendor_id, $k_state, $state);
			update_post_meta($vendor_id, $k_zip,   $t('vms_zip'));

			$vendor_update_context = '';

			if ($provider === 'upload') {

				if (!empty($_FILES['vms_w9_upload']['name'])) {

					$allowed = array('application/pdf', 'image/jpeg', 'image/png', 'image/webp');
					$type = isset($_FILES['vms_w9_upload']['type']) ? (string) $_FILES['vms_w9_upload']['type'] : '';

					if ($type && !in_array($type, $allowed, true)) {
						echo vms_portal_notice('error', __('Upload must be a PDF or image (JPG/PNG/WEBP).', 'backstage-venue-manager'));
					} else {

						$attach_id = media_handle_upload('vms_w9_upload', 0);

						if (is_wp_error($attach_id)) {
							echo vms_portal_notice('error', __('W-9 upload failed: ', 'backstage-venue-manager') . $attach_id->get_error_message());
						} else {
							update_post_meta($vendor_id, $k_upload, (int) $attach_id);
							update_post_meta($vendor_id, $k_recv, function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d'));
							$vendor_update_context = 'tax_w9_upload';
						}
					}
				}

			} else {

				$attest = isset($_POST['vms_w9_offsite_attest']) ? (string) $_POST['vms_w9_offsite_attest'] : '';
				$was_attested = (int) get_post_meta($vendor_id, $k_attest, true);

				if ($attest === '1') {
					if (!$was_attested) {
						update_post_meta($vendor_id, $k_attest, time());
						$vendor_update_context = 'tax_w9_offsite_attest';
					}
					update_post_meta($vendor_id, $k_prov, $provider);
				} else {
					delete_post_meta($vendor_id, $k_attest);
					delete_post_meta($vendor_id, $k_prov);
				}
			}

			if ($vendor_update_context !== '' && function_exists('vms_vendor_flag_vendor_update')) {
				vms_vendor_flag_vendor_update($vendor_id, $vendor_update_context);
			}

			// Completion stamp truth
			if (vms_vendor_tax_profile_is_complete($vendor_id)) {
				if (!(int) get_post_meta($vendor_id, $k_done, true)) {
					update_post_meta($vendor_id, $k_done, time());
				}
			} else {
				delete_post_meta($vendor_id, $k_done);
			}

			echo vms_portal_notice('success', __('Tax Profile saved.', 'backstage-venue-manager'));
		}
	}

	// Load current values
	$m = function ($key, $default = '') use ($vendor_id) {
		$v = get_post_meta($vendor_id, $key, true);
		return ($v === '' || $v === null) ? $default : $v;
	};

	$payee_legal = $m($k_legal);
	$dba         = $m($k_dba);
	$entity      = $m($k_entity);

	$addr1 = $m($k_addr1);
	$addr2 = $m($k_addr2);
	$city  = $m($k_city);
	$state = $m($k_state);
	$zip   = $m($k_zip);

	$w9_upload_id = (int) get_post_meta($vendor_id, $k_upload, true);
	$w9_url = $w9_upload_id ? wp_get_attachment_url($w9_upload_id) : '';

	$attested_at = (int) get_post_meta($vendor_id, $k_attest, true);
	$attested_checked = ($attested_at > 0);

	$is_complete = vms_vendor_tax_profile_is_complete($vendor_id);

	$entity_types = array(
		''            => __('— Select —', 'backstage-venue-manager'),
		'individual'  => __('Individual / Sole Proprietor', 'backstage-venue-manager'),
		'single_llc'  => __('Single-member LLC', 'backstage-venue-manager'),
		'llc'         => __('LLC (multi-member)', 'backstage-venue-manager'),
		'partnership' => __('Partnership', 'backstage-venue-manager'),
		's_corp'      => __('S-Corp', 'backstage-venue-manager'),
		'c_corp'      => __('C-Corp', 'backstage-venue-manager'),
		'nonprofit'   => __('Nonprofit / Exempt', 'backstage-venue-manager'),
		'other'       => __('Other', 'backstage-venue-manager'),
	);

	$provider_label = vms_tax_provider_label($provider);

	echo '<details class="vms-panel" open>';
	echo '<summary class="vms-panel-summary">';
	echo '<span>' . esc_html__('Tax Profile (Required)', 'backstage-venue-manager') . '</span>';
	echo $is_complete
		? '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>'
		: '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>';
	echo '</summary>';

	echo '<div class="vms-vtp-panel-body">';

	echo '<p class="description vms-vtp-intro">' .
		esc_html__('Please complete this once so we have everything needed for year-end 1099 processing. For security, do NOT enter SSN/EIN on this website.', 'backstage-venue-manager') .
		'</p>';

	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field('vms_vendor_tax_save', 'vms_vendor_tax_nonce');

	echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'backstage-venue-manager') . '</strong> ' .
		esc_html__('Do not type SSN/EIN here.', 'backstage-venue-manager') .
		'</div>';

	echo '<h3 class="vms-vtp-subhead vms-vtp-subhead-first">' . esc_html__('Payee & Entity', 'backstage-venue-manager') . '</h3>';
	echo '<div class="vms-grid vms-vtp-grid">';

	echo '<div class="vms-field">';
	echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
	echo '</div>';

	echo '<div class="vms-field">';
	echo '<label for="vms_payee_dba">' . esc_html__('DBA (Optional)', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_payee_dba" name="vms_payee_dba" value="' . esc_attr($dba) . '">';
	echo '</div>';

	echo '<div class="vms-field vms-vtp-full">';
	echo '<label for="vms_entity_type">' . esc_html__('Entity Type', 'backstage-venue-manager') . '</label>';
	echo '<select id="vms_entity_type" name="vms_entity_type" required>';
	foreach ($entity_types as $k => $label) {
		echo '<option value="' . esc_attr($k) . '"' . selected($entity, $k, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select>';
	echo '</div>';

	echo '</div>';

	echo '<h3 class="vms-vtp-subhead">' . esc_html__('Mailing Address', 'backstage-venue-manager') . '</h3>';
	echo '<div class="vms-grid vms-vtp-grid">';

	echo '<div class="vms-field vms-vtp-full">';
	echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
	echo '</div>';

	echo '<div class="vms-field vms-vtp-full">';
	echo '<label for="vms_addr2">' . esc_html__('Address Line 2 (Optional)', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_addr2" name="vms_addr2" value="' . esc_attr($addr2) . '">';
	echo '</div>';

	echo '<div class="vms-field">';
	echo '<label for="vms_city">' . esc_html__('City', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_city" name="vms_city" value="' . esc_attr($city) . '" required>';
	echo '</div>';

	echo '<div class="vms-field">';
	echo '<label for="vms_state">' . esc_html__('State', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_state" name="vms_state" value="' . esc_attr($state) . '" maxlength="2" placeholder="TX" required>';
	echo '</div>';

	echo '<div class="vms-field">';
	echo '<label for="vms_zip">' . esc_html__('ZIP', 'backstage-venue-manager') . '</label>';
	echo '<input type="text" id="vms_zip" name="vms_zip" value="' . esc_attr($zip) . '" required>';
	echo '</div>';

	echo '</div>';

	echo '<h3 class="vms-vtp-subhead">' . esc_html__('W-9', 'backstage-venue-manager') . ' (' . esc_html($provider_label) . ')</h3>';

	echo '<div class="vms-note vms-vtp-provider-note">';
	echo esc_html(vms_tax_provider_instructions($provider));
	echo '</div>';

	if ($provider === 'upload') {

		if ($w9_url) {
			echo '<p class="description vms-vtp-upload-note">' .
				esc_html__('W-9 on file:', 'backstage-venue-manager') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' . esc_html__('View', 'backstage-venue-manager') . '</a>' .
				'</p>';
		}

		echo '<div class="vms-field vms-vtp-field-wide">';
		echo '<label for="vms_w9_upload">' . esc_html__('W-9 Upload (PDF/image)', 'backstage-venue-manager') . '</label>';
		echo '<input type="file" id="vms_w9_upload" name="vms_w9_upload" accept=".pdf,image/jpeg,image/png,image/webp">';
		echo '</div>';

	} else {

		echo '<div class="vms-field vms-vtp-field-wide">';
		echo '<label class="vms-vtp-attest-label">';
		echo '<input type="checkbox" name="vms_w9_offsite_attest" value="1"' . checked($attested_checked, true, false) . '>';
		echo '<span>' . esc_html__('I have completed my W-9/tax information using the secure email request.', 'backstage-venue-manager') . '</span>';
		echo '</label>';
		echo '</div>';
	}

	echo '<p class="vms-m0 vms-mt-14">';
	echo '<button type="submit" class="button button-primary" name="vms_vendor_tax_save" value="1">' . esc_html__('Save Tax Profile', 'backstage-venue-manager') . '</button>';
	echo '</p>';

	echo '</form>';

	echo '</div></details>';
}
