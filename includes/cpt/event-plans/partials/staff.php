    <?php defined('ABSPATH') || exit; ?>
    <?php $vms_staff_include_heading = !isset($vms_staff_include_heading) || $vms_staff_include_heading; ?>
    <?php if ($vms_staff_include_heading) : ?>
    <h4 id="vms-staffing" class="vms-collapsible-title" data-section-key="staff" data-section-has-data="<?php echo $vms_staff_has_data ? '1' : '0'; ?>"><?php esc_html_e('Staff', 'vms'); ?></h4>
    <?php endif; ?>
    <div class="vms-ep-card vms-ep-card--white vms-ep-card--staff" data-vms-section-has-data="<?php echo $vms_staff_has_data ? '1' : '0'; ?>">
    <p class="description"><?php esc_html_e('Structured staffing by role: set staff needed and shift windows, then assign staff. Missing staff is based only on roles with Staff needed above 0.', 'vms'); ?></p>
    <p class="description vms-ep-staff-headcount-summary <?php echo $staff_headcount_wired ? '' : 'is-muted'; ?>" id="vms-ep-staff-headcount-summary">
        <?php
            echo esc_html(
                $staff_headcount_wired
                    ? sprintf(
                        __('Anticipated guests: %1$d. Staffing highlights below update against this number.', 'vms'),
                        $staff_current_headcount,
                        $staff_headcount_label
                    )
                    : __('Anticipated guest count is not available yet. Staffing highlights will appear once ticket sales or guest entries are available.', 'vms')
            );
        ?>
    </p>

    <?php
        $staff_template_alerts = array();
        $applied_template_band_min = (is_array($staff_applied_template) && isset($staff_applied_template['min_headcount']) && $staff_applied_template['min_headcount'] !== null && $staff_applied_template['min_headcount'] !== '') ? max(0, (int) $staff_applied_template['min_headcount']) : null;
        $applied_template_band_max = (is_array($staff_applied_template) && isset($staff_applied_template['max_headcount']) && $staff_applied_template['max_headcount'] !== null && $staff_applied_template['max_headcount'] !== '') ? max(0, (int) $staff_applied_template['max_headcount']) : null;
        if ($staff_headcount_wired && is_array($staff_applied_template) && $applied_template_band_max !== null && $staff_current_headcount > $applied_template_band_max) {
            $staff_template_alerts[] = sprintf(__('Anticipated guests (%1$d) are above the applied template ceiling of %2$d. Review staffing now.', 'vms'), $staff_current_headcount, $applied_template_band_max);
        }
        if ($staff_headcount_wired && is_array($staff_applied_template) && $applied_template_band_min !== null && $staff_current_headcount < $applied_template_band_min) {
            $staff_template_alerts[] = sprintf(__('Anticipated guests (%1$d) are below the applied template floor of %2$d.', 'vms'), $staff_current_headcount, $applied_template_band_min);
        }
        if ($staff_headcount_wired && is_array($staff_recommended_template) && !empty($staff_recommended_template['template_id']) && (int) $staff_recommended_template['template_id'] > 0 && (int) $staff_recommended_template['template_id'] !== (int) $staff_applied_template_id) {
            $staff_template_alerts[] = sprintf(__('Current guest count fits a different staffing template: %s.', 'vms'), isset($staff_recommended_template['name']) ? (string) $staff_recommended_template['name'] : __('Recommended template', 'vms'));
        }

        $next_threshold_gap = null;
        $next_threshold_role = '';
        foreach ((array) $staff_roles as $role) {
            if (!is_object($role) || empty($role->term_id)) {
                continue;
            }
            $rid = (int) $role->term_id;
            $threshold = array_key_exists($rid, (array) $staff_activation_thresholds) ? max(0, (int) $staff_activation_thresholds[$rid]) : 0;
            if ($threshold <= 0 || $threshold <= $staff_current_headcount) {
                continue;
            }
            $gap = $threshold - $staff_current_headcount;
            if ($next_threshold_gap === null || $gap < $next_threshold_gap) {
                $next_threshold_gap = $gap;
                $next_threshold_role = isset($role->name) ? (string) $role->name : '';
            }
        }
        if ($staff_headcount_wired && $next_threshold_gap !== null && $next_threshold_gap <= 10) {
            $staff_template_alerts[] = sprintf(__('This event is %1$d away from the next staffing trigger%2$s.', 'vms'), $next_threshold_gap, $next_threshold_role !== '' ? sprintf(__(' for %s', 'vms'), $next_threshold_role) : '');
        }
    ?>
    <?php if (!empty($staff_template_alerts)) : ?>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e('Staffing alert:', 'vms'); ?></strong></p>
            <ul style="margin:0 0 0 18px;">
                <?php foreach ($staff_template_alerts as $staff_template_alert) : ?>
                    <li><?php echo esc_html((string) $staff_template_alert); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="vms-ep-inline-card vms-mb-12">
        <strong><?php esc_html_e('Staffing template', 'vms'); ?></strong>
        <p class="description vms-m0">
            <?php
                $applied_name = (is_array($staff_applied_template) && !empty($staff_applied_template['name'])) ? (string) $staff_applied_template['name'] : __('None recorded', 'vms');
                $recommended_name = (is_array($staff_recommended_template) && !empty($staff_recommended_template['name'])) ? (string) $staff_recommended_template['name'] : __('No match', 'vms');
                echo esc_html(sprintf(__('Applied: %1$s · Recommended now: %2$s', 'vms'), $applied_name, $recommended_name));
            ?>
        </p>
        <p class="vms-m0 vms-mt-8">
            <label>
                <?php esc_html_e('Template', 'vms'); ?>
                <select name="vms_staffing_template_id">
                    <option value="0"><?php esc_html_e('Select staffing template', 'vms'); ?></option>
                    <?php foreach ((array) $staffing_templates as $tpl_row) : ?>
                        <?php
                            if (!is_array($tpl_row)) {
                                continue;
                            }
                            $tpl_id = isset($tpl_row['template_id']) ? absint($tpl_row['template_id']) : 0;
                            if ($tpl_id <= 0) {
                                continue;
                            }
                            $tpl_label_parts = array();
                            $tpl_label_parts[] = isset($tpl_row['name']) ? (string) $tpl_row['name'] : ('#' . $tpl_id);
                            if (isset($tpl_row['min_headcount']) && $tpl_row['min_headcount'] !== null && $tpl_row['min_headcount'] !== '' || isset($tpl_row['max_headcount']) && $tpl_row['max_headcount'] !== null && $tpl_row['max_headcount'] !== '') {
                                $tpl_label_parts[] = sprintf(__('guests %1$s-%2$s', 'vms'), (isset($tpl_row['min_headcount']) && $tpl_row['min_headcount'] !== null && $tpl_row['min_headcount'] !== '' ? (int) $tpl_row['min_headcount'] : 0), (isset($tpl_row['max_headcount']) && $tpl_row['max_headcount'] !== null && $tpl_row['max_headcount'] !== '' ? (int) $tpl_row['max_headcount'] : '∞'));
                            }
                        ?>
                        <option value="<?php echo esc_attr((string) $tpl_id); ?>" <?php selected($staff_applied_template_id, $tpl_id); ?>><?php echo esc_html(implode(' · ', $tpl_label_parts)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="vms-ml-8">
                <?php esc_html_e('Mode', 'vms'); ?>
                <select name="vms_staffing_template_mode">
                    <option value="merge_missing"><?php esc_html_e('Merge missing roles only', 'vms'); ?></option>
                    <option value="replace_all"><?php esc_html_e('Replace staffing from template', 'vms'); ?></option>
                </select>
            </label>
            <button type="submit" class="button" name="vms_staffing_template_apply" value="1"><?php esc_html_e('Apply selected template', 'vms'); ?></button>
        </p>
        <p class="description vms-m0"><?php esc_html_e('Use this when the event was not created from Schedule or when current guest count points to a different staffing package.', 'vms'); ?></p>
    </div>

    <input type="hidden" name="vms_staff_assignments_present" value="1" />
    <input type="hidden" name="vms_staffing_roles_present" value="1" />

    <div
        class="vms-ep-staff-wrap"
        data-vms-staff-wrap="1"
        data-vms-current-headcount="<?php echo esc_attr((string) $staff_current_headcount); ?>"
        data-vms-headcount-wired="<?php echo $staff_headcount_wired ? '1' : '0'; ?>"
        data-vms-headcount-label="<?php echo esc_attr($staff_headcount_label); ?>"
    >
        <?php if (empty($staff_roles) || is_wp_error($staff_roles)): ?>
            <p class="description"><?php esc_html_e('No staff roles are configured yet. Create roles in Staff Roles first.', 'vms'); ?></p>
        <?php else: ?>
            <?php foreach ($staff_roles as $role): ?>
                <?php
                    $rid = isset($role->term_id) ? (int) $role->term_id : 0;
                    if ($rid <= 0) continue;

                    $role_meta = isset($staff_role_meta_map[$rid]) && is_array($staff_role_meta_map[$rid]) ? $staff_role_meta_map[$rid] : array();
                    $slot_row = isset($staff_slot_by_role[$rid]) && is_array($staff_slot_by_role[$rid]) ? $staff_slot_by_role[$rid] : array();

                    $assigned = array();
                    if (!empty($slot_row['assignments']) && is_array($slot_row['assignments'])) {
                        foreach ($slot_row['assignments'] as $a) {
                            if (!is_array($a)) continue;
                            $a_status = isset($a['status']) ? sanitize_key((string) $a['status']) : '';
                            if (!in_array($a_status, array('proposed', 'confirmed'), true)) continue;
                            $sid = isset($a['staff_id']) ? absint($a['staff_id']) : 0;
                            if ($sid > 0) $assigned[] = $sid;
                        }
                    } elseif (isset($staff_assignments[$rid]) && is_array($staff_assignments[$rid])) {
                        $assigned = array_map('intval', $staff_assignments[$rid]);
                    }
                    $assigned = array_values(array_unique(array_filter($assigned, function ($v) {
                        return $v > 0;
                    })));

                    $default_headcount = isset($role_meta['default_headcount']) ? max(1, (int) $role_meta['default_headcount']) : 1;
                    $use_role_default_headcount = empty($slot_row) && empty($vms_staff_has_data) && empty($staff_applied_template_id);
                    $headcount = isset($slot_row['headcount_needed'])
                        ? max(0, (int) $slot_row['headcount_needed'])
                        : ($use_role_default_headcount ? $default_headcount : 0);
                    $time_mode = isset($slot_row['shift_time_mode']) ? sanitize_key((string) $slot_row['shift_time_mode']) : 'absolute';
                    if (!in_array($time_mode, array('absolute', 'relative'), true)) $time_mode = 'absolute';
                    $shift_start = isset($slot_row['shift_start_local']) ? (string) $slot_row['shift_start_local'] : '';
                    $shift_end = isset($slot_row['shift_end_local']) ? (string) $slot_row['shift_end_local'] : '';

                    $filled = count($assigned);
                    $open = max(0, $headcount - $filled);
                    $is_critical = !empty($role_meta['is_critical']);
                    $role_in_use = ($headcount > 0 || $filled > 0);
                    $activation_threshold = array_key_exists($rid, $staff_activation_thresholds)
                        ? max(0, (int) $staff_activation_thresholds[$rid])
                        : ($role_in_use ? 1 : 0);
                    $threshold_met = $staff_headcount_wired && ($staff_current_headcount >= $activation_threshold);
                    $required_now = ($headcount > 0) && $threshold_met;
                    $absolute_time_missing = $role_in_use && $time_mode === 'absolute' && ($shift_start === '' || ($shift_end === '' && (int) $duration_minutes <= 0));
                    $missing_staff_now = $required_now && ($filled < $headcount);

                    $role_card_classes = array('vms-ep-staff-role');
                    if ($required_now) {
                        $role_card_classes[] = 'is-required-now';
                    }
                    if ($absolute_time_missing || $missing_staff_now) {
                        $role_card_classes[] = 'has-inline-warning';
                    }
                    if ($missing_staff_now) {
                        $role_card_classes[] = 'has-required-gap';
                    }
                    if ($role_in_use && !$required_now && $staff_headcount_wired && $activation_threshold > 0) {
                        $role_card_classes[] = 'is-waiting-threshold';
                    }

                    if (!$role_in_use) {
                        $state_pill = __('Not set', 'vms');
                        $state_class = 'is-inactive';
                    } elseif (!$staff_headcount_wired) {
                        $state_pill = __('Guests pending', 'vms');
                        $state_class = 'is-unwired';
                    } elseif ($required_now) {
                        $state_pill = __('Needed now', 'vms');
                        $state_class = 'is-required';
                    } elseif ($activation_threshold <= 0) {
                        $state_pill = __('Always needed', 'vms');
                        $state_class = 'is-active';
                    } else {
                        $state_pill = sprintf(__('Needed at %d+ guests', 'vms'), $activation_threshold);
                        $state_class = 'is-waiting';
                    }

                    if (!$role_in_use) {
                        $threshold_copy = __('Set staff needed and the guest trigger for when this role should become needed.', 'vms');
                    } elseif (!$staff_headcount_wired) {
                        $threshold_copy = sprintf(
                            __('Guest count is not available yet. This role will become needed at %d guests once sales or guest entries are available.', 'vms'),
                            $activation_threshold
                        );
                    } elseif ($required_now) {
                        $threshold_copy = sprintf(
                            __('This role is needed now based on %1$d anticipated guests. It turns on at %2$d guests.', 'vms'),
                            $staff_current_headcount,
                            $activation_threshold
                        );
                    } elseif ($activation_threshold <= 0) {
                        $threshold_copy = sprintf(
                            __('This role is needed as soon as guest counts are available.', 'vms'),
                            $staff_current_headcount
                        );
                    } else {
                        $threshold_copy = sprintf(
                            __('This role becomes needed at %2$d anticipated guests. Current guest count: %1$d.', 'vms'),
                            $staff_current_headcount,
                            $activation_threshold
                        );
                    }

                    $role_staff = isset($staff_by_role[$rid]) && is_array($staff_by_role[$rid]) ? $staff_by_role[$rid] : array();
                    $role_eligible_count = isset($staff_eligible_counts_by_role[$rid]) ? max(0, (int) $staff_eligible_counts_by_role[$rid]) : 0;
                ?>
                <div
                    class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $role_card_classes))); ?>"
                    data-vms-staff-role="1"
                    data-role-id="<?php echo esc_attr((string) $rid); ?>"
                    data-role-name="<?php echo esc_attr((string) $role->name); ?>"
                    data-role-critical="<?php echo $is_critical ? '1' : '0'; ?>"
                >
                    <div class="vms-ep-staff-role__head">
                        <div class="vms-ep-staff-role__head-copy">
                            <strong><?php echo esc_html($role->name); ?></strong>
                            <span class="description" data-vms-role-base-summary>
                                <?php
                                    echo esc_html(sprintf(
                                        __('Need %1$d · Filled %2$d · Open %3$d%4$s', 'vms'),
                                        (int) $headcount,
                                        (int) $filled,
                                        (int) $open,
                                        $is_critical ? ' · ' . __('Critical', 'vms') : ''
                                    ));
                                ?>
                            </span>
                        </div>
                        <span class="vms-ep-staff-role__state <?php echo esc_attr('vms-ep-staff-role__state--' . $state_class); ?>" data-vms-role-state-pill><?php echo esc_html($state_pill); ?></span>
                    </div>

                    <?php
                        $start_anchor_key = isset($slot_row['start_anchor_key']) ? (string) $slot_row['start_anchor_key'] : 'event_start';
                        $end_anchor_key = isset($slot_row['end_anchor_key']) ? (string) $slot_row['end_anchor_key'] : 'event_end';
                        $start_offset_minutes = isset($slot_row['start_offset_minutes']) ? (int) $slot_row['start_offset_minutes'] : 0;
                        $end_offset_minutes = isset($slot_row['end_offset_minutes']) ? (int) $slot_row['end_offset_minutes'] : 0;
                        $duration_minutes = isset($slot_row['duration_minutes']) && $slot_row['duration_minutes'] !== null ? (int) $slot_row['duration_minutes'] : '';
                        $anchor_options = array(
                            'event_start' => __('Event start', 'vms'),
                            'event_end' => __('Event end', 'vms'),
                            'a1' => __('Anchor 1', 'vms'),
                            'a2' => __('Anchor 2', 'vms'),
                            'a3' => __('Anchor 3', 'vms'),
                            'a4' => __('Anchor 4', 'vms'),
                        );
                    ?>
                    <p class="vms-m0 vms-mb-8 vms-ep-staff-role__controls">
                        <label>
                            <?php esc_html_e('Staff needed', 'vms'); ?>
                            <input type="number" min="0" step="1" name="vms_staff_role_headcount[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr((string) $headcount); ?>" data-vms-role-headcount-input="1">
                        </label>
                        <label>
                            <?php esc_html_e('Activate at attendance', 'vms'); ?>
                            <input type="number" min="0" step="1" name="vms_staff_role_activation_threshold[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr((string) $activation_threshold); ?>" data-vms-role-threshold-input="1">
                        </label>
                        <label>
                            <?php esc_html_e('Time mode', 'vms'); ?>
                            <select name="vms_staff_role_time_mode[<?php echo esc_attr((string) $rid); ?>]" data-vms-role-time-mode-input="1">
                                <option value="absolute" <?php selected($time_mode, 'absolute'); ?>><?php esc_html_e('Absolute', 'vms'); ?></option>
                                <option value="relative" <?php selected($time_mode, 'relative'); ?>><?php esc_html_e('Relative', 'vms'); ?></option>
                            </select>
                        </label>
                        <label data-vms-role-absolute-field="1">
                            <?php esc_html_e('Shift start', 'vms'); ?>
                            <input type="time" name="vms_staff_role_shift_start[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr($shift_start); ?>" data-vms-role-shift-start-input="1">
                        </label>
                        <label data-vms-role-absolute-field="1" data-vms-role-end-field="1">
                            <?php esc_html_e('Shift end', 'vms'); ?>
                            <input type="time" name="vms_staff_role_shift_end[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr($shift_end); ?>" data-vms-role-shift-end-input="1">
                        </label>
                        <label data-vms-role-relative-field="1">
                            <?php esc_html_e('Start anchor', 'vms'); ?>
                            <select name="vms_staff_role_start_anchor[<?php echo esc_attr((string) $rid); ?>]" data-vms-role-start-anchor-input="1">
                                <?php foreach ($anchor_options as $anchor_key => $anchor_label) : ?>
                                    <option value="<?php echo esc_attr($anchor_key); ?>" <?php selected($start_anchor_key, $anchor_key); ?>><?php echo esc_html($anchor_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label data-vms-role-relative-field="1">
                            <?php esc_html_e('Start offset (min)', 'vms'); ?>
                            <input type="number" step="1" name="vms_staff_role_start_offset[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr((string) $start_offset_minutes); ?>" data-vms-role-start-offset-input="1">
                        </label>
                        <label data-vms-role-relative-field="1" data-vms-role-end-field="1">
                            <?php esc_html_e('End anchor', 'vms'); ?>
                            <select name="vms_staff_role_end_anchor[<?php echo esc_attr((string) $rid); ?>]" data-vms-role-end-anchor-input="1">
                                <?php foreach ($anchor_options as $anchor_key => $anchor_label) : ?>
                                    <option value="<?php echo esc_attr($anchor_key); ?>" <?php selected($end_anchor_key, $anchor_key); ?>><?php echo esc_html($anchor_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label data-vms-role-relative-field="1" data-vms-role-end-field="1">
                            <?php esc_html_e('End offset (min)', 'vms'); ?>
                            <input type="number" step="1" name="vms_staff_role_end_offset[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr((string) $end_offset_minutes); ?>" data-vms-role-end-offset-input="1">
                        </label>
                        <label data-vms-role-duration-field="1">
                            <?php esc_html_e('Duration (min)', 'vms'); ?>
                            <input type="number" min="0" step="1" name="vms_staff_role_duration_minutes[<?php echo esc_attr((string) $rid); ?>]" value="<?php echo esc_attr((string) $duration_minutes); ?>" data-vms-role-duration-input="1">
                        </label>
                    </p>
                    <p class="description vms-m0" data-vms-role-threshold-copy><?php echo esc_html($threshold_copy); ?></p>
                    <?php if (!empty($role_meta['required_qualification_rules']) && is_array($role_meta['required_qualification_rules'])) : ?>
                        <?php
                            $qualification_summary_parts = array();
                            foreach ((array) $role_meta['required_qualification_rules'] as $qualification_rule) {
                                if (!is_array($qualification_rule) || empty($qualification_rule['name'])) {
                                    continue;
                                }
                                $qualification_summary_parts[] = sprintf(
                                    __('%1$s (%2$s)', 'vms'),
                                    (string) $qualification_rule['name'],
                                    function_exists('vms_staffing_admin_qualification_mode_label')
                                        ? vms_staffing_admin_qualification_mode_label((string) ($qualification_rule['mode'] ?? 'warn'))
                                        : (string) ($qualification_rule['mode'] ?? 'warn')
                                );
                            }
                        ?>
                        <?php if (!empty($qualification_summary_parts)) : ?>
                            <p class="description vms-m0"><?php echo esc_html(sprintf(__('Required qualifications: %s.', 'vms'), implode(', ', $qualification_summary_parts))); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="description vms-m0"><?php esc_html_e('Absolute mode uses Shift start plus Shift end or Duration. Relative mode uses start anchor/offset plus End anchor/offset or Duration.', 'vms'); ?></p>
                    <div class="vms-ep-inline-warning <?php echo $absolute_time_missing ? '' : 'vms-hidden'; ?>" data-vms-role-absolute-warning>
                        <?php esc_html_e('Absolute time mode requires Shift start plus Shift end or Duration when this role is in use.', 'vms'); ?>
                    </div>
                    <div class="vms-ep-inline-warning vms-ep-inline-warning--required <?php echo $missing_staff_now ? '' : 'vms-hidden'; ?>" data-vms-role-required-warning>
                        <?php esc_html_e('Current guest count has reached this role\'s trigger. Assign staff until Filled reaches Staff needed.', 'vms'); ?>
                    </div>

                    <?php if ($role_eligible_count <= 0) : ?>
                        <p class="description vms-m0">
                            <?php
                                echo esc_html(sprintf(
                                    __('No %s-eligible staff found.', 'vms'),
                                    strtolower((string) $role->name)
                                ));
                            ?>
                        </p>
                        <?php if (!empty($role_staff)) : ?>
                            <p class="description vms-m0"><?php esc_html_e('Currently assigned but now-ineligible staff are shown below so this plan does not silently lose them.', 'vms'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (empty($role_staff)): ?>
                        <p class="description vms-m0"><?php esc_html_e('No staff candidates are available for this role yet.', 'vms'); ?></p>
                    <?php else: ?>
                        <div class="vms-ep-check-grid" role="group" aria-label="<?php echo esc_attr($role->name); ?>">
                            <?php foreach ($role_staff as $sp): ?>
                                <?php
                                    $sid = is_object($sp) && isset($sp->ID) ? (int) $sp->ID : 0;
                                    if ($sid <= 0) continue;
                                    $checked = in_array($sid, $assigned, true);
                                    $candidate_status = function_exists('vms_staffing_staff_candidate_status_for_role')
                                        ? (array) vms_staffing_staff_candidate_status_for_role($sid, $rid)
                                        : array(
                                            'eligible' => true,
                                            'qualification' => array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array()),
                                            'ineligibility_reason' => '',
                                        );
                                    $role_eligible = !empty($candidate_status['eligible']);
                                    $eligibility_reason = isset($candidate_status['ineligibility_reason']) ? (string) $candidate_status['ineligibility_reason'] : '';
                                    $qual_check = isset($candidate_status['qualification']) && is_array($candidate_status['qualification'])
                                        ? $candidate_status['qualification']
                                        : array('ok' => true, 'mode' => 'warn', 'missing' => array(), 'expired' => array());
                                    $qual_ok = !empty($qual_check['ok']);
                                    $qual_mode = isset($qual_check['mode']) ? (string) $qual_check['mode'] : 'warn';
                                    $qual_disabled = (!$qual_ok && $qual_mode === 'hard_block' && !$checked);
                                    $qual_parts = array();
                                    if (!empty($qual_check['missing'])) {
                                        $qual_parts[] = sprintf(__('missing %s', 'vms'), implode(', ', array_map('strval', (array) $qual_check['missing'])));
                                    }
                                    if (!empty($qual_check['expired'])) {
                                        $qual_parts[] = sprintf(__('expired %s', 'vms'), implode(', ', array_map('strval', (array) $qual_check['expired'])));
                                    }
                                ?>
                                <label class="vms-ep-check">
                                    <input type="checkbox" name="vms_staff_assignments[<?php echo esc_attr((string)$rid); ?>][]" value="<?php echo esc_attr((string)$sid); ?>" <?php checked($checked); ?> <?php disabled($qual_disabled); ?> data-vms-role-assignment-input="1" />
                                    <span class="vms-ep-check__label"><?php echo esc_html(get_the_title($sid)); ?></span>
                                    <?php echo $render_tax_badge($sid); ?>
                                    <?php if (!$role_eligible && $checked) : ?>
                                        <span class="vms-ep-tax-badge vms-ep-tax-badge--missing"><?php esc_html_e('Role⚠', 'vms'); ?></span>
                                        <?php if ($eligibility_reason !== '') : ?>
                                            <span class="vms-ep-tax-badge-note"><?php echo esc_html($eligibility_reason); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!$qual_ok) : ?>
                                        <span class="vms-ep-tax-badge <?php echo esc_attr($qual_disabled ? 'vms-ep-tax-badge--missing' : 'vms-ep-tax-badge--bypass'); ?>"><?php echo esc_html($qual_disabled ? 'Q✕' : 'Q⚠'); ?></span>
                                        <span class="vms-ep-tax-badge-note"><?php echo esc_html(implode('; ', $qual_parts)); ?></span>
                                    <?php elseif (!empty($role_meta['required_qualification_rules'])) : ?>
                                        <span class="vms-ep-tax-badge vms-ep-tax-badge--ok">Q✓</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description vms-m0"><?php esc_html_e('Tax status: T✓ ok, T⚠ missing, TB bypass active. Assigned staff default to Proposed status in staffing rollups.', 'vms'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


    </div>
