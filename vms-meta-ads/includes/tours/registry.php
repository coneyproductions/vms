<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Tours')) {
	class VMS_Meta_Ads_Tours {
		public const BUILDER_TOUR_ID = 'vms_ma_ads_builder_v1';
		public const SETTINGS_TOUR_ID = 'vms_ma_ads_settings_v1';
		public const TOUR_VERSION = 1;

		public static function init(): void
		{
			add_filter('vms_register_tours', array(__CLASS__, 'register_tour'));
		}

		public static function register_tour(array $tours): array
		{
			$catalog = self::get_step_catalog();
			$tours[] = array(
				'id' => self::BUILDER_TOUR_ID,
				'title' => 'Meta Ads Builder Tour',
				'version' => self::TOUR_VERSION,
				'contexts' => array(
					array(
						'context_key' => 'vms-ma-builder',
						'screen_id' => 'vms-dashboard_page_vms-ma-ads-builder',
						'page_hook' => 'vms-dashboard_page_vms-ma-ads-builder',
						'url' => 'admin.php?page=vms-ma-ads-builder',
					),
				),
				'steps' => self::steps_to_anchor_registry((array) ($catalog['builder']['steps'] ?? array())),
			);
			$tours[] = array(
				'id' => self::SETTINGS_TOUR_ID,
				'title' => 'Meta Ads Settings Tour',
				'version' => self::TOUR_VERSION,
				'contexts' => array(
					array(
						'context_key' => 'vms-ma-settings',
						'screen_id' => 'vms-dashboard_page_vms-ma-ads-settings',
						'page_hook' => 'vms-dashboard_page_vms-ma-ads-settings',
						'url' => 'admin.php?page=vms-ma-ads-settings',
					),
				),
				'steps' => self::steps_to_anchor_registry((array) ($catalog['settings']['steps'] ?? array())),
			);
			return $tours;
		}

		public static function get_tours_payload(): array
		{
			return self::get_step_catalog();
		}

		public static function get_steps_payload(): array
		{
			$catalog = self::get_step_catalog();
			$builder_steps = (array) ($catalog['builder']['steps'] ?? array());
			$payload = array();
				foreach ($builder_steps as $step) {
					$payload[] = array(
						'selector' => (string) ($step['selector'] ?? ''),
						'step_key' => (string) ($step['step_key'] ?? ''),
						'title' => (string) ($step['title'] ?? ''),
						'content' => (string) ($step['html_standard'] ?? ''),
					);
				}
			return $payload;
		}

		public static function reset_user_tours(int $user_id): void
		{
			delete_user_meta($user_id, 'vms_ma_tour_dismissed_' . self::BUILDER_TOUR_ID);
			delete_user_meta($user_id, 'vms_ma_tour_dismissed_' . self::SETTINGS_TOUR_ID);
			delete_user_meta($user_id, 'vms_tours_state');
		}

		public static function reset_all_tours(): void
		{
			$users = get_users(array('fields' => array('ID')));
			foreach ($users as $user) {
				self::reset_user_tours((int) $user->ID);
			}
		}

		private static function steps_to_anchor_registry(array $steps): array
		{
			$out = array();
			foreach ($steps as $step) {
				if (!is_array($step)) {
					continue;
				}
				$anchor = sanitize_key((string) ($step['anchor'] ?? ''));
				if ($anchor === '') {
					continue;
				}
				$out[] = array(
					'anchor' => $anchor,
					'title' => (string) ($step['title'] ?? $anchor),
					'content' => (string) ($step['html_standard'] ?? ''),
					'placement' => sanitize_key((string) ($step['prefer_edge'] ?? 'right')),
				);
			}
			return $out;
		}

		private static function get_step_catalog(): array
		{
			$settings = VMS_Meta_Ads_Utils::get_settings();
			$page_url = esc_url((string) ($settings['facebook_page_url'] ?? ''));
			$page_links_html = '';
			if ($page_url !== '') {
				$transparency = trailingslashit(untrailingslashit($page_url)) . 'about_profile_transparency';
				$page_links_html = '<p><a href="' . esc_url($page_url) . '" target="_blank" rel="noopener noreferrer">Open Facebook Page</a> | <a href="' . esc_url($transparency) . '" target="_blank" rel="noopener noreferrer">Open Page Transparency</a></p>';
			} else {
				$page_links_html = '<p>Add your Facebook Page URL in Social Settings to enable one-click links.</p>';
			}
			return array(
				'builder' => array(
					'tourId' => self::BUILDER_TOUR_ID,
					'steps' => array(
							array(
								'anchor' => 'vms-ma-event-picker',
								'selector' => '#vms-ma-event-picker',
								'step_key' => 'A',
								'title' => __('Pick an event (this fills everything else)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What you are doing:</b> Choosing which show you are advertising.</p><p><b>Do this now:</b></p><ol><li>Use <b>Select Event Plan</b> and search by event title/date.</li><li>Pick the event you want to promote.</li><li>Confirm the autofilled fields: Event name, Venue name, Event start, and Destination URL.</li><li>Adjust Destination URL if you want a different landing page.</li></ol><p><b>What to expect next:</b> The ad name, UTMs, and schedule will auto-build from this event context.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>What this does:</b> Picker-driven autofill sets event context for names, timing, and UTMs.</p><p><b>Default:</b> Choose your next upcoming show.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'right',
						),
							array(
								'anchor' => 'vms-ma-preset-mode',
							'selector' => '#vms-ma-preset-mode',
							'step_key' => 'B',
							'title' => __('Choose a strategy (recommended is fine)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What you are doing:</b> Picking a safe schedule shape.</p><p><b>Recommended:</b> <b>Flat run</b> (start now, end shortly before event).</p><p><b>Do this now:</b> Keep Flat run unless you specifically want tiered 30/14/7 or manual expert dates.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>What this does:</b> Picks a proven layout so you aren&#39;t buried in Meta settings.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'right',
						),
							array(
								'anchor' => 'vms-ma-creative-dark',
								'selector' => '#vms-ma-creative-dark',
								'step_key' => 'C',
								'title' => __('Creative mode (choose how the ad is made)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>Most people should choose:</b> <b>Dark Post Link Ad</b></p><p><b>Why:</b> It is the most reliable and clicks straight to tickets.</p><p><b>Do this now:</b> Leave <b>Dark Post Link Ad</b> selected.</p><p><b>Only choose "Boost Existing Post" if:</b> you already have a post with good likes/comments and you want that social proof to carry into the ad.</p><p><b>Promote Facebook Event:</b> advanced. Use only if your Facebook Event is public and you want Meta to optimize for event responses.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Default:</b> Dark Post Link Ad for most event promotions.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'right',
						),
							array(
								'anchor' => 'vms-ma-radius',
								'selector' => '#vms-ma-radius-wrap',
								'step_key' => 'D',
								'title' => __('Audience (who sees the ad)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>Radius:</b> This limits the ad to people near the venue.</p><p><b>Do this now:</b> Leave <b>15 miles</b> unless you are in a rural area (try 20-30).</p><p><b>Age:</b> Use your venue policy. If your shows are 21+, keep 21+.</p><p><b>Interests:</b> Leave blank unless you know exactly what you&#39;re doing. Too narrow can hurt results.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Default:</b> Broad local radius with minimal targeting.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'right',
						),
							array(
								'anchor' => 'vms-ma-budget-total',
								'selector' => '#vms-ma-budget-total',
								'step_key' => 'E',
								'title' => __('Budget and schedule (dollars, not surprises)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What you are doing:</b> Setting the maximum spend for this promo.</p><p><b>Do this now:</b> Enter a total budget you are comfortable with (in dollars).</p><ul><li>If this is your first ad, start small so you can learn.</li><li>VMS will split the budget across tiers automatically.</li><li>VMS will block budgets above your safety clamp.</li></ul><p><b>What to expect next:</b> You&#39;ll review the Copy Pack and then choose Export or Create in Meta (Paused).</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Guardrail:</b> VMS validates totals against clamps before export.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'left',
						),
							array(
								'anchor' => 'vms-ma-output-pack',
								'selector' => '#vms-ma-output-pack',
								'step_key' => 'F',
								'title' => __('Copy Pack output (your paste-ready plan)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What this is:</b> A paste-ready package: names, copy, UTMs, and your settings summary.</p><p><b>Do this now:</b> Click <b>Export Copy Pack</b> and skim it once.</p><p><b>If you are not ready for API:</b> this is all you need. Paste into Meta manually.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Best for:</b> Phase 1 manual workflows without API.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'top',
						),
					),
				),
				'settings' => array(
					'tourId' => self::SETTINGS_TOUR_ID,
					'steps' => array(
						array(
							'anchor' => 'vms-ma-setting-ad-account-id',
							'selector' => '#vms-ma-setting-ad-account-id',
							'title' => __('Ad Account ID (this is what gets charged)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What you are doing:</b> Choosing the ad account Meta will charge.</p><p><b>Do this now:</b></p><ol><li>Open Meta Business Settings: <b>https://business.facebook.com/settings</b></li><li>Go to <b>Accounts -> Ad accounts</b></li><li>Click your ad account and copy the <b>Ad Account ID</b></li><li>Paste it here. It should look like numbers (sometimes shown as act_123...)</li></ol>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Required:</b> set the billing ad account before any API create action.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'left',
						),
						array(
							'anchor' => 'vms-ma-setting-page-id',
							'selector' => '#vms-ma-setting-page-id',
							'title' => __('Page ID (the page ads run from)', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>What you are doing:</b> Setting the Facebook Page that owns the ads.</p><p><b>Do this now:</b></p><ol><li>Open your Facebook Page in browser.</li><li>Go to <b>About</b> (or Page settings where IDs are shown).</li><li>Copy the numeric <b>Page ID</b>.</li><li>Paste it here.</li></ol><p><b>Tip:</b> Use the same page your audience recognizes from your venue/artist posts.</p>', 'vms-meta-ads'),
							'html_standard' => __('<p><b>Use the exact numeric Page ID</b> for consistent attribution and permissions.</p>', 'vms-meta-ads'),
							'prefer_edge' => 'left',
						),
						array(
							'anchor' => 'vms-ma-setting-token',
							'selector' => '#vms-ma-setting-token',
							'title' => __('Access Token (permission to create ads)', 'vms-meta-ads'),
							'html_beginner' => vms_ma_copy_access_token_beginner_html(),
							'html_standard' => vms_ma_copy_access_token_beginner_html(),
							'html_expert' => vms_ma_copy_access_token_expert_html(),
							'prefer_edge' => 'left',
						),
						array(
							'anchor' => 'vms-ma-settings-connection-test',
							'selector' => '#vms-ma-settings-actions',
							'title' => __('Save + Test Connection', 'vms-meta-ads'),
							'html_beginner' => __('<p><b>Do this now:</b></p><ol><li>Click <b>Save Settings</b>.</li><li>Then click <b>Test Connection</b>.</li><li>If test passes, go back to Builder and continue.</li></ol><p><b>If test fails:</b> re-check Ad Account ID, Page ID, and token permissions.</p>', 'vms-meta-ads') . $page_links_html,
							'html_standard' => __('<p><b>Required before launch:</b> save settings and verify connection is green.</p>', 'vms-meta-ads'),
							'html_expert' => __('<p>Save settings, run Test Connection, verify green result, then return to Builder.</p>', 'vms-meta-ads') . $page_links_html,
							'prefer_edge' => 'top',
						),
					),
				),
			);
		}
	}
}
