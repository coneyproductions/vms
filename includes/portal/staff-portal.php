<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Staff Portal Shortcode
 * Uses user_meta: _vms_staff_id (links WP user to a vms_staff post)
 *
 * Tabs:
 *  - dashboard
 *  - tax-profile (contractors)
 *  - employee-packet (employees)
 *  - availability
 */

add_shortcode('vms_staff_portal', 'vms_staff_portal_shortcode');

if (!function_exists('vms_staff_portal_provider_label')) {
    function vms_staff_portal_provider_label(string $provider): string
    {
        if (function_exists('vms_tax_provider_label')) {
            return (string) vms_tax_provider_label($provider);
        }

        if ($provider === 'quickbooks_email') return 'QuickBooks Online';
        if ($provider === 'tax1099_email') return 'Tax1099';
        return 'Upload';
    }
}

if (!function_exists('vms_staff_portal_tax_provider')) {
    function vms_staff_portal_tax_provider(): string
    {
        if (function_exists('vms_tax_settings_get_provider')) {
            return (string) vms_tax_settings_get_provider();
        }

        $settings = get_option('vms_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : 'upload';
        if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
            $provider = 'upload';
        }

        return $provider;
    }
}

if (!function_exists('vms_staff_portal_tax_status')) {
    function vms_staff_portal_tax_status(int $staff_id): array
    {
        $staff_id = absint($staff_id);
        $provider = vms_staff_portal_tax_provider();

        $k_done = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'tax_profile_completed_at') : '_vms_tax_profile_completed_at';
        $k_attest = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'w9_attested_at') : '_vms_w9_external_vendor_attested_at';
        $k_prov = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'w9_provider') : '_vms_w9_offsite_provider';
        $k_confirmed_at = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'tax_admin_confirmed_at') : '_vms_tax_admin_confirmed_at';
        $k_confirmed_by = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'tax_admin_confirmed_by') : '_vms_tax_admin_confirmed_by';

        $done_at = (int) get_post_meta($staff_id, $k_done, true);
        $attested_at = (int) get_post_meta($staff_id, $k_attest, true);
        $stored_provider = (string) get_post_meta($staff_id, $k_prov, true);
        if (!in_array($stored_provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
            $stored_provider = '';
        }
        $effective_provider = ($done_at > 0 && $stored_provider !== '') ? $stored_provider : $provider;

        $admin_confirmed_at = (int) get_post_meta($staff_id, $k_confirmed_at, true);
        if ($admin_confirmed_at <= 0 && $done_at > 0) {
            $admin_confirmed_at = $done_at;
        }
        $admin_confirmed_by = (int) get_post_meta($staff_id, $k_confirmed_by, true);
        $admin_user = $admin_confirmed_by > 0 ? get_user_by('id', $admin_confirmed_by) : null;
        $admin_name = $admin_user ? (string) ($admin_user->display_name ?: $admin_user->user_login) : '';

        $missing = function_exists('bvmgr_vendor_tax_profile_missing_items')
            ? (array) bvmgr_vendor_tax_profile_missing_items($staff_id)
            : array();

        if ($done_at > 0) {
            $stage = 'complete';
            $label = __('Complete', 'backstage-venue-manager');
        } elseif ($effective_provider !== 'upload' && $attested_at > 0) {
            $stage = 'submitted';
            $label = __('Submitted', 'backstage-venue-manager');
        } else {
            $stage = 'incomplete';
            $label = __('Incomplete', 'backstage-venue-manager');
        }

        return array(
            'provider' => $effective_provider,
            'provider_label' => vms_staff_portal_provider_label($effective_provider),
            'global_provider' => $provider,
            'done_at' => $done_at,
            'attested_at' => $attested_at,
            'admin_confirmed_at' => $admin_confirmed_at,
            'admin_confirmed_by' => $admin_confirmed_by,
            'admin_confirmed_by_name' => $admin_name,
            'missing' => $missing,
            'is_complete' => ($done_at > 0),
            'stage' => $stage,
            'stage_label' => $label,
        );
    }
}

if (!function_exists('vms_staff_portal_badge_html')) {
    function vms_staff_portal_badge_html(string $stage): string
    {
        $stage = sanitize_key($stage);
        if ($stage === 'complete') {
            return '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>';
        }
        if ($stage === 'submitted') {
            return '<span class="vms-badge vms-badge-warn">' . esc_html__('Submitted', 'backstage-venue-manager') . '</span>';
        }
        return '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>';
    }
}

if (!function_exists('vms_staff_portal_format_ts')) {
    function vms_staff_portal_format_ts(int $ts, string $format = 'M j, Y g:ia'): string
    {
        if ($ts <= 0) {
            return '';
        }
        return wp_date($format, $ts, wp_timezone());
    }
}

if (!function_exists('vms_staff_portal_safe_html_allowed_html')) {
    function vms_staff_portal_safe_html_allowed_html(): array
    {
        return array(
            'a' => array(
                'class' => true,
                'href' => true,
                'loading' => true,
                'rel' => true,
                'target' => true,
            ),
            'div' => array(
                'class' => true,
                'tabindex' => true,
            ),
            'img' => array(
                'alt' => true,
                'class' => true,
                'loading' => true,
                'src' => true,
            ),
            'p' => array(
                'class' => true,
            ),
            'span' => array(
                'aria-hidden' => true,
                'class' => true,
            ),
        );
    }
}

if (!function_exists('vms_staff_portal_safe_html')) {
    function vms_staff_portal_safe_html(string $html): string
    {
        return wp_kses($html, vms_staff_portal_safe_html_allowed_html());
    }
}

if (!function_exists('vms_staff_portal_notice_html')) {
    function vms_staff_portal_notice_html(string $type, string $message): string
    {
        if (function_exists('bvmgr_portal_notice')) {
            return vms_staff_portal_safe_html(bvmgr_portal_notice($type, $message));
        }

        return '<p>' . esc_html($message) . '</p>';
    }
}

if (!function_exists('vms_staff_portal_certification_status_badge')) {
    function vms_staff_portal_certification_status_badge(string $status): string
    {
        $status = sanitize_key($status);
        $label = function_exists('vms_staffing_staff_qualification_status_label')
            ? (string) vms_staffing_staff_qualification_status_label($status)
            : ucwords(str_replace('_', ' ', $status));
        $class = 'vms-badge';
        if ($status === 'active') {
            $class .= ' vms-badge-ok';
        } elseif ($status === 'pending_verification') {
            $class .= ' vms-badge-warn';
        } else {
            $class .= ' vms-badge-miss';
        }
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }
}

if (!function_exists('vms_staff_portal_handle_certification_submission')) {
    function vms_staff_portal_handle_certification_submission(int $staff_id): string
    {
        $staff_id = absint($staff_id);
        if ($staff_id <= 0 || !isset($_POST['vms_staff_certification_submit'])) {
            return '';
        }
        $nonce = (isset($_POST['vms_staff_certification_nonce']) && !is_array($_POST['vms_staff_certification_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_certification_nonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_certification_submit')) {
            return vms_staff_portal_notice_html('error', __('Could not verify the certification upload. Please refresh and try again.', 'backstage-venue-manager'));
        }

        $name = vms_staffing_normalize_qualification_name(vms_staff_portal_post_text_field('vms_certification_name'));
        if ($name === '') {
            return vms_staff_portal_notice_html('error', __('Please choose or enter the certification type before uploading.', 'backstage-venue-manager'));
        }

        if (!bvmgr_upload_request_has_file($_FILES, 'vms_staff_certification_file')) {
            return vms_staff_portal_notice_html('error', __('Please choose a certificate file to upload.', 'backstage-venue-manager'));
        }

        $file_id = function_exists('bvmgr_private_staff_cert_store_upload')
            ? bvmgr_private_staff_cert_store_upload($staff_id, $_FILES)
            : new WP_Error('staff_cert_upload_unavailable', __('The certificate upload handler is unavailable.', 'backstage-venue-manager'));
        if (is_wp_error($file_id)) {
            /* translators: %s: upload error message from WordPress media handling. */
            return vms_staff_portal_notice_html('error', sprintf(__('Upload failed: %s', 'backstage-venue-manager'), $file_id->get_error_message()));
        }

        $authority = isset($_POST['vms_certification_authority']) ? sanitize_text_field((string) wp_unslash($_POST['vms_certification_authority'])) : '';
        $credential_number = isset($_POST['vms_certification_number']) ? sanitize_text_field((string) wp_unslash($_POST['vms_certification_number'])) : '';
        $issue_date = isset($_POST['vms_certification_issue_date']) ? sanitize_text_field((string) wp_unslash($_POST['vms_certification_issue_date'])) : '';
        $expiration_date = isset($_POST['vms_certification_expiration_date']) ? sanitize_text_field((string) wp_unslash($_POST['vms_certification_expiration_date'])) : '';

        $row = array(
            'name' => $name,
            'authority' => $authority,
            'credential_number' => $credential_number,
            'issue_date' => $issue_date,
            'expiration_date' => $expiration_date,
            'attachment_id' => absint($file_id),
            'storage_kind' => 'private_file',
            'proof_url' => '',
        );

        $result = function_exists('vms_staffing_add_staff_qualification_submission')
            ? vms_staffing_add_staff_qualification_submission($staff_id, $row, get_current_user_id())
            : array('ok' => false, 'message' => __('Certification upload saved, but the qualification workflow is unavailable.', 'backstage-venue-manager'));

        if (empty($result['ok'])) {
            return vms_staff_portal_notice_html('error', (string) ($result['message'] ?? __('Could not save the certification submission.', 'backstage-venue-manager')));
        }

        return vms_staff_portal_notice_html('success', __('Certificate uploaded. It is pending review, and you will receive an email when it is approved or if it needs correction.', 'backstage-venue-manager'));
    }
}


if (!function_exists('vms_staff_portal_render_certifications')) {
    function vms_staff_portal_render_certifications(int $staff_id): void
    {
        $staff_id = absint($staff_id);
        if ($staff_id <= 0) {
            echo '<p>' . esc_html__('Staff profile not found.', 'backstage-venue-manager') . '</p>';
            return;
        }

        echo wp_kses(vms_staff_portal_safe_html(vms_staff_portal_handle_certification_submission($staff_id)), vms_staff_portal_safe_html_allowed_html());

        $rows = function_exists('vms_staffing_get_staff_qualifications')
            ? (array) vms_staffing_get_staff_qualifications($staff_id)
            : array();

        $suggestions = array('TABC', 'Food Handler Permit', 'Security License', 'CPR / First Aid');
        $suggestions = apply_filters('vms_staff_certification_portal_suggestions', $suggestions, $staff_id);
        $suggestions = is_array($suggestions) ? array_values(array_filter(array_map('sanitize_text_field', $suggestions))) : array();

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Upload a Certification', 'backstage-venue-manager') . '</h3>';
        echo '<p class="vms-muted">' . esc_html__('Upload proof of a certification, license, or permit. New uploads stay Pending Review until an admin approves them.', 'backstage-venue-manager') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" class="vms-staff-certification-form">';
        wp_nonce_field('vms_staff_certification_submit', 'vms_staff_certification_nonce');
        echo '<div class="vms-portal-grid vms-staff-certification-grid">';
        echo '<label class="vms-field"><span>' . esc_html__('Certification type', 'backstage-venue-manager') . '</span><input type="text" name="vms_certification_name" list="vms-staff-certification-types" value="" placeholder="' . esc_attr__('Example: TABC', 'backstage-venue-manager') . '"></label>';
        if (!empty($suggestions)) {
            echo '<datalist id="vms-staff-certification-types">';
            foreach ($suggestions as $suggestion) {
                if ($suggestion === '') {
                    continue;
                }
                echo '<option value="' . esc_attr($suggestion) . '"></option>';
            }
            echo '</datalist>';
        }
        echo '<label class="vms-field"><span>' . esc_html__('Issuing organization', 'backstage-venue-manager') . '</span><input type="text" name="vms_certification_authority" value="" placeholder="' . esc_attr__('Optional', 'backstage-venue-manager') . '"></label>';
        echo '<label class="vms-field"><span>' . esc_html__('Credential #', 'backstage-venue-manager') . '</span><input type="text" name="vms_certification_number" value="" placeholder="' . esc_attr__('Optional', 'backstage-venue-manager') . '"></label>';
        echo '<label class="vms-field"><span>' . esc_html__('Issue date', 'backstage-venue-manager') . '</span><input type="date" name="vms_certification_issue_date" value=""></label>';
        echo '<label class="vms-field"><span>' . esc_html__('Expiration date', 'backstage-venue-manager') . '</span><input type="date" name="vms_certification_expiration_date" value=""></label>';
        echo '<label class="vms-field vms-staff-certification-file"><span>' . esc_html__('Certificate file', 'backstage-venue-manager') . '</span><input type="file" name="vms_staff_certification_file" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif"></label>';
        echo '</div>';
        echo '<p><button type="submit" class="button" name="vms_staff_certification_submit" value="1">' . esc_html__('Upload for Review', 'backstage-venue-manager') . '</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('My Certifications', 'backstage-venue-manager') . '</h3>';
        if (empty($rows)) {
            echo '<p class="vms-muted">' . esc_html__('No certifications have been uploaded yet.', 'backstage-venue-manager') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="vms-staff-certification-list">';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = isset($row['name']) ? (string) $row['name'] : __('Certification', 'backstage-venue-manager');
            $status = isset($row['effective_status']) ? sanitize_key((string) $row['effective_status']) : sanitize_key((string) ($row['status'] ?? 'inactive'));
            $authority = !empty($row['authority']) ? (string) $row['authority'] : '';
            $credential_number = !empty($row['credential_number']) ? (string) $row['credential_number'] : '';
            $issue_date = !empty($row['issue_date']) ? (string) $row['issue_date'] : '';
            $expiration_date = !empty($row['expiration_date']) ? (string) $row['expiration_date'] : '';
            $proof_url = !empty($row['proof_download_url']) ? (string) $row['proof_download_url'] : (!empty($row['proof_url']) ? (string) $row['proof_url'] : '');
            $notes = !empty($row['notes']) ? (string) $row['notes'] : '';
            $submitted_at = !empty($row['submitted_at']) ? absint($row['submitted_at']) : 0;
            $reviewed_at = !empty($row['reviewed_at']) ? absint($row['reviewed_at']) : 0;

            echo '<div class="vms-staff-certification-row">';
            echo '<div class="vms-staff-certification-main">';
            echo '<strong>' . esc_html($name) . '</strong> ' . wp_kses(vms_staff_portal_safe_html(vms_staff_portal_certification_status_badge($status)), vms_staff_portal_safe_html_allowed_html());
            echo '<p class="vms-muted">';
            $details = array();
            if ($authority !== '') {
                /* translators: %s: certification issuing organization. */
                $details[] = sprintf(__('Issued by %s', 'backstage-venue-manager'), $authority);
            }
            if ($credential_number !== '') {
                /* translators: %s: certification or license number. */
                $details[] = sprintf(__('Credential #%s', 'backstage-venue-manager'), $credential_number);
            }
            if ($issue_date !== '') {
                /* translators: %s: certification issue date. */
                $details[] = sprintf(__('Issued %s', 'backstage-venue-manager'), $issue_date);
            }
            if ($expiration_date !== '') {
                /* translators: %s: certification expiration date. */
                $details[] = sprintf(__('Expires %s', 'backstage-venue-manager'), $expiration_date);
            }
            if ($submitted_at > 0) {
                /* translators: %s: localized certification submission timestamp. */
                $details[] = sprintf(__('Submitted %s', 'backstage-venue-manager'), vms_staff_portal_format_ts($submitted_at));
            }
            if ($reviewed_at > 0) {
                /* translators: %s: localized certification review timestamp. */
                $details[] = sprintf(__('Reviewed %s', 'backstage-venue-manager'), vms_staff_portal_format_ts($reviewed_at));
            }
            echo esc_html(!empty($details) ? implode(' · ', $details) : __('Details pending review.', 'backstage-venue-manager'));
            echo '</p>';
            if ($notes !== '' && in_array($status, array('rejected', 'inactive'), true)) {
                echo '<p class="vms-muted"><strong>' . esc_html__('Review note:', 'backstage-venue-manager') . '</strong> ' . esc_html($notes) . '</p>';
            }
            echo '</div>';
            if ($proof_url !== '') {
                echo '<div class="vms-staff-certification-actions"><a class="button" href="' . esc_url($proof_url) . '" target="_blank" rel="noopener">' . esc_html__('View File', 'backstage-venue-manager') . '</a></div>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_staff_portal_assignment_status_label')) {
    function vms_staff_portal_assignment_status_label(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === 'confirmed') return __('Confirmed', 'backstage-venue-manager');
        if ($status === 'proposed') return __('Proposed', 'backstage-venue-manager');
        return __('Scheduled', 'backstage-venue-manager');
    }
}

if (!function_exists('vms_staff_portal_plan_event_icon')) {
    function vms_staff_portal_plan_event_icon(int $plan_id): string
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        if (isset($cache[$plan_id])) {
            return (string) $cache[$plan_id];
        }

        $icon_map = function_exists('vms_calendar_vendor_type_icons') ? (array) vms_calendar_vendor_type_icons() : array();
        $fallback = trim((string) ($icon_map['talent'] ?? ''));
        $icon = $fallback;

        if ($plan_id > 0 && function_exists('bvmgr_calendar_plan_vendor_ids')) {
            $vendor_ids = (array) bvmgr_calendar_plan_vendor_ids($plan_id);
            $primary_vendor_id = absint($vendor_ids['band_id'] ?? 0);
            if ($primary_vendor_id > 0 && function_exists('bvmgr_calendar_vendor_primary_type')) {
                $primary_type = (array) bvmgr_calendar_vendor_primary_type($primary_vendor_id);
                $primary_slug = sanitize_key((string) ($primary_type['slug'] ?? 'talent'));
                if ($primary_slug !== '' && !empty($icon_map[$primary_slug])) {
                    $icon = trim((string) $icon_map[$primary_slug]);
                }
            } elseif (function_exists('vms_add_dispatch_get_event_plan_context')) {
                $context = (array) vms_add_dispatch_get_event_plan_context($plan_id);
                $secondary_slug = sanitize_key((string) ($context['secondary_vendor_type'] ?? ''));
                if ($secondary_slug !== '' && !empty($icon_map[$secondary_slug])) {
                    $icon = trim((string) $icon_map[$secondary_slug]);
                }
            }
        }

        $cache[$plan_id] = $icon;
        return $icon;
    }
}

if (!function_exists('vms_staff_portal_calendar_event_map')) {
    function vms_staff_portal_calendar_event_map(array $assignment_rows): array
    {
        if (empty($assignment_rows) || !function_exists('bvmgr_get_calendar_events')) {
            return array();
        }

        $dates = array();
        foreach ($assignment_rows as $row) {
            $event_date = trim((string) ($row['event_date'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
                $dates[] = $event_date;
            }
        }

        if (empty($dates)) {
            return array();
        }

        sort($dates);
        $events = (array) bvmgr_get_calendar_events(array(
            'context' => 'admin',
            'start_date' => (string) reset($dates),
            'end_date' => (string) end($dates),
            'include_past' => true,
            'include_open_close_shading' => false,
        ));

        $map = array();
        foreach ($events as $event) {
            $plan_id = absint($event['event_plan_id'] ?? 0);
            if ($plan_id > 0) {
                $map[$plan_id] = is_array($event) ? $event : array();
            }
        }

        return $map;
    }
}

if (!function_exists('vms_staff_portal_assignment_event_payload')) {
    function vms_staff_portal_assignment_event_payload(array $assignment, array $event_map = array()): array
    {
        $plan_id = absint($assignment['event_plan_id'] ?? 0);
        $event = ($plan_id > 0 && isset($event_map[$plan_id]) && is_array($event_map[$plan_id])) ? $event_map[$plan_id] : array();

        $title = trim((string) ($event['title'] ?? ($assignment['event_title'] ?? '')));
        if ($title === '') {
            $title = __('Event Plan', 'backstage-venue-manager');
        }

        $date_label = trim((string) ($assignment['date_label'] ?? ''));
        if (!empty($event['date_key']) && function_exists('vms_vendor_portal_modal_date_label')) {
            $date_label = vms_vendor_portal_modal_date_label((string) $event['date_key']);
        }

        $time_label = '';
        if (function_exists('vms_vendor_portal_format_modal_time_label')) {
            $time_label = vms_vendor_portal_format_modal_time_label((string) ($event['start_local'] ?? ''), (string) ($event['end_local'] ?? ''));
        }

        $view_url = trim((string) ($event['public_url'] ?? ''));
        $excerpt = trim((string) ($event['excerpt'] ?? ''));
        $image_url = trim((string) ($event['image_url'] ?? ''));
        $venue_name = trim((string) ($event['venue_name'] ?? ''));

        if ($plan_id > 0) {
            $post = get_post($plan_id);
            if ($post instanceof WP_Post) {
                if ($excerpt === '') {
                    $excerpt = trim((string) $post->post_excerpt);
                    if ($excerpt === '') {
                        $excerpt = wp_strip_all_tags((string) $post->post_content);
                        $excerpt = wp_trim_words($excerpt, 24, '...');
                    }
                }

                if ($image_url === '') {
                    $img_id = get_post_thumbnail_id($plan_id);
                    if (!$img_id && function_exists('bvmgr_calendar_plan_vendor_ids')) {
                        $vendor_ids = (array) bvmgr_calendar_plan_vendor_ids($plan_id);
                        $band_id = absint($vendor_ids['band_id'] ?? 0);
                        if ($band_id > 0) {
                            $img_id = get_post_thumbnail_id($band_id);
                        }
                    }
                    if ($img_id) {
                        $image_url = (string) wp_get_attachment_image_url($img_id, 'large');
                    }
                }
            }

            if ($venue_name === '') {
                $venue_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'venue_id') ?: '_vms_venue_id') : '_vms_venue_id';
                $venue_id = absint(get_post_meta($plan_id, $venue_key, true));
                if ($venue_id > 0) {
                    $venue_name = trim((string) get_the_title($venue_id));
                }
            }

            if ($view_url === '') {
                $tec_event_url_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'tec_event_url') ?: '_vms_tec_event_url') : '_vms_tec_event_url';
                $tec_event_id_key = function_exists('bvmgr_meta_key') ? (string) (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
                $view_url = trim((string) get_post_meta($plan_id, $tec_event_url_key, true));
                if ($view_url === '') {
                    $tec_event_id = absint(get_post_meta($plan_id, $tec_event_id_key, true));
                    if ($tec_event_id > 0) {
                        $view_url = (string) get_permalink($tec_event_id);
                    }
                }
            }
        }

        return array(
            'icon' => vms_staff_portal_plan_event_icon($plan_id),
            'title' => $title,
            'date_label' => $date_label,
            'time_label' => $time_label,
            'excerpt' => $excerpt,
            'image_url' => $image_url,
            'venue_name' => $venue_name,
            'view_url' => $view_url,
        );
    }
}


