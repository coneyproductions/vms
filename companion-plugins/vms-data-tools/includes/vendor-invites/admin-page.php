<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_register_vendor_invites_page')) {
    function vms_dt_register_vendor_invites_page(): void
    {
        add_submenu_page(
            'vms-data-tools',
            __('Vendor Invites', 'vms-data-tools'),
            __('Vendor Invites', 'vms-data-tools'),
            vms_dt_vio_required_cap(),
            vms_dt_get_menu_slug_vendor_invites(),
            'vms_dt_vio_render_admin_page'
        );
    }
}

if (!function_exists('vms_dt_vio_admin_vendor_type_options')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_admin_vendor_type_options(): array
    {
        $terms = get_terms(array(
            'taxonomy' => 'vms_vendor_type',
            'hide_empty' => false,
        ));

        $options = array();
        if (!is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }
                $slug = sanitize_key((string) $term->slug);
                if ($slug === '') {
                    continue;
                }
                $options[$slug] = (string) $term->name;
            }
        }

        asort($options);
        return $options;
    }
}

if (!function_exists('vms_dt_vio_admin_venue_options')) {
    /**
     * @return array<int,string> venue_id => label
     */
    function vms_dt_vio_admin_venue_options(): array
    {
        $ids = get_posts(array(
            'post_type' => 'vms_venue',
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        if (!is_array($ids) || empty($ids)) {
            return array();
        }

        $options = array();
        foreach ($ids as $venue_id_raw) {
            $venue_id = absint($venue_id_raw);
            if ($venue_id <= 0) {
                continue;
            }

            $title = trim((string) get_the_title($venue_id));
            if ($title === '') {
                $title = sprintf(
                    /* translators: %d is a venue ID. */
                    __('Venue #%d', 'vms-data-tools'),
                    $venue_id
                );
            }

            $options[$venue_id] = sprintf(
                /* translators: 1: venue title, 2: venue ID */
                __('%1$s (#%2$d)', 'vms-data-tools'),
                $title,
                $venue_id
            );
        }

        if (!empty($options)) {
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $options;
    }
}

if (!function_exists('vms_dt_vio_admin_get_tab')) {
    function vms_dt_vio_admin_get_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'send';
        if (!in_array($tab, array('settings', 'send', 'logs', 'interest'), true)) {
            $tab = 'send';
        }
        return $tab;
    }
}

if (!function_exists('vms_dt_vio_admin_make_required_tags')) {
    /**
     * @return string[]
     */
    function vms_dt_vio_admin_make_required_tags(array $settings): array
    {
        $general_base = sanitize_key((string) ($settings['tag_base_general'] ?? 'vms_invite_general'));
        $intent_base = sanitize_key((string) ($settings['tag_base_intent'] ?? 'vms_invite_intent'));

        $tags = array();
        $langs = array_keys(vms_dt_vio_supported_languages());
        if (empty($langs)) {
            $langs = array(vms_dt_vio_site_default_lang());
        }

        foreach ($langs as $lang_raw) {
            $lang = vms_dt_vio_sanitize_lang((string) $lang_raw);
            if ($lang === '') {
                continue;
            }

            $tags[] = $general_base . '_' . $lang;
        }

        $vendor_types = array_keys(vms_dt_vio_admin_vendor_type_options());
        foreach ($vendor_types as $vendor_type_raw) {
            $vendor_type = sanitize_key((string) $vendor_type_raw);
            if ($vendor_type === '') {
                continue;
            }

            foreach ($langs as $lang_raw) {
                $lang = vms_dt_vio_sanitize_lang((string) $lang_raw);
                if ($lang === '') {
                    continue;
                }

                $tags[] = $intent_base . '_' . $vendor_type . '_' . $lang;
            }
        }

        $claimed_tag = sanitize_key((string) ($settings['tag_claimed'] ?? ''));
        if ($claimed_tag !== '') {
            $tags[] = $claimed_tag;
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $tags))));
    }
}

if (!function_exists('vms_dt_vio_admin_event_type_labels')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_admin_event_type_labels(): array
    {
        return vms_dt_vio_log_event_type_labels();
    }
}

if (!function_exists('vms_dt_vio_render_notice_block')) {
    /**
     * @param string[] $messages
     */
    function vms_dt_vio_render_notice_block(array $messages, string $type): void
    {
        if (empty($messages)) {
            return;
        }

        $class = 'notice notice-info';
        if ($type === 'error') {
            $class = 'notice notice-error';
        } elseif ($type === 'success') {
            $class = 'notice notice-success';
        } elseif ($type === 'warning') {
            $class = 'notice notice-warning';
        }

        echo '<div class="' . esc_attr($class) . '"><ul>';
        foreach ($messages as $message) {
            echo '<li>' . esc_html((string) $message) . '</li>';
        }
        echo '</ul></div>';
    }
}

