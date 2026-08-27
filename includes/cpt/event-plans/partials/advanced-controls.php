    <?php
        defined('ABSPATH') || exit;
        $lookup_trace = function_exists('bvmgr_event_plan_perf_span_start')
            ? bvmgr_event_plan_perf_span_start('event_plan_advanced_controls_lookup', (int) $post->ID, array('section' => 'advanced_controls_lookup'))
            : '';
        $vms_ticketing_v2_render_mode = isset($vms_ticketing_v2_render_mode) ? sanitize_key((string) $vms_ticketing_v2_render_mode) : 'full';
        $meta_bundle = method_exists($this, 'get_event_plan_meta_bundle')
            ? (array) $this->get_event_plan_meta_bundle((int) $post->ID)
            : array();
        $linked_tec_summary = method_exists($this, 'get_event_plan_linked_tec_summary')
            ? (array) $this->get_event_plan_linked_tec_summary((int) $post->ID)
            : array();

        $linked_tec_id  = absint($linked_tec_summary['linked_tec_id'] ?? ($meta_bundle['linked_tec_id'] ?? 0));
        $linked_tec_url = (string) ($linked_tec_summary['linked_tec_url'] ?? ($meta_bundle['linked_tec_url'] ?? ''));
        $linked_tec_title = (string) ($linked_tec_summary['linked_tec_title'] ?? '');

        $k_ticket_pids = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'ticket_product_ids') ?: '_vms_ticket_product_ids_v1') : '_vms_ticket_product_ids_v1';
        $ticket_pids = isset($meta_bundle['ticket_product_ids']) && is_array($meta_bundle['ticket_product_ids']) ? $meta_bundle['ticket_product_ids'] : array();
        $ticket_stats = isset($ticket_stats) && is_array($ticket_stats)
            ? $ticket_stats
            : (isset($meta_bundle['ticket_stats']) && is_array($meta_bundle['ticket_stats']) ? $meta_bundle['ticket_stats'] : array());
        $manual_ticket_pids = isset($meta_bundle['manual_ticket_product_ids']) && is_array($meta_bundle['manual_ticket_product_ids']) ? $meta_bundle['manual_ticket_product_ids'] : array();
        $ticket_pids_meta_exists = function_exists('metadata_exists') ? metadata_exists('post', $post->ID, $k_ticket_pids) : !empty($ticket_pids);

        $tec_admin_edit_url = ($linked_tec_id > 0)
            ? (string) ($linked_tec_summary['linked_tec_admin_url'] ?? admin_url('post.php?post=' . $linked_tec_id . '&action=edit'))
            : '';

        // Optional: show legacy/import IDs (if present) to avoid confusing operators.
        $linked_tec_legacy = array();
        if ($linked_tec_id > 0 && function_exists('bvmgr_ticketing_get_tec_legacy_identifiers')) {
            $linked_tec_legacy = bvmgr_ticketing_get_tec_legacy_identifiers($linked_tec_id);
        }
        $linked_tec_legacy_str = '';
        if (!empty($linked_tec_legacy)) {
            $parts = array();
            foreach ($linked_tec_legacy as $row) {
                if (!is_array($row)) { continue; }
                $label = isset($row['label']) ? trim((string) $row['label']) : '';
                $val   = isset($row['value']) ? trim((string) $row['value']) : '';
                if ($label !== '' && $val !== '') {
                    $parts[] = $label . ': ' . $val;
                }
            }
            $linked_tec_legacy_str = implode(' · ', $parts);
        }
        // Guardrail: On post-new.php, WordPress creates an auto-draft.
        // Actions in this section can redirect/reload to the stable edit screen.
        // If the operator has not saved yet, that can discard unsaved entries.
        $is_autodraft = false;
        if (!empty($post) && !empty($post->ID)) {
            $is_autodraft = (get_post_status($post->ID) === 'auto-draft');
        }
        $has_stable_draft = !$is_autodraft;
        $resync_form_id = 'vms-event-plan-calendar-resync-' . (int) $post->ID;
        $resync_redirect_to = admin_url('post.php?post=' . (int) $post->ID . '&action=edit');
        if ($has_stable_draft && function_exists('bvmgr_event_plan_editor_register_detached_form')) {
            bvmgr_event_plan_editor_register_detached_form(
                $resync_form_id,
                'post',
                admin_url('admin-post.php'),
                array(
                    'action' => 'vms_resync_event_to_calendar',
                    'post_id' => (int) $post->ID,
                    'redirect_to' => $resync_redirect_to,
                    'source' => 'advanced_controls',
                    '_vms_resync_calendar_nonce' => wp_create_nonce('vms_resync_calendar'),
                )
            );
        }
        if (function_exists('bvmgr_event_plan_perf_span_finish')) {
            bvmgr_event_plan_perf_span_finish('event_plan_advanced_controls_lookup', (int) $post->ID, $lookup_trace, array(
                'section' => 'advanced_controls_lookup',
                'linked_tec_event_id' => $linked_tec_id,
                'ticket_product_count' => is_array($ticket_pids) ? count($ticket_pids) : 0,
                'manual_ticket_product_count' => is_array($manual_ticket_pids) ? count($manual_ticket_pids) : 0,
            ));
        }
    ?>


    <details id="vms-advanced-controls" class="vms-advanced-controls" data-vms-plan-id="<?php echo (int) $post->ID; ?>" data-vms-stable-draft="<?php echo $has_stable_draft ? '1' : '0'; ?>" <?php echo ((int)$linked_tec_id <= 0 ? 'open' : ''); ?>>
        <summary>
            <strong><?php esc_html_e('Advanced Controls', 'backstage-venue-manager'); ?></strong>
            <span class="description"><?php esc_html_e('Calendar link + troubleshooting', 'backstage-venue-manager'); ?></span>
        </summary>

        <div class="vms-advanced-controls__body">

            <p class="description"><?php esc_html_e('Most operators can ignore this section. Use it only if you are troubleshooting calendar links or you have been instructed to change these settings.', 'backstage-venue-manager'); ?></p>

            <?php
                do_action('vms_event_plan_advanced_controls_after_intro', $post, array(
                    'is_autodraft' => $is_autodraft,
                    'has_stable_draft' => $has_stable_draft,
                    'linked_tec_id' => $linked_tec_id,
                ));
            ?>

            <?php if ($is_autodraft) : ?>
                <div class="notice notice-warning inline vms-notice vms-notice--warning">
                    <p>
                        <strong><?php esc_html_e('Save Draft first.', 'backstage-venue-manager'); ?></strong>
                        <?php esc_html_e('These advanced tools are disabled until this Event Plan has been saved at least once. This prevents losing information you have entered.', 'backstage-venue-manager'); ?>
                    </p>
                </div>
            <?php else : ?>

            <p>
                <button type="submit" form="<?php echo esc_attr($resync_form_id); ?>" class="button button-secondary"
                    data-vms-link-sensitive="1"
                    <?php echo ($linked_tec_id > 0) ? '' : ' disabled="disabled"'; ?>>
                    <?php esc_html_e('Re-sync to Calendar', 'backstage-venue-manager'); ?>
                </button>

                <?php if ($linked_tec_id > 0 && $linked_tec_url) : ?>
                    <a class="button" href="<?php echo esc_url($linked_tec_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('View in Calendar', 'backstage-venue-manager'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($linked_tec_id > 0 && $tec_admin_edit_url) : ?>
                    <a class="button" href="<?php echo esc_url($tec_admin_edit_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Edit Calendar Event', 'backstage-venue-manager'); ?>
                    </a>
                <?php endif; ?>
            </p>

            <p class="description"><?php esc_html_e('Re-sync uses saved Event Plan data only. Click Update first if you need unsaved edits included.', 'backstage-venue-manager'); ?></p>

            <div class="vms-ticketing" data-vms-stable-draft="<?php echo $has_stable_draft ? '1' : '0'; ?>">

                <?php
                    echo $this->capture_event_plan_partial('legacy-imported-ticketing-integration', array(
                        'post' => $post,
                        'linked_tec_id' => $linked_tec_id,
                        'linked_tec_title' => $linked_tec_title,
                        'linked_tec_legacy_str' => $linked_tec_legacy_str,
                        'ticket_stats' => $ticket_stats,
                        'ticket_pids' => $ticket_pids,
                        'ticket_pids_meta_exists' => $ticket_pids_meta_exists,
                        'manual_ticket_pids' => $manual_ticket_pids,
                    ));
                ?> 

						<?php if ($vms_ticketing_v2_render_mode === 'omit') : ?>
							<p class="description"><?php esc_html_e('The full GA attendance + entitlements editor now opens in the Ticketing metabox above. This panel keeps the legacy link tools and calendar troubleshooting controls.', 'backstage-venue-manager'); ?></p>
						<?php else : ?>
						<div id="vms-ticketing-v2-source" class="vms-ticketing__managed vms-ticketing__managed-v2">
							<h5><?php esc_html_e('GA attendance + entitlements', 'backstage-venue-manager'); ?></h5>

							<?php
								$v2_lookup_trace = function_exists('bvmgr_event_plan_perf_span_start')
									? bvmgr_event_plan_perf_span_start('event_plan_ticketing_v2_lookup', (int) $post->ID, array('section' => 'ticketing_v2_lookup', 'linked_tec_event_id' => $linked_tec_id))
									: '';
								$can_phase_b = function_exists('bvmgr_ticketing_b_is_event_tickets_woo_available')
									? bvmgr_ticketing_b_is_event_tickets_woo_available()
									: false;
								$cfg_v2 = function_exists('bvmgr_ticketing_v2_get_admin_config')
									? bvmgr_ticketing_v2_get_admin_config($post->ID)
									: (function_exists('bvmgr_ticketing_v2_get_config') ? bvmgr_ticketing_v2_get_config($post->ID) : array());
								$sync_v2 = function_exists('bvmgr_ticketing_v2_get_sync') ? bvmgr_ticketing_v2_get_sync($post->ID) : array();
								$mode_v2 = is_array($cfg_v2) ? (string) ($cfg_v2['mode'] ?? 'read_only') : 'read_only';
								$sync_map_v2 = (is_array($sync_v2) && !empty($sync_v2['map']) && is_array($sync_v2['map'])) ? $sync_v2['map'] : array();
								$last_commit_ts = 0;
								$last_commit_at = '';
								if (is_array($sync_v2) && !empty($sync_v2['last_commit']) && is_array($sync_v2['last_commit']) && !empty($sync_v2['last_commit']['at'])) {
									$last_commit_ts = absint($sync_v2['last_commit']['at']);
									$last_commit_at = ($last_commit_ts > 0) ? (string) $last_commit_ts : '';
									if ($last_commit_ts > 0 && function_exists('wp_date') && function_exists('wp_timezone')) {
										$last_commit_at = wp_date('Y-m-d H:i', $last_commit_ts, wp_timezone());
									}
								}
								$cfg_v2_exists = (function_exists('metadata_exists') && function_exists('bvmgr_ticketing_v2_k'))
									? (metadata_exists('post', $post->ID, bvmgr_ticketing_v2_k('config')) ? '1' : '0')
									: '0';
								$templates_v2 = function_exists('bvmgr_ticketing_v2_templates_get_all') ? bvmgr_ticketing_v2_templates_get_all() : array();
								$default_tpl_id = function_exists('bvmgr_ticketing_v2_get_default_template_id') ? bvmgr_ticketing_v2_get_default_template_id() : '';
								$default_tpl_name = '';
								if ($default_tpl_id && !empty($templates_v2[$default_tpl_id]) && is_array($templates_v2[$default_tpl_id])) {
									$default_tpl_name = (string) (($templates_v2[$default_tpl_id]['name'] ?? '') ?: $default_tpl_id);
								}
								$settings = (array) get_option('vms_settings', array());
								$global_ticketing_default = !empty($settings['ticketing_enabled_default']);
								$ticketing_override = (string) get_post_meta($post->ID, '_vms_ticketing_enabled_override', true);
								$ticketing_effective = function_exists('bvmgr_event_plan_is_ticketing_enabled') ? bvmgr_event_plan_is_ticketing_enabled((int) $post->ID) : $global_ticketing_default;
								$ticketing_global_label = $global_ticketing_default ? __('ON', 'backstage-venue-manager') : __('OFF', 'backstage-venue-manager');
								$ticket_ui_settings = (array) get_option('vms_settings', array());
								$ticket_ui_global_layout = isset($ticket_ui_settings['ticket_ui_layout']) ? sanitize_key((string) $ticket_ui_settings['ticket_ui_layout']) : 'classic';
								if (!in_array($ticket_ui_global_layout, array('classic', 'v2', 'progressive'), true)) {
									$ticket_ui_global_layout = 'classic';
								}
								$ticket_ui_global_labels = array(
									'classic' => __('Safe Mode / legacy TEC', 'backstage-venue-manager'),
									'v2' => __('V2 unified', 'backstage-venue-manager'),
									'progressive' => __('Progressive', 'backstage-venue-manager'),
								);
								$ticket_ui_layout_override = sanitize_key((string) get_post_meta($post->ID, '_vms_ticket_ui_layout_override', true));
								if (!in_array($ticket_ui_layout_override, array('', 'classic', 'v2', 'progressive'), true)) {
									$ticket_ui_layout_override = '';
								}
								$preview_disabled = (!$ticketing_effective || !$can_phase_b);
								$stats_computed_ts = (is_array($ticket_stats) && isset($ticket_stats['computed_at_gmt'])) ? absint($ticket_stats['computed_at_gmt']) : 0;
								$stats_stale_after_commit = ($last_commit_ts > 0 && $stats_computed_ts < $last_commit_ts);
								$recon_v2 = array();
								if ($linked_tec_id > 0 && $mode_v2 === 'vms_managed' && function_exists('bvmgr_ticketing_v2_reconcile_event_plan_ticket_cache')) {
									$recon_v2 = bvmgr_ticketing_v2_reconcile_event_plan_ticket_cache((int) $post->ID, (int) $linked_tec_id, $sync_map_v2, false);
								}
								$recon_v2_warnings = (is_array($recon_v2) && !empty($recon_v2['warnings']) && is_array($recon_v2['warnings'])) ? $recon_v2['warnings'] : array();
								$recon_v2_warnings = array_values(array_unique(array_filter(array_map('strval', $recon_v2_warnings))));
								if (function_exists('bvmgr_event_plan_perf_span_finish')) {
									bvmgr_event_plan_perf_span_finish('event_plan_ticketing_v2_lookup', (int) $post->ID, $v2_lookup_trace, array(
										'section' => 'ticketing_v2_lookup',
										'linked_tec_event_id' => $linked_tec_id,
										'ticketing_mode' => sanitize_key($mode_v2),
										'ticket_warning_count' => count($recon_v2_warnings),
									));
								}
							?>

						<?php if (!$ticketing_effective) : ?>
							<p class="description"><?php esc_html_e('Ticketing is disabled for this event. Set “Tickets for this event” to On to configure and create tickets.', 'backstage-venue-manager'); ?></p>
						<?php elseif (!$can_phase_b) : ?>
							<p class="description"><?php esc_html_e('Event Tickets (WooCommerce) is not available. Activate Event Tickets, Event Tickets Plus, and WooCommerce.', 'backstage-venue-manager'); ?></p>
						<?php elseif ($linked_tec_id <= 0) : ?>
							<p class="description"><?php esc_html_e('No calendar event is linked yet. Click “Preview sync” and Backstage Venue Manager will create and link the calendar event automatically, then show you exactly what tickets/products will be created on Commit.', 'backstage-venue-manager'); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e('Configure GA attendance and entitlements here. Use Save → Preview → Commit. No tickets or products are deleted by Backstage Venue Manager.', 'backstage-venue-manager'); ?></p>
						<?php endif; ?>

						<?php if (!empty($recon_v2_warnings)) : ?>
							<div class="notice notice-warning inline vms-notice vms-notice--warning">
								<p><strong><?php esc_html_e('Ticketing reconciliation found mismatches.', 'backstage-venue-manager'); ?></strong></p>
								<?php foreach ($recon_v2_warnings as $rw) : ?>
									<p class="vms-m0"><?php echo esc_html($rw); ?></p>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ($mode_v2 === 'vms_managed' && $stats_stale_after_commit) : ?>
							<div class="notice notice-info inline vms-notice vms-notice--info">
								<p><?php esc_html_e('Ticketing was committed after the last stats refresh. Click “Refresh ticket stats” to update sold/revenue totals for this Event Plan.', 'backstage-venue-manager'); ?></p>
							</div>
						<?php endif; ?>

						<?php
							$ticket_help_tickets_override = (string) get_post_meta($post->ID, '_vms_ticket_ui_help_tickets_override', true);
							$ticket_help_addons_override = (string) get_post_meta($post->ID, '_vms_ticket_ui_help_addons_override', true);
							$ticket_addons_heading_override = trim(html_entity_decode((string) get_post_meta($post->ID, '_vms_ticket_ui_addons_heading_override', true), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
							$ticket_addons_subtext_override = trim(html_entity_decode((string) get_post_meta($post->ID, '_vms_ticket_ui_addons_subtext_override', true), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
							$ticket_addons_heading_placeholder = function_exists('bvmgr_ticketing_ui_addons_section_heading')
								? (string) bvmgr_ticketing_ui_addons_section_heading()
								: __('Fire Pits & Tables', 'backstage-venue-manager');
							$ticket_addons_subtext_placeholder = function_exists('bvmgr_ticketing_ui_addons_section_subtext')
								? (string) bvmgr_ticketing_ui_addons_section_subtext()
								: __('Click here to add a fire pit or table to your order.', 'backstage-venue-manager');
							$ticket_help_tickets_placeholder = function_exists('bvmgr_ticketing_ui_help_global_text')
								? (string) bvmgr_ticketing_ui_help_global_text('tickets')
								: '';
							$ticket_help_addons_placeholder = function_exists('bvmgr_ticketing_ui_help_global_text')
								? (string) bvmgr_ticketing_ui_help_global_text('addons')
								: '';
						?>
						<div class="vms-ticketing__togglebar">
							<strong><?php esc_html_e('Tickets for this event:', 'backstage-venue-manager'); ?></strong>
							<select name="vms_ticketing_enabled_override" id="vms-ticketing-enabled-override">
								<?php /* translators: %s: current global ticketing setting label. */ ?>
								<option value="" <?php selected($ticketing_override, ''); ?>><?php echo esc_html(sprintf(__('Inherit (Global: %s)', 'backstage-venue-manager'), $ticketing_global_label)); ?></option>
								<option value="on" <?php selected($ticketing_override, 'on'); ?>><?php esc_html_e('On', 'backstage-venue-manager'); ?></option>
								<option value="off" <?php selected($ticketing_override, 'off'); ?>><?php esc_html_e('Off', 'backstage-venue-manager'); ?></option>
							</select>
							<?php if (!$ticketing_effective): ?>
								<span class="description vms-ml-10"><?php esc_html_e('Ticketing is currently disabled for this event.', 'backstage-venue-manager'); ?></span>
							<?php endif; ?>
						</div>

						<div class="vms-ticketing__togglebar" data-vms-tour="ticketing-ui.event-override">
							<strong><?php esc_html_e('Public ticket UI:', 'backstage-venue-manager'); ?></strong>
							<select name="vms_ticket_ui_layout_override" id="vms-ticket-ui-layout-override">
								<?php /* translators: %s: current global public ticket UI layout label. */ ?>
								<option value="" <?php selected($ticket_ui_layout_override, ''); ?>><?php echo esc_html(sprintf(__('Inherit (Global: %s)', 'backstage-venue-manager'), (string) ($ticket_ui_global_labels[$ticket_ui_global_layout] ?? $ticket_ui_global_layout))); ?></option>
								<option value="progressive" <?php selected($ticket_ui_layout_override, 'progressive'); ?>><?php esc_html_e('Force Progressive', 'backstage-venue-manager'); ?></option>
								<option value="v2" <?php selected($ticket_ui_layout_override, 'v2'); ?>><?php esc_html_e('Force V2 Unified', 'backstage-venue-manager'); ?></option>
								<option value="classic" <?php selected($ticket_ui_layout_override, 'classic'); ?>><?php esc_html_e('Force Legacy / Safe Mode', 'backstage-venue-manager'); ?></option>
							</select>
							<span class="description vms-ml-10"><?php esc_html_e('Use this for per-event rollback or testing without changing the global setting.', 'backstage-venue-manager'); ?></span>
						</div>

						<div class="vms-ticketing__box vms-ticketing__helpcopy-box">
							<h4 class="vms-ticketing__box-title"><?php esc_html_e('Public help copy overrides', 'backstage-venue-manager'); ?></h4>
							<p class="description"><?php esc_html_e('Use these only when this event needs wording that differs from the global default. Leave blank to inherit the global help copy from Backstage Venue Manager Settings.', 'backstage-venue-manager'); ?></p>
							<div class="vms-ticketing__help-label-grid">
								<p>
									<label for="vms_ticket_ui_addons_heading_override"><strong><?php esc_html_e('Add-on section heading override', 'backstage-venue-manager'); ?></strong></label><br />
									<input id="vms_ticket_ui_addons_heading_override" type="text" class="regular-text" name="vms_ticket_ui_addons_heading_override" value="<?php echo esc_attr($ticket_addons_heading_override); ?>" placeholder="<?php echo esc_attr($ticket_addons_heading_placeholder); ?>" />
									<span class="description"><?php esc_html_e('Example: Fire Pits & Tables. Leave blank to inherit the global heading.', 'backstage-venue-manager'); ?></span>
								</p>
								<p>
									<label for="vms_ticket_ui_addons_subtext_override"><strong><?php esc_html_e('Add-on section subtext override', 'backstage-venue-manager'); ?></strong></label><br />
									<input id="vms_ticket_ui_addons_subtext_override" type="text" class="large-text" name="vms_ticket_ui_addons_subtext_override" value="<?php echo esc_attr($ticket_addons_subtext_override); ?>" placeholder="<?php echo esc_attr($ticket_addons_subtext_placeholder); ?>" />
									<span class="description"><?php esc_html_e('Shown under the collapsed Progressive add-on heading.', 'backstage-venue-manager'); ?></span>
								</p>
							</div>
							<p>
								<label><strong><?php esc_html_e('Ticket help override', 'backstage-venue-manager'); ?></strong></label><br />
								<span class="description"><?php echo esc_html($ticket_help_tickets_placeholder !== '' ? __('Leave blank to inherit the global ticket help copy. Current global copy shown below this editor.', 'backstage-venue-manager') : __('Leave blank to inherit the global ticket help copy.', 'backstage-venue-manager')); ?></span>
								<?php
								wp_editor(
									$ticket_help_tickets_override,
									'vms_ticket_ui_help_tickets_override_editor',
									array(
										'textarea_name' => 'vms_ticket_ui_help_tickets_override',
										'textarea_rows' => 5,
										'media_buttons' => false,
										'teeny' => false,
										'quicktags' => true,
										'tinymce' => array(
											'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo',
											'toolbar2' => '',
										),
									)
								);
								?>
							</p>
							<p>
								<label><strong><?php esc_html_e('Add-on help override', 'backstage-venue-manager'); ?></strong></label><br />
								<span class="description"><?php echo esc_html($ticket_help_addons_placeholder !== '' ? __('Leave blank to inherit the global add-on help copy. Current global copy shown below this editor.', 'backstage-venue-manager') : __('Leave blank to inherit the global add-on help copy.', 'backstage-venue-manager')); ?></span>
								<?php
								wp_editor(
									$ticket_help_addons_override,
									'vms_ticket_ui_help_addons_override_editor',
									array(
										'textarea_name' => 'vms_ticket_ui_help_addons_override',
										'textarea_rows' => 5,
										'media_buttons' => false,
										'teeny' => false,
										'quicktags' => true,
										'tinymce' => array(
											'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo',
											'toolbar2' => '',
										),
									)
								);
								?>
							</p>
						</div>
 
						<div class="vms-ticketing__tplbar">
							<strong><?php esc_html_e('Templates:', 'backstage-venue-manager'); ?></strong>
							<select id="vms-ticketing-v2-template-select" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>>
								<option value=""><?php esc_html_e('Select a template…', 'backstage-venue-manager'); ?></option>
								<?php foreach ($templates_v2 as $tid => $t) : ?>
									<?php
										$tpl_label = (string) (($t['name'] ?? '') ?: $tid);
										if ($default_tpl_id && $tid === $default_tpl_id) {
											$tpl_label .= ' (Default)';
										}
										$tpl_guardrail = (isset($t['sales_end_guardrail']) && is_array($t['sales_end_guardrail']))
											? $t['sales_end_guardrail']
											: array();
									?>
									<option value="<?php echo esc_attr($tid); ?>" data-sales-end-guardrail="<?php echo esc_attr(wp_json_encode($tpl_guardrail)); ?>"><?php echo esc_html($tpl_label); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button" id="vms-ticketing-v2-apply-template-btn" disabled="disabled"><?php esc_html_e('Apply', 'backstage-venue-manager'); ?></button>
							<button type="button" class="button button-secondary" id="vms-ticketing-v2-set-default-template-btn" disabled="disabled"><?php esc_html_e('Set as default', 'backstage-venue-manager'); ?></button>
							<button type="button" class="button-link vms-ml-6" id="vms-ticketing-v2-clear-default-template-btn" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>><?php esc_html_e('Clear default', 'backstage-venue-manager'); ?></button>
							<span id="vms-ticketing-v2-default-template-label" class="description vms-ml-10"><?php echo esc_html($default_tpl_name ? ('Default: ' . $default_tpl_name) : 'Default: none'); ?></span>
								<button type="button" class="button button-secondary" id="vms-ticketing-v2-init-legacy-btn" disabled="disabled"><?php esc_html_e('Initialize from legacy add-ons (Retired)', 'backstage-venue-manager'); ?></button>
							<button type="button" class="button" id="vms-ticketing-v2-clear-config-btn" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>><?php esc_html_e('Clear config', 'backstage-venue-manager'); ?></button>
						</div>

						<div class="vms-ticketing__tplsave">
							<input type="text" id="vms-ticketing-v2-template-name" class="regular-text" placeholder="<?php esc_attr_e('New template name…', 'backstage-venue-manager'); ?>" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?> />
							<button type="button" class="button" id="vms-ticketing-v2-save-template-btn" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>><?php esc_html_e('Save current as template', 'backstage-venue-manager'); ?></button>
						</div>

						<div id="vms-ticketing-v2-config-note" class="vms-ticketing__msg vms-notice vms-notice--info vms-ticketing__msg--info vms-hidden" aria-live="polite"></div>
						<div id="vms-ticketing-v2-template-guardrail" class="vms-ticketing__guardrail vms-hidden" aria-live="polite"></div>
						<div id="vms-ticketing-v2-sales-end-warning" class="vms-ticketing__guardrail vms-hidden" aria-live="polite"></div>
						<p class="description"><?php esc_html_e('Templates update only this Event Plan’s saved Ticketing config. Nothing is created in TEC/Woo until Preview → Commit. Applying a template overwrites the saved config for this plan.', 'backstage-venue-manager'); ?></p>

						<div class="vms-ticketing__managed-mode">
							<strong><?php esc_html_e('Mode:', 'backstage-venue-manager'); ?></strong>
							<select id="vms-ticketing-v2-mode" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>>
								<option value="none" <?php selected($mode_v2, 'none'); ?>><?php esc_html_e('None', 'backstage-venue-manager'); ?></option>
								<option value="read_only" <?php selected($mode_v2, 'read_only'); ?>><?php esc_html_e('Read-only', 'backstage-venue-manager'); ?></option>
								<option value="vms_managed" <?php selected($mode_v2, 'vms_managed'); ?>><?php esc_html_e('VMS-managed', 'backstage-venue-manager'); ?></option>
							</select>
							<?php if ($last_commit_at) : ?>
								<span class="description vms-ml-8"><?php echo esc_html('Last commit: ' . $last_commit_at); ?></span>
							<?php endif; ?>
						</div>

						<?php
							$plan_image_id = function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($post->ID)) : 0;
							$plan_image_url = ($plan_image_id > 0 && function_exists('wp_get_attachment_image_url'))
								? (string) wp_get_attachment_image_url($plan_image_id, 'thumbnail')
								: '';
							if (function_exists('wp_enqueue_media')) {
								wp_enqueue_media();
							}
						?>

						<div id="vms-ticketing-v2-editor" data-initial-config="<?php echo esc_attr(wp_json_encode($cfg_v2)); ?>" data-initial-sync="<?php echo esc_attr(wp_json_encode($sync_v2)); ?>" data-tec-event-id="<?php echo (int) $linked_tec_id; ?>" data-plan-id="<?php echo (int) $post->ID; ?>" data-config-exists="<?php echo esc_attr($cfg_v2_exists); ?>" data-default-template-id="<?php echo esc_attr($default_tpl_id); ?>" data-default-template-name="<?php echo esc_attr($default_tpl_name); ?>" data-ticketing-effective="<?php echo $ticketing_effective ? 1 : 0; ?>" data-plan-image-id="<?php echo esc_attr((string) $plan_image_id); ?>" data-plan-image-url="<?php echo esc_attr($plan_image_url); ?>"></div>

						<p>
							<button type="button" class="button button-secondary" id="vms-ticketing-v2-save-config-btn"><?php esc_html_e('Save config', 'backstage-venue-manager'); ?></button>
							<button type="button" class="button" id="vms-ticketing-v2-preview-sync-btn" <?php echo $preview_disabled ? 'disabled="disabled"' : ''; ?>><?php esc_html_e('Preview sync', 'backstage-venue-manager'); ?></button>
							<button type="button" class="button button-primary" id="vms-ticketing-v2-commit-sync-btn" disabled="disabled"><?php esc_html_e('Commit sync', 'backstage-venue-manager'); ?></button>
						</p>

						<div id="vms-ticketing-v2-sync-preview" class="vms-ticketing__sync-preview vms-hidden"></div>
						<div id="vms-ticketing-v2-sync-msg" class="vms-ticketing__msg vms-notice" aria-live="polite"></div>
						<div id="vms-ticketing-v2-sync-details" class="vms-ticketing__sync-details vms-hidden" aria-live="polite"></div>

						</div>
							<?php endif; ?>

						<p class="description"><?php esc_html_e('Linking does not modify the calendar event. Use “Re-sync to Calendar” if you want Backstage Venue Manager to update it.', 'backstage-venue-manager'); ?></p>
            </div>

            <?php
                $k_sup = function_exists('bvmgr_meta_key')
                    ? (bvmgr_meta_key('event_plan', 'calendar_unpublished_suppress') ?: '_vms_calendar_unpublished_suppress')
                    : '_vms_calendar_unpublished_suppress';
                $sup_val = (string) get_post_meta($post->ID, $k_sup, true);
                $sup_checked = in_array($sup_val, array('1', 'yes', 'true'), true);
                $sup_nonce = wp_create_nonce('vms_event_plan_calendar_unpublished_suppress_save');
            ?>

            <div
                class="vms-calendar-unpublished-suppressor"
                data-vms-calendar-suppressor="1"
                data-post-id="<?php echo (int) $post->ID; ?>"
                data-save-nonce="<?php echo esc_attr($sup_nonce); ?>"
                data-current="<?php echo $sup_checked ? '1' : '0'; ?>"
            >
                <label>
                    <input type="checkbox" id="vms-calendar-unpublished-suppress" value="1" <?php checked($sup_checked); ?> />
                    <strong><?php esc_html_e('Allow linked calendar event to remain unpublished', 'backstage-venue-manager'); ?></strong>
                    <?php
                        if (function_exists('bvmgr_help_icon')) {
                            bvmgr_help_icon(__('When enabled, this Event Plan will not show “calendar event is not published” as Needs attention. Missing or trashed calendar links are still flagged.', 'backstage-venue-manager'));
                        }
                    ?>
                </label>
                <button type="button" class="button button-secondary vms-ml-10" id="vms-calendar-unpublished-suppress-save" disabled="disabled"><?php esc_html_e('Save warning setting', 'backstage-venue-manager'); ?></button>
                <span id="vms-calendar-unpublished-suppress-status" class="description vms-ml-10" aria-live="polite"></span>
            </div>

            <p class="description"><?php esc_html_e('This only suppresses the “calendar event is not published” warning for this Event Plan. Missing or trashed calendar links are still flagged.', 'backstage-venue-manager'); ?></p>
            <p class="description"><?php esc_html_e('This setting now saves separately and does not update any other Event Plan fields.', 'backstage-venue-manager'); ?></p>

            <p class="description"><?php esc_html_e('“Re-sync to Calendar” updates the existing calendar event and will not create a duplicate.', 'backstage-venue-manager'); ?></p>

            <?php endif; ?>

	        </div>
	    </details>