if (!function_exists('vms_staff_portal_visible_event_statuses')) {
    function vms_staff_portal_visible_event_statuses(): array
    {
        return array('ready', 'published', 'tentative', 'confirmed');
    }
}

if (!function_exists('vms_staff_portal_doc_visibility_role_ids')) {
    function vms_staff_portal_doc_visibility_role_ids(): array
    {
        $settings = get_option('vms_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $raw = $settings['staff_portal_doc_visibility_role_ids'] ?? array();

        if (!is_array($raw)) {
            $raw = array($raw);
        }

        $role_ids = array_values(array_unique(array_filter(array_map('absint', $raw), static function ($value) {
            return $value > 0;
        })));
        sort($role_ids, SORT_NUMERIC);

        return $role_ids;
    }
}

if (!function_exists('vms_staff_portal_assignment_can_view_docs')) {
    function vms_staff_portal_assignment_can_view_docs(array $assignment): bool
    {
        $allowed_role_ids = vms_staff_portal_doc_visibility_role_ids();
        if (empty($allowed_role_ids)) {
            return true;
        }

        $role_id = absint($assignment['role_id'] ?? 0);
        return $role_id > 0 && in_array($role_id, $allowed_role_ids, true);
    }
}

if (!function_exists('vms_staff_portal_get_event_ticket_qty')) {
    function vms_staff_portal_get_event_ticket_qty(int $plan_id): ?int
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return null;
        }
        if (array_key_exists($plan_id, $cache)) {
            return $cache[$plan_id];
        }

        $qty = null;

        if (function_exists('vms_vendor_portal_get_ticket_sales_snapshot')) {
            $snapshot = (array) vms_vendor_portal_get_ticket_sales_snapshot($plan_id);
            if (array_key_exists('qty_sold', $snapshot) && is_numeric($snapshot['qty_sold'])) {
                $qty = max(0, (int) $snapshot['qty_sold']);
            } elseif (array_key_exists('qty', $snapshot) && is_numeric($snapshot['qty'])) {
                $qty = max(0, (int) $snapshot['qty']);
            }
        }

        if ($qty === null && function_exists('vms_calendar_get_ticket_sold_count')) {
            $resolved = vms_calendar_get_ticket_sold_count($plan_id);
            if ($resolved !== null && $resolved !== '') {
                $qty = max(0, (int) $resolved);
            }
        }

        if ($qty === null) {
            $candidate_keys = array(
                function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tickets_sold_count') : '',
                '_vms_tickets_sold_count',
                'vms_tickets_sold_count',
            );
            foreach ($candidate_keys as $key) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                $raw = get_post_meta($plan_id, $key, true);
                if ($raw === '' || $raw === null) {
                    continue;
                }
                $qty = max(0, (int) $raw);
                break;
            }
        }

        $cache[$plan_id] = $qty;
        return $qty;
    }
}

if (!function_exists('vms_staff_portal_get_event_tech_docs')) {
    function vms_staff_portal_get_event_tech_docs(int $plan_id): array
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        $vendor_ids = array();
        if (function_exists('bvmgr_calendar_plan_vendor_ids')) {
            $bundle = (array) bvmgr_calendar_plan_vendor_ids($plan_id);
            $vendor_ids[] = absint($bundle['band_id'] ?? 0);
            foreach ((array) ($bundle['secondary_ids'] ?? array()) as $vendor_id) {
                $vendor_ids[] = absint($vendor_id);
            }
            foreach ((array) ($bundle['lineup_ids'] ?? array()) as $vendor_id) {
                $vendor_ids[] = absint($vendor_id);
            }
        }

        $vendor_ids = array_values(array_unique(array_filter($vendor_ids, static function ($vendor_id) {
            return $vendor_id > 0;
        })));

        $docs = array();
        foreach ($vendor_ids as $vendor_id) {
            $vendor_name = trim((string) get_the_title($vendor_id));
            if ($vendor_name === '') {
                $vendor_name = __('Vendor', 'backstage-venue-manager');
            }

            $pairs = array(
                'stage_plot' => array(
                    'label' => __('Stage plot', 'backstage-venue-manager'),
                ),
                'input_list' => array(
                    'label' => __('Input list', 'backstage-venue-manager'),
                ),
            );

            foreach ($pairs as $doc_key => $doc) {
                if (!function_exists('vms_vendor_portal_tech_doc_payload') || !function_exists('vms_vendor_portal_tech_doc_download_url')) {
                    continue;
                }

                $payload = vms_vendor_portal_tech_doc_payload($vendor_id, $doc_key);
                if (is_wp_error($payload)) {
                    continue;
                }

                $docs[] = array(
                    'vendor_id' => $vendor_id,
                    'vendor_name' => $vendor_name,
                    'doc_key' => $doc_key,
                    'label' => (string) ($doc['label'] ?? __('Document', 'backstage-venue-manager')),
                    'url' => vms_vendor_portal_tech_doc_download_url($vendor_id, $doc_key, $plan_id),
                );
            }
        }

        $cache[$plan_id] = $docs;
        return $docs;
    }
}

if (!function_exists('vms_staff_portal_get_event_crew_rows')) {
    function vms_staff_portal_get_event_crew_rows(int $plan_id): array
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }
        if (isset($cache[$plan_id])) {
            return $cache[$plan_id];
        }

        global $wpdb;

        if (!function_exists('vms_staffing_table_name')) {
            return array();
        }

        $t_assignments = vms_staffing_table_name('assignments');
        $t_slots = vms_staffing_table_name('event_slots');
        if ($t_assignments === '' || $t_slots === '') {
            return array();
        }

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff portal crew reads join normalized staffing tables with prepared identifier/filter values, and shift-glance consumers must observe immediate staffing changes without a persistent cache layer.
	        $raw_rows = $wpdb->get_results(
	            $wpdb->prepare(
	                'SELECT a.assignment_id, a.staff_id, a.status AS assignment_status, a.shift_start_ts, a.shift_end_ts,
	                        s.slot_id, s.role_id, s.display_label_override, s.shift_start_local, s.shift_end_local
	                 FROM %i a
	                 INNER JOIN %i s ON s.slot_id = a.slot_id
	                 WHERE s.event_plan_id = %d
	                   AND s.status = \'active\'
	                   AND a.status IN (\'proposed\',\'confirmed\')
	                 ORDER BY COALESCE(a.shift_start_ts, 9223372036854775807) ASC, s.slot_id ASC, a.assignment_id ASC',
	                $t_assignments,
	                $t_slots,
	                $plan_id
	            ),
	            ARRAY_A
	        );
        if (!is_array($raw_rows) || empty($raw_rows)) {
            $cache[$plan_id] = array();
            return $cache[$plan_id];
        }

        $role_map = function_exists('vms_staffing_role_map_by_id') ? (array) vms_staffing_role_map_by_id(true) : array();
        $rows = array();

        foreach ($raw_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $staff_id = absint($row['staff_id'] ?? 0);
            if ($staff_id <= 0) {
                continue;
            }

            $name = trim((string) get_the_title($staff_id));
            if ($name === '') {
                /* translators: %d: staff post ID. */
                $name = sprintf(__('Staff #%d', 'backstage-venue-manager'), $staff_id);
            }

            $role_id = absint($row['role_id'] ?? 0);
            $role_label = trim((string) ($row['display_label_override'] ?? ''));
            if ($role_label === '' && $role_id > 0 && isset($role_map[$role_id]['name'])) {
                $role_label = (string) $role_map[$role_id]['name'];
            }
            if ($role_label === '') {
                $role_label = __('Shift', 'backstage-venue-manager');
            }

            $start_ts = isset($row['shift_start_ts']) && $row['shift_start_ts'] !== null ? (int) $row['shift_start_ts'] : 0;
            $end_ts = isset($row['shift_end_ts']) && $row['shift_end_ts'] !== null ? (int) $row['shift_end_ts'] : 0;
            $shift_label = '';
            if ($start_ts > 0 && $end_ts > 0) {
                $shift_label = wp_date('g:ia', $start_ts, wp_timezone()) . '–' . wp_date('g:ia', $end_ts, wp_timezone());
            } elseif ($start_ts > 0) {
                $shift_label = wp_date('g:ia', $start_ts, wp_timezone());
            } else {
                $start_local = trim((string) ($row['shift_start_local'] ?? ''));
                $end_local = trim((string) ($row['shift_end_local'] ?? ''));
                if (preg_match('/^\d{2}:\d{2}$/', $start_local)) {
                    $shift_label = $start_local;
                    if (preg_match('/^\d{2}:\d{2}$/', $end_local)) {
                        $shift_label .= '–' . $end_local;
                    }
                }
            }

            $status_key = sanitize_key((string) ($row['assignment_status'] ?? ''));
            $rows[] = array(
                'assignment_id' => absint($row['assignment_id'] ?? 0),
                'staff_id' => $staff_id,
                'name' => $name,
                'role_id' => $role_id,
                'role_label' => $role_label,
                'shift_label' => $shift_label,
                'assignment_status' => $status_key,
                'assignment_status_label' => vms_staff_portal_assignment_status_label($status_key),
            );
        }

        $cache[$plan_id] = $rows;
        return $rows;
    }
}

