<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_ma_render_ads_builder_screen')) {
	function vms_ma_render_ads_builder_screen(): void
	{
		$settings = VMS_Meta_Ads_Utils::get_settings();
		$state = VMS_Meta_Ads_Utils::get_user_guidance_state(get_current_user_id());
		$help_mode = (int) $state['help_mode'];
		$tour_autorun = (int) $state['tour_autorun'];
		$guidance_level = (string) $state['guidance_level'];
		$prefill_event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
		$prefill_hint = isset($_GET['prefill']) ? 1 : 0;
		$copy_pack_hint = isset($_GET['copy_pack']) ? 1 : 0;
		$tour_query = isset($_GET['tour']) ? absint($_GET['tour']) : 0;
		$scroll_to = isset($_GET['scroll_to']) ? sanitize_key((string) $_GET['scroll_to']) : '';
		$event_plan_seed = VMS_Meta_Ads_Event_Plans_Service::list_event_plans(array(
			'after' => wp_date('Y-m-d', null, wp_timezone()),
			'days' => 180,
			'limit' => 120,
			'statuses' => array('published', 'ready', 'draft'),
		));
		$age_max_default = (int) ($settings['default_age_max'] ?? 65);
		if ($age_max_default < 25) {
			$age_max_default = 25;
		}
		if ($age_max_default > 65) {
			$age_max_default = 65;
		}
		$age_options = array(25, 30, 35, 40, 45, 50, 55, 60, 65);
		?>
		<div class="wrap vms-ma" id="vms-ma-ads-builder-wrap" data-help-mode="<?php echo esc_attr($help_mode); ?>" data-tour-autorun="<?php echo esc_attr($tour_autorun); ?>" data-guidance-level="<?php echo esc_attr($guidance_level); ?>" data-prefill-event-plan-id="<?php echo esc_attr($prefill_event_plan_id); ?>" data-prefill="<?php echo esc_attr($prefill_hint); ?>" data-copy-pack-hint="<?php echo esc_attr($copy_pack_hint); ?>" data-tour-query="<?php echo esc_attr($tour_query); ?>" data-scroll-to="<?php echo esc_attr($scroll_to); ?>">
		  <div class="vms-ma-page-header">
		    <div class="vms-ma-page-title">
		      <h1><?php esc_html_e('Meta Ads Builder', 'vms-meta-ads'); ?></h1>
		      <p class="description vms-ma-intro"><?php esc_html_e('Guided wizard for simple and auto-ramp promo bundles.', 'vms-meta-ads'); ?></p>
		    </div>
		    <div class="vms-ma-topbar">
		      <div class="vms-ma-topbar-main">
		        <button type="button" class="button" id="vms-ma-start-tour"><?php esc_html_e('Start tour', 'vms-meta-ads'); ?></button>
		      </div>
		      <div class="vms-ma-topbar-meta">
		        <label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
		          <span><?php esc_html_e('Guidance', 'vms-meta-ads'); ?></span>
		          <select id="vms-ma-guidance-level">
		            <option value="beginner" <?php selected($guidance_level, 'beginner'); ?>><?php esc_html_e('Beginner', 'vms-meta-ads'); ?></option>
		            <option value="standard" <?php selected($guidance_level, 'standard'); ?>><?php esc_html_e('Standard', 'vms-meta-ads'); ?></option>
		            <option value="expert" <?php selected($guidance_level, 'expert'); ?>><?php esc_html_e('Expert', 'vms-meta-ads'); ?></option>
		          </select>
		        </label>
		        <label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
		          <input type="checkbox" id="vms-ma-tour-autorun-toggle" <?php checked(1, $tour_autorun); ?> />
		          <span><?php esc_html_e('Guided tour (auto)', 'vms-meta-ads'); ?></span>
		        </label>
		        <label class="vms-ma-choice vms-ma-inline-choice vms-ma-control">
		          <input type="checkbox" id="vms-ma-help-mode-toggle" <?php checked(1, $help_mode); ?> />
		          <span><?php esc_html_e('Help Mode', 'vms-meta-ads'); ?></span>
		        </label>
		      </div>
		    </div>
		  </div>

		  <div id="vms-ma-builder-notices" class="notice notice-alt is-dismissible vms-ma-notice vms-ma-hidden"></div>
		  <div id="vms-ma-toast-root" class="vms-ma-toast-root" aria-live="polite"></div>

		  <section id="vms-ma-step-a" class="vms-ma-section vms-ma-step vms-ma-card" data-step="source" data-step-key="A" data-step-index="1">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="true">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step A - Source', 'vms-meta-ads'); ?></h2>
		      <div class="vms-ma-step-head-actions">
		        <button type="button" class="button-link vms-ma-advanced-toggle"><?php esc_html_e('Manual entry', 'vms-meta-ads'); ?></button>
		        <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Not started', 'vms-meta-ads'); ?></span>
		      </div>
		    </div>
		    <div class="vms-ma-step-body">
		      <p class="description vms-ma-section-intro"><?php esc_html_e('Choose your Event Plan context and destination URL.', 'vms-meta-ads'); ?></p>
		      <div id="vms-ma-event-picker" class="vms-ma-grid-two" data-vms-tour="vms-ma-event-picker">
		        <label for="vms-ma-event-plan-picker"><span><?php esc_html_e('Select Event Plan', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for Event Plan ID', 'vms-meta-ads'); ?>" data-help-id="ma_help_event_plan_id">i</button></span><select id="vms-ma-event-plan-picker"><option value=""><?php esc_html_e('Search upcoming Event Plans...', 'vms-meta-ads'); ?></option><?php foreach ($event_plan_seed as $seed_item) : ?><?php $seed_text = trim((string) ($seed_item['title'] ?? '')); $seed_meta = trim((string) ($seed_item['start_display'] ?? ($seed_item['start_local'] ?? ''))); if ($seed_meta !== '') { $seed_text .= ' - ' . $seed_meta; } if (!empty($seed_item['venue_name'])) { $seed_text .= ' | ' . (string) $seed_item['venue_name']; } ?><option value="<?php echo esc_attr((int) ($seed_item['id'] ?? 0)); ?>" data-event-item="<?php echo esc_attr(wp_json_encode($seed_item)); ?>"><?php echo esc_html($seed_text); ?></option><?php endforeach; ?></select></label>
		        <input type="hidden" id="vms-ma-event-plan-id" data-required-step="1" />
		        <input type="hidden" id="vms-ma-venue-id" />
		        <div class="vms-ma-manual-entry" data-advanced="1">
		          <label for="vms-ma-event-plan-id-manual"><span><?php esc_html_e('Event Plan ID (manual)', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-event-plan-id-manual" min="0" step="1" /></label>
		          <label for="vms-ma-venue-id-manual"><span><?php esc_html_e('Venue ID (optional)', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-venue-id-manual" min="0" step="1" /></label>
		        </div>
		        <label><span><?php esc_html_e('Event name', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-event-name" /></label>
		        <label><span><?php esc_html_e('Venue name', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-venue-name" /></label>
		        <label><span><?php esc_html_e('Event start (site timezone)', 'vms-meta-ads'); ?></span><input type="datetime-local" id="vms-ma-event-start" /></label>
		        <label for="vms-ma-destination-url"><span><?php esc_html_e('Destination URL', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for Destination URL', 'vms-meta-ads'); ?>" data-help-id="ma_help_destination_url">i</button></span><input type="url" id="vms-ma-destination-url" placeholder="https://" data-required-step="1" /></label>
		      </div>
		      <p id="vms-ma-event-picker-warning" class="description vms-ma-inline-warning vms-ma-hidden"><?php esc_html_e('Choose an Event Plan first. This fills the rest automatically.', 'vms-meta-ads'); ?></p>
		      <p class="description vms-ma-beginner-copy"><?php esc_html_e('Do this now: pick your Event Plan from the dropdown. VMS autofills the rest.', 'vms-meta-ads'); ?></p>
		      <p id="vms-ma-step-a-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-b" class="vms-ma-section vms-ma-step vms-ma-card" data-step="strategy" data-step-key="B" data-step-index="2">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step B - Strategy / Preset', 'vms-meta-ads'); ?></h2>
		      <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		    </div>
		    <div class="vms-ma-step-body">
		      <div id="vms-ma-preset" class="vms-ma-grid-two" data-vms-tour="vms-ma-preset">
		        <label>
		          <span><?php esc_html_e('Preset mode', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for preset mode', 'vms-meta-ads'); ?>" data-help-id="ma_help_preset">i</button></span>
		          <select id="vms-ma-preset-mode" data-vms-tour="vms-ma-preset-mode" data-required-step="2">
		            <option value="flat_run"><?php esc_html_e('Flat run (recommended)', 'vms-meta-ads'); ?></option>
		            <option value="promo_bundle_30_14_7"><?php esc_html_e('Promo Bundle 30/14/7', 'vms-meta-ads'); ?></option>
		            <option value="simple_7_day"><?php esc_html_e('Simple 7 day', 'vms-meta-ads'); ?></option>
		            <option value="simple_14_day"><?php esc_html_e('Simple 14 day', 'vms-meta-ads'); ?></option>
		            <option value="simple_30_day"><?php esc_html_e('Simple 30 day', 'vms-meta-ads'); ?></option>
		            <option value="manual_dates"><?php esc_html_e('Manual dates (Expert)', 'vms-meta-ads'); ?></option>
		          </select>
		        </label>
		        <label><span><?php esc_html_e('End ads this many hours before event', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-end_buffer_hours" min="0" step="1" value="2" data-required-step="2" /><small class="description"><?php esc_html_e('Most venues stop ads shortly before doors so you do not pay for late clicks.', 'vms-meta-ads'); ?></small></label>
		        <label data-advanced="1"><span><?php esc_html_e('Manual start (Expert)', 'vms-meta-ads'); ?></span><input type="datetime-local" id="vms-ma-manual-start" /></label>
		        <label data-advanced="1"><span><?php esc_html_e('Manual end (Expert)', 'vms-meta-ads'); ?></span><input type="datetime-local" id="vms-ma-manual-end" /></label>
		        <div class="vms-ma-how-scheduling description">
		          <strong><?php esc_html_e('How scheduling works', 'vms-meta-ads'); ?></strong><br />
		          <?php esc_html_e('1) Manual dates preset: manual start/end win. 2) Any other preset: VMS computes windows from preset. 3) End buffer always applies unless Manual dates is selected.', 'vms-meta-ads'); ?>
		        </div>
		        <div class="vms-ma-grid-three" data-advanced="1" id="vms-ma-custom-weight-wrap">
		          <label><span><?php esc_html_e('Weight 30 day %', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-weight_30" min="0" max="100" step="1" value="30" /></label>
		          <label><span><?php esc_html_e('Weight 14 day %', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-weight_14" min="0" max="100" step="1" value="30" /></label>
		          <label><span><?php esc_html_e('Weight 7 day %', 'vms-meta-ads'); ?></span><input type="number" id="vms-ma-weight_7" min="0" max="100" step="1" value="40" /></label>
		        </div>
		      </div>
		      <p class="description vms-ma-beginner-copy"><?php esc_html_e('Promo Bundle uses 30/14/7 tiers and VMS skips tiers that do not fit the calendar.', 'vms-meta-ads'); ?></p>
		      <p id="vms-ma-step-b-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-c" class="vms-ma-section vms-ma-step vms-ma-card" data-step="creative" data-step-key="C" data-step-index="3">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step C - Creative Mode', 'vms-meta-ads'); ?></h2>
		      <div class="vms-ma-step-head-actions">
		        <button type="button" class="button-link vms-ma-advanced-toggle"><?php esc_html_e('Advanced', 'vms-meta-ads'); ?></button>
		        <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		      </div>
		    </div>
		    <div class="vms-ma-step-body">
		      <p class="description vms-ma-beginner-copy"><?php esc_html_e('Do this now: keep Dark Post Link Ad unless you already have a high-performing existing post.', 'vms-meta-ads'); ?></p>
		      <fieldset class="vms-ma-choice-group vms-ma-choice-group-cards" id="vms-ma-creative-mode-group">
		        <legend class="screen-reader-text"><?php esc_html_e('Creative mode', 'vms-meta-ads'); ?></legend>
		        <label class="vms-ma-choice vms-ma-choice-card" data-vms-tour="vms-ma-creative-dark">
		          <input type="radio" id="vms-ma-creative-dark" name="vms_ma_creative_mode" value="dark_post" checked data-required-step="3" />
		          <span><strong><?php esc_html_e('Dark Post Link Ad', 'vms-meta-ads'); ?></strong> <span class="vms-ma-tag"><?php esc_html_e('Recommended', 'vms-meta-ads'); ?></span> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for dark post mode', 'vms-meta-ads'); ?>" data-help-id="ma_help_dark_post">i</button></span>
		        </label>
		        <label class="vms-ma-choice vms-ma-choice-card" data-advanced="1">
		          <input type="radio" id="vms-ma-creative-boost" name="vms_ma_creative_mode" value="boost_post" />
		          <span><strong><?php esc_html_e('Boost Existing Post', 'vms-meta-ads'); ?></strong> <span class="vms-ma-tag"><?php esc_html_e('Social proof', 'vms-meta-ads'); ?></span></span>
		        </label>
		        <label class="vms-ma-choice vms-ma-choice-card" data-advanced="1">
		          <input type="radio" id="vms-ma-creative-event" name="vms_ma_creative_mode" value="fb_event" />
		          <span><strong><?php esc_html_e('Promote Facebook Event', 'vms-meta-ads'); ?></strong> <span class="vms-ma-tag"><?php esc_html_e('Advanced', 'vms-meta-ads'); ?></span></span>
		        </label>
		      </fieldset>
		      <p><button type="button" class="button-link" id="vms-ma-copy_regen"><?php esc_html_e('Regenerate from event', 'vms-meta-ads'); ?></button></p>
		      <div id="vms-ma-creative-dark-fields" class="vms-ma-creative-fields vms-ma-creative-fields--dark vms-ma-grid-two" data-mode="dark_post">
		        <label><span><?php esc_html_e('Primary text', 'vms-meta-ads'); ?></span><textarea id="vms-ma-primary-text" name="primary_text" rows="2"></textarea></label>
		        <label><span><?php esc_html_e('Headline', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-headline" name="headline" value="" /></label>
		        <label><span><?php esc_html_e('Description', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-description" name="description" value="" /></label>
		      </div>
		      <div id="vms-ma-creative-boost-fields" class="vms-ma-creative-fields vms-ma-creative-fields--boost" data-mode="boost_post" data-advanced="1">
		        <label id="vms-ma-post-picker-wrap" class="vms-ma-hidden"><span><?php esc_html_e('Find existing post', 'vms-meta-ads'); ?></span><select id="vms-ma-post-picker"><option value=""><?php esc_html_e('Select a recent post...', 'vms-meta-ads'); ?></option></select></label>
		        <label id="vms-ma-post-url-wrap"><span><?php esc_html_e('Paste Facebook post URL or ID', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-post-url" placeholder="https://facebook.com/.../posts/..." /></label>
		        <input type="hidden" id="vms-ma-post-id" name="post_asset_id" value="" />
		      </div>
		      <div id="vms-ma-creative-event-fields" class="vms-ma-creative-fields vms-ma-creative-fields--fb-event" data-mode="fb_event" data-advanced="1">
		        <label id="vms-ma-fb-event-picker-wrap" class="vms-ma-hidden"><span><?php esc_html_e('Find Facebook Event', 'vms-meta-ads'); ?></span><select id="vms-ma-fb-event-picker"><option value=""><?php esc_html_e('Select upcoming event...', 'vms-meta-ads'); ?></option></select></label>
		        <label id="vms-ma-fb-event-url-wrap"><span><?php esc_html_e('Paste Facebook Event URL or ID', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-fb-event-url" placeholder="https://facebook.com/events/..." /></label>
		        <input type="hidden" id="vms-ma-fb-event-id" name="facebook_event_id" value="" />
		      </div>
		      <p id="vms-ma-step-c-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-d" class="vms-ma-section vms-ma-step vms-ma-card" data-step="audience" data-step-key="D" data-step-index="4">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step D - Audience', 'vms-meta-ads'); ?></h2>
		      <div class="vms-ma-step-head-actions">
		        <button type="button" class="button-link vms-ma-advanced-toggle"><?php esc_html_e('Advanced', 'vms-meta-ads'); ?></button>
		        <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		      </div>
		    </div>
		    <div class="vms-ma-step-body">
		      <div class="vms-ma-grid-two">
		        <label id="vms-ma-radius-wrap" data-vms-tour="vms-ma-radius"><span><?php esc_html_e('Radius (miles)', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for radius', 'vms-meta-ads'); ?>" data-help-id="ma_help_radius">i</button></span><input type="number" id="vms-ma-radius" min="1" value="<?php echo esc_attr((int) $settings['default_radius_miles']); ?>" data-required-step="4" /></label>
		        <label><span><?php esc_html_e('Age min', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for age range', 'vms-meta-ads'); ?>" data-help-id="ma_help_age">i</button></span><input type="number" id="vms-ma-age-min" min="13" value="<?php echo esc_attr((int) $settings['default_age_min']); ?>" data-required-step="4" /></label>
		        <label><span><?php esc_html_e('Age max', 'vms-meta-ads'); ?></span><select id="vms-ma-age-max" data-required-step="4"><?php foreach ($age_options as $age_opt) : ?><option value="<?php echo esc_attr((string) $age_opt); ?>" <?php selected($age_max_default, $age_opt); ?>><?php echo esc_html($age_opt === 65 ? '65+' : (string) $age_opt); ?></option><?php endforeach; ?></select></label>
		        <label data-advanced="1"><span><?php esc_html_e('Interests (comma separated)', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for interests', 'vms-meta-ads'); ?>" data-help-id="ma_help_interests">i</button></span><input type="text" id="vms-ma-interests" /></label>
		      </div>
		      <div class="vms-ma-grid-two vms-ma-audience-saved-wrap" data-advanced="1">
		        <label class="vms-ma-choice vms-ma-inline-choice"><input type="checkbox" id="vms-ma-use-saved-audience" value="1" /> <span><?php esc_html_e('Use saved audience', 'vms-meta-ads'); ?></span></label>
		        <label><span><?php esc_html_e('Saved audiences', 'vms-meta-ads'); ?></span><select id="vms-ma-saved-audience" disabled><option value=""><?php esc_html_e('No saved audience available', 'vms-meta-ads'); ?></option></select></label>
		      </div>
		      <p id="vms-ma-radius-warning" class="description vms-ma-inline-warning vms-ma-hidden"></p>
		      <p class="description vms-ma-beginner-copy"><?php esc_html_e('Keep audience broad; interests can reduce delivery if too narrow.', 'vms-meta-ads'); ?></p>
		      <p id="vms-ma-step-d-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-e" class="vms-ma-section vms-ma-step vms-ma-card" data-step="budget" data-step-key="E" data-step-index="5" data-vms-tour="vms-ma-budget">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step E - Budget + Schedule', 'vms-meta-ads'); ?></h2>
		      <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		    </div>
		    <div class="vms-ma-step-body">
		      <div class="vms-ma-grid-two">
		        <label><span><?php esc_html_e('Total budget (dollars)', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for total budget', 'vms-meta-ads'); ?>" data-help-id="ma_help_budget_total">i</button></span><input type="number" id="vms-ma-budget-total" data-vms-tour="vms-ma-budget-total" min="1" step="0.01" data-required-step="5" /></label>
		        <label>
		          <span><?php esc_html_e('Goal', 'vms-meta-ads'); ?></span>
		          <select id="vms-ma-goal" data-required-step="5">
		            <option value="traffic"><?php esc_html_e('Traffic', 'vms-meta-ads'); ?></option>
		            <option value="engagement"><?php esc_html_e('Engagement', 'vms-meta-ads'); ?></option>
		            <option value="leads"><?php esc_html_e('Leads', 'vms-meta-ads'); ?></option>
		          </select>
		        </label>
		        <label id="vms-ma-optimization-wrap"><span><?php esc_html_e('Optimization', 'vms-meta-ads'); ?></span><select id="vms-ma-optimization"><option value="link_clicks"><?php esc_html_e('Link clicks', 'vms-meta-ads'); ?></option><option value="landing_page_views"><?php esc_html_e('Landing page views', 'vms-meta-ads'); ?></option></select><small class="description"><?php esc_html_e('Traffic is the goal. Link clicks is how Meta optimizes delivery.', 'vms-meta-ads'); ?></small></label>
		        <label><span><?php esc_html_e('Schedule start', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-sched-start" readonly /></label>
		        <label><span><?php esc_html_e('Schedule end', 'vms-meta-ads'); ?></span><input type="text" id="vms-ma-sched-end" readonly /></label>
		      </div>
		      <p class="description vms-ma-section-intro"><?php esc_html_e('Tier windows and budgets are generated automatically and validated against clamps.', 'vms-meta-ads'); ?></p>
		      <p class="description"><?php esc_html_e('Schedule is computed from your preset. Switch to Manual Dates in Expert if you need custom.', 'vms-meta-ads'); ?></p>
		      <div id="vms-ma-tier-table" class="vms-ma-tier-table" aria-live="polite"></div>
		      <p id="vms-ma-step-e-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-f" class="vms-ma-section vms-ma-step vms-ma-card" data-step="output" data-step-key="F" data-step-index="6">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step F - Copy Pack Output', 'vms-meta-ads'); ?> <button type="button" class="vms-ma-info" aria-label="<?php esc_attr_e('Help for Copy Pack', 'vms-meta-ads'); ?>" data-help-id="ma_help_copy_pack">i</button></h2>
		      <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		    </div>
		    <div class="vms-ma-step-body">
		      <div id="vms-ma-copy-pack-warning" class="vms-ma-inline-warning vms-ma-hidden"></div>
		      <div id="vms-ma-output-pack" data-vms-tour="vms-ma-output-pack"><pre id="vms-ma-copy-pack"></pre></div>
		      <div class="vms-ma-actions vms-ma-actions--copy">
		        <button type="button" class="button" id="vms-ma-copy-all"><?php esc_html_e('Copy All', 'vms-meta-ads'); ?></button>
		        <button type="button" class="button" id="vms-ma-copy-url"><?php esc_html_e('Copy URL', 'vms-meta-ads'); ?></button>
		        <button type="button" class="button" id="vms-ma-download-pack"><?php esc_html_e('Download .txt', 'vms-meta-ads'); ?></button>
		      </div>
		      <p id="vms-ma-step-f-status" class="description" aria-live="polite"></p>
		      <p><button type="button" class="button button-secondary vms-ma-step-continue"><?php esc_html_e('Continue', 'vms-meta-ads'); ?></button></p>
		    </div>
		  </section>

		  <section id="vms-ma-step-g" class="vms-ma-section vms-ma-step vms-ma-card" data-step="actions" data-step-key="G" data-step-index="7">
		    <div class="vms-ma-step-header vms-ma-step-head" role="button" tabindex="0" aria-expanded="false">
		      <h2 class="vms-ma-section-title"><?php esc_html_e('Step G - Actions', 'vms-meta-ads'); ?></h2>
		      <span class="vms-ma-step-status" data-step-status><?php esc_html_e('Locked', 'vms-meta-ads'); ?></span>
		    </div>
		    <div class="vms-ma-step-body">
		      <p class="description vms-ma-section-intro"><?php esc_html_e('What happens next: save your draft, export copy pack, then optionally create paused assets in Meta once connection is ready.', 'vms-meta-ads'); ?></p>
		      <p id="vms-ma-api-gating-reason" class="description"></p>
		      <div class="vms-ma-actions vms-ma-actions--primary">
		        <button id="vms-ma-save-draft" class="button button-primary" type="button"><?php esc_html_e('Save Draft', 'vms-meta-ads'); ?></button>
		        <button id="vms-ma-export-pack" class="button" type="button"><?php esc_html_e('Export Copy Pack', 'vms-meta-ads'); ?></button>
		        <button id="vms-ma-create-paused" class="button" type="button"><?php esc_html_e('Create in Meta (PAUSED)', 'vms-meta-ads'); ?></button>
		        <button id="vms-ma-go-live" class="button" type="button"><?php esc_html_e('Go Live', 'vms-meta-ads'); ?></button>
		        <input type="hidden" id="vms-ma-build-id" value="" />
		      </div>
		      <p id="vms-ma-step-g-status" class="description" aria-live="polite"></p>
		      <p><a href="<?php echo esc_url(add_query_arg(array('page' => 'vms-ma-ads-settings'), admin_url('admin.php'))); ?>"><?php esc_html_e('Open Settings', 'vms-meta-ads'); ?></a></p>
		    </div>
		  </section>
		</div>
		<div id="vms-ma-gated-modal" class="vms-ma-modal vms-ma-hidden" role="dialog" aria-modal="true" aria-labelledby="vms-ma-gated-title">
		  <div class="vms-ma-modal-card">
		    <h2 id="vms-ma-gated-title"><?php esc_html_e('This action is gated in the current phase.', 'vms-meta-ads'); ?></h2>
		    <p id="vms-ma-gated-message"><?php esc_html_e('Use Save Draft or Export Copy Pack in this phase. You can raise phase in Settings when ready.', 'vms-meta-ads'); ?></p>
		    <p>
		      <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'vms-ma-ads-settings'), admin_url('admin.php'))); ?>"><?php esc_html_e('Open Settings', 'vms-meta-ads'); ?></a>
		      <button type="button" class="button" id="vms-ma-gated-close"><?php esc_html_e('Close', 'vms-meta-ads'); ?></button>
		    </p>
		  </div>
		</div>
		<?php
	}
}