if (!function_exists('vms_dt_vio_render_admin_page')) {
    function vms_dt_vio_render_admin_page(): void
    {
        if (!vms_dt_vio_current_user_can_manage()) {
            wp_die(esc_html__('Insufficient permissions.', 'vms-data-tools'));
        }

        $tab = vms_dt_vio_admin_get_tab();

        $state = array(
            'success' => array(),
            'errors' => array(),
            'warnings' => array(),
            'info' => array(),
            'preview' => null,
            'preview_token' => '',
            'commit_result' => null,
            'retention_preview' => null,
            'retention_preview_token' => '',
            'retention_commit_result' => null,
            'verify_result' => null,
            'generated_claim_link' => '',
        );

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_dt_vio_action'])) {
            $action = sanitize_key((string) wp_unslash($_POST['vms_dt_vio_action']));

            if ($action === 'save_settings') {
                check_admin_referer('vms_dt_vio_save_settings');
                $input = isset($_POST['settings']) && is_array($_POST['settings']) ? (array) wp_unslash($_POST['settings']) : array();
                vms_dt_vio_update_settings($input);
                $state['success'][] = __('Vendor Invite settings saved.', 'vms-data-tools');
                $tab = 'settings';
            }

            if ($action === 'verify_mailpoet') {
                check_admin_referer('vms_dt_vio_verify_mailpoet');
                $settings = vms_dt_vio_get_settings();
                $verify = vms_dt_vio_mailpoet_verify_setup($settings, vms_dt_vio_admin_make_required_tags($settings));
                $state['verify_result'] = $verify;
                if (!empty($verify['ok'])) {
                    $state['success'][] = __('MailPoet setup verified.', 'vms-data-tools');
                } else {
                    $state['errors'][] = __('MailPoet setup verification reported issues.', 'vms-data-tools');
                }
                $tab = 'settings';
            }

            if ($action === 'send_preview') {
                check_admin_referer('vms_dt_vio_send_preview');
                $args = vms_dt_vio_sanitize_send_args($_POST);
                $preview = vms_dt_vio_build_preview($args);
                $preview_token = vms_dt_vio_store_preview($preview);

                $state['preview'] = $preview;
                $state['preview_token'] = $preview_token;
                $state['info'][] = __('Preview generated. Review rows before committing invites.', 'vms-data-tools');
                $tab = 'send';
            }

            if ($action === 'send_commit') {
                check_admin_referer('vms_dt_vio_send_commit');
                $preview_token = isset($_POST['preview_token']) ? sanitize_text_field((string) wp_unslash($_POST['preview_token'])) : '';
                $include_ids = isset($_POST['include_vendor_ids']) ? (array) wp_unslash($_POST['include_vendor_ids']) : array();
                $force_resend = !empty($_POST['force_resend']);

                $commit = vms_dt_vio_commit_preview($preview_token, array_map('absint', $include_ids), $force_resend);
                if (is_wp_error($commit)) {
                    $state['errors'][] = $commit->get_error_message();
                } else {
                    $state['commit_result'] = $commit;
                    $state['success'][] = __('Invite commit finished. Review the summary below.', 'vms-data-tools');
                }
                $tab = 'send';
            }

            if ($action === 'resend_from_log') {
                check_admin_referer('vms_dt_vio_resend_from_log');
                $log_id = absint($_POST['log_id'] ?? 0);
                $force = !empty($_POST['force_resend']);
                $resend = vms_dt_vio_resend_from_log($log_id, $force);
                if (is_wp_error($resend)) {
                    $state['errors'][] = $resend->get_error_message();
                } else {
                    $result = (array) ($resend['result'] ?? array());
                    if (($result['status'] ?? 'failed') === 'sent') {
                        $state['success'][] = __('Resend succeeded.', 'vms-data-tools');
                    } else {
                        $state['errors'][] = (string) ($result['message'] ?? __('Resend failed.', 'vms-data-tools'));
                    }
                }
                $tab = 'logs';
            }

            if ($action === 'retention_preview') {
                check_admin_referer('vms_dt_vio_retention_preview');
                $retention_action = sanitize_key((string) wp_unslash($_POST['retention_action'] ?? ''));
                $preview = vms_dt_vio_build_retention_preview(array(
                    'action' => $retention_action,
                    'scope' => 'filtered',
                    'filters' => vms_dt_vio_collect_log_filters_from_request((array) $_POST),
                ));

                if (is_wp_error($preview)) {
                    $state['errors'][] = $preview->get_error_message();
                } else {
                    $state['retention_preview'] = $preview;
                    $state['retention_preview_token'] = vms_dt_vio_store_retention_preview($preview);
                    $state['info'][] = __('Retention preview generated. Review eligible rows and skipped reasons before committing cleanup.', 'vms-data-tools');
                }
                $tab = 'logs';
            }

            if ($action === 'retention_commit') {
                check_admin_referer('vms_dt_vio_retention_commit');
                $preview_token = sanitize_text_field((string) wp_unslash($_POST['retention_preview_token'] ?? ''));
                $commit = vms_dt_vio_commit_retention_preview($preview_token, array(
                    'user_id' => absint(get_current_user_id()),
                    'acknowledge' => !empty($_POST['retention_acknowledge']),
                    'operator_note' => sanitize_textarea_field((string) wp_unslash($_POST['operator_note'] ?? '')),
                    'purge_phrase' => sanitize_text_field((string) wp_unslash($_POST['purge_phrase'] ?? '')),
                    'reason_code' => 'manual_cleanup',
                ));

                if (is_wp_error($commit)) {
                    $state['errors'][] = $commit->get_error_message();
                } else {
                    $state['retention_commit_result'] = $commit;
                    $summary = (array) ($commit['summary'] ?? array());
                    $action_done = sanitize_key((string) ($commit['action'] ?? ''));
                    if ($action_done === 'archive') {
                        $state['success'][] = sprintf(
                            /* translators: 1: archived count, 2: skipped count */
                            __('Archived %1$d logs. Skipped %2$d rows.', 'vms-data-tools'),
                            absint($summary['archived'] ?? 0),
                            absint($summary['skipped'] ?? 0)
                        );
                    } elseif ($action_done === 'restore') {
                        $state['success'][] = sprintf(
                            /* translators: 1: restored count, 2: skipped count */
                            __('Restored %1$d logs. Skipped %2$d rows.', 'vms-data-tools'),
                            absint($summary['restored'] ?? 0),
                            absint($summary['skipped'] ?? 0)
                        );
                    } else {
                        $state['success'][] = sprintf(
                            /* translators: 1: purged count, 2: deleted token count, 3: skipped count */
                            __('Purged %1$d archived logs and deleted %2$d unused claim tokens. Skipped %3$d rows.', 'vms-data-tools'),
                            absint($summary['purged'] ?? 0),
                            absint($summary['deleted_tokens'] ?? 0),
                            absint($summary['skipped'] ?? 0)
                        );
                    }

                    $skip_reasons = isset($summary['skip_reasons']) && is_array($summary['skip_reasons'])
                        ? $summary['skip_reasons']
                        : array();
                    foreach ($skip_reasons as $reason => $count) {
                        $state['warnings'][] = sprintf(
                            /* translators: 1: skipped row count, 2: skip reason */
                            __('Skipped %1$d rows: %2$s', 'vms-data-tools'),
                            absint($count),
                            (string) $reason
                        );
                    }
                }
                $tab = 'logs';
            }

            if ($action === 'generate_claim_link') {
                check_admin_referer('vms_dt_vio_generate_claim_link');
                $vendor_id = absint($_POST['vendor_id'] ?? 0);
                $token = vms_dt_vio_generate_claim_link_for_vendor($vendor_id);
                if (is_wp_error($token)) {
                    $state['errors'][] = $token->get_error_message();
                } else {
                    $state['generated_claim_link'] = (string) ($token['claim_url'] ?? '');
                    $state['success'][] = __('A new claim link was generated.', 'vms-data-tools');
                }
                $tab = 'logs';
            }

            if ($action === 'assign_interest_submission') {
                check_admin_referer('vms_dt_vio_assign_interest_submission');
                $submission_id = absint($_POST['submission_id'] ?? 0);
                $assigned = vms_dt_vio_assign_interest_submission($submission_id, array(
                    'actor_user_id' => absint(get_current_user_id()),
                ));
                if (is_wp_error($assigned)) {
                    $state['errors'][] = $assigned->get_error_message();
                } else {
                    $accepted = is_array($assigned['accepted_submission'] ?? null) ? (array) $assigned['accepted_submission'] : array();
                    $vendor_name = trim((string) ($accepted['vendor_name'] ?? ''));
                    $event_title = trim((string) ($accepted['event_title'] ?? ''));
                    $declined_count = absint($assigned['declined_count'] ?? 0);
                    if ($vendor_name !== '' && $event_title !== '') {
                        $state['success'][] = sprintf(
                            /* translators: 1: vendor name, 2: event plan title */
                            __('Assigned %1$s to %2$s.', 'vms-data-tools'),
                            $vendor_name,
                            $event_title
                        );
                    } else {
                        $state['success'][] = __('Interest assignment saved.', 'vms-data-tools');
                    }
                    if ($declined_count > 0) {
                        $state['info'][] = sprintf(
                            /* translators: %d is a count of declined submissions. */
                            __('Declined %d other pending submission(s) because the opportunity is now filled.', 'vms-data-tools'),
                            $declined_count
                        );
                    }
                }
                $tab = 'interest';
            }

            if ($action === 'decline_interest_submission') {
                check_admin_referer('vms_dt_vio_decline_interest_submission');
                $submission_id = absint($_POST['submission_id'] ?? 0);
                $declined = vms_dt_vio_update_interest_submission_status($submission_id, 'declined', array(
                    'actor_user_id' => absint(get_current_user_id()),
                ));
                if (is_wp_error($declined)) {
                    $state['errors'][] = $declined->get_error_message();
                } else {
                    $vendor_name = trim((string) ($declined['vendor_name'] ?? ''));
                    if ($vendor_name !== '') {
                        $state['success'][] = sprintf(
                            /* translators: %s is a vendor name. */
                            __('Declined interest from %s.', 'vms-data-tools'),
                            $vendor_name
                        );
                    } else {
                        $state['success'][] = __('Interest submission declined.', 'vms-data-tools');
                    }
                }
                $tab = 'interest';
            }
        }

        $settings = vms_dt_vio_get_settings();
        $tour_id = function_exists('vms_dt_vio_help_tour_id') ? vms_dt_vio_help_tour_id() : 'vms_dt_vendor_invites_overview';

        echo '<div class="wrap vms-dt-vio-wrap">';
        echo '<div class="vms-dt-vio-header" data-vms-tour="vendor-invites.help">';
        echo '<div>';
        echo '<h1>' . esc_html__('Vendor Invite Orchestrator', 'vms-data-tools') . '</h1>';
        echo '<p class="description">' . esc_html__('VMS builds the invite payload here; the actual email body and automation remain in MailPoet.', 'vms-data-tools') . '</p>';
        echo '</div>';
        echo '<div class="vms-dt-vio-header-actions">';
        echo '<button type="button" class="button button-secondary" data-vms-tour-start="' . esc_attr($tour_id) . '">' . esc_html__('Start Guided Tour', 'vms-data-tools') . '</button>';
        echo '</div>';
        echo '</div>';

        vms_dt_vio_render_notice_block((array) $state['errors'], 'error');
        vms_dt_vio_render_notice_block((array) $state['warnings'], 'warning');
        vms_dt_vio_render_notice_block((array) $state['success'], 'success');
        vms_dt_vio_render_notice_block((array) $state['info'], 'info');

        if ((string) $state['generated_claim_link'] !== '') {
            echo '<div class="notice notice-info"><p>' . esc_html__('Copy this claim link:', 'vms-data-tools') . '</p>';
            echo '<p><input class="regular-text code" type="text" readonly value="' . esc_attr((string) $state['generated_claim_link']) . '"></p></div>';
        }

        $base_url = admin_url('admin.php?page=' . urlencode(vms_dt_get_menu_slug_vendor_invites()));
        echo '<nav class="nav-tab-wrapper" data-vms-tour="vendor-invites.tabs">';
        echo '<a class="nav-tab ' . ($tab === 'send' ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('tab', 'send', $base_url)) . '">' . esc_html__('Send Invites', 'vms-data-tools') . '</a>';
        echo '<a class="nav-tab ' . ($tab === 'settings' ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('tab', 'settings', $base_url)) . '">' . esc_html__('Settings', 'vms-data-tools') . '</a>';
        echo '<a class="nav-tab ' . ($tab === 'interest' ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('tab', 'interest', $base_url)) . '">' . esc_html__('Interest Queue', 'vms-data-tools') . '</a>';
        echo '<a class="nav-tab ' . ($tab === 'logs' ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('tab', 'logs', $base_url)) . '">' . esc_html__('Invite Logs', 'vms-data-tools') . '</a>';
        echo '</nav>';

        if ($tab === 'settings') {
            vms_dt_vio_render_settings_tab($settings, (array) $state['verify_result']);
        } elseif ($tab === 'interest') {
            vms_dt_vio_render_interest_tab();
        } elseif ($tab === 'logs') {
            vms_dt_vio_render_logs_tab(
                is_array($state['retention_preview']) ? $state['retention_preview'] : array(),
                (string) $state['retention_preview_token'],
                is_array($state['retention_commit_result']) ? $state['retention_commit_result'] : array()
            );
        } else {
            vms_dt_vio_render_send_tab($settings, is_array($state['preview']) ? $state['preview'] : array(), (string) $state['preview_token'], is_array($state['commit_result']) ? $state['commit_result'] : array());
        }

        echo '</div>';
    }
}

