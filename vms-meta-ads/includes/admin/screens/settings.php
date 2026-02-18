<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_ma_render_settings_screen')) {
	function vms_ma_render_settings_screen(): void
	{
		$settings = VMS_Meta_Ads_Utils::get_settings();
		$daily_dollars = number_format(((int) $settings['budget_max_daily_minor']) / 100, 2, '.', '');
		$lifetime_dollars = number_format(((int) $settings['budget_max_lifetime_minor']) / 100, 2, '.', '');
		$message = '';
		$message_type = 'updated';
		$message_details = array();
		$connection_verified = false;
		$social_connection = apply_filters('vms_social_meta_connection', null);
		$using_social = is_array($social_connection) && !empty($social_connection['ad_account_id']) && empty($settings['meta_ad_account_override']);
		$social_page_id = is_array($social_connection) ? trim((string) ($social_connection['page_id'] ?? '')) : '';
		$social_ig_actor_id = is_array($social_connection) ? trim((string) ($social_connection['ig_actor_id'] ?? '')) : '';
		$has_social_page = ($social_page_id !== '');

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_ma_settings_nonce'])) {
			if (!wp_verify_nonce((string) $_POST['vms_ma_settings_nonce'], 'vms_ma_settings_save')) {
				wp_die(__('Invalid nonce.', 'vms-meta-ads'));
			}

			if (isset($_POST['vms_ma_test_connection'])) {
				$result = VMS_Meta_Ads_Meta_Client::test_connection();
				if (is_wp_error($result)) {
					$message = $result->get_error_message();
					$message_type = 'error';
					$error_data = $result->get_error_data();
					$message_details = is_array($error_data) && isset($error_data['details']) && is_array($error_data['details'])
						? $error_data['details']
						: array();
				} else {
					$message = __('Meta connection test passed.', 'vms-meta-ads');
					$connection_verified = true;
				}
			} else {
				$daily_in = isset($_POST['budget_max_daily_minor']) ? str_replace(',', '', (string) $_POST['budget_max_daily_minor']) : (string) $daily_dollars;
				$lifetime_in = isset($_POST['budget_max_lifetime_minor']) ? str_replace(',', '', (string) $_POST['budget_max_lifetime_minor']) : (string) $lifetime_dollars;
					$updates = array(
						'budget_max_daily_minor' => max(0, (int) round(((float) $daily_in) * 100)),
					'budget_max_lifetime_minor' => max(0, (int) round(((float) $lifetime_in) * 100)),
						'default_radius_miles' => absint($_POST['default_radius_miles'] ?? $settings['default_radius_miles']),
						'max_radius_miles' => max(1, absint($_POST['max_radius_miles'] ?? ($settings['max_radius_miles'] ?? 100))),
						'default_age_min' => absint($_POST['default_age_min'] ?? $settings['default_age_min']),
					'default_age_max' => absint($_POST['default_age_max'] ?? $settings['default_age_max']),
					'vms_ma_phase' => max(1, absint($_POST['vms_ma_phase'] ?? ($settings['vms_ma_phase'] ?? 1))),
					'vms_ma_default_preset' => sanitize_key((string) ($_POST['vms_ma_default_preset'] ?? ($settings['vms_ma_default_preset'] ?? 'flat_run'))),
					'enable_api_create' => isset($_POST['enable_api_create']) ? 1 : 0,
					'meta_graph_version' => sanitize_text_field((string) ($_POST['meta_graph_version'] ?? $settings['meta_graph_version'])),
					'meta_ad_account_id' => ltrim(sanitize_text_field((string) ($_POST['meta_ad_account_id'] ?? $settings['meta_ad_account_id'])), 'act_'),
					'meta_page_id' => sanitize_text_field((string) ($_POST['meta_page_id'] ?? $settings['meta_page_id'])),
					'facebook_page_url' => esc_url_raw((string) ($_POST['facebook_page_url'] ?? $settings['facebook_page_url'])),
					'meta_ig_actor_id' => sanitize_text_field((string) ($_POST['meta_ig_actor_id'] ?? $settings['meta_ig_actor_id'])),
					'meta_access_token_encrypted' => $settings['meta_access_token_encrypted'],
					'tour_autostart' => isset($_POST['tour_autostart']) ? 1 : 0,
					'meta_ad_account_override' => isset($_POST['meta_ad_account_override']) ? 1 : 0,
				);
				if (isset($_POST['meta_access_token_clear'])) {
					$updates['meta_access_token_encrypted'] = '';
				}
				if (!empty($_POST['meta_access_token'])) {
					$updates['meta_access_token_encrypted'] = VMS_Meta_Ads_Utils::encrypt_token(sanitize_text_field((string) $_POST['meta_access_token']));
				}
				if (!in_array($updates['vms_ma_default_preset'], array('flat_run', 'promo_bundle_30_14_7', 'simple_7_day', 'simple_14_day', 'simple_30_day', 'manual_dates'), true)) {
					$updates['vms_ma_default_preset'] = 'flat_run';
				}
				VMS_Meta_Ads_Utils::update_settings($updates);
				$settings = VMS_Meta_Ads_Utils::get_settings();
				$daily_dollars = number_format(((int) $settings['budget_max_daily_minor']) / 100, 2, '.', '');
				$lifetime_dollars = number_format(((int) $settings['budget_max_lifetime_minor']) / 100, 2, '.', '');
				$message = __('Settings saved.', 'vms-meta-ads');
			}
		}

		if (isset($_POST['vms_ma_reset_tours']) && check_admin_referer('vms_ma_reset_tours')) {
			VMS_Meta_Ads_Tours::reset_user_tours(get_current_user_id());
			update_user_meta(get_current_user_id(), 'vms_ma_help_mode', 0);
			$message = __('Tour and help mode reset for the current user.', 'vms-meta-ads');
		}
		if (isset($_POST['vms_ma_reset_all_tours']) && check_admin_referer('vms_ma_reset_tours')) {
			VMS_Meta_Ads_Tours::reset_all_tours();
			$message = __('Tour state reset for everyone.', 'vms-meta-ads');
		}
		$state = VMS_Meta_Ads_Utils::get_user_guidance_state(get_current_user_id());
		$help_mode = (int) $state['help_mode'];
		$tour_autorun = (int) $state['tour_autorun'];
		$guidance_level = (string) $state['guidance_level'];
		$stored_token = VMS_Meta_Ads_Utils::decrypt_token((string) ($settings['meta_access_token_encrypted'] ?? ''));
		$token_present = $stored_token !== '';
		$effective_page_id = $has_social_page && empty($settings['meta_ad_account_override']) ? $social_page_id : trim((string) ($settings['meta_page_id'] ?? ''));
		$effective_ig_actor_id = $social_ig_actor_id !== '' && empty($settings['meta_ad_account_override']) ? $social_ig_actor_id : trim((string) ($settings['meta_ig_actor_id'] ?? ''));
		$api_ready = ($token_present && $effective_page_id !== '' && trim((string) ($settings['meta_ad_account_id'] ?? '')) !== '');
		$token_suffix = $token_present ? substr($stored_token, -6) : '';
		$facebook_page_url = esc_url((string) ($settings['facebook_page_url'] ?? ''));
		$page_transparency_url = '';
		if ($facebook_page_url !== '') {
			$page_transparency_url = trailingslashit(untrailingslashit($facebook_page_url)) . 'about_profile_transparency';
		}
		$builder_url = add_query_arg(array('page' => 'vms-ma-ads-builder'), admin_url('admin.php'));
		$promote_builder_url = add_query_arg(array('page' => 'vms-ma-ads-builder', 'prefill' => 1, 'scroll_to' => 'stepA', 'tour' => 0), admin_url('admin.php'));
		$copy_pack_builder_url = add_query_arg(array('page' => 'vms-ma-ads-builder', 'copy_pack' => 1), admin_url('admin.php'));
		$promote_list_url = add_query_arg(array('page' => 'vms-ma-ads-promote'), admin_url('admin.php'));
		?>
		<div class="wrap vms-ma" id="vms-ma-settings-wrap" data-help-mode="<?php echo esc_attr($help_mode); ?>" data-tour-autorun="<?php echo esc_attr($tour_autorun); ?>" data-guidance-level="<?php echo esc_attr($guidance_level); ?>">
			<div class="vms-ma-page-header">
				<div class="vms-ma-page-title">
					<h1><?php esc_html_e('Meta Ads Settings', 'vms-meta-ads'); ?></h1>
					<p class="description vms-ma-intro"><?php esc_html_e('Control budget guardrails, API credentials, and guided tour behavior.', 'vms-meta-ads'); ?></p>
				</div>
				<div class="vms-ma-topbar">
					<div class="vms-ma-topbar-main">
						<button type="button" class="button" id="vms-ma-settings-start-tour"><?php esc_html_e('Start tour', 'vms-meta-ads'); ?></button>
					</div>
					<div class="vms-ma-topbar-meta">
						<label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
							<span><?php esc_html_e('Guidance', 'vms-meta-ads'); ?></span>
							<select id="vms-ma-settings-guidance-level">
								<option value="beginner" <?php selected($guidance_level, 'beginner'); ?>><?php esc_html_e('Beginner', 'vms-meta-ads'); ?></option>
								<option value="standard" <?php selected($guidance_level, 'standard'); ?>><?php esc_html_e('Standard', 'vms-meta-ads'); ?></option>
								<option value="expert" <?php selected($guidance_level, 'expert'); ?>><?php esc_html_e('Expert', 'vms-meta-ads'); ?></option>
							</select>
						</label>
						<label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
							<input type="checkbox" id="vms-ma-settings-tour-autorun-toggle" <?php checked(1, $tour_autorun); ?> />
							<span><?php esc_html_e('Guided tour (auto)', 'vms-meta-ads'); ?></span>
						</label>
						<label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
							<input type="checkbox" id="vms-ma-settings-help-mode-toggle" <?php checked(1, $help_mode); ?> />
							<span><?php esc_html_e('Help Mode', 'vms-meta-ads'); ?></span>
						</label>
					</div>
				</div>
			</div>
			<?php if ($message) : ?>
				<div class="notice notice-<?php echo esc_attr($message_type); ?> vms-ma-notice">
					<p><?php echo esc_html($message); ?></p>
					<?php if (!empty($message_details) && $message_type === 'error') : ?>
						<details class="vms-ma-error-details">
							<summary><?php esc_html_e('Details', 'vms-meta-ads'); ?></summary>
							<ul>
								<li><strong><?php esc_html_e('Type:', 'vms-meta-ads'); ?></strong> <?php echo esc_html((string) ($message_details['type'] ?? '')); ?></li>
								<li><strong><?php esc_html_e('Code:', 'vms-meta-ads'); ?></strong> <?php echo esc_html((string) ($message_details['code'] ?? '')); ?></li>
								<li><strong><?php esc_html_e('Message:', 'vms-meta-ads'); ?></strong> <?php echo esc_html((string) ($message_details['message'] ?? '')); ?></li>
								<li><strong><?php esc_html_e('fbtrace_id:', 'vms-meta-ads'); ?></strong> <?php echo esc_html((string) ($message_details['fbtrace_id'] ?? '')); ?></li>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($using_social) : ?><div class="notice notice-info vms-ma-notice"><p><?php esc_html_e('Using Social Sharer Connection via filter. Enable local override below to provide separate credentials.', 'vms-meta-ads'); ?></p></div><?php endif; ?>

			<form method="post">
				<?php wp_nonce_field('vms_ma_settings_save', 'vms_ma_settings_nonce'); ?>
				<table class="form-table vms-ma-settings-table">
					<tr id="vms-ma-settings-budget-clamps" data-vms-tour="vms-ma-settings-budget-clamps"><th><?php esc_html_e('Budget max (daily, dollars)', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for daily budget max', 'vms-meta-ads'); ?>" data-help-id="ma_help_budget_max_daily">i</button></th><td><input type="number" step="0.01" min="0" name="budget_max_daily_minor" value="<?php echo esc_attr($daily_dollars); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Budget max (lifetime, dollars)', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for lifetime budget max', 'vms-meta-ads'); ?>" data-help-id="ma_help_budget_max_lifetime">i</button></th><td><input type="number" step="0.01" min="0" name="budget_max_lifetime_minor" value="<?php echo esc_attr($lifetime_dollars); ?>" /></td></tr>
					<tr id="vms-ma-settings-default-targeting" data-vms-tour="vms-ma-settings-default-targeting"><th><?php esc_html_e('Default radius (miles)', 'vms-meta-ads'); ?></th><td><input type="number" name="default_radius_miles" value="<?php echo esc_attr($settings['default_radius_miles']); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Max radius (miles)', 'vms-meta-ads'); ?></th><td><input type="number" min="1" name="max_radius_miles" value="<?php echo esc_attr((int) ($settings['max_radius_miles'] ?? 100)); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Default age min', 'vms-meta-ads'); ?></th><td><input type="number" name="default_age_min" value="<?php echo esc_attr($settings['default_age_min']); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Default age max', 'vms-meta-ads'); ?></th><td><input type="number" name="default_age_max" value="<?php echo esc_attr($settings['default_age_max']); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Phase', 'vms-meta-ads'); ?></th><td><input type="number" min="1" step="1" name="vms_ma_phase" value="<?php echo esc_attr((int) ($settings['vms_ma_phase'] ?? 1)); ?>" /> <span><?php esc_html_e('1 = Copy Pack only, 2 = Create in Meta (paused), 3 = Go Live.', 'vms-meta-ads'); ?></span></td></tr>
					<tr><th><?php esc_html_e('Default preset', 'vms-meta-ads'); ?></th><td><select name="vms_ma_default_preset"><option value="flat_run" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'flat_run'); ?>><?php esc_html_e('Flat run', 'vms-meta-ads'); ?></option><option value="promo_bundle_30_14_7" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'promo_bundle_30_14_7'); ?>><?php esc_html_e('Promo Bundle 30/14/7', 'vms-meta-ads'); ?></option><option value="simple_7_day" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'simple_7_day'); ?>><?php esc_html_e('Simple 7 day', 'vms-meta-ads'); ?></option><option value="simple_14_day" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'simple_14_day'); ?>><?php esc_html_e('Simple 14 day', 'vms-meta-ads'); ?></option><option value="simple_30_day" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'simple_30_day'); ?>><?php esc_html_e('Simple 30 day', 'vms-meta-ads'); ?></option><option value="manual_dates" <?php selected((string) ($settings['vms_ma_default_preset'] ?? 'flat_run'), 'manual_dates'); ?>><?php esc_html_e('Manual dates (Expert)', 'vms-meta-ads'); ?></option></select></td></tr>
					<tr id="vms-ma-settings-api-create" data-vms-tour="vms-ma-settings-api-create"><th><?php esc_html_e('Enable API create', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for enabling API create', 'vms-meta-ads'); ?>" data-help-id="ma_help_enable_api">i</button></th><td><label><input type="checkbox" name="enable_api_create" value="1" <?php checked(1, $settings['enable_api_create']); ?> /> <?php esc_html_e('Yes (feature flagged; keep OFF until credentials are verified)', 'vms-meta-ads'); ?></label></td></tr>
					<tr><th><?php esc_html_e('Meta graph version', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for graph version', 'vms-meta-ads'); ?>" data-help-id="ma_help_graph_version">i</button></th><td><input type="text" name="meta_graph_version" value="<?php echo esc_attr($settings['meta_graph_version']); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Use local override', 'vms-meta-ads'); ?></th><td><label><input type="checkbox" name="meta_ad_account_override" value="1" <?php checked(1, (int) ($settings['meta_ad_account_override'] ?? 0)); ?> /> <?php esc_html_e('Ignore Social Sharer connection and use values below', 'vms-meta-ads'); ?></label></td></tr>
					<tr id="vms-ma-setting-ad-account-id" data-vms-tour="vms-ma-setting-ad-account-id"><th><?php esc_html_e('Ad account ID', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for ad account ID', 'vms-meta-ads'); ?>" data-help-id="ma_help_ad_account_id">i</button></th><td><input type="text" name="meta_ad_account_id" value="<?php echo esc_attr($settings['meta_ad_account_id']); ?>" placeholder="act_123" /></td></tr>
						<tr id="vms-ma-setting-page-id" data-vms-tour="vms-ma-setting-page-id"><th><?php esc_html_e('Page ID', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for page ID', 'vms-meta-ads'); ?>" data-help-id="ma_help_page_id">i</button></th><td><?php if ($has_social_page && empty($settings['meta_ad_account_override'])) : ?><input type="text" value="<?php echo esc_attr($social_page_id); ?>" readonly /> <strong><?php esc_html_e('From Social Sharing', 'vms-meta-ads'); ?></strong> | <a href="<?php echo esc_url(admin_url('admin.php?page=vms-social-sharing')); ?>"><?php esc_html_e('Change in Social Sharing settings', 'vms-meta-ads'); ?></a><?php else : ?><input type="text" name="meta_page_id" value="<?php echo esc_attr($settings['meta_page_id']); ?>" /> <button type="button" class="button" id="vms-ma-pages-lookup"><?php esc_html_e('Lookup my Pages', 'vms-meta-ads'); ?></button> <select id="vms-ma-pages-select" class="vms-ma-hidden"></select> <?php if (!$using_social) : ?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-social-sharing')); ?>"><?php esc_html_e('Connect Social Sharing', 'vms-meta-ads'); ?></a><?php endif; ?><?php endif; ?></td></tr>
						<tr><th><?php esc_html_e('Facebook Page URL', 'vms-meta-ads'); ?></th><td><input type="url" name="facebook_page_url" value="<?php echo esc_attr((string) ($settings['facebook_page_url'] ?? '')); ?>" placeholder="https://facebook.com/your-page" /><?php if ($facebook_page_url !== '') : ?> <a href="<?php echo esc_url($facebook_page_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Facebook Page', 'vms-meta-ads'); ?></a><?php if ($page_transparency_url !== '') : ?> | <a href="<?php echo esc_url($page_transparency_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Page Transparency', 'vms-meta-ads'); ?></a><?php endif; ?><?php else : ?> <span><?php esc_html_e('Add your Facebook Page URL to enable one-click links.', 'vms-meta-ads'); ?></span><?php endif; ?></td></tr>
						<tr><th><?php esc_html_e('IG Actor ID', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for IG actor ID', 'vms-meta-ads'); ?>" data-help-id="ma_help_ig_actor_id">i</button></th><td><?php if ($effective_ig_actor_id !== '' && empty($settings['meta_ad_account_override'])) : ?><input type="text" value="<?php echo esc_attr($effective_ig_actor_id); ?>" readonly /> <strong><?php esc_html_e('From Social Sharing', 'vms-meta-ads'); ?></strong><?php else : ?><input type="text" name="meta_ig_actor_id" value="<?php echo esc_attr($settings['meta_ig_actor_id']); ?>" /><?php endif; ?></td></tr>
						<tr id="vms-ma-setting-token" data-vms-tour="vms-ma-setting-token"><th><?php esc_html_e('Access token', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for access token', 'vms-meta-ads'); ?>" data-help-id="ma_help_access_token">i</button></th><td><div class="vms-ma-token-controls"><input type="password" id="vms-ma-token-input" name="meta_access_token" value="" placeholder="••••" autocomplete="new-password" /> <button type="button" class="button" id="vms-ma-token-reveal" data-token-present="<?php echo esc_attr($token_present ? '1' : '0'); ?>"><?php esc_html_e('Reveal', 'vms-meta-ads'); ?></button> <button type="button" class="button" id="vms-ma-token-copy"><?php esc_html_e('Copy', 'vms-meta-ads'); ?></button> <label><input type="checkbox" name="meta_access_token_clear" value="1" /> <?php esc_html_e('Clear token on save', 'vms-meta-ads'); ?></label></div><?php if ($token_present) : ?><span><?php echo esc_html(sprintf(__('Token saved (ending %s)', 'vms-meta-ads'), $token_suffix)); ?></span><?php endif; ?> <span><?php esc_html_e('Leave blank to keep current token masked and unchanged.', 'vms-meta-ads'); ?></span></td></tr>
					<tr><th><?php esc_html_e('Tour auto-start', 'vms-meta-ads'); ?></th><td><label><input type="checkbox" name="tour_autostart" value="1" <?php checked(1, $settings['tour_autostart']); ?> /> <?php esc_html_e('Start guided tour on first builder load', 'vms-meta-ads'); ?></label></td></tr>
				</table>
				<div id="vms-ma-settings-actions" class="vms-ma-settings-primary-actions" data-vms-tour="vms-ma-settings-connection-test">
					<?php submit_button(__('Save Settings', 'vms-meta-ads')); ?>
					<?php submit_button(__('Test Connection', 'vms-meta-ads'), 'secondary', 'vms_ma_test_connection', false); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for Test Connection', 'vms-meta-ads'); ?>" data-help-id="ma_help_test_connection">i</button>
				</div>
			</form>

			<form method="post" class="vms-ma-settings-actions">
				<?php wp_nonce_field('vms_ma_reset_tours'); ?>
				<input type="submit" name="vms_ma_reset_tours" class="button" value="<?php esc_attr_e('Reset tour for me', 'vms-meta-ads'); ?>" />
				<input type="submit" name="vms_ma_reset_all_tours" class="button" value="<?php esc_attr_e('Reset tour for everyone', 'vms-meta-ads'); ?>" />
			</form>

			<?php if ($connection_verified) : ?>
				<div class="notice notice-success vms-ma-notice">
					<p><strong><?php esc_html_e('Setup complete. Next: Build your first ad.', 'vms-meta-ads'); ?></strong></p>
					<p><a class="button button-primary" href="<?php echo esc_url($promote_builder_url); ?>"><?php esc_html_e('Go to Meta Ads Builder', 'vms-meta-ads'); ?></a></p>
				</div>
			<?php endif; ?>

			<section class="vms-ma-next-steps">
				<h2><?php esc_html_e('Next Steps', 'vms-meta-ads'); ?></h2>
				<?php if ($api_ready) : ?>
					<p class="vms-ma-next-steps-status is-ready"><?php esc_html_e('Ready to build your first ad.', 'vms-meta-ads'); ?></p>
					<p class="vms-ma-next-steps-actions">
						<a class="button button-primary" href="<?php echo esc_url($promote_builder_url); ?>"><?php esc_html_e('Promote an Event', 'vms-meta-ads'); ?></a>
						<a class="button" href="<?php echo esc_url($promote_list_url); ?>"><?php esc_html_e('View Promotable Events', 'vms-meta-ads'); ?></a>
					</p>
				<?php else : ?>
					<p class="vms-ma-next-steps-status is-warning"><?php esc_html_e('Finish setup to enable Create in Meta.', 'vms-meta-ads'); ?></p>
					<p class="vms-ma-next-steps-actions">
						<a class="button button-primary" href="<?php echo esc_url($copy_pack_builder_url); ?>"><?php esc_html_e('Open Builder (Copy Pack mode)', 'vms-meta-ads'); ?></a>
						<a class="button button-secondary" href="#vms-ma-settings-actions"><?php esc_html_e('Test Connection', 'vms-meta-ads'); ?></a>
						<a class="button" href="<?php echo esc_url($builder_url); ?>"><?php esc_html_e('Open Builder', 'vms-meta-ads'); ?></a>
					</p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}