if (!function_exists('vms_staff_portal_build_shift_glance')) {
    function vms_staff_portal_build_shift_glance(array $assignment, array $event_map = array()): array
    {
        $plan_id = absint($assignment['event_plan_id'] ?? 0);
        $docs = vms_staff_portal_assignment_can_view_docs($assignment)
            ? vms_staff_portal_get_event_tech_docs($plan_id)
            : array();

        return array(
            'event' => vms_staff_portal_assignment_event_payload($assignment, $event_map),
            'ticket_qty' => vms_staff_portal_get_event_ticket_qty($plan_id),
            'docs' => $docs,
            'crew' => vms_staff_portal_get_event_crew_rows($plan_id),
            'can_view_docs' => vms_staff_portal_assignment_can_view_docs($assignment),
        );
    }
}

if (!function_exists('vms_staff_portal_consolidate_crew_rows')) {
    function vms_staff_portal_consolidate_crew_rows(array $rows): array
    {
        if (empty($rows)) {
            return array();
        }

        $grouped = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $staff_id = absint($row['staff_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $role_label = trim((string) ($row['role_label'] ?? ''));
            $shift_label = trim((string) ($row['shift_label'] ?? ''));
            $group_key = $staff_id > 0 ? ('staff:' . $staff_id) : ('name:' . strtolower($name));
            if ($group_key === 'name:') {
                $group_key = 'row:' . md5(wp_json_encode($row));
            }

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = array(
                    'staff_id' => $staff_id,
                    'name' => $name,
                    'role_labels' => array(),
                    'shift_labels' => array(),
                    'assignment_ids' => array(),
                    'assignment_statuses' => array(),
                );
            }

            if ($role_label !== '' && !in_array($role_label, $grouped[$group_key]['role_labels'], true)) {
                $grouped[$group_key]['role_labels'][] = $role_label;
            }
            if ($shift_label !== '' && !in_array($shift_label, $grouped[$group_key]['shift_labels'], true)) {
                $grouped[$group_key]['shift_labels'][] = $shift_label;
            }

            $assignment_id = absint($row['assignment_id'] ?? 0);
            if ($assignment_id > 0 && !in_array($assignment_id, $grouped[$group_key]['assignment_ids'], true)) {
                $grouped[$group_key]['assignment_ids'][] = $assignment_id;
            }

            $status_key = sanitize_key((string) ($row['assignment_status'] ?? ''));
            if ($status_key !== '' && !in_array($status_key, $grouped[$group_key]['assignment_statuses'], true)) {
                $grouped[$group_key]['assignment_statuses'][] = $status_key;
            }
        }

        $result = array();
        foreach ($grouped as $group) {
            $role_labels = array_values(array_filter(array_map('strval', (array) ($group['role_labels'] ?? array()))));
            $shift_labels = array_values(array_filter(array_map('strval', (array) ($group['shift_labels'] ?? array()))));
            $group['role_labels'] = $role_labels;
            $group['shift_labels'] = $shift_labels;
            $group['role_label'] = implode(', ', $role_labels);
            $group['shift_label'] = implode(', ', $shift_labels);
            $result[] = $group;
        }

        usort($result, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $result;
    }
}

if (!function_exists('vms_staff_portal_group_assignments_by_event')) {
    function vms_staff_portal_group_assignments_by_event(array $assignments): array
    {
        if (empty($assignments)) {
            return array();
        }

        $grouped = array();
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }

            $plan_id = absint($assignment['event_plan_id'] ?? 0);
            if ($plan_id <= 0) {
                continue;
            }

            if (!isset($grouped[$plan_id])) {
                $grouped[$plan_id] = array(
                    'event_plan_id' => $plan_id,
                    'assignments' => array(),
                    'event_title' => (string) ($assignment['event_title'] ?? ''),
                    'event_date' => (string) ($assignment['event_date'] ?? ''),
                    'date_label' => (string) ($assignment['date_label'] ?? ''),
                    'event_status' => (string) ($assignment['event_status'] ?? ''),
                    'event_status_label' => (string) ($assignment['event_status_label'] ?? ''),
                    'start_ts' => isset($assignment['start_ts']) ? (int) $assignment['start_ts'] : 0,
                );
            }

            $grouped[$plan_id]['assignments'][] = $assignment;
            $start_ts = isset($assignment['start_ts']) ? (int) $assignment['start_ts'] : 0;
            if ($start_ts > 0 && (($grouped[$plan_id]['start_ts'] ?? 0) <= 0 || $start_ts < (int) $grouped[$plan_id]['start_ts'])) {
                $grouped[$plan_id]['start_ts'] = $start_ts;
            }
        }

        $result = array();
        foreach ($grouped as $group) {
            $lines = array();
            foreach ((array) ($group['assignments'] ?? array()) as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }
                $role_label = trim((string) ($assignment['role_label'] ?? ''));
                $shift_label = trim((string) ($assignment['shift_label'] ?? ''));
                $line = $role_label;
                if ($shift_label !== '') {
                    $line = $line !== '' ? ($line . ' · ' . $shift_label) : $shift_label;
                }
                if ($line === '') {
                    $line = __('Shift', 'backstage-venue-manager');
                }
                if (!in_array($line, $lines, true)) {
                    $lines[] = $line;
                }
            }
            $group['summary_lines'] = $lines;
            $group['can_view_docs'] = false;
            foreach ((array) ($group['assignments'] ?? array()) as $assignment) {
                if (vms_staff_portal_assignment_can_view_docs((array) $assignment)) {
                    $group['can_view_docs'] = true;
                    break;
                }
            }
            $result[] = $group;
        }

        usort($result, static function (array $a, array $b): int {
            $a_ts = isset($a['start_ts']) ? (int) $a['start_ts'] : 0;
            $b_ts = isset($b['start_ts']) ? (int) $b['start_ts'] : 0;
            if ($a_ts > 0 && $b_ts > 0 && $a_ts !== $b_ts) {
                return ($a_ts < $b_ts) ? -1 : 1;
            }
            $a_date = (string) ($a['event_date'] ?? '');
            $b_date = (string) ($b['event_date'] ?? '');
            if ($a_date !== $b_date) {
                return strcmp($a_date, $b_date);
            }
            return strcmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
        });

        return $result;
    }
}