if (!function_exists('vms_dt_vio_render_settings_tab')) {
    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $verify_result
     */
    function vms_dt_vio_render_settings_tab(array $settings, array $verify_result = array()): void
    {
        echo '<h2 data-vms-tour="vendor-invites.settings.verify">' . esc_html__('Setup and Verification', 'vms-data-tools') . '</h2>';

        echo '<form method="post" data-vms-tour="vendor-invites.settings.verify">';
        wp_nonce_field('vms_dt_vio_save_settings');
        echo '<input type="hidden" name="vms_dt_vio_action" value="save_settings">';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('MailPoet integration', 'vms-data-tools') . '</th><td>';
        echo '<label><input type="checkbox" name="settings[mailpoet_enabled]" value="1" ' . checked(!empty($settings['mailpoet_enabled']), true, false) . '> ' . esc_html__('Enable MailPoet bridge for invite sends', 'vms-data-tools') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Subscriber creation strategy', 'vms-data-tools') . '</th><td>';
        echo '<label><input type="checkbox" name="settings[create_subscribers_on_invite]" value="1" ' . checked(!empty($settings['create_subscribers_on_invite']), true, false) . '> ' . esc_html__('Create missing subscribers when committing invites (recommended)', 'vms-data-tools') . '</label>';
        echo '<p class="description">' . esc_html__('Turn this off only if vendor subscribers are already being synced into MailPoet elsewhere.', 'vms-data-tools') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="tag_base_general">' . esc_html__('General invite tag base', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="tag_base_general" class="regular-text code" name="settings[tag_base_general]" value="' . esc_attr((string) ($settings['tag_base_general'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="tag_base_intent">' . esc_html__('Intent invite tag base', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="tag_base_intent" class="regular-text code" name="settings[tag_base_intent]" value="' . esc_attr((string) ($settings['tag_base_intent'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="tag_claimed">' . esc_html__('Claimed tag', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="tag_claimed" class="regular-text code" name="settings[tag_claimed]" value="' . esc_attr((string) ($settings['tag_claimed'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="claim_field_key">' . esc_html__('MailPoet claim field key', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="claim_field_key" class="regular-text code" name="settings[claim_field_key]" value="' . esc_attr((string) ($settings['claim_field_key'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="open_dates_field_key">' . esc_html__('MailPoet open dates field key', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="open_dates_field_key" class="regular-text code" name="settings[open_dates_field_key]" value="' . esc_attr((string) ($settings['open_dates_field_key'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="invites_per_minute">' . esc_html__('Throttle (invites per minute)', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="invites_per_minute" type="number" min="1" max="240" name="settings[invites_per_minute]" value="' . esc_attr((string) ($settings['invites_per_minute'] ?? 20)) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="token_expiration_days">' . esc_html__('Token expiration (days)', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="token_expiration_days" type="number" min="1" max="90" name="settings[token_expiration_days]" value="' . esc_attr((string) ($settings['token_expiration_days'] ?? 14)) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="resend_cooldown_days">' . esc_html__('Resend cooldown (days)', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="resend_cooldown_days" type="number" min="0" max="30" name="settings[resend_cooldown_days]" value="' . esc_attr((string) ($settings['resend_cooldown_days'] ?? 3)) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="default_next_n">' . esc_html__('Default Next N', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="default_next_n" type="number" min="1" max="30" name="settings[default_next_n]" value="' . esc_attr((string) ($settings['default_next_n'] ?? 8)) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="default_lookahead_days">' . esc_html__('Default lookahead days', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="default_lookahead_days" type="number" min="1" max="365" name="settings[default_lookahead_days]" value="' . esc_attr((string) ($settings['default_lookahead_days'] ?? 90)) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save Settings', 'vms-data-tools'), 'primary');
        echo '</form>';

        echo '<form method="post" class="vms-dt-vio-verify-form" data-vms-tour="vendor-invites.settings.verify-action">';
        wp_nonce_field('vms_dt_vio_verify_mailpoet');
        echo '<input type="hidden" name="vms_dt_vio_action" value="verify_mailpoet">';
        submit_button(__('Verify MailPoet Setup', 'vms-data-tools'), 'secondary', 'submit', false);
        echo '</form>';

        if (!empty($verify_result)) {
            $messages = isset($verify_result['messages']) && is_array($verify_result['messages']) ? $verify_result['messages'] : array();
            $errors = isset($verify_result['errors']) && is_array($verify_result['errors']) ? $verify_result['errors'] : array();
            vms_dt_vio_render_notice_block($messages, 'info');
            vms_dt_vio_render_notice_block($errors, 'error');
        }
    }
}

if (!function_exists('vms_dt_vio_render_send_tab')) {
    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $preview
     * @param array<string,mixed> $commit_result
     */
    function vms_dt_vio_render_send_tab(array $settings, array $preview, string $preview_token, array $commit_result): void
    {
        $vendor_types = vms_dt_vio_admin_vendor_type_options();
        $venue_options = vms_dt_vio_admin_venue_options();
        $langs = vms_dt_vio_supported_languages();
        $mailpoet_commit_block_reason = '';

        $defaults = array(
            'vendor_type' => '',
            'vendor_status' => 'unclaimed',
            'invite_mode' => 'general',
            'language_mode' => 'auto',
            'force_lang' => '',
            'next_n' => (int) ($settings['default_next_n'] ?? 8),
            'lookahead_days' => (int) ($settings['default_lookahead_days'] ?? 90),
            'venue_ids' => array(),
            'include_primary_vendor' => false,
            'include_tentative' => false,
        );

        $current = $defaults;
        if (!empty($preview['args']) && is_array($preview['args'])) {
            $current = array_merge($current, $preview['args']);
        }
        $current_venue_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($current['venue_ids'] ?? array())))));

        if (count($venue_options) === 1 && empty($current_venue_ids)) {
            $only_venue_id = (int) array_key_first($venue_options);
            if ($only_venue_id > 0) {
                $current_venue_ids = array($only_venue_id);
            }
        }

        echo '<h2 data-vms-tour="vendor-invites.send.filters">' . esc_html__('Preview and Commit Invites', 'vms-data-tools') . '</h2>';
        echo '<p>' . esc_html__('Preview rows first. Commit creates claim tokens, pushes MailPoet fields/tags, and writes audit logs for every row.', 'vms-data-tools') . '</p>';

        if (empty($settings['mailpoet_enabled'])) {
            $mailpoet_commit_block_reason = __('MailPoet delivery is disabled in Vendor Invite settings. Preview remains available, but commit is blocked until MailPoet delivery is enabled.', 'vms-data-tools');
        } elseif (!vms_dt_vio_mailpoet_is_active()) {
            $mailpoet_commit_block_reason = __('MailPoet is not active. Preview is available, but commit is blocked until MailPoet is installed and active.', 'vms-data-tools');
        }

        if ($mailpoet_commit_block_reason !== '') {
            echo '<div class="notice notice-warning"><p>' . esc_html($mailpoet_commit_block_reason) . '</p></div>';
        }

        echo '<form method="post" class="vms-dt-vio-send-form">';
        wp_nonce_field('vms_dt_vio_send_preview');
        echo '<input type="hidden" name="vms_dt_vio_action" value="send_preview">';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="vendor_type">' . esc_html__('Vendor type filter', 'vms-data-tools') . '</label></th><td>';
        echo '<select id="vendor_type" name="vendor_type">';
        echo '<option value="">' . esc_html__('All vendor types', 'vms-data-tools') . '</option>';
        foreach ($vendor_types as $slug => $name) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected((string) $current['vendor_type'], $slug, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="vendor_status">' . esc_html__('Vendor status filter', 'vms-data-tools') . '</label></th><td>';
        echo '<select id="vendor_status" name="vendor_status">';
        foreach (array(
            'unclaimed' => __('Unclaimed', 'vms-data-tools'),
            'invited' => __('Invited', 'vms-data-tools'),
            'claimed' => __('Claimed', 'vms-data-tools'),
            'disabled' => __('Disabled', 'vms-data-tools'),
            'all' => __('All', 'vms-data-tools'),
        ) as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $current['vendor_status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="invite_mode">' . esc_html__('Invite mode', 'vms-data-tools') . '</label></th><td>';
        echo '<select id="invite_mode" name="invite_mode">';
        echo '<option value="general" ' . selected((string) $current['invite_mode'], 'general', false) . '>' . esc_html__('General', 'vms-data-tools') . '</option>';
        echo '<option value="intent" ' . selected((string) $current['invite_mode'], 'intent', false) . '>' . esc_html__('Intent (with open dates)', 'vms-data-tools') . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="language_mode">' . esc_html__('Language routing', 'vms-data-tools') . '</label></th><td>';
        echo '<select id="language_mode" name="language_mode">';
        echo '<option value="auto" ' . selected((string) $current['language_mode'], 'auto', false) . '>' . esc_html__('Auto (vendor preferred language -> site default)', 'vms-data-tools') . '</option>';
        echo '<option value="force" ' . selected((string) $current['language_mode'], 'force', false) . '>' . esc_html__('Force language', 'vms-data-tools') . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="force_lang">' . esc_html__('Forced language', 'vms-data-tools') . '</label></th><td>';
        echo '<select id="force_lang" name="force_lang">';
        echo '<option value="">' . esc_html__('Use auto', 'vms-data-tools') . '</option>';
        foreach ($langs as $code => $label) {
            echo '<option value="' . esc_attr($code) . '" ' . selected((string) $current['force_lang'], $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="next_n">' . esc_html__('Next N open dates', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="next_n" type="number" min="1" max="30" name="next_n" value="' . esc_attr((string) absint($current['next_n'])) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="lookahead_days">' . esc_html__('Lookahead days', 'vms-data-tools') . '</label></th><td>';
        echo '<input id="lookahead_days" type="number" min="1" max="365" name="lookahead_days" value="' . esc_attr((string) absint($current['lookahead_days'])) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="venue_ids">' . esc_html__('Venue filter', 'vms-data-tools') . '</label></th><td>';
        if (empty($venue_options)) {
            echo '<p>' . esc_html__('No venues found. Invites will not be filtered by venue.', 'vms-data-tools') . '</p>';
        } elseif (count($venue_options) === 1) {
            $only_venue_id = (int) array_key_first($venue_options);
            $only_venue_label = (string) ($venue_options[$only_venue_id] ?? '');
            if ($only_venue_id > 0) {
                echo '<input type="hidden" name="venue_ids[]" value="' . esc_attr((string) $only_venue_id) . '">';
            }
            echo '<p><strong>' . esc_html($only_venue_label) . '</strong></p>';
            echo '<p class="description">' . esc_html__('Only one venue is available, so it is selected automatically.', 'vms-data-tools') . '</p>';
        } else {
            $size = min(8, max(4, count($venue_options)));
            echo '<select id="venue_ids" name="venue_ids[]" multiple size="' . esc_attr((string) $size) . '">';
            foreach ($venue_options as $venue_id => $venue_label) {
                $selected = in_array((int) $venue_id, $current_venue_ids, true);
                echo '<option value="' . esc_attr((string) $venue_id) . '" ' . selected($selected, true, false) . '>' . esc_html($venue_label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Leave all unselected to include all venues. Hold Cmd/Ctrl to select multiple venues.', 'vms-data-tools') . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Intent options', 'vms-data-tools') . '</th><td>';
        echo '<label><input type="checkbox" name="include_primary_vendor" value="1" ' . checked(!empty($current['include_primary_vendor']), true, false) . '> ' . esc_html__('Include primary vendor name', 'vms-data-tools') . '</label><br>';
        echo '<label><input type="checkbox" name="include_tentative" value="1" ' . checked(!empty($current['include_tentative']), true, false) . '> ' . esc_html__('Include tentative statuses', 'vms-data-tools') . '</label>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(__('Preview Invites', 'vms-data-tools'), 'primary');
        echo '</form>';

        if (!empty($commit_result)) {
            $summary = (array) ($commit_result['summary'] ?? array());
            echo '<h3>' . esc_html__('Commit Summary', 'vms-data-tools') . '</h3>';
            echo '<p>' . esc_html(sprintf(
                /* translators: 1: selected, 2: processed, 3: sent, 4: failed, 5: skipped */
                __('Selected %1$d | Processed %2$d | Sent %3$d | Failed %4$d | Skipped %5$d', 'vms-data-tools'),
                absint($summary['selected'] ?? 0),
                absint($summary['processed'] ?? 0),
                absint($summary['sent'] ?? 0),
                absint($summary['failed'] ?? 0),
                absint($summary['skipped'] ?? 0)
            )) . '</p>';

            $results = isset($commit_result['results']) && is_array($commit_result['results']) ? $commit_result['results'] : array();
            if (!empty($results)) {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Vendor', 'vms-data-tools') . '</th>';
                echo '<th>' . esc_html__('Email', 'vms-data-tools') . '</th>';
                echo '<th>' . esc_html__('Status', 'vms-data-tools') . '</th>';
                echo '<th>' . esc_html__('Message', 'vms-data-tools') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($results as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    echo '<tr>';
                    echo '<td>' . esc_html((string) ($row['vendor_name'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
                    echo '<td>' . esc_html(strtoupper((string) ($row['status'] ?? 'failed'))) . '</td>';
                    echo '<td>' . esc_html((string) ($row['message'] ?? '')) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        if (!empty($preview)) {
            $rows = isset($preview['rows']) && is_array($preview['rows']) ? $preview['rows'] : array();
            $summary = (array) ($preview['summary'] ?? array());
            $preview_includable = absint($summary['includable'] ?? 0);
            $can_commit = ($preview_token !== '' && $mailpoet_commit_block_reason === '' && $preview_includable > 0);
            echo '<h3 data-vms-tour="vendor-invites.send.preview">' . esc_html__('Preview Results', 'vms-data-tools') . '</h3>';
            echo '<p>' . esc_html(sprintf(
                /* translators: 1: total rows, 2: includable rows, 3: missing email, 4: invalid email, 5: missing vendor type */
                __('Total %1$d | Includable %2$d | Missing email %3$d | Invalid email %4$d | Missing vendor type %5$d', 'vms-data-tools'),
                absint($summary['total'] ?? 0),
                $preview_includable,
                absint($summary['missing_email'] ?? 0),
                absint($summary['invalid_email'] ?? 0),
                absint($summary['missing_vendor_type'] ?? 0)
            )) . '</p>';

            echo '<form method="post">';
            wp_nonce_field('vms_dt_vio_send_commit');
            echo '<input type="hidden" name="vms_dt_vio_action" value="send_commit">';
            echo '<input type="hidden" name="preview_token" value="' . esc_attr($preview_token) . '">';

            echo '<table class="widefat striped vms-dt-vio-preview-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Include', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Vendor', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Email', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Language', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Claim Link', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Open Dates', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Tag', 'vms-data-tools') . '</th>';
            echo '<th>' . esc_html__('Warnings', 'vms-data-tools') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $vendor_id = absint($row['vendor_id'] ?? 0);
                $row_includable = !empty($row['includable']);
                $warnings = isset($row['warnings']) && is_array($row['warnings']) ? $row['warnings'] : array();

                echo '<tr>';
                echo '<td>';
                echo '<label><input type="checkbox" name="include_vendor_ids[]" value="' . esc_attr((string) $vendor_id) . '" ' . checked($row_includable, true, false) . ' ' . disabled($row_includable, false, false) . '> ' . esc_html__('Send', 'vms-data-tools') . '</label>';
                echo '</td>';

                echo '<td>' . esc_html((string) ($row['vendor_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['lang'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($row['claim_link_masked'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) absint($row['open_dates_count'] ?? 0));
                if (!empty($row['open_dates_snippet'])) {
                    echo '<details><summary>' . esc_html__('Snippet preview', 'vms-data-tools') . '</summary>';
                    echo wp_kses_post((string) $row['open_dates_snippet']);
                    echo '</details>';
                }
                echo '</td>';
                echo '<td><code>' . esc_html((string) ($row['mailpoet_tag'] ?? '')) . '</code></td>';
                echo '<td>';
                if (!empty($warnings)) {
                    foreach ($warnings as $warning) {
                        echo '<div>' . esc_html((string) $warning) . '</div>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p><label><input type="checkbox" name="force_resend" value="1"> ' . esc_html__('Force resend (bypass cooldown)', 'vms-data-tools') . '</label></p>';
            if (!$can_commit) {
                if ($mailpoet_commit_block_reason !== '') {
                    echo '<p class="description">' . esc_html($mailpoet_commit_block_reason) . '</p>';
                } elseif ($preview_includable <= 0) {
                    echo '<p class="description">' . esc_html__('No preview rows are currently eligible for commit.', 'vms-data-tools') . '</p>';
                }
            }
            submit_button(
                __('Commit Selected Invites', 'vms-data-tools'),
                'primary',
                'submit',
                true,
                $can_commit ? array() : array('disabled' => 'disabled')
            );
            echo '</form>';
        }
    }
}

if (!function_exists('vms_dt_vio_render_interest_tab')) {
    function vms_dt_vio_render_interest_tab(): void
    {
        $vendor_types = vms_dt_vio_admin_vendor_type_options();
        $status_labels = vms_dt_vio_opportunity_status_labels();
        $filters = array(
            'vendor_type' => isset($_GET['vendor_type']) ? sanitize_key((string) wp_unslash($_GET['vendor_type'])) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : '',
            'status' => isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'pending',
            'limit' => 300,
        );

        if ($filters['status'] === '') {
            $filters['status'] = 'pending';
        }

        $rows = vms_dt_vio_list_opportunity_submissions($filters);
        $groups = vms_dt_vio_group_opportunity_submissions_by_event($rows);
        $tz = vms_dt_has_core_function('vms_get_timezone') ? vms_dt_call_core_function('vms_get_timezone') : wp_timezone();
        $date_format = (string) get_option('date_format', 'M j, Y');

        $render_hidden_filters = static function () use ($filters): void {
            foreach ($filters as $key => $value) {
                if ($key === 'limit') {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
            }
        };

        echo '<h2 data-vms-tour="vendor-invites.interest.queue">' . esc_html__('Vendor Interest Queue', 'vms-data-tools') . '</h2>';
        echo '<p>' . esc_html__('Track vendor interest submissions, assign a vendor to the Event Plan, and keep pending responses visible until the opportunity is filled.', 'vms-data-tools') . '</p>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(vms_dt_get_menu_slug_vendor_invites()) . '">';
        echo '<input type="hidden" name="tab" value="interest">';
        echo '<div class="vms-dt-vio-log-filters" data-vms-tour="vendor-invites.interest.filters">';

        echo '<label>' . esc_html__('Vendor type', 'vms-data-tools') . '<br>';
        echo '<select name="vendor_type">';
        echo '<option value="">' . esc_html__('All', 'vms-data-tools') . '</option>';
        foreach ($vendor_types as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected($filters['vendor_type'], $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';

        echo '<label>' . esc_html__('Status', 'vms-data-tools') . '<br>';
        echo '<select name="status">';
        echo '<option value="all"' . selected($filters['status'], 'all', false) . '>' . esc_html__('All', 'vms-data-tools') . '</option>';
        foreach ($status_labels as $status => $label) {
            echo '<option value="' . esc_attr($status) . '"' . selected($filters['status'], $status, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';

        echo '<label>' . esc_html__('Date from', 'vms-data-tools') . '<br>';
        echo '<input type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '"></label>';

        echo '<label>' . esc_html__('Date to', 'vms-data-tools') . '<br>';
        echo '<input type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '"></label>';

        echo '<div class="vms-dt-vio-filter-actions">';
        submit_button(__('Filter Queue', 'vms-data-tools'), 'secondary', '', false);
        echo '</div>';

        echo '</div>';
        echo '</form>';

        if (empty($groups)) {
            echo '<p class="description">' . esc_html__('No interest submissions match the current filters.', 'vms-data-tools') . '</p>';
            return;
        }

        echo '<div class="vms-dt-vio-interest-queue">';
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $event_date = trim((string) ($group['event_date'] ?? ''));
            $event_label = $event_date;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
                $ts = strtotime($event_date . ' 12:00:00');
                if ($ts !== false) {
                    $event_label = wp_date($date_format, (int) $ts, $tz);
                }
            }

            echo '<section class="vms-dt-vio-interest-card">';
            echo '<div class="vms-dt-vio-interest-card__header">';
            echo '<div>';
            echo '<h3>' . esc_html((string) ($group['event_title'] ?? __('Untitled Event Plan', 'vms-data-tools'))) . '</h3>';
            echo '<p class="description">';
            echo esc_html($event_label !== '' ? $event_label : __('Date unavailable', 'vms-data-tools'));
            if ((string) ($group['venue_name'] ?? '') !== '') {
                echo ' | ' . esc_html((string) $group['venue_name']);
            }
            if ((string) ($group['vendor_type_label'] ?? '') !== '') {
                echo ' | ' . esc_html((string) $group['vendor_type_label']);
            }
            if ((string) ($group['event_status_label'] ?? '') !== '') {
                echo ' | ' . esc_html((string) $group['event_status_label']);
            }
            echo '</p>';
            echo '</div>';
            echo '</div>';

            echo '<div class="vms-dt-vio-interest-list">';
            foreach ((array) ($group['submissions'] ?? array()) as $submission) {
                if (!is_array($submission)) {
                    continue;
                }

                $submission_id = absint($submission['id'] ?? 0);
                $status = vms_dt_vio_normalize_opportunity_status((string) ($submission['status'] ?? 'pending'));
                $status_label = (string) ($submission['status_label'] ?? ($status_labels[$status] ?? ucfirst($status)));
                $submitted_at = trim((string) ($submission['submitted_at'] ?? ''));

                echo '<article class="vms-dt-vio-interest-row">';
                echo '<div class="vms-dt-vio-interest-row__meta">';
                echo '<strong>' . esc_html((string) ($submission['vendor_name'] ?? '')) . '</strong>';
                echo '<span class="vms-dt-vio-interest-status vms-dt-vio-interest-status--' . esc_attr($status) . '">' . esc_html($status_label) . '</span>';
                if ($submitted_at !== '') {
                    echo '<div class="description">' . sprintf(
                        /* translators: %s is a UTC datetime string. */
                        esc_html__('Submitted %s UTC', 'vms-data-tools'),
                        esc_html($submitted_at)
                    ) . '</div>';
                }
                if ((string) ($submission['note'] ?? '') !== '') {
                    echo '<div class="description">' . esc_html((string) $submission['note']) . '</div>';
                }
                echo '</div>';

                echo '<div class="vms-dt-vio-interest-row__actions">';
                if ($status === 'pending') {
                    echo '<form method="post" class="vms-dt-vio-inline-form">';
                    wp_nonce_field('vms_dt_vio_assign_interest_submission');
                    echo '<input type="hidden" name="page" value="' . esc_attr(vms_dt_get_menu_slug_vendor_invites()) . '">';
                    echo '<input type="hidden" name="tab" value="interest">';
                    $render_hidden_filters();
                    echo '<input type="hidden" name="vms_dt_vio_action" value="assign_interest_submission">';
                    echo '<input type="hidden" name="submission_id" value="' . esc_attr((string) $submission_id) . '">';
                    echo '<button class="button button-primary button-small" type="submit">' . esc_html__('Assign Vendor', 'vms-data-tools') . '</button>';
                    echo '</form>';

                    echo '<form method="post" class="vms-dt-vio-inline-form">';
                    wp_nonce_field('vms_dt_vio_decline_interest_submission');
                    echo '<input type="hidden" name="page" value="' . esc_attr(vms_dt_get_menu_slug_vendor_invites()) . '">';
                    echo '<input type="hidden" name="tab" value="interest">';
                    $render_hidden_filters();
                    echo '<input type="hidden" name="vms_dt_vio_action" value="decline_interest_submission">';
                    echo '<input type="hidden" name="submission_id" value="' . esc_attr((string) $submission_id) . '">';
                    echo '<button class="button button-small" type="submit">' . esc_html__('Decline', 'vms-data-tools') . '</button>';
                    echo '</form>';
                }
                echo '</div>';
                echo '</article>';
            }
            echo '</div>';
            echo '</section>';
        }
        echo '</div>';
    }

}

if (!function_exists('vms_dt_vio_collect_log_filters_from_request')) {
    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    function vms_dt_vio_collect_log_filters_from_request(array $source): array
    {
        return array(
            'event_type' => sanitize_key((string) ($source['event_type'] ?? '')),
            'vendor_id' => absint($source['vendor_id'] ?? 0),
            'mode' => sanitize_key((string) ($source['mode'] ?? '')),
            'status' => sanitize_key((string) ($source['status'] ?? '')),
            'date_from' => sanitize_text_field((string) ($source['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string) ($source['date_to'] ?? '')),
            'retention_state' => vms_dt_vio_normalize_retention_filter((string) ($source['retention_state'] ?? 'active')),
            'limit' => 200,
        );
    }
}

if (!function_exists('vms_dt_vio_render_hidden_log_filter_fields')) {
    /**
     * @param array<string,mixed> $filters
     */
    function vms_dt_vio_render_hidden_log_filter_fields(array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($key === 'limit') {
                continue;
            }

            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
    }
}

if (!function_exists('vms_dt_vio_render_retention_preview_panel')) {
    /**
     * @param array<string,mixed> $preview
     */
    function vms_dt_vio_render_retention_preview_panel(array $preview, string $preview_token): void
    {
        if (empty($preview) || $preview_token === '') {
            return;
        }

        $summary = isset($preview['summary']) && is_array($preview['summary']) ? (array) $preview['summary'] : array();
        $rows = isset($preview['rows']) && is_array($preview['rows']) ? (array) $preview['rows'] : array();
        $action = sanitize_key((string) ($preview['action'] ?? ''));
        $action_label = trim((string) ($preview['action_label'] ?? ''));
        if ($action_label === '') {
            $action_label = ucfirst($action);
        }

        $eligible_count = absint($summary['eligible_count'] ?? 0);
        $candidate_count = absint($summary['candidate_count'] ?? 0);
        $skipped_count = absint($summary['skipped_count'] ?? 0);
        $token_delete_count = absint($summary['token_delete_count'] ?? 0);
        $blocked_used_tokens = absint($summary['blocked_used_tokens'] ?? 0);
        $skip_reasons = isset($summary['skip_reasons']) && is_array($summary['skip_reasons']) ? (array) $summary['skip_reasons'] : array();
        $can_commit = ($preview_token !== '' && $eligible_count > 0);

        echo '<section class="vms-dt-vio-retention-preview" data-vms-tour="vendor-invites.logs.retention-preview">';
        echo '<h3>' . esc_html(sprintf(
            /* translators: %s is a retention action label. */
            __('Retention Preview: %s', 'vms-data-tools'),
            $action_label
        )) . '</h3>';
        echo '<p>' . esc_html(sprintf(
            /* translators: 1: candidate count, 2: eligible count, 3: skipped count, 4: token delete count */
            __('Candidates %1$d | Eligible %2$d | Skipped %3$d | Unused tokens to delete %4$d', 'vms-data-tools'),
            $candidate_count,
            $eligible_count,
            $skipped_count,
            $token_delete_count
        )) . '</p>';

        if ($action === 'purge') {
            echo '<p class="description">' . esc_html__('Only archived eligible rows will be removed. Successful production rows stay protected, and retention audit entries remain after purge.', 'vms-data-tools') . '</p>';
        }
        if (!$can_commit) {
            echo '<p class="description">' . esc_html__('This preview has no eligible rows to commit.', 'vms-data-tools') . '</p>';
        }

        if (!empty($skip_reasons)) {
            echo '<div class="vms-dt-vio-retention-preview__reasons">';
            echo '<strong>' . esc_html__('Skip summary', 'vms-data-tools') . '</strong>';
            echo '<ul>';
            foreach ($skip_reasons as $reason => $count) {
                echo '<li>' . esc_html(sprintf(
                    /* translators: 1: skipped count, 2: skip reason */
                    __('%1$d rows: %2$s', 'vms-data-tools'),
                    absint($count),
                    (string) $reason
                )) . '</li>';
            }
            if ($blocked_used_tokens > 0) {
                echo '<li>' . esc_html(sprintf(
                    /* translators: %d is a count of blocked used tokens. */
                    __('%d rows were blocked because a linked token was already used.', 'vms-data-tools'),
                    $blocked_used_tokens
                )) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '<table class="widefat striped vms-dt-vio-retention-preview-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Log ID', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Sent At', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Event Type', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Vendor', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Status', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Retention State', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Token Impact', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Outcome', 'vms-data-tools') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="8">' . esc_html__('No sample rows are available for this retention preview.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                echo '<tr>';
                echo '<td>' . esc_html((string) absint($row['log_id'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['sent_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['event_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['vendor_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html(strtoupper((string) ($row['status'] ?? ''))) . '</td>';
                echo '<td>' . esc_html((string) ($row['retention_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['token_impact'] ?? '')) . '</td>';
                echo '<td>';
                echo '<strong>' . esc_html((string) ($row['outcome'] ?? '')) . '</strong>';
                if ((string) ($row['outcome_detail'] ?? '') !== '') {
                    echo '<div class="description">' . esc_html((string) $row['outcome_detail']) . '</div>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<form method="post" class="vms-dt-vio-retention-commit-form">';
        wp_nonce_field('vms_dt_vio_retention_commit');
        echo '<input type="hidden" name="vms_dt_vio_action" value="retention_commit">';
        echo '<input type="hidden" name="retention_preview_token" value="' . esc_attr($preview_token) . '">';
        echo '<p><label><input type="checkbox" name="retention_acknowledge" value="1"> ' . esc_html__('I understand this action will update invite-log retention state.', 'vms-data-tools') . '</label></p>';
        echo '<p><label for="operator_note"><strong>' . esc_html__('Operator note', 'vms-data-tools') . '</strong></label><br>';
        echo '<textarea id="operator_note" name="operator_note" rows="3" class="large-text"></textarea></p>';

        if ($action === 'purge') {
            echo '<p><label for="purge_phrase"><strong>' . esc_html__('Type PURGE to confirm', 'vms-data-tools') . '</strong></label><br>';
            echo '<input id="purge_phrase" type="text" name="purge_phrase" class="regular-text" autocomplete="off"></p>';
        }

        submit_button(
            sprintf(
                /* translators: %s is a retention action label. */
                __('Commit %s', 'vms-data-tools'),
                $action_label
            ),
            'primary',
            'submit',
            true,
            $can_commit ? array() : array('disabled' => 'disabled')
        );
        echo '</form>';
        echo '</section>';
    }
}

if (!function_exists('vms_dt_vio_render_logs_tab')) {
    /**
     * @param array<string,mixed> $retention_preview
     * @param array<string,mixed> $retention_commit_result
     */
    function vms_dt_vio_render_logs_tab(array $retention_preview = array(), string $retention_preview_token = '', array $retention_commit_result = array()): void
    {
        $event_type_labels = vms_dt_vio_admin_event_type_labels();
        $filters = vms_dt_vio_collect_log_filters_from_request((array) $_GET);
        $retention_state_labels = vms_dt_vio_get_retention_state_labels();
        $current_retention_filter = vms_dt_vio_normalize_retention_filter((string) ($filters['retention_state'] ?? 'active'));

        $logs = vms_dt_vio_get_logs($filters);

        echo '<h2 data-vms-tour="vendor-invites.logs.audit">' . esc_html__('Invite Logs', 'vms-data-tools') . '</h2>';
        echo '<p>' . esc_html__('Every invite send and claim attempt is logged for auditability.', 'vms-data-tools') . '</p>';
        if (!empty($retention_commit_result['batch_id'])) {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: %s is a retention batch ID. */
                __('Last retention batch: %s', 'vms-data-tools'),
                (string) $retention_commit_result['batch_id']
            )) . '</p>';
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr(vms_dt_get_menu_slug_vendor_invites()) . '">';
        echo '<input type="hidden" name="tab" value="logs">';

        echo '<div class="vms-dt-vio-log-filters">';

        echo '<label>' . esc_html__('Event', 'vms-data-tools') . '<br>';
        echo '<select name="event_type">';
        echo '<option value="">' . esc_html__('All', 'vms-data-tools') . '</option>';
        foreach ($event_type_labels as $event_type => $label) {
            echo '<option value="' . esc_attr($event_type) . '" ' . selected($filters['event_type'], $event_type, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';

        echo '<label>' . esc_html__('Vendor ID', 'vms-data-tools') . '<br>';
        echo '<input type="number" name="vendor_id" min="0" value="' . esc_attr((string) $filters['vendor_id']) . '"></label>';

        echo '<label>' . esc_html__('Mode', 'vms-data-tools') . '<br>';
        echo '<select name="mode">';
        echo '<option value="">' . esc_html__('All', 'vms-data-tools') . '</option>';
        echo '<option value="general" ' . selected($filters['mode'], 'general', false) . '>' . esc_html__('General', 'vms-data-tools') . '</option>';
        echo '<option value="intent" ' . selected($filters['mode'], 'intent', false) . '>' . esc_html__('Intent', 'vms-data-tools') . '</option>';
        echo '</select></label>';

        echo '<label>' . esc_html__('Status', 'vms-data-tools') . '<br>';
        echo '<select name="status">';
        echo '<option value="">' . esc_html__('All', 'vms-data-tools') . '</option>';
        echo '<option value="sent" ' . selected($filters['status'], 'sent', false) . '>' . esc_html__('Sent', 'vms-data-tools') . '</option>';
        echo '<option value="failed" ' . selected($filters['status'], 'failed', false) . '>' . esc_html__('Failed', 'vms-data-tools') . '</option>';
        echo '<option value="queued" ' . selected($filters['status'], 'queued', false) . '>' . esc_html__('Queued', 'vms-data-tools') . '</option>';
        echo '</select></label>';

        echo '<label>' . esc_html__('Date from', 'vms-data-tools') . '<br>';
        echo '<input type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '"></label>';

        echo '<label>' . esc_html__('Date to', 'vms-data-tools') . '<br>';
        echo '<input type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '"></label>';

        echo '<label>' . esc_html__('Retention', 'vms-data-tools') . '<br>';
        echo '<select name="retention_state">';
        echo '<option value="active" ' . selected($current_retention_filter, 'active', false) . '>' . esc_html__('Active', 'vms-data-tools') . '</option>';
        echo '<option value="archived" ' . selected($current_retention_filter, 'archived', false) . '>' . esc_html__('Archived', 'vms-data-tools') . '</option>';
        echo '<option value="all" ' . selected($current_retention_filter, 'all', false) . '>' . esc_html__('All', 'vms-data-tools') . '</option>';
        echo '</select></label>';

        echo '<div class="vms-dt-vio-filter-actions">';
        submit_button(__('Filter Logs', 'vms-data-tools'), 'secondary', '', false);
        echo '</div>';

        echo '</div>';
        echo '</form>';

        echo '<section class="vms-dt-vio-retention-panel" data-vms-tour="vendor-invites.logs.retention-panel">';
        echo '<h3>' . esc_html__('Retention Controls', 'vms-data-tools') . '</h3>';
        echo '<p>' . esc_html__('Archive hides logs from the normal working view but keeps them auditable. Restore brings archived rows back. Purge permanently removes only eligible archived rows and writes a retention audit first.', 'vms-data-tools') . '</p>';
        echo '<div class="vms-dt-vio-retention-actions">';

        if ($current_retention_filter === 'active' || $current_retention_filter === 'all') {
            echo '<form method="post" class="vms-dt-vio-inline-form">';
            wp_nonce_field('vms_dt_vio_retention_preview');
            echo '<input type="hidden" name="vms_dt_vio_action" value="retention_preview">';
            echo '<input type="hidden" name="retention_action" value="archive">';
            vms_dt_vio_render_hidden_log_filter_fields($filters);
            echo '<button class="button button-secondary" type="submit">' . esc_html__('Preview Archive Filtered Logs', 'vms-data-tools') . '</button>';
            echo '</form>';
        }

        if ($current_retention_filter === 'archived' || $current_retention_filter === 'all') {
            echo '<form method="post" class="vms-dt-vio-inline-form">';
            wp_nonce_field('vms_dt_vio_retention_preview');
            echo '<input type="hidden" name="vms_dt_vio_action" value="retention_preview">';
            echo '<input type="hidden" name="retention_action" value="restore">';
            vms_dt_vio_render_hidden_log_filter_fields($filters);
            echo '<button class="button button-secondary" type="submit">' . esc_html__('Preview Restore Filtered Logs', 'vms-data-tools') . '</button>';
            echo '</form>';

            echo '<form method="post" class="vms-dt-vio-inline-form">';
            wp_nonce_field('vms_dt_vio_retention_preview');
            echo '<input type="hidden" name="vms_dt_vio_action" value="retention_preview">';
            echo '<input type="hidden" name="retention_action" value="purge">';
            vms_dt_vio_render_hidden_log_filter_fields($filters);
            echo '<button class="button button-secondary" type="submit">' . esc_html__('Preview Purge Eligible Archived Logs', 'vms-data-tools') . '</button>';
            echo '</form>';
        }

        echo '</div>';
        echo '</section>';

        vms_dt_vio_render_retention_preview_panel($retention_preview, $retention_preview_token);

        echo '<table class="widefat striped" data-vms-tour="vendor-invites.logs.table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Sent At (UTC)', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Event', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Vendor', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Mode', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Lang', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Tag', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Claimed', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Status', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Retention', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Error', 'vms-data-tools') . '</th>';
        echo '<th>' . esc_html__('Actions', 'vms-data-tools') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($logs)) {
            echo '<tr><td colspan="11">' . esc_html__('No logs found for the selected filters.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ($logs as $log) {
                if (!is_array($log)) {
                    continue;
                }

                $log_id = absint($log['id'] ?? 0);
                $vendor_id = absint($log['vendor_id'] ?? 0);
                $vendor_name = trim((string) ($log['vendor_name'] ?? ''));
                if ($vendor_name === '') {
                    $vendor_name = sprintf(
                        /* translators: %d is a vendor id. */
                        __('Vendor #%d', 'vms-data-tools'),
                        $vendor_id
                    );
                }

                $claim_used = trim((string) ($log['claim_used_at'] ?? ''));
                $claimed = ($claim_used !== '' && $claim_used !== '0000-00-00 00:00:00')
                    ? __('Yes', 'vms-data-tools')
                    : __('No', 'vms-data-tools');
                $event_type = sanitize_key((string) ($log['event_type'] ?? ''));
                $event_label = (string) ($event_type_labels[$event_type] ?? $event_type);
                $status = sanitize_key((string) ($log['status'] ?? ''));
                $retention_state = vms_dt_vio_normalize_retention_state((string) ($log['retention_state'] ?? 'active'));
                $retention_label = (string) ($retention_state_labels[$retention_state] ?? ucfirst($retention_state));
                $archived_at = trim((string) ($log['archived_at'] ?? ''));

                echo '<tr>';
                echo '<td>' . esc_html((string) ($log['sent_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html($event_label) . '</td>';
                echo '<td>' . esc_html($vendor_name) . '</td>';
                echo '<td>' . esc_html((string) ($log['mode'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($log['lang'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($log['mailpoet_tag'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html($claimed) . '</td>';
                echo '<td>' . esc_html(strtoupper((string) ($log['status'] ?? ''))) . '</td>';
                echo '<td>';
                echo '<span class="vms-dt-vio-retention-badge vms-dt-vio-retention-badge--' . esc_attr($retention_state) . '">' . esc_html($retention_label) . '</span>';
                if ($retention_state === 'archived' && $archived_at !== '') {
                    echo '<div class="description">' . esc_html($archived_at) . '</div>';
                }
                echo '</td>';
                echo '<td>' . esc_html((string) ($log['error_message'] ?? '')) . '</td>';

                echo '<td>';
                if ($event_type === 'invite_send') {
                    echo '<form method="post" class="vms-dt-vio-inline-form">';
                    wp_nonce_field('vms_dt_vio_resend_from_log');
                    echo '<input type="hidden" name="vms_dt_vio_action" value="resend_from_log">';
                    echo '<input type="hidden" name="log_id" value="' . esc_attr((string) $log_id) . '">';
                    echo '<button class="button button-small" type="submit">' . esc_html__('Resend', 'vms-data-tools') . '</button>';
                    echo '<label><input type="checkbox" name="force_resend" value="1"> ' . esc_html__('Force', 'vms-data-tools') . '</label>';
                    echo '</form>';
                }

                echo '<form method="post" class="vms-dt-vio-inline-form">';
                wp_nonce_field('vms_dt_vio_generate_claim_link');
                echo '<input type="hidden" name="vms_dt_vio_action" value="generate_claim_link">';
                echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
                echo '<button class="button button-small" type="submit">' . esc_html__('Generate Claim Link', 'vms-data-tools') . '</button>';
                echo '</form>';

                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }
}
