        <?php
            defined('ABSPATH') || exit;
            $lineup_summary_trace = function_exists('vms_event_plan_perf_span_start')
                ? vms_event_plan_perf_span_start('event_plan_time_lineup_summary_render', (int) $post->ID, array('section' => 'time_lineup_summary'))
                : '';
        ?>
        <h4 class="vms-ep-basic-span"><?php esc_html_e('Time + Lineup & Schedule', 'vms'); ?></h4>

        <div class="vms-ep-basic-item">
            <label for="vms_start_time"><strong><?php esc_html_e('Event Start / End', 'vms'); ?></strong></label><br />
            <div class="vms-ep-time-row">
                <select id="vms_start_time" name="vms_start_time" class="vms-ep-time-select">
                    <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                        <option value="<?php echo esc_attr($time_value); ?>" <?php selected($start_time_current, (string) $time_value); ?>>
                            <?php echo esc_html((string) $time_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="vms-ep-time-sep"><?php esc_html_e('to', 'vms'); ?></span>
                <select id="vms_end_time" name="vms_end_time" class="vms-ep-time-select">
                    <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                        <option value="<?php echo esc_attr($time_value); ?>" <?php selected($end_time_current, (string) $time_value); ?>>
                            <?php echo esc_html((string) $time_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span class="description"><?php esc_html_e('Event-level bounds stay operator-controlled. Lineup times below are checked against these bounds and warnings are shown instead of silently rewriting anything.', 'vms'); ?></span>
        </div>

        <div class="vms-ep-basic-item vms-ep-basic-span">
            <input type="hidden" name="vms_lineup_present" value="1" />
            <div
                class="vms-lineup-section"
                id="vms-lineup-schedule-section"
                data-lineup-storage-scope="<?php echo esc_attr((string) $post->ID); ?>"
                data-lineup-post-id="<?php echo esc_attr((string) $post->ID); ?>"
                data-lineup-vendor-options-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                data-lineup-vendor-options-nonce="<?php echo esc_attr(wp_create_nonce('vms_event_plan_admin_section')); ?>"
            >
                <div class="vms-lineup-summary">
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Primary vendor', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-primary"><?php echo esc_html($lineup_primary_vendor_label); ?></strong>
                    </div>
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Supporting vendors', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-supporting"><?php echo esc_html((string) ((int) ($lineup_summary['supporting_count'] ?? count($lineup_supporting_entries)))); ?></strong>
                    </div>
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Earliest start', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-earliest"><?php echo esc_html((string) ($lineup_summary['earliest_start'] ?? '')); ?></strong>
                    </div>
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Primary start', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-primary-start"><?php echo esc_html((string) ($lineup_summary['primary_start'] ?? $lineup_primary_time_label)); ?></strong>
                    </div>
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Total runtime', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-runtime"><?php echo esc_html((string) ($lineup_summary['total_runtime_label'] ?? '')); ?></strong>
                    </div>
                    <div class="vms-lineup-summary__item">
                        <span class="vms-lineup-summary__label"><?php esc_html_e('Warnings', 'vms'); ?></span>
                        <strong class="vms-lineup-summary__value" id="vms-lineup-summary-warnings"><?php echo esc_html((string) ((int) ($lineup_summary['warning_count'] ?? count($lineup_warning_messages)))); ?></strong>
                    </div>
                </div>
                <?php
                    if (function_exists('vms_event_plan_perf_span_finish')) {
                        vms_event_plan_perf_span_finish('event_plan_time_lineup_summary_render', (int) $post->ID, $lineup_summary_trace, array(
                            'section' => 'time_lineup_summary',
                            'supporting_vendor_count' => is_array($lineup_supporting_entries) ? count($lineup_supporting_entries) : 0,
                            'warning_count' => is_array($lineup_warning_messages) ? count($lineup_warning_messages) : 0,
                        ));
                    }

                    $primary_vendor_trace = function_exists('vms_event_plan_perf_span_start')
                        ? vms_event_plan_perf_span_start('event_plan_primary_vendor_editor_render', (int) $post->ID, array('section' => 'primary_vendor_editor'))
                        : '';
                ?>

                <div class="vms-lineup-actions">
                    <button type="button" class="button button-secondary" id="vms-lineup-add-supporting"><?php esc_html_e('Add Supporting Vendor', 'vms'); ?></button>
                    <button type="button" class="button button-secondary" id="vms-lineup-expand-all"><?php esc_html_e('Expand All', 'vms'); ?></button>
                    <button type="button" class="button button-secondary" id="vms-lineup-collapse-all"><?php esc_html_e('Collapse All', 'vms'); ?></button>
                </div>

                <div class="vms-lineup-editor">
                    <details class="vms-lineup-row vms-lineup-row--primary" data-lineup-primary open>
                        <summary class="vms-lineup-row__summary">
                            <span class="vms-lineup-row__summary-main">
                                <span class="vms-lineup-row__title-wrap">
                                    <span class="vms-lineup-row__eyebrow"><?php esc_html_e('Primary Vendor', 'vms'); ?></span>
                                    <span class="vms-lineup-row__title" id="vms-lineup-primary-summary-title"><?php echo esc_html($lineup_primary_vendor_label); ?></span>
                                </span>
                            </span>
                            <span class="vms-lineup-row__summary-meta">
                                <span class="vms-lineup-row__pill vms-lineup-row__pill--primary"><?php esc_html_e('Primary', 'vms'); ?></span>
                                <span class="vms-lineup-row__meta vms-lineup-row__meta--time" id="vms-lineup-primary-summary-time"><?php echo esc_html($lineup_primary_time_label); ?></span>
                                <span class="vms-lineup-row__meta vms-lineup-row__meta--duration" id="vms-lineup-primary-summary-duration"><?php echo esc_html($lineup_primary_duration_label); ?></span>
                                <span class="vms-lineup-row__meta vms-lineup-row__meta--downtime" id="vms-lineup-primary-summary-downtime"><?php echo esc_html((string) ($lineup_primary_entry['downtime_before_label'] ?? '')); ?></span>
                                <span class="vms-lineup-row__meta vms-lineup-row__meta--fee" id="vms-lineup-primary-summary-pay"><?php echo esc_html($lineup_primary_pay_summary); ?></span>
                                <span class="vms-lineup-row__warning<?php echo ((int) $lineup_primary_warning_count) > 0 ? '' : ' is-clear'; ?>" id="vms-lineup-primary-summary-warning"><?php echo esc_html((string) $lineup_primary_warning_count); ?></span>
                                <span class="vms-lineup-row__toggle" aria-hidden="true"></span>
                            </span>
                        </summary>
	                        <div class="vms-lineup-row__body vms-lineup-row__body--primary">
	                            <input type="hidden" name="vms_lineup_entries[primary][row_id]" value="<?php echo esc_attr((string) ($lineup_primary_entry['row_id'] ?? '')); ?>" />
	                            <input type="hidden" name="vms_lineup_entries[primary][role]" value="primary" />
	                            <input type="hidden" name="vms_lineup_entries[primary][sort_order]" value="<?php echo esc_attr((string) (count($lineup_supporting_entries))); ?>" data-lineup-primary-sort-order />
	                            <input type="hidden" name="vms_lineup_entries[primary][vendor_id]" value="<?php echo esc_attr((string) $lineup_primary_vendor_id); ?>" id="vms-lineup-primary-vendor-id" />
	                            <input type="hidden" name="vms_clear_primary_vendor" value="0" id="vms-clear-primary-vendor-intent" />
	                            <input type="hidden" name="vms_clear_lineup_primary_vendor" value="0" id="vms-clear-lineup-primary-vendor-intent" />

	                            <div class="vms-lineup-row__fields vms-lineup-row__fields--primary">
	                                <p class="vms-lineup-field vms-lineup-field--vendor">
	                                    <label class="vms-lineup-field__label" for="vms_band_vendor_id"><strong><?php esc_html_e('Primary Vendor', 'vms'); ?></strong></label>
                                    <select id="vms_band_vendor_id" name="vms_band_vendor_id" class="vms-ep-select-md" data-lineup-primary-vendor-select>
                                        <?php $render_primary_vendor_select_options($selected_band_id); ?>
                                    </select>
                                    <?php
                                    $add_primary_vendor_url = add_query_arg(array(
                                        'post_type' => 'vms_vendor',
                                        'vms_return_to_event_plan' => (int) $post->ID,
                                        'vms_prefill_vendor_role' => 'primary',
                                    ), admin_url('post-new.php'));
                                    ?>
	                                    <span class="vms-lineup-field__actions vms-ep-vendor-actions">
	                                        <a class="button button-secondary button-small" href="<?php echo esc_url($add_primary_vendor_url); ?>" target="_blank" rel="noopener">
	                                            <?php esc_html_e('Add new vendor', 'vms'); ?>
	                                        </a>
	                                        <button type="button" class="button button-secondary button-small" id="vms-clear-primary-vendor-button">
	                                            <?php esc_html_e('Clear primary vendor', 'vms'); ?>
	                                        </button>
	                                        <?php if ($selected_band_id > 0): ?>
	                                            <?php $edit_vendor_url = admin_url('post.php?post=' . $selected_band_id . '&action=edit'); ?>
	                                            <a class="button button-secondary button-small" href="<?php echo esc_url($edit_vendor_url); ?>">
	                                                <?php esc_html_e('Edit vendor profile', 'vms'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </p>
                                <p class="vms-lineup-field vms-lineup-field--name">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Public name override', 'vms'); ?></strong></label>
                                    <input type="text" name="vms_lineup_entries[primary][public_name_override]" value="<?php echo esc_attr((string) ($lineup_primary_entry['public_name_override'] ?? '')); ?>" class="regular-text" />
                                </p>
                                <p class="vms-lineup-field vms-lineup-field--time">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set start', 'vms'); ?></strong></label>
                                    <select name="vms_lineup_entries[primary][set_start]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-primary-start>
                                        <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                        <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                            <option value="<?php echo esc_attr($time_value); ?>" <?php selected((string) ($lineup_primary_entry['set_start'] ?? ''), (string) $time_value); ?>><?php echo esc_html((string) $time_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p class="vms-lineup-field vms-lineup-field--time">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set end', 'vms'); ?></strong></label>
                                    <select name="vms_lineup_entries[primary][set_end]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-primary-end>
                                        <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                        <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                            <option value="<?php echo esc_attr($time_value); ?>" <?php selected((string) ($lineup_primary_entry['set_end'] ?? ''), (string) $time_value); ?>><?php echo esc_html((string) $time_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <div class="vms-lineup-field vms-lineup-field--flags">
                                    <span class="vms-lineup-field__label"><strong><?php esc_html_e('Visibility', 'vms'); ?></strong></span>
                                    <label><input type="checkbox" name="vms_lineup_entries[primary][show_public]" value="1" <?php checked((string) ($lineup_primary_entry['show_public'] ?? '1'), '1'); ?> /> <?php esc_html_e('Show publicly', 'vms'); ?></label>
                                    <label><input type="checkbox" name="vms_lineup_entries[primary][show_portal]" value="1" <?php checked((string) ($lineup_primary_entry['show_portal'] ?? '1'), '1'); ?> /> <?php esc_html_e('Show in portal', 'vms'); ?></label>
                                </div>
                                <div class="vms-lineup-field vms-lineup-field--status">
                                    <span class="vms-lineup-field__label"><strong><?php esc_html_e('Status', 'vms'); ?></strong></span>
                                    <div class="vms-lineup-status">
                                        <div class="vms-lineup-status__item">
                                            <span class="vms-lineup-status__label"><?php esc_html_e('Fee summary', 'vms'); ?></span>
                                            <strong><?php echo esc_html($lineup_primary_pay_summary); ?></strong>
                                        </div>
                                        <div class="vms-lineup-status__item">
                                            <span class="vms-lineup-status__label"><?php esc_html_e('Duration', 'vms'); ?></span>
                                            <strong id="vms-lineup-primary-derived-duration"><?php echo esc_html((string) ($lineup_primary_entry['duration_label'] ?? '')); ?></strong>
                                        </div>
                                        <div class="vms-lineup-status__item">
                                            <span class="vms-lineup-status__label"><?php esc_html_e('Downtime', 'vms'); ?></span>
                                            <strong id="vms-lineup-primary-derived-downtime"><?php echo esc_html((string) ($lineup_primary_entry['downtime_before_label'] ?? '')); ?></strong>
                                        </div>
                                        <div class="vms-lineup-status__item">
                                            <span class="vms-lineup-status__label"><?php esc_html_e('Warnings', 'vms'); ?></span>
                                            <strong class="vms-lineup-status__value<?php echo ((int) $lineup_primary_warning_count) > 0 ? '' : ' is-clear'; ?>" id="vms-lineup-primary-derived-warning"><?php echo esc_html((string) $lineup_primary_warning_count); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="vms-lineup-row__notes">
                                <p class="vms-lineup-field vms-lineup-field--notes">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Schedule notes', 'vms'); ?></strong></label>
                                    <textarea name="vms_lineup_entries[primary][schedule_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_primary_entry['schedule_notes'] ?? '')); ?></textarea>
                                </p>
                                <p class="vms-lineup-field vms-lineup-field--notes">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Pay notes', 'vms'); ?></strong></label>
                                    <textarea name="vms_lineup_entries[primary][pay_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_primary_entry['pay_notes'] ?? '')); ?></textarea>
                                </p>
                                <p class="vms-lineup-field vms-lineup-field--notes">
                                    <label class="vms-lineup-field__label"><strong><?php esc_html_e('Internal notes', 'vms'); ?></strong></label>
                                    <textarea name="vms_lineup_entries[primary][internal_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_primary_entry['internal_notes'] ?? '')); ?></textarea>
                                </p>
                            </div>

                            <div class="vms-lineup-row__aux">
                                <?php if ($event_date): ?>
                                    <?php $ts = strtotime($event_date);
                                    $nice = $ts ? date_i18n('M j, Y', $ts) : $event_date; ?>
                                    <p class="description vms-lineup-row__aux-copy">
                                        <?php
                                        printf(
                                            esc_html__('Availability for %s: [✓] Available, [✖] Not Available, [?] Unknown', 'vms'),
                                            esc_html($nice)
                                        );
                                        ?>
                                    </p>
                                    <div id="vms-tax-status"></div>
                                <?php else: ?>
                                    <p class="description vms-lineup-row__aux-copy"><?php esc_html_e('Set the Event Date to see vendor availability hints here.', 'vms'); ?></p>
                                    <div id="vms-tax-status"></div>
                                <?php endif; ?>

                                <?php
                                    $tax_bypass_default_until = function_exists('wp_date')
                                        ? wp_date('Y-m-d', strtotime('+30 days'), wp_timezone())
                                        : date('Y-m-d', strtotime('+30 days'));
                                ?>
                                <div id="vms-tax-bypass-inline"
                                     class="vms-tax-bypass-inline"
                                     data-nonce="<?php echo esc_attr(wp_create_nonce('vms_tax_bypass_ajax')); ?>"
                                     data-default-until="<?php echo esc_attr($tax_bypass_default_until); ?>">
                                    <p class="description vms-mt-8">
                                        <?php esc_html_e('Tax bypass (temporary): set an expiration + reason for the selected vendor without leaving this page.', 'vms'); ?>
                                    </p>
                                    <div class="vms-tax-bypass-inline__controls">
                                        <span id="vms-tax-bypass-active-flag" class="vms-tax-bypass-inline__flag vms-hidden"><?php esc_html_e('Active bypass', 'vms'); ?></span>
                                        <label for="vms-tax-bypass-until"><strong><?php esc_html_e('Until', 'vms'); ?></strong></label>
                                        <input type="date" id="vms-tax-bypass-until" class="vms-tax-bypass-inline__until" />

                                        <label for="vms-tax-bypass-reason"><strong><?php esc_html_e('Reason', 'vms'); ?></strong></label>
                                        <input type="text" id="vms-tax-bypass-reason" class="regular-text vms-tax-bypass-inline__reason" placeholder="<?php esc_attr_e('Required reason', 'vms'); ?>" />

                                        <button type="button" class="button button-secondary" id="vms-tax-bypass-set"><?php esc_html_e('Apply bypass', 'vms'); ?></button>
                                        <button type="button" class="button" id="vms-tax-bypass-clear"><?php esc_html_e('Clear bypass', 'vms'); ?></button>
                                    </div>
                                    <div id="vms-tax-bypass-msg" class="description vms-mt-6" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>
                    </details>
                    <?php
                        if (function_exists('vms_event_plan_perf_span_finish')) {
                            vms_event_plan_perf_span_finish('event_plan_primary_vendor_editor_render', (int) $post->ID, $primary_vendor_trace, array(
                                'section' => 'primary_vendor_editor',
                                'selected_vendor_id' => (int) $selected_band_id,
                            ));
                        }

                        if (!empty($lineup_supporting_vendor_option_html)) :
                    ?>
                        <template id="vms-lineup-supporting-vendor-options-template">
                            <?php echo $lineup_supporting_vendor_option_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </template>
                    <?php
                        endif;

                        $supporting_cards_trace = function_exists('vms_event_plan_perf_span_start')
                            ? vms_event_plan_perf_span_start('event_plan_supporting_act_card_render', (int) $post->ID, array(
                                'section' => 'supporting_act_cards',
                                'supporting_card_count' => is_array($lineup_supporting_entries) ? count($lineup_supporting_entries) : 0,
                            ))
                            : '';
                    ?>

                    <div class="vms-lineup-rows" id="vms-lineup-supporting-rows">
                        <?php foreach ($lineup_supporting_entries as $lineup_support_index => $lineup_support_entry) : ?>
                            <?php
                                $lineup_support_vendor_id = (int) ($lineup_support_entry['vendor_id'] ?? 0);
                                $lineup_support_name = trim((string) ($lineup_support_entry['display_name'] ?? ($lineup_support_entry['vendor_title'] ?? '')));
                                if ($lineup_support_name === '') {
                                    $lineup_support_name = __('Unassigned supporting vendor', 'vms');
                                }
                                $lineup_support_time = trim(implode(' – ', array_filter(array(
                                    (string) ($lineup_support_entry['set_start_label'] ?? ''),
                                    (string) ($lineup_support_entry['set_end_label'] ?? ''),
                                ))));
                                $lineup_support_fee = $lineup_support_entry['guaranteed_fee'] ?? '';
                                $lineup_support_fee_auto = false;
                                if (($lineup_support_fee === '' || $lineup_support_fee === null) && $lineup_support_vendor_id > 0) {
                                    $lineup_support_default_fee = (string) ($lineup_supporting_default_fee_map[$lineup_support_vendor_id] ?? '');
                                    if ($lineup_support_default_fee !== '') {
                                        $lineup_support_fee = $lineup_support_default_fee;
                                        $lineup_support_fee_auto = true;
                                    }
                                }
                                $lineup_support_fee_label = ($lineup_support_fee !== '' && is_numeric($lineup_support_fee))
                                    ? (function_exists('vms_format_currency') ? vms_format_currency((float) $lineup_support_fee) : ('$' . number_format((float) $lineup_support_fee, 2)))
                                    : __('No fee set', 'vms');
                                $lineup_support_warning_count = (int) ($lineup_support_entry['warning_count'] ?? 0);
                            ?>
                            <details class="vms-lineup-row vms-lineup-row--supporting" data-lineup-row data-lineup-role="supporting" draggable="true" open>
                                <summary class="vms-lineup-row__summary">
                                    <span class="vms-lineup-row__summary-main">
                                        <span class="vms-lineup-row__handle" title="<?php esc_attr_e('Drag to reorder', 'vms'); ?>">↕</span>
                                        <span class="vms-lineup-row__title-wrap">
                                            <span class="vms-lineup-row__eyebrow"><?php esc_html_e('Supporting Vendor', 'vms'); ?></span>
                                            <span class="vms-lineup-row__title" data-lineup-summary-title><?php echo esc_html($lineup_support_name); ?></span>
                                        </span>
                                    </span>
                                    <span class="vms-lineup-row__summary-meta">
                                        <span class="vms-lineup-row__pill"><?php esc_html_e('Supporting', 'vms'); ?></span>
                                        <span class="vms-lineup-row__meta vms-lineup-row__meta--time" data-lineup-summary-time><?php echo esc_html($lineup_support_time); ?></span>
                                        <span class="vms-lineup-row__meta vms-lineup-row__meta--duration" data-lineup-summary-duration><?php echo esc_html((string) ($lineup_support_entry['duration_label'] ?? '')); ?></span>
                                        <span class="vms-lineup-row__meta vms-lineup-row__meta--downtime" data-lineup-summary-downtime><?php echo esc_html((string) ($lineup_support_entry['downtime_before_label'] ?? '')); ?></span>
                                        <span class="vms-lineup-row__meta vms-lineup-row__meta--fee" data-lineup-summary-fee><?php echo esc_html($lineup_support_fee_label); ?></span>
                                        <span class="vms-lineup-row__warning<?php echo $lineup_support_warning_count > 0 ? '' : ' is-clear'; ?>" data-lineup-summary-warning><?php echo esc_html((string) $lineup_support_warning_count); ?></span>
                                        <span class="vms-lineup-row__toggle" aria-hidden="true"></span>
                                    </span>
                                </summary>
                                <div class="vms-lineup-row__body">
                                    <input type="hidden" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][row_id]" value="<?php echo esc_attr((string) ($lineup_support_entry['row_id'] ?? '')); ?>" data-lineup-row-id />
                                    <input type="hidden" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][role]" value="supporting" />
                                    <input type="hidden" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][sort_order]" value="<?php echo esc_attr((string) ($lineup_support_entry['sort_order'] ?? $lineup_support_index)); ?>" data-lineup-sort-order />

                                    <div class="vms-lineup-row__fields">
                                        <p class="vms-lineup-field vms-lineup-field--vendor">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Primary Vendor', 'vms'); ?></strong></label>
                                            <select name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][vendor_id]" class="vms-ep-select-md vms-lineup-vendor-select" data-lineup-vendor-select>
                                                <?php $render_lineup_vendor_select_options($lineup_support_vendor_id); ?>
                                            </select>
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--name">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Public name override', 'vms'); ?></strong></label>
                                            <input type="text" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][public_name_override]" value="<?php echo esc_attr((string) ($lineup_support_entry['public_name_override'] ?? '')); ?>" class="regular-text" />
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--time">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set start', 'vms'); ?></strong></label>
                                            <select name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][set_start]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-start>
                                                <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                                <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                                    <option value="<?php echo esc_attr($time_value); ?>" <?php selected((string) ($lineup_support_entry['set_start'] ?? ''), (string) $time_value); ?>><?php echo esc_html((string) $time_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--time">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set end', 'vms'); ?></strong></label>
                                            <select name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][set_end]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-end>
                                                <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                                <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                                    <option value="<?php echo esc_attr($time_value); ?>" <?php selected((string) ($lineup_support_entry['set_end'] ?? ''), (string) $time_value); ?>><?php echo esc_html((string) $time_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--fee">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Compensation (Guaranteed fee)', 'vms'); ?></strong></label>
                                            <input type="text" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][guaranteed_fee]" value="<?php echo esc_attr((string) $lineup_support_fee); ?>" class="regular-text" data-lineup-fee data-lineup-fee-auto="<?php echo $lineup_support_fee_auto ? '1' : '0'; ?>" />
                                            <span class="description"><?php esc_html_e('Auto-fills from the vendor default when available. Change it here anytime for this event.', 'vms'); ?></span>
                                        </p>
                                        <div class="vms-lineup-field vms-lineup-field--flags">
                                            <span class="vms-lineup-field__label"><strong><?php esc_html_e('Visibility', 'vms'); ?></strong></span>
                                            <label><input type="checkbox" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][show_public]" value="1" <?php checked((string) ($lineup_support_entry['show_public'] ?? ''), '1'); ?> /> <?php esc_html_e('Show publicly', 'vms'); ?></label>
                                            <label><input type="checkbox" name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][show_portal]" value="1" <?php checked((string) ($lineup_support_entry['show_portal'] ?? ''), '1'); ?> /> <?php esc_html_e('Show in portal', 'vms'); ?></label>
                                        </div>
                                        <div class="vms-lineup-field vms-lineup-field--status">
                                            <span class="vms-lineup-field__label"><strong><?php esc_html_e('Status', 'vms'); ?></strong></span>
                                            <div class="vms-lineup-status">
                                                <div class="vms-lineup-status__item">
                                                    <span class="vms-lineup-status__label"><?php esc_html_e('Duration', 'vms'); ?></span>
                                                    <strong data-lineup-derived-duration><?php echo esc_html((string) ($lineup_support_entry['duration_label'] ?? '')); ?></strong>
                                                </div>
                                                <div class="vms-lineup-status__item">
                                                    <span class="vms-lineup-status__label"><?php esc_html_e('Downtime', 'vms'); ?></span>
                                                    <strong data-lineup-derived-downtime><?php echo esc_html((string) ($lineup_support_entry['downtime_before_label'] ?? '')); ?></strong>
                                                </div>
                                                <div class="vms-lineup-status__item">
                                                    <span class="vms-lineup-status__label"><?php esc_html_e('Warnings', 'vms'); ?></span>
                                                    <strong class="vms-lineup-status__value<?php echo $lineup_support_warning_count > 0 ? '' : ' is-clear'; ?>" data-lineup-derived-warning><?php echo esc_html((string) $lineup_support_warning_count); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="vms-lineup-row__notes">
                                        <p class="vms-lineup-field vms-lineup-field--notes">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Schedule notes', 'vms'); ?></strong></label>
                                            <textarea name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][schedule_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_support_entry['schedule_notes'] ?? '')); ?></textarea>
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--notes">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Pay notes', 'vms'); ?></strong></label>
                                            <textarea name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][pay_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_support_entry['pay_notes'] ?? '')); ?></textarea>
                                        </p>
                                        <p class="vms-lineup-field vms-lineup-field--notes">
                                            <label class="vms-lineup-field__label"><strong><?php esc_html_e('Internal notes', 'vms'); ?></strong></label>
                                            <textarea name="vms_lineup_entries[<?php echo esc_attr((string) $lineup_support_index); ?>][internal_notes]" rows="2" class="large-text"><?php echo esc_textarea((string) ($lineup_support_entry['internal_notes'] ?? '')); ?></textarea>
                                        </p>
                                    </div>

                                    <div class="vms-lineup-row__footer">
                                        <button type="button" class="button button-secondary vms-lineup-remove"><?php esc_html_e('Remove entry', 'vms'); ?></button>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                    <?php
                        if (function_exists('vms_event_plan_perf_span_finish')) {
                            vms_event_plan_perf_span_finish('event_plan_supporting_act_card_render', (int) $post->ID, $supporting_cards_trace, array(
                                'section' => 'supporting_act_cards',
                                'supporting_card_count' => is_array($lineup_supporting_entries) ? count($lineup_supporting_entries) : 0,
                                'supporting_detail_body_count' => is_array($lineup_supporting_entries) ? count($lineup_supporting_entries) : 0,
                            ));
                        }
                    ?>

                    <template id="vms-lineup-supporting-template">
                        <details class="vms-lineup-row vms-lineup-row--supporting" data-lineup-row data-lineup-role="supporting" draggable="true" open>
                            <summary class="vms-lineup-row__summary">
                                <span class="vms-lineup-row__summary-main">
                                    <span class="vms-lineup-row__handle" title="<?php esc_attr_e('Drag to reorder', 'vms'); ?>">↕</span>
                                    <span class="vms-lineup-row__title-wrap">
                                        <span class="vms-lineup-row__eyebrow"><?php esc_html_e('Supporting Vendor', 'vms'); ?></span>
                                        <span class="vms-lineup-row__title" data-lineup-summary-title><?php esc_html_e('Unassigned supporting vendor', 'vms'); ?></span>
                                    </span>
                                </span>
                                <span class="vms-lineup-row__summary-meta">
                                    <span class="vms-lineup-row__pill"><?php esc_html_e('Supporting', 'vms'); ?></span>
                                    <span class="vms-lineup-row__meta vms-lineup-row__meta--time" data-lineup-summary-time></span>
                                    <span class="vms-lineup-row__meta vms-lineup-row__meta--duration" data-lineup-summary-duration></span>
                                    <span class="vms-lineup-row__meta vms-lineup-row__meta--downtime" data-lineup-summary-downtime></span>
                                    <span class="vms-lineup-row__meta vms-lineup-row__meta--fee" data-lineup-summary-fee><?php esc_html_e('No fee set', 'vms'); ?></span>
                                    <span class="vms-lineup-row__warning is-clear" data-lineup-summary-warning>0</span>
                                    <span class="vms-lineup-row__toggle" aria-hidden="true"></span>
                                </span>
                            </summary>
                            <div class="vms-lineup-row__body">
                                <input type="hidden" name="vms_lineup_entries[__INDEX__][row_id]" value="" data-lineup-row-id />
                                <input type="hidden" name="vms_lineup_entries[__INDEX__][role]" value="supporting" />
                                <input type="hidden" name="vms_lineup_entries[__INDEX__][sort_order]" value="0" data-lineup-sort-order />
                                <div class="vms-lineup-row__fields">
                                    <p class="vms-lineup-field vms-lineup-field--vendor">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Primary Vendor', 'vms'); ?></strong></label>
                                        <select name="vms_lineup_entries[__INDEX__][vendor_id]" class="vms-ep-select-md vms-lineup-vendor-select" data-lineup-vendor-select>
                                            <?php $render_lineup_vendor_select_options(0); ?>
                                        </select>
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--name">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Public name override', 'vms'); ?></strong></label>
                                        <input type="text" name="vms_lineup_entries[__INDEX__][public_name_override]" value="" class="regular-text" />
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--time">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set start', 'vms'); ?></strong></label>
                                        <select name="vms_lineup_entries[__INDEX__][set_start]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-start>
                                            <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                            <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                                <option value="<?php echo esc_attr($time_value); ?>"><?php echo esc_html((string) $time_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--time">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Set end', 'vms'); ?></strong></label>
                                        <select name="vms_lineup_entries[__INDEX__][set_end]" class="vms-ep-time-select vms-lineup-time-select" data-lineup-end>
                                            <option value=""><?php esc_html_e('-- Select --', 'vms'); ?></option>
                                            <?php foreach ($vms_time_options as $time_value => $time_label) : ?>
                                                <option value="<?php echo esc_attr($time_value); ?>"><?php echo esc_html((string) $time_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--fee">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Compensation (Guaranteed fee)', 'vms'); ?></strong></label>
                                        <input type="text" name="vms_lineup_entries[__INDEX__][guaranteed_fee]" value="" class="regular-text" data-lineup-fee data-lineup-fee-auto="0" />
                                        <span class="description"><?php esc_html_e('Auto-fills from the vendor default when available. Change it here anytime for this event.', 'vms'); ?></span>
                                    </p>
                                    <div class="vms-lineup-field vms-lineup-field--flags">
                                        <span class="vms-lineup-field__label"><strong><?php esc_html_e('Visibility', 'vms'); ?></strong></span>
                                        <label><input type="checkbox" name="vms_lineup_entries[__INDEX__][show_public]" value="1" /> <?php esc_html_e('Show publicly', 'vms'); ?></label>
                                        <label><input type="checkbox" name="vms_lineup_entries[__INDEX__][show_portal]" value="1" /> <?php esc_html_e('Show in portal', 'vms'); ?></label>
                                    </div>
                                    <div class="vms-lineup-field vms-lineup-field--status">
                                        <span class="vms-lineup-field__label"><strong><?php esc_html_e('Status', 'vms'); ?></strong></span>
                                        <div class="vms-lineup-status">
                                            <div class="vms-lineup-status__item">
                                                <span class="vms-lineup-status__label"><?php esc_html_e('Duration', 'vms'); ?></span>
                                                <strong data-lineup-derived-duration></strong>
                                            </div>
                                            <div class="vms-lineup-status__item">
                                                <span class="vms-lineup-status__label"><?php esc_html_e('Downtime', 'vms'); ?></span>
                                                <strong data-lineup-derived-downtime></strong>
                                            </div>
                                            <div class="vms-lineup-status__item">
                                                <span class="vms-lineup-status__label"><?php esc_html_e('Warnings', 'vms'); ?></span>
                                                <strong class="vms-lineup-status__value is-clear" data-lineup-derived-warning>0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="vms-lineup-row__notes">
                                    <p class="vms-lineup-field vms-lineup-field--notes">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Schedule notes', 'vms'); ?></strong></label>
                                        <textarea name="vms_lineup_entries[__INDEX__][schedule_notes]" rows="2" class="large-text"></textarea>
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--notes">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Pay notes', 'vms'); ?></strong></label>
                                        <textarea name="vms_lineup_entries[__INDEX__][pay_notes]" rows="2" class="large-text"></textarea>
                                    </p>
                                    <p class="vms-lineup-field vms-lineup-field--notes">
                                        <label class="vms-lineup-field__label"><strong><?php esc_html_e('Internal notes', 'vms'); ?></strong></label>
                                        <textarea name="vms_lineup_entries[__INDEX__][internal_notes]" rows="2" class="large-text"></textarea>
                                    </p>
                                </div>
                                <div class="vms-lineup-row__footer">
                                    <button type="button" class="button button-secondary vms-lineup-remove"><?php esc_html_e('Remove entry', 'vms'); ?></button>
                                </div>
                            </div>
                        </details>
                    </template>
                </div>

                <?php
                    $timeline_trace = function_exists('vms_event_plan_perf_span_start')
                        ? vms_event_plan_perf_span_start('event_plan_time_lineup_timeline_render', (int) $post->ID, array('section' => 'time_lineup_timeline'))
                        : '';
                ?>
                <div class="vms-lineup-insights">
                    <div class="vms-lineup-timeline">
                        <div class="vms-lineup-timeline__heading">
                            <strong><?php esc_html_e('Timeline preview', 'vms'); ?></strong>
                        </div>
                        <div id="vms-lineup-timeline-list" class="vms-lineup-timeline__list">
                            <?php foreach ($lineup_entries as $timeline_entry) : ?>
                                <?php if (!empty($timeline_entry['downtime_before_label'])) : ?>
                                    <div class="vms-lineup-timeline__gap">
                                        <span><?php esc_html_e('Changeover / gap', 'vms'); ?></span>
                                        <strong><?php echo esc_html((string) $timeline_entry['downtime_before_label']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="vms-lineup-timeline__entry <?php echo (sanitize_key((string) ($timeline_entry['role'] ?? '')) === 'primary') ? 'is-primary' : ''; ?>">
                                    <span class="vms-lineup-timeline__name"><?php echo esc_html((string) ($timeline_entry['display_name'] ?? __('Lineup entry', 'vms'))); ?></span>
                                    <span class="vms-lineup-timeline__time"><?php echo esc_html(trim(implode(' – ', array_filter(array((string) ($timeline_entry['set_start_label'] ?? ''), (string) ($timeline_entry['set_end_label'] ?? '')))))); ?></span>
                                    <span class="vms-lineup-timeline__duration"><?php echo esc_html((string) ($timeline_entry['duration_label'] ?? '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                        if (function_exists('vms_event_plan_perf_span_finish')) {
                            vms_event_plan_perf_span_finish('event_plan_time_lineup_timeline_render', (int) $post->ID, $timeline_trace, array(
                                'section' => 'time_lineup_timeline',
                                'timeline_entry_count' => is_array($lineup_entries) ? count($lineup_entries) : 0,
                            ));
                        }

                        $health_trace = function_exists('vms_event_plan_perf_span_start')
                            ? vms_event_plan_perf_span_start('event_plan_time_lineup_health_render', (int) $post->ID, array('section' => 'time_lineup_health'))
                            : '';
                    ?>

                    <div class="vms-lineup-health">
                        <div class="vms-lineup-health__heading">
                            <strong><?php esc_html_e('Schedule Health', 'vms'); ?></strong>
                        </div>
                        <ul id="vms-lineup-health-list" class="vms-lineup-health__list">
                            <?php if (!empty($lineup_warning_messages)) : ?>
                                <?php foreach ($lineup_warning_messages as $lineup_warning_message) : ?>
                                    <li><?php echo esc_html((string) $lineup_warning_message); ?></li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <li><?php esc_html_e('No lineup warnings right now.', 'vms'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php
                        if (function_exists('vms_event_plan_perf_span_finish')) {
                            vms_event_plan_perf_span_finish('event_plan_time_lineup_health_render', (int) $post->ID, $health_trace, array(
                                'section' => 'time_lineup_health',
                                'warning_count' => is_array($lineup_warning_messages) ? count($lineup_warning_messages) : 0,
                            ));
                        }
                    ?>
                </div>
            </div>
        </div>