if (!function_exists('vms_staff_portal_render_assigned_event_cards')) {
    function vms_staff_portal_render_assigned_event_cards(int $staff_id, array $assignments, array $event_map = array()): void
    {
        if (empty($assignments)) {
            return;
        }

        $event_groups = vms_staff_portal_group_assignments_by_event($assignments);
        if (empty($event_groups)) {
            return;
        }

        echo '<div class="vms-staff-shift-section">';
        echo '<h3>' . esc_html__('Assigned Events', 'backstage-venue-manager') . '</h3>';
        echo '<div class="vms-staff-shift-cards">';

        foreach ($event_groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $assignments_for_event = (array) ($group['assignments'] ?? array());
            if (empty($assignments_for_event)) {
                continue;
            }

            $primary_assignment = (array) reset($assignments_for_event);
            $plan_id = absint($group['event_plan_id'] ?? ($primary_assignment['event_plan_id'] ?? 0));
            $event = vms_staff_portal_assignment_event_payload($primary_assignment, $event_map);
            $ticket_qty = vms_staff_portal_get_event_ticket_qty($plan_id);
            $docs = !empty($group['can_view_docs']) ? vms_staff_portal_get_event_tech_docs($plan_id) : array();
            $staff_rows = vms_staff_portal_consolidate_crew_rows(vms_staff_portal_get_event_crew_rows($plan_id));
            $can_view_docs = !empty($group['can_view_docs']);

            $title = trim((string) ($group['event_title'] ?? ($event['title'] ?? '')));
            if ($title === '') {
                $title = __('Event Plan', 'backstage-venue-manager');
            }

            $date_label = trim((string) ($group['date_label'] ?? ($event['date_label'] ?? '')));
            $summary_lines = array_values(array_filter(array_map('strval', (array) ($group['summary_lines'] ?? array()))));
            $venue_name = trim((string) ($event['venue_name'] ?? ''));
            $image_url = trim((string) ($event['image_url'] ?? ''));
            $ticket_summary = ($ticket_qty === null)
                ? __('Tickets sold: —', 'backstage-venue-manager')
                : sprintf(
                    /* translators: %d: total tickets sold for the event. */
                    __('Tickets sold: %d', 'backstage-venue-manager'),
                    (int) $ticket_qty
                );
            $event_status = sanitize_key((string) ($group['event_status'] ?? ($primary_assignment['event_status'] ?? '')));
            $event_status_label = function_exists('bvmgr_event_plan_status_label')
                ? (string) bvmgr_event_plan_status_label($event_status)
                : ucfirst($event_status);

            echo '<details class="vms-staff-shift-card">';

            echo '<summary class="vms-staff-shift-summary">';
            if ($image_url !== '') {
                echo '<div class="vms-staff-shift-thumb"><img src="' . esc_url($image_url) . '" alt="" loading="lazy" /></div>';
            } else {
                echo '<div class="vms-staff-shift-thumb vms-staff-shift-thumb--fallback" aria-hidden="true"><span>' . esc_html((string) ($event['icon'] ?? '🎟️')) . '</span></div>';
            }

            echo '<div class="vms-staff-shift-summary-main">';
            echo '<div class="vms-staff-shift-title">' . esc_html($title) . '</div>';
            if ($date_label !== '') {
                echo '<div class="vms-staff-shift-meta">' . esc_html($date_label) . '</div>';
            }
            foreach ($summary_lines as $line) {
                echo '<div class="vms-staff-shift-meta">' . esc_html($line) . '</div>';
            }
            echo '<div class="vms-staff-shift-meta vms-staff-shift-meta--tickets">' . esc_html($ticket_summary) . '</div>';
            echo '</div>';

            echo '<span class="vms-staff-shift-expand">' . esc_html__('Details', 'backstage-venue-manager') . '</span>';
            echo '</summary>';

            echo '<div class="vms-staff-shift-body">';
            echo '<div class="vms-staff-shift-kpis">';
            echo '<div class="vms-staff-shift-kpi"><b>' . esc_html__('Tickets sold', 'backstage-venue-manager') . '</b><span>' . esc_html($ticket_qty === null ? '—' : (string) (int) $ticket_qty) . '</span></div>';
            echo '<div class="vms-staff-shift-kpi"><b>' . esc_html__('Staff assigned', 'backstage-venue-manager') . '</b><span>' . esc_html((string) count($staff_rows)) . '</span></div>';
            if ($can_view_docs) {
                echo '<div class="vms-staff-shift-kpi"><b>' . esc_html__('Tech docs', 'backstage-venue-manager') . '</b><span>' . esc_html((string) count($docs)) . '</span></div>';
            }
            echo '</div>';

            $body_meta = array();
            if ($venue_name !== '') {
                $body_meta[] = $venue_name;
            }
            if ($event_status_label !== '') {
                /* translators: %s: event status label. */
                $body_meta[] = sprintf(__('Status: %s', 'backstage-venue-manager'), $event_status_label);
            }
            if (!empty($body_meta)) {
                echo '<p class="vms-muted vms-m0">' . esc_html(implode(' · ', $body_meta)) . '</p>';
            }

            $note_lines = array();
            foreach ($assignments_for_event as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }
                $slot_notes = trim((string) ($assignment['slot_notes'] ?? ''));
                if ($slot_notes !== '' && !in_array($slot_notes, $note_lines, true)) {
                    $note_lines[] = $slot_notes;
                }
            }
            foreach ($note_lines as $slot_note) {
                echo '<p class="vms-muted vms-mt-10">' . esc_html($slot_note) . '</p>';
            }

            echo '<div class="vms-staff-shift-detail-grid">';

            echo '<div class="vms-staff-shift-panel">';
            echo '<h4>' . esc_html__('Staff on this event', 'backstage-venue-manager') . '</h4>';
            if (!empty($staff_rows)) {
                echo '<ul class="vms-staff-shift-list">';
                foreach ($staff_rows as $staff_row) {
                    if (!is_array($staff_row)) {
                        continue;
                    }
                    $name = trim((string) ($staff_row['name'] ?? ''));
                    if ($name === '') {
                        $name = __('Assigned staff', 'backstage-venue-manager');
                    }
                    if (absint($staff_row['staff_id'] ?? 0) === $staff_id) {
                        $name .= ' ' . __('(You)', 'backstage-venue-manager');
                    }

                    $parts = array();
                    foreach ((array) ($staff_row['role_labels'] ?? array()) as $role_text) {
                        $role_text = trim((string) $role_text);
                        if ($role_text !== '') {
                            $parts[] = $role_text;
                        }
                    }
                    foreach ((array) ($staff_row['shift_labels'] ?? array()) as $staff_shift_label) {
                        $staff_shift_label = trim((string) $staff_shift_label);
                        if ($staff_shift_label !== '') {
                            $parts[] = $staff_shift_label;
                        }
                    }
                    $parts = array_values(array_unique($parts));

                    echo '<li><strong>' . esc_html($name) . '</strong>';
                    if (!empty($parts)) {
                        echo '<span>' . esc_html(implode(' · ', $parts)) . '</span>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="vms-muted vms-m0">' . esc_html__('No other staff assignments are visible yet.', 'backstage-venue-manager') . '</p>';
            }
            echo '</div>';

            if ($can_view_docs) {
                echo '<div class="vms-staff-shift-panel">';
                echo '<h4>' . esc_html__('Event docs', 'backstage-venue-manager') . '</h4>';
                if (!empty($docs)) {
                    echo '<ul class="vms-staff-shift-list">';
                    foreach ($docs as $doc) {
                        if (!is_array($doc)) {
                            continue;
                        }
                        $label = trim((string) ($doc['label'] ?? __('Document', 'backstage-venue-manager')));
                        $vendor_name = trim((string) ($doc['vendor_name'] ?? ''));
                        $doc_text = $vendor_name !== '' ? ($vendor_name . ' · ' . $label) : $label;
                        echo '<li><a href="' . esc_url((string) ($doc['url'] ?? '')) . '" target="_blank" rel="noopener">' . esc_html($doc_text) . '</a></li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="vms-muted vms-m0">' . esc_html__('No stage plot or input list has been uploaded yet.', 'backstage-venue-manager') . '</p>';
                }
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_staff_portal_get_assignment_rows')) {
    function vms_staff_portal_get_assignment_rows(int $staff_id, int $limit = 100): array
    {
        global $wpdb;

        $staff_id = absint($staff_id);
        $limit = max(1, (int) $limit);
        if ($staff_id <= 0 || !function_exists('vms_staffing_table_name')) {
            return array();
        }

        $t_assignments = vms_staffing_table_name('assignments');
        $t_slots = vms_staffing_table_name('event_slots');
        if ($t_assignments === '' || $t_slots === '') {
            return array();
        }

	        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Staff portal assignment reads join normalized staffing tables with prepared identifier/filter values, and the portal timeline must observe immediate staffing changes without a persistent cache layer.
	        $raw_rows = $wpdb->get_results(
	            $wpdb->prepare(
	                'SELECT a.assignment_id, a.staff_id, a.status AS assignment_status, a.shift_start_ts, a.shift_end_ts,
	                        s.slot_id, s.event_plan_id, s.role_id, s.display_label_override, s.shift_time_mode,
	                        s.shift_start_local, s.shift_end_local, s.start_anchor_key, s.start_offset_minutes,
	                        s.end_anchor_key, s.end_offset_minutes, s.duration_minutes, s.notes AS slot_notes
	                 FROM %i a
	                 INNER JOIN %i s ON s.slot_id = a.slot_id
	                 WHERE a.staff_id = %d
	                   AND a.status IN (\'proposed\',\'confirmed\')
	                   AND s.status = \'active\'
	                 ORDER BY COALESCE(a.shift_start_ts, 9223372036854775807) ASC, s.event_plan_id ASC, s.slot_id ASC
	                 LIMIT %d',
	                $t_assignments,
	                $t_slots,
	                $staff_id,
	                $limit
	            ),
	            ARRAY_A
	        );
        if (!is_array($raw_rows) || empty($raw_rows)) {
            return array();
        }

        $today = wp_date('Y-m-d', time(), wp_timezone());
        $role_map = function_exists('vms_staffing_role_map_by_id') ? (array) vms_staffing_role_map_by_id(true) : array();
        $plan_cache = array();
        $rows = array();

        foreach ($raw_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $plan_id = isset($row['event_plan_id']) ? absint($row['event_plan_id']) : 0;
            if ($plan_id <= 0) {
                continue;
            }

            if (!isset($plan_cache[$plan_id])) {
                $event_date = (string) get_post_meta($plan_id, '_vms_event_date', true);
                $status = function_exists('bvmgr_event_plan_get_status')
                    ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
                    : 'draft';
                $plan_cache[$plan_id] = array(
                    'event_date' => $event_date,
                    'status' => $status,
                    'title' => get_the_title($plan_id),
                );
            }

            $plan = $plan_cache[$plan_id];
            $event_date = (string) ($plan['event_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
                continue;
            }
            if ($event_date < $today) {
                continue;
            }
            $plan_status = sanitize_key((string) ($plan['status'] ?? ''));
            if (!in_array($plan_status, vms_staff_portal_visible_event_statuses(), true)) {
                continue;
            }

            $window = function_exists('bvmgr_staffing_resolve_slot_window')
                ? (array) bvmgr_staffing_resolve_slot_window($plan_id, $row)
                : array();

            $start_ts = isset($row['shift_start_ts']) && $row['shift_start_ts'] !== null ? (int) $row['shift_start_ts'] : 0;
            $end_ts = isset($row['shift_end_ts']) && $row['shift_end_ts'] !== null ? (int) $row['shift_end_ts'] : 0;
            if ($start_ts <= 0 && isset($window['start_ts']) && $window['start_ts'] !== null) {
                $start_ts = (int) $window['start_ts'];
            }
            if ($end_ts <= 0 && isset($window['end_ts']) && $window['end_ts'] !== null) {
                $end_ts = (int) $window['end_ts'];
            }

            $shift_label = '';
            if ($start_ts > 0 && $end_ts > 0) {
                $shift_label = wp_date('g:ia', $start_ts, wp_timezone()) . '–' . wp_date('g:ia', $end_ts, wp_timezone());
            } elseif ($start_ts > 0) {
                $shift_label = wp_date('g:ia', $start_ts, wp_timezone());
            } else {
                $start_local = isset($row['shift_start_local']) ? trim((string) $row['shift_start_local']) : '';
                $end_local = isset($row['shift_end_local']) ? trim((string) $row['shift_end_local']) : '';
                if (preg_match('/^\d{2}:\d{2}$/', $start_local)) {
                    $shift_label = $start_local;
                    if (preg_match('/^\d{2}:\d{2}$/', $end_local)) {
                        $shift_label .= '–' . $end_local;
                    }
                }
            }

            $role_id = isset($row['role_id']) ? absint($row['role_id']) : 0;
            $role_label = trim((string) ($row['display_label_override'] ?? ''));
            if ($role_label === '' && $role_id > 0 && isset($role_map[$role_id]['name'])) {
                $role_label = (string) $role_map[$role_id]['name'];
            }
            if ($role_label === '') {
                $role_label = __('Shift', 'backstage-venue-manager');
            }

            $status_key = sanitize_key((string) ($row['assignment_status'] ?? ''));
            $status_label = vms_staff_portal_assignment_status_label($status_key);

            $rows[] = array(
                'assignment_id' => isset($row['assignment_id']) ? absint($row['assignment_id']) : 0,
                'event_plan_id' => $plan_id,
                'event_title' => (string) ($plan['title'] ?? __('Event Plan', 'backstage-venue-manager')),
                'event_date' => $event_date,
                'date_label' => function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($event_date, 'D, M j, Y') : $event_date,
                'short_date_label' => function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($event_date, 'M j') : $event_date,
                'assignment_status' => $status_key,
                'assignment_status_label' => $status_label,
                'event_status' => (string) ($plan['status'] ?? ''),
                'event_status_label' => function_exists('bvmgr_event_plan_status_label')
                    ? (string) bvmgr_event_plan_status_label((string) ($plan['status'] ?? ''))
                    : (string) ($plan['status'] ?? ''),
                'role_id' => $role_id,
                'role_label' => $role_label,
                'shift_label' => $shift_label,
                'start_ts' => $start_ts,
                'end_ts' => $end_ts,
                'slot_notes' => isset($row['slot_notes']) ? (string) $row['slot_notes'] : '',
            );
        }

        usort($rows, static function (array $a, array $b): int {
            $a_ts = isset($a['start_ts']) ? (int) $a['start_ts'] : 0;
            $b_ts = isset($b['start_ts']) ? (int) $b['start_ts'] : 0;
            if ($a_ts > 0 && $b_ts > 0 && $a_ts !== $b_ts) {
                return ($a_ts < $b_ts) ? -1 : 1;
            }
            $a_date = (string) ($a['event_date'] ?? '');
            $b_date = (string) ($b['event_date'] ?? '');
            if ($a_date !== $b_date) {
                return strcmp($a_date, $b_date);
            }
            return strcmp((string) ($a['role_label'] ?? ''), (string) ($b['role_label'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('vms_staff_portal_get_active_dates')) {
    function vms_staff_portal_get_active_dates(): array
    {
        if (function_exists('vms_vendor_get_active_dates_or_rolling_window')) {
            return (array) vms_vendor_get_active_dates_or_rolling_window(12);
        }

        $active_dates = get_option('vms_active_dates', array());
        if (!is_array($active_dates)) {
            $active_dates = array();
        }

        return array_values(array_filter(array_map(static function ($date) {
            $date = trim((string) $date);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
        }, $active_dates)));
    }
}

if (!function_exists('vms_staff_portal_group_dates_by_month')) {
    function vms_staff_portal_group_dates_by_month(array $dates): array
    {
        $grouped = array();
        foreach ($dates as $date) {
            $date = trim((string) $date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $ym = substr($date, 0, 7);
            if (!isset($grouped[$ym])) {
                $grouped[$ym] = array();
            }
            $grouped[$ym][] = $date;
        }
        ksort($grouped);
        return $grouped;
    }
}


if (!function_exists('vms_staff_portal_normalize_manual_availability')) {
    function vms_staff_portal_normalize_manual_availability(int $staff_id): array
    {
        if (function_exists('vms_vendor_normalize_manual_availability')) {
            return (array) vms_vendor_normalize_manual_availability($staff_id);
        }

        $manual = get_post_meta($staff_id, '_vms_availability_manual', true);
        if (!is_array($manual)) {
            $manual = array();
        }

        $normalized = array();
        foreach ($manual as $date => $state) {
            $date = sanitize_text_field((string) $date);
            $state = sanitize_key((string) $state);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if (!in_array($state, array('available', 'unavailable'), true)) {
                continue;
            }
            $normalized[$date] = $state;
        }

        return $normalized;
    }
}

if (!function_exists('vms_staff_portal_normalize_pattern_days')) {
    function vms_staff_portal_normalize_pattern_days(int $staff_id): array
    {
        if (function_exists('vms_vendor_normalize_pattern_days')) {
            return (array) vms_vendor_normalize_pattern_days($staff_id);
        }

        $pattern_days = get_post_meta($staff_id, '_vms_pattern_days', true);
        if (!is_array($pattern_days)) {
            $pattern_days = array();
        }

        $pattern_days = array_values(array_unique(array_filter(array_map('intval', $pattern_days), static function ($d) {
            return $d >= 0 && $d <= 6;
        })));
        sort($pattern_days);

        return $pattern_days;
    }
}

if (!function_exists('vms_staff_portal_normalize_ics_unavailable')) {
    function vms_staff_portal_normalize_ics_unavailable(int $staff_id): array
    {
        if (function_exists('vms_vendor_normalize_ics_unavailable')) {
            return (array) vms_vendor_normalize_ics_unavailable($staff_id);
        }

        $ics_unavailable = get_post_meta($staff_id, '_vms_ics_unavailable', true);
        if (!is_array($ics_unavailable)) {
            $ics_unavailable = array();
        }

        $is_list = (array_keys($ics_unavailable) === range(0, max(0, count($ics_unavailable) - 1)));
        if (!$is_list && !empty($ics_unavailable)) {
            $ics_unavailable = array_keys($ics_unavailable);
        } elseif (empty($ics_unavailable)) {
            $ics_layer = get_post_meta($staff_id, '_vms_availability_ics', true);
            if (is_array($ics_layer) && !empty($ics_layer)) {
                $ics_unavailable = array_keys($ics_layer);
            }
        }

        $ics_unavailable = array_map('sanitize_text_field', $ics_unavailable);
        $ics_unavailable = array_filter($ics_unavailable, static function ($date) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date);
        });

        return array_values(array_unique($ics_unavailable));
    }
}

if (!function_exists('vms_staff_portal_has_availability_setup')) {
    function vms_staff_portal_has_availability_setup(int $staff_id): bool
    {
        $manual = vms_staff_portal_normalize_manual_availability($staff_id);
        $pattern_enabled = (int) get_post_meta($staff_id, '_vms_pattern_enabled', true);
        $pattern_days = vms_staff_portal_normalize_pattern_days($staff_id);
        $ics_url = trim((string) get_post_meta($staff_id, '_vms_ics_url', true));
        $ics_unavailable = vms_staff_portal_normalize_ics_unavailable($staff_id);

        return !empty($manual)
            || ($pattern_enabled && !empty($pattern_days))
            || $ics_url !== ''
            || !empty($ics_unavailable);
    }
}

if (!function_exists('vms_staff_portal_availability_source_label')) {
    function vms_staff_portal_availability_source_label(string $reason): string
    {
        if (function_exists('vms_vendor_availability_source_label')) {
            return (string) vms_vendor_availability_source_label($reason);
        }

        $reason = sanitize_key($reason);
        $map = array(
            'manual' => __('Manual', 'backstage-venue-manager'),
            'pattern' => __('Pattern', 'backstage-venue-manager'),
            'ics' => __('ICS', 'backstage-venue-manager'),
            'no_response' => __('No response', 'backstage-venue-manager'),
        );

        return isset($map[$reason]) ? (string) $map[$reason] : ucfirst(str_replace('_', ' ', $reason));
    }
}

if (!function_exists('vms_staff_portal_source_icon')) {
    function vms_staff_portal_source_icon(string $reason): string
    {
        $reason = sanitize_key($reason);
        if ($reason === 'ics') {
            return '📅';
        }
        if ($reason === 'pattern') {
            return '🗓️';
        }
        return '';
    }
}

if (!function_exists('vms_staff_portal_effective_availability_for_date')) {
    function vms_staff_portal_effective_availability_for_date(int $staff_id, string $date): array
    {
        $staff_id = absint($staff_id);
        $date = sanitize_text_field((string) $date);
        if ($staff_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return array(
                'state' => 'no-response',
                'label' => __('Unset', 'backstage-venue-manager'),
                'visual_state' => '',
                'reason' => 'invalid',
                'source' => vms_staff_portal_availability_source_label('no_response'),
                'manual_state' => '',
            );
        }

        $manual = vms_staff_portal_normalize_manual_availability($staff_id);
        $manual_state = isset($manual[$date]) ? sanitize_key((string) $manual[$date]) : '';
        if ($manual_state === 'available') {
            return array(
                'state' => 'available',
                'label' => __('Available', 'backstage-venue-manager'),
                'visual_state' => 'available',
                'reason' => 'manual',
                'source' => vms_staff_portal_availability_source_label('manual'),
                'manual_state' => $manual_state,
            );
        }
        if ($manual_state === 'unavailable') {
            return array(
                'state' => 'unavailable',
                'label' => __('Unavailable', 'backstage-venue-manager'),
                'visual_state' => 'unavailable',
                'reason' => 'manual',
                'source' => vms_staff_portal_availability_source_label('manual'),
                'manual_state' => $manual_state,
            );
        }

        $pattern_enabled = (int) get_post_meta($staff_id, '_vms_pattern_enabled', true);
        $pattern_days = vms_staff_portal_normalize_pattern_days($staff_id);
        $pattern_matches = false;
        if ($pattern_enabled && !empty($pattern_days)) {
            $dow = function_exists('bvmgr_local_ymd_dow') ? (int) (bvmgr_local_ymd_dow($date) ?? -1) : (int) wp_date('w', strtotime($date), wp_timezone());
            if (!in_array($dow, $pattern_days, true)) {
                return array(
                    'state' => 'unavailable',
                    'label' => __('Unavailable', 'backstage-venue-manager'),
                    'visual_state' => 'unavailable',
                    'reason' => 'pattern',
                    'source' => vms_staff_portal_availability_source_label('pattern'),
                    'manual_state' => '',
                );
            }
            $pattern_matches = true;
        }

        $ics_lookup = array_fill_keys(vms_staff_portal_normalize_ics_unavailable($staff_id), true);
        if (isset($ics_lookup[$date])) {
            return array(
                'state' => 'unavailable',
                'label' => __('Unavailable', 'backstage-venue-manager'),
                'visual_state' => 'unavailable',
                'reason' => 'ics',
                'source' => vms_staff_portal_availability_source_label('ics'),
                'manual_state' => '',
            );
        }

        if ($pattern_matches) {
            return array(
                'state' => 'available',
                'label' => __('Available', 'backstage-venue-manager'),
                'visual_state' => 'available',
                'reason' => 'pattern',
                'source' => vms_staff_portal_availability_source_label('pattern'),
                'manual_state' => '',
            );
        }

        return array(
            'state' => 'no-response',
            'label' => __('Unset', 'backstage-venue-manager'),
            'visual_state' => '',
            'reason' => 'no_response',
            'source' => vms_staff_portal_availability_source_label('no_response'),
            'manual_state' => '',
        );
    }
}

if (!function_exists('vms_staff_portal_month_matrix')) {
    function vms_staff_portal_month_matrix(string $ym): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return array();
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $first_dt = DateTimeImmutable::createFromFormat('!Y-m', $ym, $tz);
        if (!$first_dt instanceof DateTimeImmutable) {
            return array();
        }

        $days_in_month = (int) $first_dt->format('t');
        $first_dow = (int) $first_dt->format('w');
        $cells = array();

        for ($i = 0; $i < $first_dow; $i++) {
            $cells[] = array('date' => '', 'day' => '');
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $cells[] = array(
                'date' => sprintf('%s-%02d', $ym, $day),
                'day' => $day,
            );
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = array('date' => '', 'day' => '');
        }

        return array_chunk($cells, 7);
    }
}

if (!function_exists('vms_staff_portal_day_visual_state')) {
    function vms_staff_portal_day_visual_state(string $date, array $manual, array $assignments_by_date, int $staff_id = 0): array
    {
        $base = ($staff_id > 0)
            ? vms_staff_portal_effective_availability_for_date($staff_id, $date)
            : array(
                'state' => (isset($manual[$date]) ? sanitize_key((string) $manual[$date]) : 'no-response'),
                'label' => __('Unset', 'backstage-venue-manager'),
                'visual_state' => '',
                'reason' => 'no_response',
                'source' => vms_staff_portal_availability_source_label('no_response'),
                'manual_state' => isset($manual[$date]) ? sanitize_key((string) $manual[$date]) : '',
            );

        $manual_state = isset($base['manual_state']) ? sanitize_key((string) $base['manual_state']) : '';
        $assignments = isset($assignments_by_date[$date]) && is_array($assignments_by_date[$date]) ? $assignments_by_date[$date] : array();
        $has_assignments = !empty($assignments);
        $base_state = sanitize_key((string) ($base['state'] ?? 'no-response'));
        $base_src = sanitize_key((string) ($base['reason'] ?? ''));
        $base_visual = sanitize_key((string) ($base['visual_state'] ?? ''));
        $base_label = (string) ($base['label'] ?? __('Unset', 'backstage-venue-manager'));

        if ($has_assignments) {
            $conflict = ($base_state === 'unavailable' || $base_visual === 'unavailable');
            return array(
                'status_key' => $conflict ? 'conflict' : 'working',
                'status_label' => $conflict ? __('Conflict', 'backstage-venue-manager') : __('Working', 'backstage-venue-manager'),
                'visual_state' => $conflict ? 'unavailable' : 'available',
                'manual_state' => $manual_state,
                'assignments' => $assignments,
                'is_read_only' => true,
                'base_state' => $base_state,
                'base_label' => $base_label,
                'base_src' => $base_src,
                'base_src_label' => (string) ($base['source'] ?? ''),
            );
        }

        return array(
            'status_key' => ($base_state === 'available' || $base_state === 'unavailable') ? $base_state : 'unset',
            'status_label' => $base_label,
            'visual_state' => $base_visual,
            'manual_state' => $manual_state,
            'assignments' => array(),
            'is_read_only' => false,
            'base_state' => $base_state,
            'base_label' => $base_label,
            'base_src' => $base_src,
            'base_src_label' => (string) ($base['source'] ?? ''),
        );
    }
}

function vms_staff_portal_shortcode()
{
    if (function_exists('wp_enqueue_style')) {
        wp_enqueue_style('vms-portal');
    }
    if (function_exists('wp_enqueue_script')) {
        $calendar_script_ver = function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : null);
        if (defined('BVMGR_PLUGIN_PATH')) {
            $calendar_script_file = BVMGR_PLUGIN_PATH . 'assets/js/vms-public-calendar.js';
            if (file_exists($calendar_script_file)) {
                $calendar_script_ver = (string) @filemtime($calendar_script_file);
            }
        }
        wp_enqueue_script(
            'vms-public-calendar',
            BVMGR_PLUGIN_URL . 'assets/js/vms-public-calendar.js',
            array(),
            $calendar_script_ver,
            true
        );
    }

    $base_url = get_permalink();

    $url_dashboard      = add_query_arg('tab', 'dashboard', $base_url);
    $url_certifications = add_query_arg('tab', 'certifications', $base_url);
    $url_availability   = add_query_arg('tab', 'availability', $base_url);

    if (!is_user_logged_in()) {
        ob_start();
        echo '<p>' . esc_html__('Please log in to access the staff portal.', 'backstage-venue-manager') . '</p>';
        echo wp_login_form(array('echo' => false, 'redirect' => esc_url(get_permalink())));
        return ob_get_clean();
    }

    $user_id  = get_current_user_id();
    $staff_id = (int) get_user_meta($user_id, '_vms_staff_id', true);

    if (!$staff_id) {
        return '<p>' . esc_html__('Your account is not linked to a staff profile yet. Please contact the admin.', 'backstage-venue-manager') . '</p>';
    }

    $staff = get_post($staff_id);
    if (!$staff || $staff->post_type !== 'vms_staff') {
        return '<p>' . esc_html__('Your linked staff profile could not be found. Please contact the admin.', 'backstage-venue-manager') . '</p>';
    }

    $worker_type = function_exists('vms_staff_get_worker_type') ? (string) vms_staff_get_worker_type($staff_id) : '';
    if ($worker_type === '') {
        $raw = (string) get_post_meta($staff_id, '_vms_staff_worker_type', true);
        $raw = sanitize_key($raw);
        $worker_type = in_array($raw, array('employee', 'contractor'), true) ? $raw : 'contractor';
    }

    $tax_tab_slug  = ($worker_type === 'employee') ? 'employee-packet' : 'tax-profile';
    $tax_tab_label = ($worker_type === 'employee') ? __('Employee Packet', 'backstage-venue-manager') : __('Tax Profile', 'backstage-venue-manager');
    $url_tax_tab   = add_query_arg('tab', $tax_tab_slug, $base_url);

    $tab = vms_staff_portal_query_key('tab');
    if ($tab === '') {
        $tab = 'dashboard';
    }
    if (!in_array($tab, array('dashboard', 'tax-profile', 'employee-packet', 'certifications', 'availability'), true)) {
        $tab = 'dashboard';
    }
    if ($tab === 'availability' && function_exists('wp_enqueue_script')) {
        $staff_portal_script_src = function_exists('bvmgr_asset_url')
            ? bvmgr_asset_url('assets/js/vms-staff-portal.js')
            : BVMGR_PLUGIN_URL . 'assets/js/vms-staff-portal.js';
        $staff_portal_script_ver = function_exists('bvmgr_asset_version_for')
            ? bvmgr_asset_version_for('assets/js/vms-staff-portal.js')
            : (function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : ''));
        wp_enqueue_script('vms-staff-portal', $staff_portal_script_src, array(), $staff_portal_script_ver, true);
    }

    ob_start();

    echo '<div class="vms-portal" id="vms-portal-root">';
    echo '<h2>' . esc_html__('Staff Portal:', 'backstage-venue-manager') . ' ' . esc_html($staff->post_title) . '</h2>';

    echo '<nav class="vms-portal-nav">';
    echo '<a class="' . ($tab === 'dashboard' ? 'is-active' : '') . '" href="' . esc_url($url_dashboard) . '">' . esc_html__('Dashboard', 'backstage-venue-manager') . '</a>';
    echo '<a class="' . ($tab === $tax_tab_slug ? 'is-active' : '') . '" href="' . esc_url($url_tax_tab) . '">' . esc_html($tax_tab_label) . '</a>';
    echo '<a class="' . ($tab === 'certifications' ? 'is-active' : '') . '" href="' . esc_url($url_certifications) . '">' . esc_html__('Certifications', 'backstage-venue-manager') . '</a>';
    echo '<a class="' . ($tab === 'availability' ? 'is-active' : '') . '" href="' . esc_url($url_availability) . '">' . esc_html__('Availability', 'backstage-venue-manager') . '</a>';
    echo '</nav>';

    if ($tab === 'dashboard') {
        vms_staff_portal_render_dashboard($staff_id, $worker_type, $tax_tab_label, $url_tax_tab, $url_availability, $url_certifications);
    } elseif ($tab === 'tax-profile' && $worker_type !== 'employee') {
        vms_staff_portal_render_tax_profile($staff_id);
    } elseif ($tab === 'employee-packet' && $worker_type === 'employee') {
        vms_staff_portal_render_employee_packet($staff_id);
    } elseif ($tab === 'certifications') {
        vms_staff_portal_render_certifications($staff_id);
    } elseif ($tab === 'availability') {
        vms_staff_portal_render_availability_manual($staff_id);
    } else {
        echo '<p>' . esc_html__('Unknown tab.', 'backstage-venue-manager') . '</p>';
    }

    echo '</div>';
    return ob_get_clean();
}

if (!function_exists('vms_staff_portal_render_dashboard')) {
    function vms_staff_portal_render_dashboard(int $staff_id, string $worker_type, string $tax_tab_label, string $url_tax_tab, string $url_availability, string $url_certifications = ''): void
    {
        $tz = wp_timezone();
        $assignments = vms_staff_portal_get_assignment_rows($staff_id, 12);
        $event_map = vms_staff_portal_calendar_event_map($assignments);
        $next_shift = !empty($assignments) ? $assignments[0] : null;
        $next_shift_glance = !empty($next_shift) ? vms_staff_portal_build_shift_glance($next_shift, $event_map) : array();

        if ($worker_type === 'employee') {
            $missing = vms_staff_portal_employee_packet_missing_items($staff_id);
            $tax_stage = empty($missing) ? 'complete' : 'incomplete';
            $tax_provider_label = __('Employee packet workflow', 'backstage-venue-manager');
        } else {
            $tax_status = vms_staff_portal_tax_status($staff_id);
            $missing = isset($tax_status['missing']) ? (array) $tax_status['missing'] : array();
            $tax_stage = isset($tax_status['stage']) ? (string) $tax_status['stage'] : 'incomplete';
            $tax_provider_label = isset($tax_status['provider_label']) ? (string) $tax_status['provider_label'] : __('Upload', 'backstage-venue-manager');
        }

        $manual = get_post_meta($staff_id, '_vms_availability_manual', true);
        if (!is_array($manual)) {
            $manual = array();
        }
        $today = wp_date('Y-m-d', time(), $tz);
        $manual_future = 0;
        $manual_available = 0;
        $manual_unavailable = 0;
        foreach ($manual as $date => $state) {
            $date = (string) $date;
            $state = sanitize_key((string) $state);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) {
                continue;
            }
            $manual_future++;
            if ($state === 'available') $manual_available++;
            if ($state === 'unavailable') $manual_unavailable++;
        }

        echo '<div class="vms-dash-grid">';

        echo '<div>';
        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Next Shift', 'backstage-venue-manager') . '</h3>';
        if (!empty($next_shift)) {
            $next_ticket_qty = $next_shift_glance['ticket_qty'] ?? null;
            $next_docs = isset($next_shift_glance['docs']) && is_array($next_shift_glance['docs']) ? (array) $next_shift_glance['docs'] : array();
            $next_crew = isset($next_shift_glance['crew']) && is_array($next_shift_glance['crew']) ? (array) $next_shift_glance['crew'] : array();

            echo '<div class="vms-portal-hero-value">' . esc_html((string) ($next_shift['date_label'] ?? '')) . '</div>';
            echo '<div class="vms-muted vms-mt-8"><strong>' . esc_html((string) ($next_shift['event_title'] ?? '')) . '</strong></div>';
            echo '<div class="vms-muted vms-mt-2">' . esc_html((string) ($next_shift['role_label'] ?? __('Shift', 'backstage-venue-manager'))) . '</div>';
            if (!empty($next_shift['shift_label'])) {
                echo '<div class="vms-muted vms-mt-2">' . esc_html((string) $next_shift['shift_label']) . '</div>';
            }
            echo '<div class="vms-mt-8">';
            echo '<span class="vms-av-badge-status is-' . esc_attr((($next_shift['assignment_status'] ?? '') === 'confirmed') ? 'working' : 'submitted') . '">' . esc_html((string) ($next_shift['assignment_status_label'] ?? __('Scheduled', 'backstage-venue-manager'))) . '</span>';
            echo '</div>';

            echo '<div class="vms-dash-kpis vms-mt-10">';
            echo '<div class="vms-dash-kpi"><b>' . esc_html__('Tickets sold', 'backstage-venue-manager') . '</b><span>' . esc_html($next_ticket_qty === null ? '—' : (string) (int) $next_ticket_qty) . '</span></div>';
            echo '<div class="vms-dash-kpi"><b>' . esc_html__('Staff assigned', 'backstage-venue-manager') . '</b><span>' . esc_html((string) count(vms_staff_portal_consolidate_crew_rows($next_crew))) . '</span></div>';
            if (!empty($next_shift_glance['can_view_docs'])) {
                echo '<div class="vms-dash-kpi"><b>' . esc_html__('Tech docs', 'backstage-venue-manager') . '</b><span>' . esc_html((string) count($next_docs)) . '</span></div>';
            }
            echo '</div>';
        } else {
            echo '<p class="vms-muted vms-m0">' . esc_html__('No upcoming shifts are assigned yet.', 'backstage-venue-manager') . '</p>';
        }
        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Upcoming Schedule', 'backstage-venue-manager') . '</h3>';
        if (!empty($assignments)) {
            echo '<ul class="vms-dash-list">';
            foreach (array_slice($assignments, 0, 6) as $assignment) {
                $line = trim((string) ($assignment['short_date_label'] ?? ''));
                if (!empty($assignment['shift_label'])) {
                    $line .= ' @ ' . (string) $assignment['shift_label'];
                }
                echo '<li><strong>' . esc_html($line) . '</strong> <span class="vms-muted">— ' . esc_html((string) ($assignment['event_title'] ?? '')) . ' · ' . esc_html((string) ($assignment['role_label'] ?? __('Shift', 'backstage-venue-manager'))) . '</span></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="vms-muted vms-m0">' . esc_html__('Nothing is scheduled yet.', 'backstage-venue-manager') . '</p>';
        }
        echo '<div class="vms-dash-actions">';
        echo '<a class="button button-primary" href="' . esc_url($url_availability) . '">' . esc_html__('Update Availability', 'backstage-venue-manager') . '</a>';
        if ($url_certifications !== '') {
            echo '<a class="button" href="' . esc_url($url_certifications) . '">' . esc_html__('Upload Certifications', 'backstage-venue-manager') . '</a>';
        }
        echo '<a class="button" href="' . esc_url($url_tax_tab) . '">' . esc_html__('Open ', 'backstage-venue-manager') . esc_html($tax_tab_label) . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div>';
        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html($tax_tab_label) . '</h3>';
        echo '<p class="vms-tax-profile-status">' . wp_kses(vms_staff_portal_safe_html(vms_staff_portal_badge_html($tax_stage)), vms_staff_portal_safe_html_allowed_html()) . '</p>';
        echo '<div class="vms-dash-kpis">';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Workflow', 'backstage-venue-manager') . '</b><span>' . esc_html($tax_provider_label) . '</span></div>';
        if ($worker_type !== 'employee' && !empty($tax_status['attested_at']) && empty($tax_status['is_complete'])) {
            echo '<div class="vms-dash-kpi"><b>' . esc_html__('You submitted', 'backstage-venue-manager') . '</b><span>' . esc_html(vms_staff_portal_format_ts((int) $tax_status['attested_at'], 'M j, Y')) . '</span></div>';
        }
        if ($worker_type !== 'employee' && !empty($tax_status['done_at'])) {
            echo '<div class="vms-dash-kpi"><b>' . esc_html__('Confirmed', 'backstage-venue-manager') . '</b><span>' . esc_html(vms_staff_portal_format_ts((int) $tax_status['done_at'], 'M j, Y')) . '</span></div>';
        }
        echo '</div>';
        if (!empty($missing)) {
            echo '<p class="vms-muted vms-mt-10">' . esc_html__('Still needed:', 'backstage-venue-manager') . ' ' . esc_html(implode(', ', array_map('strval', $missing))) . '</p>';
        } elseif ($worker_type !== 'employee' && !empty($tax_status['is_complete'])) {
            echo '<p class="vms-muted vms-mt-10">' . esc_html__('You are cleared for tax/payment processing.', 'backstage-venue-manager') . '</p>';
        }
        echo '</div>';

        echo '<div class="vms-portal-card">';
        echo '<h3>' . esc_html__('Availability Snapshot', 'backstage-venue-manager') . '</h3>';
        echo '<div class="vms-dash-kpis">';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Manual dates', 'backstage-venue-manager') . '</b><span>' . esc_html((string) $manual_future) . '</span></div>';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Available', 'backstage-venue-manager') . '</b><span>' . esc_html((string) $manual_available) . '</span></div>';
        echo '<div class="vms-dash-kpi"><b>' . esc_html__('Unavailable', 'backstage-venue-manager') . '</b><span>' . esc_html((string) $manual_unavailable) . '</span></div>';
        echo '</div>';
        echo '<div class="vms-muted vms-mt-10">' . esc_html__('Your dashboard shows upcoming assigned shifts. Your Availability tab is where you manage future working dates.', 'backstage-venue-manager') . '</div>';
        echo '</div>';
        echo '</div>';

        vms_staff_portal_render_assigned_event_cards($staff_id, array_slice($assignments, 0, 6), $event_map);

        echo '</div>';
    }
}

/**
 * Employee packet helpers (front-end safe)
 */
function vms_staff_portal_is_exact_post_request(): bool
{
    $request_method = $_SERVER['REQUEST_METHOD'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The local exact-post helper intentionally preserves raw method casing while unslashing before comparison.
    if (!is_scalar($request_method)) {
        return false;
    }

    $request_method = wp_unslash($request_method);
    if (!is_string($request_method)) {
        return false;
    }

    return 'POST' === $request_method;
}

function vms_staff_portal_query_key(string $key): string
{
    return bvmgr_request_read_key($_GET, $key); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Staff portal tab state is read-only navigation context.
}

function vms_staff_portal_post_text_field(string $key): string
{
    return bvmgr_request_read_text_field($_POST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Staff portal form fields are only read after the matching nonce has been verified.
}

function vms_staff_portal_post_scalar(string $key): string
{
    return bvmgr_request_read_scalar($_POST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Staff portal form fields are only read after the matching nonce has been verified.
}

function vms_staff_portal_post_bool_flag(string $key): bool
{
    return bvmgr_request_read_bool_flag($_POST, $key); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Staff portal checkbox fields are only read after the matching nonce has been verified.
}

function vms_staff_portal_post_array(string $key): array
{
    // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $value = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
    // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    return is_array($value) ? $value : array();
}

function vms_staff_portal_employee_packet_missing_items(int $staff_id): array
{
    $staff_id = (int) $staff_id;
    $missing = array();

    $w4 = (int) get_post_meta($staff_id, '_vms_employee_w4_received', true) ? 1 : 0;
    $i9 = (int) get_post_meta($staff_id, '_vms_employee_i9_verified', true) ? 1 : 0;

    if (!$w4) $missing[] = __('W-4 received', 'backstage-venue-manager');
    if (!$i9) $missing[] = __('I-9 verified', 'backstage-venue-manager');

    return $missing;
}

/**
 * Employee Packet portal tab
 * This does NOT collect SSN or identity documents.
 * It lets staff acknowledge they have submitted paperwork to the venue.
 */
function vms_staff_portal_render_employee_packet(int $staff_id): void
{
    $staff_id = (int) $staff_id;

    $missing = vms_staff_portal_employee_packet_missing_items($staff_id);
    $is_complete = empty($missing);

    if (vms_staff_portal_is_exact_post_request() && isset($_POST['vms_employee_packet_ack'])) {
        $nonce = (isset($_POST['vms_employee_packet_nonce']) && !is_array($_POST['vms_employee_packet_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['vms_employee_packet_nonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_employee_packet_ack')) {
            echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
        } else {
            $now = time();
            $ack_w4 = isset($_POST['vms_ack_w4']) ? 1 : 0;
            $ack_i9 = isset($_POST['vms_ack_i9']) ? 1 : 0;
            $ack_dd = isset($_POST['vms_ack_dd']) ? 1 : 0;

            if ($ack_w4) update_post_meta($staff_id, '_vms_employee_w4_attested_at', $now);
            if ($ack_i9) update_post_meta($staff_id, '_vms_employee_i9_attested_at', $now);
            if ($ack_dd) update_post_meta($staff_id, '_vms_employee_direct_deposit_attested_at', $now);

            update_post_meta($staff_id, '_vms_employee_packet_attested_at', $now);

            echo wp_kses(vms_staff_portal_notice_html('success', __('Thanks! Your submission was recorded. The admin will verify your packet.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
        }
    }

    echo '<div class="vms-portal-card">';
    echo '<h3 class="vms-mt-0">' . esc_html__('Employee Packet', 'backstage-venue-manager') . '</h3>';
    echo '<p class="vms-tax-profile-status">' . ($is_complete ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>' : '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>') . '</p>';
    echo '<p>' . esc_html__('W-2 employees must provide onboarding paperwork. This portal does not collect SSN or identity documents.', 'backstage-venue-manager') . '</p>';
    echo '</div>';

    if (!$is_complete) {
        echo '<div class="vms-portal-card">';
        echo '<h4 class="vms-mt-0">' . esc_html__('Missing items (admin verification)', 'backstage-venue-manager') . '</h4>';
        echo '<ul>';
        foreach ($missing as $m) {
            echo '<li>' . esc_html((string) $m) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    $attested_at = (int) get_post_meta($staff_id, '_vms_employee_packet_attested_at', true);
    if ($attested_at > 0 && !$is_complete) {
        echo '<div class="vms-portal-card">';
        echo '<p class="vms-muted">' . esc_html__('Submission recorded. Waiting on admin verification.', 'backstage-venue-manager') . '</p>';
        echo '</div>';
    }

    echo '<div class="vms-portal-card">';
    echo '<h4 class="vms-mt-0">' . esc_html__('Tell the admin you submitted your packet', 'backstage-venue-manager') . '</h4>';
    echo '<form method="post">';
    wp_nonce_field('vms_employee_packet_ack', 'vms_employee_packet_nonce');
    echo '<label class="vms-portal-check"><input type="checkbox" name="vms_ack_w4" value="1"> ' . esc_html__('I submitted my W-4', 'backstage-venue-manager') . '</label>';
    echo '<label class="vms-portal-check"><input type="checkbox" name="vms_ack_i9" value="1"> ' . esc_html__('I completed my I-9 verification', 'backstage-venue-manager') . '</label>';
    echo '<label class="vms-portal-check"><input type="checkbox" name="vms_ack_dd" value="1"> ' . esc_html__('I submitted direct deposit info (if used)', 'backstage-venue-manager') . '</label>';
    echo '<p><button class="button button-primary" type="submit" name="vms_employee_packet_ack" value="1">' . esc_html__('Submit', 'backstage-venue-manager') . '</button></p>';
    echo '</form>';
    echo '</div>';
}

/**
 * Staff Tax Profile (provider-aware).
 * - NO SSN/EIN typed into site
 * - Collect payee, address, entity
 * - W-9 step depends on the configured source of truth
 */
function vms_staff_portal_render_tax_profile($staff_id)
{
    $staff_id = (int) $staff_id;
    $provider = vms_staff_portal_tax_provider();
    $provider_label = vms_staff_portal_provider_label($provider);

    $k_done = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'tax_profile_completed_at') : '_vms_tax_profile_completed_at';
    $k_attest = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'w9_attested_at') : '_vms_w9_external_vendor_attested_at';
    $k_prov = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('vendor', 'w9_provider') : '_vms_w9_offsite_provider';
    $k_upload_kind = function_exists('bvmgr_private_w9_storage_kind_meta_key') ? bvmgr_private_w9_storage_kind_meta_key() : '_vms_w9_upload_storage_kind';

    if (vms_staff_portal_is_exact_post_request() && isset($_POST['vms_staff_tax_save'])) {
        $nonce = (isset($_POST['vms_staff_tax_nonce']) && !is_array($_POST['vms_staff_tax_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_tax_nonce']))
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_tax_save')) {
            echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
        } else {
            $t = static function ($key) {
                return vms_staff_portal_post_text_field((string) $key);
            };

            $payee_legal = $t('vms_payee_legal_name');
            $dba         = $t('vms_payee_dba');
            $entity      = $t('vms_entity_type');
            $addr1       = $t('vms_addr1');
            $addr2       = $t('vms_addr2');
            $city        = $t('vms_city');
            $state       = strtoupper($t('vms_state'));
            $zip         = $t('vms_zip');

            if (strlen($state) > 2) {
                $state = substr($state, 0, 2);
            }

            update_post_meta($staff_id, '_vms_payee_legal_name', $payee_legal);
            update_post_meta($staff_id, '_vms_payee_dba', $dba);
            update_post_meta($staff_id, '_vms_entity_type', $entity);
            update_post_meta($staff_id, '_vms_addr1', $addr1);
            update_post_meta($staff_id, '_vms_addr2', $addr2);
            update_post_meta($staff_id, '_vms_city', $city);
            update_post_meta($staff_id, '_vms_state', $state);
            update_post_meta($staff_id, '_vms_zip', $zip);

            if ($provider === 'upload') {
                if (bvmgr_upload_request_has_file($_FILES, 'vms_w9_upload')) {
                    $previous_upload_id = (int) get_post_meta($staff_id, '_vms_w9_upload_id', true);
                    $previous_kind = sanitize_key((string) get_post_meta($staff_id, $k_upload_kind, true));
                    $file_id = function_exists('bvmgr_private_w9_store_upload')
                        ? bvmgr_private_w9_store_upload($staff_id, $_FILES)
                        : new WP_Error('w9_upload_unavailable', __('The W-9 upload handler is unavailable.', 'backstage-venue-manager'));
                    if (is_wp_error($file_id)) {
                        echo wp_kses(vms_staff_portal_notice_html('error', __('W-9 upload failed: ', 'backstage-venue-manager') . $file_id->get_error_message()), vms_staff_portal_safe_html_allowed_html());
                    } else {
                        update_post_meta($staff_id, '_vms_w9_upload_id', (int) $file_id);
                        update_post_meta($staff_id, $k_upload_kind, 'private_file');
                        update_post_meta($staff_id, '_vms_w9_received_date', wp_date('Y-m-d', time(), wp_timezone()));
                        if ($previous_kind === 'private_file' && $previous_upload_id > 0 && $previous_upload_id !== (int) $file_id && function_exists('bvmgr_private_files_delete')) {
                            bvmgr_private_files_delete($previous_upload_id);
                        }
                    }
                }
            } else {
                $attest = vms_staff_portal_post_bool_flag('vms_w9_offsite_attest') ? '1' : '';
                if ($attest === '1') {
                    if (!(int) get_post_meta($staff_id, $k_attest, true)) {
                        update_post_meta($staff_id, $k_attest, time());
                    }
                    update_post_meta($staff_id, $k_prov, $provider);
                } else {
                    delete_post_meta($staff_id, $k_attest);
                    delete_post_meta($staff_id, $k_prov);
                    delete_post_meta($staff_id, $k_done);
                }
            }

            if (function_exists('bvmgr_vendor_tax_profile_is_complete')) {
                if (bvmgr_vendor_tax_profile_is_complete($staff_id)) {
                    if (!(int) get_post_meta($staff_id, $k_done, true)) {
                        update_post_meta($staff_id, $k_done, time());
                    }
                } elseif ($provider === 'upload') {
                    delete_post_meta($staff_id, $k_done);
                }
            }

            echo wp_kses(vms_staff_portal_notice_html('success', __('Tax Profile saved.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
        }
    }

    $m = static function ($key, $default = '') use ($staff_id) {
        $v = get_post_meta($staff_id, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $payee_legal = $m('_vms_payee_legal_name');
    $dba         = $m('_vms_payee_dba');
    $entity      = $m('_vms_entity_type');
    $addr1       = $m('_vms_addr1');
    $addr2       = $m('_vms_addr2');
    $city        = $m('_vms_city');
    $state       = $m('_vms_state');
    $zip         = $m('_vms_zip');

    $w9_upload_id = (int) get_post_meta($staff_id, '_vms_w9_upload_id', true);
    $w9_url = $w9_upload_id && function_exists('bvmgr_private_w9_download_url') ? bvmgr_private_w9_download_url($staff_id) : '';
    $w9_label = $w9_upload_id && function_exists('bvmgr_private_w9_file_label') ? bvmgr_private_w9_file_label($staff_id) : '';

    $tax_status = vms_staff_portal_tax_status($staff_id);
    $missing = isset($tax_status['missing']) ? (array) $tax_status['missing'] : array();
    $is_complete = !empty($tax_status['is_complete']);
    $attested_checked = !empty($tax_status['attested_at']);

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

    echo '<div class="vms-portal-card">';
    echo '<h3 class="vms-mt-0">' . esc_html__('Tax Profile', 'backstage-venue-manager') . '</h3>';
    echo '<p class="vms-tax-profile-status">' . wp_kses(vms_staff_portal_safe_html(vms_staff_portal_badge_html((string) ($tax_status['stage'] ?? 'incomplete'))), vms_staff_portal_safe_html_allowed_html()) . '</p>';
    echo '<div class="vms-note"><strong>' . esc_html__('Active W-9 source of truth:', 'backstage-venue-manager') . '</strong> ' . esc_html($provider_label) . '</div>';
    if ($provider !== 'upload') {
        echo '<p class="vms-muted vms-mt-10">' . esc_html__('Complete your W-9/tax step through the secure off-site workflow, then return here to confirm you completed it. The venue will review and mark it complete after verification.', 'backstage-venue-manager') . '</p>';
        if (!empty($tax_status['attested_at']) && empty($tax_status['is_complete'])) {
            echo '<p class="vms-muted">' . esc_html__('You confirmed completion on', 'backstage-venue-manager') . ' <strong>' . esc_html(vms_staff_portal_format_ts((int) $tax_status['attested_at'])) . '</strong>.</p>';
        }
        if (!empty($tax_status['done_at'])) {
            echo '<p class="vms-muted">' . esc_html__('Admin confirmed on', 'backstage-venue-manager') . ' <strong>' . esc_html(vms_staff_portal_format_ts((int) $tax_status['done_at'])) . '</strong>.</p>';
        }
    }
    echo '</div>';

    echo '<details class="vms-panel" open>';
    echo '<summary class="vms-panel-summary">';
    echo '<span>' . esc_html__('Tax Profile (Required)', 'backstage-venue-manager') . '</span>';
    echo $is_complete
        ? '<span class="vms-badge vms-badge-ok">' . esc_html__('Complete', 'backstage-venue-manager') . '</span>'
        : (!empty($tax_status['attested_at']) && $provider !== 'upload'
            ? '<span class="vms-badge vms-badge-warn">' . esc_html__('Submitted', 'backstage-venue-manager') . '</span>'
            : '<span class="vms-badge vms-badge-miss">' . esc_html__('Incomplete', 'backstage-venue-manager') . '</span>');
    echo '</summary>';

    echo '<div class="vms-staff-tax-body">';

    if (!empty($missing)) {
        echo '<div class="vms-note"><strong>' . esc_html__('Still needed:', 'backstage-venue-manager') . '</strong> ' . esc_html(implode(', ', $missing)) . '</div>';
    }

    echo '<p class="description vms-staff-tax-intro">' .
        esc_html__('Please complete this once so we have everything needed for year-end 1099 processing. For security, do NOT enter SSN/EIN on this website.', 'backstage-venue-manager') .
        '</p>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('vms_staff_tax_save', 'vms_staff_tax_nonce');

    echo '<div class="vms-note"><strong>' . esc_html__('Privacy note:', 'backstage-venue-manager') . '</strong> ' .
        esc_html__('Do not type SSN/EIN here.', 'backstage-venue-manager') .
        '</div>';

    echo '<h3 class="vms-staff-tax-subhead vms-staff-tax-subhead-first">' . esc_html__('Payee & Entity', 'backstage-venue-manager') . '</h3>';
    echo '<div class="vms-grid vms-vtp-grid">';
    echo '<div class="vms-field vms-vtp-full">';
    echo '<label for="vms_payee_legal_name">' . esc_html__('Legal / Payee Name (as on W-9)', 'backstage-venue-manager') . ' *</label>';
    echo '<input type="text" id="vms_payee_legal_name" name="vms_payee_legal_name" value="' . esc_attr($payee_legal) . '" required>';
    echo '</div>';
    echo '<div class="vms-field vms-vtp-full">';
    echo '<label for="vms_payee_dba">' . esc_html__('Business Name / DBA (optional)', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_payee_dba" name="vms_payee_dba" value="' . esc_attr($dba) . '">';
    echo '</div>';
    echo '<div class="vms-field vms-vtp-full">';
    echo '<label for="vms_entity_type">' . esc_html__('Entity Type', 'backstage-venue-manager') . ' *</label>';
    echo '<select id="vms_entity_type" name="vms_entity_type" required>';
    foreach ($entity_types as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($entity, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<h3 class="vms-staff-tax-subhead">' . esc_html__('Mailing Address', 'backstage-venue-manager') . '</h3>';
    echo '<div class="vms-grid vms-vtp-grid">';
    echo '<div class="vms-field vms-vtp-full">';
    echo '<label for="vms_addr1">' . esc_html__('Address Line 1', 'backstage-venue-manager') . ' *</label>';
    echo '<input type="text" id="vms_addr1" name="vms_addr1" value="' . esc_attr($addr1) . '" required>';
    echo '</div>';
    echo '<div class="vms-field vms-vtp-full">';
    echo '<label for="vms_addr2">' . esc_html__('Address Line 2 (optional)', 'backstage-venue-manager') . '</label>';
    echo '<input type="text" id="vms_addr2" name="vms_addr2" value="' . esc_attr($addr2) . '">';
    echo '</div>';
    echo '<div class="vms-field">';
    echo '<label for="vms_city">' . esc_html__('City', 'backstage-venue-manager') . ' *</label>';
    echo '<input type="text" id="vms_city" name="vms_city" value="' . esc_attr($city) . '" required>';
    echo '</div>';
    echo '<div class="vms-field">';
    echo '<label for="vms_state">' . esc_html__('State', 'backstage-venue-manager') . ' *</label>';
    echo '<input type="text" id="vms_state" name="vms_state" value="' . esc_attr($state) . '" maxlength="2" placeholder="TX" required>';
    echo '</div>';
    echo '<div class="vms-field">';
    echo '<label for="vms_zip">' . esc_html__('ZIP', 'backstage-venue-manager') . ' *</label>';
    echo '<input type="text" id="vms_zip" name="vms_zip" value="' . esc_attr($zip) . '" required>';
    echo '</div>';
    echo '</div>';

    echo '<h3 class="vms-staff-tax-subhead">' . esc_html__('W-9', 'backstage-venue-manager') . ' (' . esc_html($provider_label) . ')</h3>';
    if (function_exists('vms_tax_provider_instructions')) {
        echo '<div class="vms-note vms-vtp-provider-note">' . esc_html(vms_tax_provider_instructions($provider)) . '</div>';
    }

    if ($provider === 'upload') {
        if ($w9_upload_id > 0 && $w9_url) {
            echo '<p class="description vms-staff-tax-upload-note">' .
                esc_html__('On file:', 'backstage-venue-manager') . ' <a href="' . esc_url($w9_url) . '" target="_blank" rel="noopener">' .
                esc_html($w9_label ? $w9_label : __('View uploaded W-9', 'backstage-venue-manager')) . '</a></p>';
        } else {
            echo '<p class="description vms-staff-tax-upload-note">' .
                esc_html__('No W-9 uploaded yet. Please upload a signed W-9 PDF/image.', 'backstage-venue-manager') . '</p>';
        }

        echo '<p class="vms-staff-tax-upload-field">';
        echo '<input type="file" name="vms_w9_upload" accept="application/pdf,image/jpeg,image/png,image/webp">';
        echo '<div class="vms-help">' . esc_html__('Accepted: PDF, JPG, PNG, WEBP.', 'backstage-venue-manager') . '</div>';
        echo '</p>';
    } else {
        echo '<div class="vms-field vms-vtp-field-wide">';
        echo '<label class="vms-vtp-attest-label">';
        echo '<input type="checkbox" name="vms_w9_offsite_attest" value="1"' . checked($attested_checked, true, false) . '>';
        echo '<span>' . esc_html__('I completed my W-9/tax information using the secure off-site request and understand the venue still needs to review it.', 'backstage-venue-manager') . '</span>';
        echo '</label>';
        echo '</div>';
    }

    echo '<p class="vms-m0 vms-mt-14">';
    echo '<button type="submit" class="button button-primary" name="vms_staff_tax_save" value="1">' . esc_html__('Save Tax Profile', 'backstage-venue-manager') . '</button>';
    echo '</p>';
    echo '</form>';
    echo '</div></details>';
}

/**
 * Availability (vendor-style calendar UI).
 * Saves into: _vms_availability_manual (array date => available|unavailable)
 */
function vms_staff_portal_render_availability_manual($staff_id)
{
    $staff_id = (int) $staff_id;
    $active_dates = vms_staff_portal_get_active_dates();
    $manual = vms_staff_portal_normalize_manual_availability($staff_id);
    $active_lookup = array_fill_keys($active_dates, true);

    $ics_url = trim((string) get_post_meta($staff_id, '_vms_ics_url', true));
    $ics_autosync = (int) get_post_meta($staff_id, '_vms_ics_autosync', true);
    $ics_last = (int) get_post_meta($staff_id, '_vms_ics_last_sync', true);
    $preferred = (string) get_post_meta($staff_id, '_vms_availability_preferred_method', true);
    if (!in_array($preferred, array('manual', 'ics', 'pattern'), true)) {
        $preferred = 'manual';
    }

    $pattern_enabled = (int) get_post_meta($staff_id, '_vms_pattern_enabled', true);
    $pattern_days = vms_staff_portal_normalize_pattern_days($staff_id);
    if (empty($pattern_days)) {
        $pattern_enabled = 0;
    }

    $ics_unavailable = vms_staff_portal_normalize_ics_unavailable($staff_id);
    $ics_meta = __('Not connected', 'backstage-venue-manager');
    if ($ics_url !== '') {
        $ics_meta = __('Connected', 'backstage-venue-manager');
        if ($ics_autosync) {
            $ics_meta .= ' | ' . __('Auto-sync on', 'backstage-venue-manager');
        }
        if ($ics_last > 0) {
            $ics_meta .= ' | ' . wp_date('M j', $ics_last, wp_timezone());
        }
    }

    $pattern_meta = __('Off', 'backstage-venue-manager');
    if ($pattern_enabled && !empty($pattern_days)) {
        $labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
        $picked = array();
        foreach ($pattern_days as $d) {
            if (isset($labels[(int) $d])) {
                $picked[] = $labels[(int) $d];
            }
        }
        $pattern_meta = __('Enabled', 'backstage-venue-manager') . ' | ' . implode(', ', $picked);
    }

    if (vms_staff_portal_is_exact_post_request()) {
        if (isset($_POST['vms_save_staff_ics_settings'])) {
            $nonce = (isset($_POST['vms_staff_ics_nonce']) && !is_array($_POST['vms_staff_ics_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_ics_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_ics_settings')) {
                echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            } else {
                $new_url = esc_url_raw(vms_staff_portal_post_scalar('vms_staff_ics_url'));
                $new_autosync = !empty($_POST['vms_staff_ics_autosync']) ? 1 : 0;
                update_post_meta($staff_id, '_vms_ics_url', $new_url);
                update_post_meta($staff_id, '_vms_ics_autosync', (int) $new_autosync);
                update_post_meta($staff_id, '_vms_availability_preferred_method', 'ics');
                $ics_url = $new_url;
                $ics_autosync = (int) $new_autosync;
                $preferred = 'ics';
                $ics_meta = ($ics_url !== '') ? __('Connected', 'backstage-venue-manager') : __('Not connected', 'backstage-venue-manager');
                if ($ics_url !== '' && $ics_autosync) {
                    $ics_meta .= ' | ' . __('Auto-sync on', 'backstage-venue-manager');
                }
                echo wp_kses(vms_staff_portal_notice_html('success', __('Calendar settings saved.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            }
        }

        if (isset($_POST['vms_sync_staff_ics_now'])) {
            $nonce = (isset($_POST['vms_staff_ics_nonce']) && !is_array($_POST['vms_staff_ics_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_ics_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_ics_settings')) {
                echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            } else {
                update_post_meta($staff_id, '_vms_availability_preferred_method', 'ics');
                $preferred = 'ics';
                if ($ics_url === '') {
                    echo wp_kses(vms_staff_portal_notice_html('warning', __('Please paste your calendar feed (ICS) URL first.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
                } elseif (!function_exists('vms_vendor_ics_sync_now')) {
                    echo wp_kses(vms_staff_portal_notice_html('error', __('ICS sync module is not loaded.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
                } else {
                    $result = (array) vms_vendor_ics_sync_now($staff_id, $active_dates);
                    if (!empty($result['ok'])) {
                        $ics_last = time();
                        update_post_meta($staff_id, '_vms_ics_last_sync', $ics_last);
                        $ics_unavailable = vms_staff_portal_normalize_ics_unavailable($staff_id);
                        $ics_meta = __('Connected', 'backstage-venue-manager');
                        if ($ics_autosync) {
                            $ics_meta .= ' | ' . __('Auto-sync on', 'backstage-venue-manager');
                        }
                        $ics_meta .= ' | ' . wp_date('M j', $ics_last, wp_timezone());
                        $count = count($ics_unavailable);
                        /* translators: %d: number of unavailable dates imported from the ICS feed. */
                        echo wp_kses(vms_staff_portal_notice_html('success', sprintf(__('Calendar synced. %d date(s) marked unavailable.', 'backstage-venue-manager'), $count)), vms_staff_portal_safe_html_allowed_html());
                    } else {
                        $msg = !empty($result['error']) ? (string) $result['error'] : __('Calendar sync failed.', 'backstage-venue-manager');
                        echo wp_kses(vms_staff_portal_notice_html('error', $msg), vms_staff_portal_safe_html_allowed_html());
                    }
                }
            }
        }

        if (isset($_POST['vms_save_staff_pattern'])) {
            $nonce = (isset($_POST['vms_staff_pattern_nonce']) && !is_array($_POST['vms_staff_pattern_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_pattern_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_pattern_settings')) {
                echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            } else {
                $days = array();
                foreach (vms_staff_portal_post_array('vms_staff_pattern_days') as $d) {
                    $d = (int) $d;
                    if ($d >= 0 && $d <= 6) {
                        $days[] = $d;
                    }
                }
                $days = array_values(array_unique($days));
                $enabled = vms_staff_portal_post_bool_flag('vms_staff_pattern_enabled') ? 1 : 0;
                if (!empty($days)) {
                    $enabled = 1;
                }
                if (!$enabled) {
                    $days = array();
                }
                update_post_meta($staff_id, '_vms_pattern_enabled', $enabled);
                update_post_meta($staff_id, '_vms_pattern_days', $days);
                update_post_meta($staff_id, '_vms_availability_preferred_method', 'pattern');
                $pattern_enabled = $enabled;
                $pattern_days = vms_staff_portal_normalize_pattern_days($staff_id);
                $preferred = 'pattern';
                $pattern_meta = __('Off', 'backstage-venue-manager');
                if ($pattern_enabled && !empty($pattern_days)) {
                    $labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
                    $picked = array();
                    foreach ($pattern_days as $d) {
                        if (isset($labels[(int) $d])) {
                            $picked[] = $labels[(int) $d];
                        }
                    }
                    $pattern_meta = __('Enabled', 'backstage-venue-manager') . ' | ' . implode(', ', $picked);
                }
                echo wp_kses(vms_staff_portal_notice_html('success', __('Pattern availability saved.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            }
        }

        $has_manual_submission = isset($_POST['vms_staff_save_availability'])
            || isset($_POST['vms_staff_avail_nonce'])
            || isset($_POST['vms_availability']);

        if ($has_manual_submission) {
            $nonce = (isset($_POST['vms_staff_avail_nonce']) && !is_array($_POST['vms_staff_avail_nonce']))
                ? sanitize_text_field(wp_unslash((string) $_POST['vms_staff_avail_nonce']))
                : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_staff_save_availability')) {
                echo wp_kses(vms_staff_portal_notice_html('error', __('Security check failed.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
            } else {
                $incoming = vms_staff_portal_post_array('vms_availability');
                $clean = array();
                foreach ($incoming as $date => $state) {
                    $date  = sanitize_text_field((string) $date);
                    $state = sanitize_key((string) $state);
                    if (!isset($active_lookup[$date])) {
                        continue;
                    }
                    if (in_array($state, array('available', 'unavailable'), true)) {
                        $clean[$date] = $state;
                    }
                }
                update_post_meta($staff_id, '_vms_availability_manual', $clean);
                update_post_meta($staff_id, '_vms_availability_preferred_method', 'manual');
                clean_post_cache($staff_id);
                $manual = vms_staff_portal_normalize_manual_availability($staff_id);
                $preferred = 'manual';

                /* translators: %d: number of manual availability dates saved. */
                echo wp_kses(vms_staff_portal_notice_html('success', sprintf(__('Availability saved. %d manual date(s) active.', 'backstage-venue-manager'), count($manual))), vms_staff_portal_safe_html_allowed_html());
            }
        }
    }

    if (empty($active_dates)) {
        echo '<p>' . esc_html__('No season dates are configured yet.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $assignments = vms_staff_portal_get_assignment_rows($staff_id, 250);
    $assignments_by_date = array();
    foreach ($assignments as $assignment) {
        $date = (string) ($assignment['event_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }
        if (!isset($assignments_by_date[$date])) {
            $assignments_by_date[$date] = array();
        }
        $assignments_by_date[$date][] = $assignment;
    }
    $assignment_event_map = vms_staff_portal_calendar_event_map($assignments);

    $grouped = vms_staff_portal_group_dates_by_month($active_dates);
    $today = wp_date('Y-m-d', time(), wp_timezone());
    $default_open_ym = '';
    foreach (array_keys($grouped) as $ym) {
        if ($default_open_ym === '' && $ym >= substr($today, 0, 7)) {
            $default_open_ym = $ym;
            break;
        }
    }
    if ($default_open_ym === '') {
        $default_open_ym = (string) array_key_first($grouped);
    }

    echo '<div class="vms-av-wrap" id="vms-av" data-today-ym="' . esc_attr(wp_date('Y-m')) . '">';

    echo '<details class="vms-av-method" data-method="ics"' . ($preferred === 'ics' ? ' open' : '') . '>';
    echo '<summary>';
    echo '<span>' . esc_html__('Calendar Sync (ICS)', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-summarymeta" data-summarymeta="ics">' . esc_html($ics_meta) . '</span>';
    echo '</summary>';
    echo '<div class="vms-pt-12">';
    echo '<form method="post" class="vms-av-row">';
    wp_nonce_field('vms_staff_ics_settings', 'vms_staff_ics_nonce');
    echo '<div class="field">';
    echo '<label><strong>' . esc_html__('ICS Feed URL', 'backstage-venue-manager') . '</strong></label><br>';
    echo '<input type="url" name="vms_staff_ics_url" value="' . esc_attr($ics_url) . '" class="vms-w-100">';
    echo '</div>';
    echo '<div class="field vms-field-tight">';
    echo '<label class="vms-label-block">';
    echo '<input type="checkbox" name="vms_staff_ics_autosync" value="1" ' . checked(1, $ics_autosync, false) . '> ';
    echo esc_html__('Auto-sync this calendar periodically (optional)', 'backstage-venue-manager');
    echo '</label>';
    echo '<div class="vms-av-actions">';
    echo '<button class="button button-primary" type="submit" name="vms_save_staff_ics_settings">' . esc_html__('Save Calendar Settings', 'backstage-venue-manager') . '</button>';
    echo '<button class="button" type="submit" name="vms_sync_staff_ics_now">' . esc_html__('Sync Now', 'backstage-venue-manager') . '</button>';
    echo '</div>';
    if ($ics_last > 0) {
        echo '<div class="vms-av-muted vms-mt-8">' . esc_html__('Last sync:', 'backstage-venue-manager') . ' ' . esc_html(wp_date('M j, Y g:ia', $ics_last, wp_timezone())) . '</div>';
    }
    if (!empty($ics_unavailable)) {
        /* translators: %d: number of dates currently blocked by the connected ICS feed. */
        echo '<div class="vms-av-muted vms-mt-8">' . esc_html(sprintf(_n('%d date currently blocked by ICS.', '%d dates currently blocked by ICS.', count($ics_unavailable), 'backstage-venue-manager'), count($ics_unavailable))) . '</div>';
    }
    echo '</div>';
    echo '</form>';
    echo '</div></details>';

    echo '<details class="vms-av-method" data-method="pattern"' . ($preferred === 'pattern' ? ' open' : '') . '>';
    echo '<summary>';
    echo '<span>' . esc_html__('Pattern Availability', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-summarymeta" data-summarymeta="pattern">' . esc_html($pattern_meta) . '</span>';
    echo '</summary>';
    echo '<div class="vms-av-card vms-av-card--plain">';
    echo '<p class="vms-av-muted vms-m0 vms-mb-10">';
    echo esc_html__('Choose the days you are usually available. All other active dates will read as unavailable until you override them.', 'backstage-venue-manager');
    echo '<br>';
    echo esc_html__('Manual date changes always win over pattern and ICS.', 'backstage-venue-manager');
    echo '</p>';
    echo '<form method="post">';
    wp_nonce_field('vms_staff_pattern_settings', 'vms_staff_pattern_nonce');
    echo '<label class="vms-flex vms-gap-8 vms-ai-center vms-m0 vms-mb-12">';
    echo '<input type="checkbox" name="vms_staff_pattern_enabled" value="1" ' . checked(1, $pattern_enabled, false) . '>';
    echo '<strong>' . esc_html__('Enable pattern availability', 'backstage-venue-manager') . '</strong>';
    echo '</label>';
    $dows = array(
        0 => __('Sun', 'backstage-venue-manager'),
        1 => __('Mon', 'backstage-venue-manager'),
        2 => __('Tue', 'backstage-venue-manager'),
        3 => __('Wed', 'backstage-venue-manager'),
        4 => __('Thu', 'backstage-venue-manager'),
        5 => __('Fri', 'backstage-venue-manager'),
        6 => __('Sat', 'backstage-venue-manager'),
    );
    echo '<div class="vms-flex vms-gap-10 vms-wrap vms-m0 vms-mb-12">';
    foreach ($dows as $i => $lbl) {
        $is_checked = in_array((int) $i, array_map('intval', $pattern_days), true);
        echo '<label class="vms-flex vms-gap-6 vms-ai-center">';
        echo '<input type="checkbox" name="vms_staff_pattern_days[]" value="' . esc_attr($i) . '" ' . checked(true, $is_checked, false) . '>';
        echo '<span>' . esc_html($lbl) . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '<button class="button button-primary" type="submit" name="vms_save_staff_pattern">' . esc_html__('Save Pattern', 'backstage-venue-manager') . '</button>';
    echo '</form>';
    echo '</div></details>';

    echo '<details class="vms-av-method" data-method="manual"' . ($preferred === 'manual' ? ' open' : '') . '>';
    echo '<summary>';
    echo '<span>' . esc_html__('Manual Availability', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-summarymeta" data-summarymeta="manual"></span>';
    echo '</summary>';
    echo '<div class="vms-pt-12">';
    echo '<div class="vms-av-card vms-staff-av-card">';
    echo '<p class="vms-av-help">' . esc_html__('Tap a date to toggle: Unset > Available > Unavailable. Assigned shifts stay locked, and event cards show your role and shift.', 'backstage-venue-manager') . '</p>';
    echo '<div class="vms-av-legend" role="note" aria-label="' . esc_attr__('Availability legend', 'backstage-venue-manager') . '">';
    echo '<span class="vms-av-leg-item"><span class="vms-av-leg-dot is-available"></span>' . esc_html__('Available', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-leg-item"><span class="vms-av-leg-dot is-unavailable"></span>' . esc_html__('Unavailable', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-leg-item"><span class="vms-av-leg-dot is-working"></span>' . esc_html__('Working', 'backstage-venue-manager') . '</span>';
    echo '<span class="vms-av-leg-item"><span class="vms-av-leg-dot is-conflict"></span>' . esc_html__('Conflict', 'backstage-venue-manager') . '</span>';
    echo '</div>';
    if (!vms_staff_portal_has_availability_setup($staff_id)) {
        echo wp_kses(vms_staff_portal_notice_html('warning', __('You have not set up availability yet. Enable Pattern, connect ICS, or set a few manual dates.', 'backstage-venue-manager')), vms_staff_portal_safe_html_allowed_html());
    }
    $staff_avail_ajax_nonce = wp_create_nonce('vms_staff_avail_ajax');
    echo '<form method="post" class="vms-staff-av-form" data-vms-staff-availability="1" data-vms-staff-availability-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-vms-staff-availability-nonce="' . esc_attr($staff_avail_ajax_nonce) . '">';
    wp_nonce_field('vms_staff_save_availability', 'vms_staff_avail_nonce');
    echo '<div class="vms-av-autosave" aria-live="polite"></div>';

    foreach ($grouped as $ym => $dates_in_month) {
        $month_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $month_dt = DateTimeImmutable::createFromFormat('!Y-m', $ym, $month_tz);
        $month_label = ($month_dt instanceof DateTimeImmutable) ? wp_date('F Y', $month_dt->getTimestamp(), $month_tz) : $ym;
        $matrix = vms_staff_portal_month_matrix($ym);
        $cnt_working = 0;
        $cnt_available = 0;
        $cnt_unavailable = 0;
        $cnt_active = count($dates_in_month);
        foreach ($dates_in_month as $date) {
            $state = vms_staff_portal_day_visual_state($date, $manual, $assignments_by_date, $staff_id);
            if (($state['status_key'] ?? '') === 'working') {
                $cnt_working++;
            }
            if (($state['base_state'] ?? '') === 'available' && ($state['status_key'] ?? '') !== 'working') {
                $cnt_available++;
            }
            if (in_array(($state['status_key'] ?? ''), array('unavailable', 'conflict'), true)) {
                $cnt_unavailable++;
            }
        }

        echo '<div class="vms-av-month" data-ym="' . esc_attr($ym) . '">';
        echo '<details' . (($ym === $default_open_ym) ? ' open' : '') . '>';
        echo '<summary>';
        echo '<span class="vms-av-monthlabel">';
        echo '<span class="vms-av-monthname">' . esc_html($month_label) . '</span>';
        echo '<span class="vms-av-howto">' . esc_html__('Tap days to toggle availability', 'backstage-venue-manager') . '</span>';
        echo '</span>';
        echo '<span class="vms-av-counts vms-av-muted" data-active="' . esc_attr((string) $cnt_active) . '">' . esc_html(sprintf('%d active | %d U | %d A | %d W', $cnt_active, $cnt_unavailable, $cnt_available, $cnt_working)) . '</span>';
        echo '</summary>';
        echo '<table class="vms-av-grid">';
        echo '<thead><tr class="vms-av-dow">';
        foreach (array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager')) as $lbl) {
            echo '<th scope="col">' . esc_html($lbl) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($matrix as $week) {
            echo '<tr>';
            foreach ($week as $cell) {
                $date = (string) ($cell['date'] ?? '');
                $daynum = (string) ($cell['day'] ?? '');
                if ($date === '') {
                    echo '<td class="vms-av-inactive"></td>';
                    continue;
                }

                $is_active = isset($active_lookup[$date]);
                $is_past = ($date < $today);
                $state = vms_staff_portal_day_visual_state($date, $manual, $assignments_by_date, $staff_id);
                $status_key = sanitize_key((string) ($state['status_key'] ?? 'unset'));
                $status_label = (string) ($state['status_label'] ?? __('Unset', 'backstage-venue-manager'));
                $visual_state = sanitize_key((string) ($state['visual_state'] ?? ''));
                $manual_state = sanitize_key((string) ($state['manual_state'] ?? ''));
                $base_src = sanitize_key((string) ($state['base_src'] ?? ''));
                $assignments_for_day = isset($state['assignments']) && is_array($state['assignments']) ? $state['assignments'] : array();
                $status_title = trim($status_label . (($base_src === 'pattern' || $base_src === 'ics') ? ' · ' . vms_staff_portal_availability_source_label($base_src) : ''));
                $source_icon = ($manual_state === '' && empty($assignments_for_day)) ? vms_staff_portal_source_icon($base_src) : '';

                $td_classes = array();
                if (!$is_active) {
                    $td_classes[] = 'vms-av-inactive';
                }
                if ($is_past) {
                    $td_classes[] = 'vms-av-past';
                }
                echo '<td' . (!empty($td_classes) ? ' class="' . esc_attr(implode(' ', $td_classes)) . '"' : '') . '>';
                echo '<div class="vms-av-cell-badges">';
                echo '<span class="vms-av-badge-status is-' . esc_attr($status_key) . '" title="' . esc_attr($status_title) . '">' . esc_html($status_label) . '</span>';
                echo '</div>';
                echo '<div class="vms-av-day"><span class="vms-av-daynum">' . esc_html($daynum) . '</span></div>';

                $assignment_markup = '';
                if (!empty($assignments_for_day)) {
                    $assignment_markup .= '<div class="vms-av-event-title vms-av-event-title--staff vms-public-cal">';
                    foreach (array_slice($assignments_for_day, 0, 1) as $assignment) {
                        $assignment_status = sanitize_key((string) ($assignment['assignment_status'] ?? 'confirmed'));
                        $status_copy = (string) ($assignment['assignment_status_label'] ?? vms_staff_portal_assignment_status_label($assignment_status));
                        $event_payload = vms_staff_portal_assignment_event_payload($assignment, $assignment_event_map);
                        $event_title = (string) ($event_payload['title'] ?? ($assignment['event_title'] ?? __('Event Plan', 'backstage-venue-manager')));
                        $event_icon = trim((string) ($event_payload['icon'] ?? ''));
                        $event_view_url = trim((string) ($event_payload['view_url'] ?? ''));
                        $popover_date = (string) ($event_payload['date_label'] ?? (string) ($assignment['date_label'] ?? ''));
                        $popover_time = (string) ($event_payload['time_label'] ?? '');
                        $popover_excerpt = (string) ($event_payload['excerpt'] ?? '');
                        $popover_image = (string) ($event_payload['image_url'] ?? '');
                        $popover_venue = (string) ($event_payload['venue_name'] ?? '');

                        $meta_parts = array();
                        if (!empty($assignment['role_label'])) {
                            $meta_parts[] = (string) $assignment['role_label'];
                        }
                        if (!empty($assignment['shift_label'])) {
                            $meta_parts[] = (string) $assignment['shift_label'];
                        }
                        if ($status_copy !== '') {
                            $meta_parts[] = $status_copy;
                        }
                        $shift_meta = implode(' · ', $meta_parts);

                        $assignment_markup .= '<div class="vms-av-meta-line is-trigger vms-cal-entry"' . ($event_view_url === '' ? ' tabindex="0"' : '') . '>';
                        $assignment_markup .= '<div class="vms-cal-entry-vendors">';
                        if ($event_view_url !== '') {
                            $assignment_markup .= '<a class="vms-cal-vendor-row vms-cal-entry-vendor is-primary" href="' . esc_url($event_view_url) . '">';
                        } else {
                            $assignment_markup .= '<span class="vms-cal-vendor-row vms-cal-entry-vendor is-primary">';
                        }
                        if ($event_icon !== '') {
                            $assignment_markup .= '<span class="vms-cal-vendor-icon" aria-hidden="true">' . esc_html($event_icon) . '</span>';
                        }
                        $assignment_markup .= '<span class="vms-cal-vendor-name">' . esc_html($event_title) . '</span>';
                        $assignment_markup .= ($event_view_url !== '') ? '</a>' : '</span>';
                        $assignment_markup .= '</div>';
                        $assignment_markup .= '<div class="vms-cal-pop">';
                        $assignment_markup .= '<div class="vms-cal-pop-body">';
                        if ($popover_image !== '') {
                            if ($event_view_url !== '') {
                                $assignment_markup .= '<a class="vms-cal-pop-media" href="' . esc_url($event_view_url) . '"><img src="' . esc_url($popover_image) . '" alt="" loading="lazy"></a>';
                            } else {
                                $assignment_markup .= '<div class="vms-cal-pop-media"><img src="' . esc_url($popover_image) . '" alt="" loading="lazy"></div>';
                            }
                        }
                        $assignment_markup .= '<div class="vms-cal-pop-vendors">';
                        if ($event_view_url !== '') {
                            $assignment_markup .= '<a class="vms-cal-vendor-row vms-cal-pop-vendor is-primary" href="' . esc_url($event_view_url) . '"><span class="vms-cal-vendor-name">' . esc_html($event_title) . '</span></a>';
                        } else {
                            $assignment_markup .= '<span class="vms-cal-vendor-row vms-cal-pop-vendor is-primary"><span class="vms-cal-vendor-name">' . esc_html($event_title) . '</span></span>';
                        }
                        if ($popover_venue !== '') {
                            $assignment_markup .= '<div class="vms-cal-vendor-row vms-cal-pop-vendor is-secondary"><span class="vms-cal-vendor-name">' . esc_html($popover_venue) . '</span></div>';
                        }
                        $assignment_markup .= '</div>';
                        if ($popover_date !== '') {
                            $assignment_markup .= '<div class="vms-cal-pop-date">' . esc_html($popover_date) . '</div>';
                        }
                        if ($popover_time !== '') {
                            $assignment_markup .= '<div class="vms-cal-pop-time">' . esc_html($popover_time) . '</div>';
                        }
                        if ($shift_meta !== '') {
                            $assignment_markup .= '<div class="vms-cal-pop-time vms-cal-pop-time--staff">' . esc_html($shift_meta) . '</div>';
                        }
                        if ($popover_excerpt !== '') {
                            $assignment_markup .= '<div class="vms-cal-pop-excerpt">' . esc_html($popover_excerpt) . '</div>';
                        }
                        if ($event_view_url !== '') {
                            $assignment_markup .= '<div class="vms-cal-pop-actions"><a class="vms-cal-pop-ticket" href="' . esc_url($event_view_url) . '">' . esc_html__('View Event Page', 'backstage-venue-manager') . '</a></div>';
                        }
                        $assignment_markup .= '</div>';
                        $assignment_markup .= '</div>';
                        $assignment_markup .= '</div>';

                        if ($shift_meta !== '') {
                            $assignment_markup .= '<span class="vms-av-meta-line is-staff-shift">' . esc_html($shift_meta) . '</span>';
                        }
                    }
                    if (count($assignments_for_day) > 1) {
                        $assignment_markup .= '<span class="vms-av-meta-more">+' . esc_html((string) (count($assignments_for_day) - 1)) . ' ' . esc_html__('more', 'backstage-venue-manager') . '</span>';
                    }
                    $assignment_markup .= '</div>';
                }

                $is_toggleable = ($is_active && !$is_past && empty($assignments_for_day));
                if ($is_toggleable) {
                    echo '<input type="hidden" name="vms_availability[' . esc_attr($date) . ']" value="' . esc_attr($manual_state) . '" data-date="' . esc_attr($date) . '" class="vms-av-hidden">';
                    echo '<button type="button" class="vms-av-btn vms-staff-av-btn" data-date="' . esc_attr($date) . '" data-state="' . esc_attr($manual_state) . '" data-base-src="' . esc_attr($base_src) . '" data-visual="' . esc_attr($visual_state) . '">';
                    if ($source_icon !== '') {
                        echo '<span class="vms-av-src" aria-hidden="true" data-src-type="' . esc_attr($base_src) . '">' . esc_html($source_icon) . '</span>';
                    }
                    echo '<span class="vms-av-statewrap"><span class="vms-av-state">' . esc_html($status_label) . '</span></span>';
                    echo '</button>';
                } else {
                    $ro_class = 'vms-av-readonly';
                    if ($visual_state === 'available') {
                        $ro_class .= ' is-available';
                    }
                    if ($visual_state === 'unavailable') {
                        $ro_class .= ' is-unavailable';
                    }
                    if ($assignment_markup !== '') {
                        $ro_class .= ' vms-staff-av-readonly--occupied';
                    }
                    echo '<div class="' . esc_attr($ro_class) . '" data-visual="' . esc_attr($visual_state) . '">';
                    if ($source_icon !== '') {
                        echo '<span class="vms-av-src" aria-hidden="true" data-src-type="' . esc_attr($base_src) . '">' . esc_html($source_icon) . '</span>';
                    }
                    if ($assignment_markup === '') {
                        echo '<span class="vms-av-chip">' . esc_html($status_label) . '</span>';
                    }
                    if ($assignment_markup !== '') {
                        echo wp_kses(vms_staff_portal_safe_html($assignment_markup), vms_staff_portal_safe_html_allowed_html());
                    }
                    echo '</div>';
                }

                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</details>';
        echo '</div>';
    }

    echo '<p class="vms-m0 vms-mt-14 vms-av-muted">' . esc_html__('Changes save automatically when you tap a date.', 'backstage-venue-manager') . '</p>';
    echo '<p class="vms-m0 vms-mt-8"><button type="submit" class="button" name="vms_staff_save_availability" value="1">' . esc_html__('Fallback Save', 'backstage-venue-manager') . '</button></p>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</details>';
    echo '</div>';
}


add_action('wp_ajax_vms_staff_save_manual_availability_day', 'vms_staff_save_manual_availability_day_ajax');

function vms_staff_save_manual_availability_day_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in.'), 403);
    }

    check_ajax_referer('vms_staff_avail_ajax', 'nonce');

    $user_id = (int) get_current_user_id();
    $staff_id = (int) get_user_meta($user_id, '_vms_staff_id', true);

    if ($staff_id <= 0 || get_post_type($staff_id) !== 'vms_staff') {
        wp_send_json_error(array('message' => 'Staff profile not linked.'), 400);
    }

    $date  = bvmgr_request_read_text_field($_POST, 'date');
    $state = bvmgr_request_read_text_field($_POST, 'state');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(array('message' => 'Invalid date.'), 400);
    }

    if (!in_array($state, array('', 'available', 'unavailable'), true)) {
        wp_send_json_error(array('message' => 'Invalid state.'), 400);
    }

    $active_dates = vms_staff_portal_get_active_dates();
    $active_lookup = array_flip($active_dates);
    if (!isset($active_lookup[$date])) {
        wp_send_json_error(array('message' => 'Date not in active range.'), 400);
    }

    $assignments = vms_staff_portal_get_assignment_rows($staff_id, 250);
    foreach ($assignments as $assignment) {
        if ((string) ($assignment['event_date'] ?? '') === $date) {
            wp_send_json_error(array('message' => 'Assigned dates are locked.'), 400);
        }
    }

    $manual = get_post_meta($staff_id, '_vms_availability_manual', true);
    if (!is_array($manual)) {
        $manual = array();
    }

    if ($state === '') {
        unset($manual[$date]);
    } else {
        $manual[$date] = $state;
    }

    update_post_meta($staff_id, '_vms_availability_manual', $manual);
    update_post_meta($staff_id, '_vms_availability_preferred_method', 'manual');
    clean_post_cache($staff_id);

    wp_send_json_success(array(
        'date' => $date,
        'state' => $state,
    ));
}
