<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('bvmgr_event_command_center_page_slug')) {
    function bvmgr_event_command_center_page_slug(): string
    {
        return 'vms-event-command-center';
    }
}

if (!function_exists('bvmgr_event_command_center_admin_url')) {
    function bvmgr_event_command_center_admin_url(array $args = array()): string
    {
        return bvmgr_admin_ui_page_url(bvmgr_event_command_center_page_slug(), $args);
    }
}

if (!function_exists('bvmgr_event_command_center_query_arg')) {
    function bvmgr_event_command_center_query_arg(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Event Command Center admin routing and display state only.
        return bvmgr_request_read_scalar($_GET, $key);
    }
}

if (!function_exists('bvmgr_event_command_center_request_cache')) {
    function bvmgr_event_command_center_request_cache(int $plan_id, string $bucket, callable $resolver)
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        $bucket = sanitize_key($bucket);
        if ($plan_id <= 0 || $bucket === '') {
            return $resolver();
        }

        if (!isset($cache[$plan_id]) || !is_array($cache[$plan_id])) {
            $cache[$plan_id] = array();
        }

        if (!array_key_exists($bucket, $cache[$plan_id])) {
            $cache[$plan_id][$bucket] = $resolver();
        }

        return $cache[$plan_id][$bucket];
    }
}


if (!function_exists('bvmgr_event_command_center_notice_query_args')) {
    function bvmgr_event_command_center_notice_query_args(string $type, string $message, int $plan_id = 0): array
    {
        return array(
            'page' => bvmgr_event_command_center_page_slug(),
            'plan_id' => max(0, $plan_id),
            'vms_cc_notice_type' => sanitize_key($type),
            'vms_cc_notice' => sanitize_text_field($message),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_redirect_with_notice')) {
    function bvmgr_event_command_center_redirect_with_notice(string $type, string $message, int $plan_id = 0): void
    {
        wp_safe_redirect(add_query_arg(bvmgr_event_command_center_notice_query_args($type, $message, $plan_id), admin_url('admin.php')));
        exit;
    }
}

if (!function_exists('bvmgr_event_command_center_render_notice')) {
    function bvmgr_event_command_center_render_notice(): void
    {
        $message = sanitize_text_field(bvmgr_event_command_center_query_arg('vms_cc_notice'));
        if ($message === '') {
            return;
        }
        $type = sanitize_key(bvmgr_event_command_center_query_arg('vms_cc_notice_type'));
        $class = in_array($type, array('error', 'warning', 'info', 'success'), true) ? $type : 'success';
        echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

if (!function_exists('bvmgr_event_command_center_can_manage_promo_video')) {
    function bvmgr_event_command_center_can_manage_promo_video(int $plan_id): bool
    {
        return $plan_id > 0 && current_user_can('edit_post', $plan_id);
    }
}

if (!function_exists('bvmgr_event_command_center_promo_video_upload_file')) {
    function bvmgr_event_command_center_promo_video_upload_file(int $plan_id, string $field_name)
    {
        if (function_exists('bvmgr_vendor_portal_handle_headliner_promo_video_media_upload')) {
            return bvmgr_vendor_portal_handle_headliner_promo_video_media_upload($field_name, $plan_id);
        }

        return new WP_Error('promo_video_upload_unavailable', __('The promo video upload handler is unavailable.', 'backstage-venue-manager'));
    }
}

if (!function_exists('bvmgr_event_command_center_handle_promo_video_action')) {
    function bvmgr_event_command_center_handle_promo_video_action(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage this event.', 'backstage-venue-manager'));
        }

        $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
        $nonce = (isset($_POST['_vms_cc_promo_nonce']) && !is_array($_POST['_vms_cc_promo_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['_vms_cc_promo_nonce']))
            : '';
        $promo_action = isset($_POST['promo_action']) ? sanitize_key((string) wp_unslash($_POST['promo_action'])) : '';
        if ($plan_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'vms_cc_promo_video_' . $plan_id)) {
            wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
        }
        if (!bvmgr_event_command_center_can_manage_promo_video($plan_id)) {
            wp_die(esc_html__('You do not have permission to manage this event.', 'backstage-venue-manager'));
        }

        $attachment_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('attachment_id') : '_vms_headliner_promo_video_attachment_id';
        $hidden_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('hidden') : '_vms_headliner_promo_video_hidden';
        $uploaded_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('uploaded_at_gmt') : '_vms_headliner_promo_video_uploaded_at_gmt';
        $uploaded_by_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('uploaded_by') : '_vms_headliner_promo_video_uploaded_by';
        $source_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('source_type') : '_vms_headliner_promo_video_source_type';
        $external_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('external_url') : '_vms_headliner_promo_video_external_url';
        $pending_attachment_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('pending_attachment_id') : '_vms_headliner_promo_video_pending_attachment_id';
        $pending_uploaded_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_at_gmt') : '_vms_headliner_promo_video_pending_uploaded_at_gmt';
        $pending_uploaded_by_key = function_exists('bvmgr_vendor_portal_headliner_promo_video_meta_key') ? bvmgr_vendor_portal_headliner_promo_video_meta_key('pending_uploaded_by') : '_vms_headliner_promo_video_pending_uploaded_by';
        $actor_user_id = (int) get_current_user_id();

        if ($promo_action === 'use_submission') {
            $pending_id = (int) get_post_meta($plan_id, $pending_attachment_key, true);
            if ($pending_id <= 0) {
                bvmgr_event_command_center_redirect_with_notice('error', __('There is no submitted vendor clip waiting for review on this event.', 'backstage-venue-manager'), $plan_id);
            }
            update_post_meta($plan_id, $attachment_key, $pending_id);
            update_post_meta($plan_id, $source_key, 'attachment');
            delete_post_meta($plan_id, $external_key);
            update_post_meta($plan_id, $hidden_key, '0');
            update_post_meta($plan_id, $uploaded_key, current_time('mysql', true));
            update_post_meta($plan_id, $uploaded_by_key, $actor_user_id);
            delete_post_meta($plan_id, $pending_attachment_key);
            delete_post_meta($plan_id, $pending_uploaded_key);
            delete_post_meta($plan_id, $pending_uploaded_by_key);
            bvmgr_event_command_center_redirect_with_notice('success', __('Vendor-submitted clip is now the live public promo video for this event.', 'backstage-venue-manager'), $plan_id);
        }

        if ($promo_action === 'upload_public') {
            if (empty($_FILES['vms_cc_headliner_promo_video']) || !is_array($_FILES['vms_cc_headliner_promo_video'])) {
                bvmgr_event_command_center_redirect_with_notice('error', __('Please choose a video file to upload.', 'backstage-venue-manager'), $plan_id);
            }
            $result = bvmgr_event_command_center_promo_video_upload_file($plan_id, 'vms_cc_headliner_promo_video');
            if (is_wp_error($result)) {
                /* translators: %s: upload error message. */
                bvmgr_event_command_center_redirect_with_notice('error', sprintf(__('Upload failed: %s', 'backstage-venue-manager'), $result->get_error_message()), $plan_id);
            }
            update_post_meta($plan_id, $attachment_key, (int) $result);
            update_post_meta($plan_id, $source_key, 'attachment');
            delete_post_meta($plan_id, $external_key);
            update_post_meta($plan_id, $hidden_key, '0');
            update_post_meta($plan_id, $uploaded_key, current_time('mysql', true));
            update_post_meta($plan_id, $uploaded_by_key, $actor_user_id);
            delete_post_meta($plan_id, $pending_attachment_key);
            delete_post_meta($plan_id, $pending_uploaded_key);
            delete_post_meta($plan_id, $pending_uploaded_by_key);
            bvmgr_event_command_center_redirect_with_notice('success', __('New public promo video uploaded for this event.', 'backstage-venue-manager'), $plan_id);
        }

        if ($promo_action === 'use_external') {
            $raw_url = isset($_POST['external_url']) ? esc_url_raw((string) wp_unslash($_POST['external_url']), array('http', 'https')) : '';
            $url = function_exists('bvmgr_vendor_portal_normalize_promo_video_external_url') ? bvmgr_vendor_portal_normalize_promo_video_external_url($raw_url) : esc_url_raw($raw_url, array('http', 'https'));
            if ($url === '') {
                bvmgr_event_command_center_redirect_with_notice('error', __('Please enter a valid YouTube, Vimeo, Facebook, or Instagram video URL.', 'backstage-venue-manager'), $plan_id);
            }
            update_post_meta($plan_id, $source_key, 'external');
            update_post_meta($plan_id, $external_key, $url);
            update_post_meta($plan_id, $hidden_key, '0');
            update_post_meta($plan_id, $uploaded_key, current_time('mysql', true));
            update_post_meta($plan_id, $uploaded_by_key, $actor_user_id);
            bvmgr_event_command_center_redirect_with_notice('success', __('External promo video link saved for this event.', 'backstage-venue-manager'), $plan_id);
        }

        if ($promo_action === 'clear_live') {
            delete_post_meta($plan_id, $attachment_key);
            delete_post_meta($plan_id, $source_key);
            delete_post_meta($plan_id, $external_key);
            delete_post_meta($plan_id, $hidden_key);
            delete_post_meta($plan_id, $uploaded_key);
            delete_post_meta($plan_id, $uploaded_by_key);
            bvmgr_event_command_center_redirect_with_notice('success', __('The live public promo video was cleared for this event.', 'backstage-venue-manager'), $plan_id);
        }

        if ($promo_action === 'remove_submission') {
            delete_post_meta($plan_id, $pending_attachment_key);
            delete_post_meta($plan_id, $pending_uploaded_key);
            delete_post_meta($plan_id, $pending_uploaded_by_key);
            bvmgr_event_command_center_redirect_with_notice('success', __('The submitted vendor clip was removed from this event.', 'backstage-venue-manager'), $plan_id);
        }

        bvmgr_event_command_center_redirect_with_notice('error', __('Unknown promo video action.', 'backstage-venue-manager'), $plan_id);
    }
}
add_action('admin_post_vms_event_command_center_promo_video', 'bvmgr_event_command_center_handle_promo_video_action');

if (!function_exists('bvmgr_event_command_center_money')) {
    function bvmgr_event_command_center_money(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        $amount = max(0, (int) $cents) / 100;
        return '$' . number_format_i18n($amount, 2);
    }
}

if (!function_exists('bvmgr_event_command_center_money_signed')) {
    function bvmgr_event_command_center_money_signed(int $cents): string
    {
        $abs = abs($cents) / 100;
        $prefix = $cents < 0 ? '-$' : '$';
        return $prefix . number_format_i18n($abs, 2);
    }
}

if (!function_exists('bvmgr_event_command_center_parse_datetime')) {
    function bvmgr_event_command_center_parse_datetime(string $value, bool $utc = false): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $tz = $utc ? new DateTimeZone('UTC') : wp_timezone();
        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return $utc ? $dt->setTimezone(wp_timezone()) : $dt;
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone());
    }
}

if (!function_exists('bvmgr_event_command_center_time_ago_label')) {
    function bvmgr_event_command_center_time_ago_label(string $value, bool $utc = false): string
    {
        $dt = bvmgr_event_command_center_parse_datetime($value, $utc);
        if (!($dt instanceof DateTimeImmutable)) {
            return '';
        }

        return sprintf(
            /* translators: 1: formatted timestamp, 2: human-readable elapsed time. */
            __('%1$s (%2$s ago)', 'backstage-venue-manager'),
            $dt->format('M j, Y g:i A'),
            human_time_diff($dt->getTimestamp(), time())
        );
    }
}

if (!function_exists('bvmgr_event_command_center_days_until')) {
    function bvmgr_event_command_center_days_until(string $event_date): ?int
    {
        $event_date = trim((string) $event_date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
            return null;
        }

        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $event = DateTimeImmutable::createFromFormat('!Y-m-d', $event_date, $tz);
        if (!($event instanceof DateTimeImmutable)) {
            return null;
        }

        return (int) $today->diff($event)->format('%r%a');
    }
}

if (!function_exists('bvmgr_event_command_center_days_until_label')) {
    function bvmgr_event_command_center_days_until_label(?int $days): string
    {
        if ($days === null) {
            return __('Date not set', 'backstage-venue-manager');
        }
        if ($days === 0) {
            return __('Today', 'backstage-venue-manager');
        }
        if ($days === 1) {
            return __('Tomorrow', 'backstage-venue-manager');
        }
        if ($days === -1) {
            return __('Yesterday', 'backstage-venue-manager');
        }
        if ($days > 1) {
            /* translators: %d: number of days until the event. */
            return sprintf(_n('%d day out', '%d days out', $days, 'backstage-venue-manager'), $days);
        }

        $past = abs($days);
        /* translators: %d: number of days since the event. */
        return sprintf(_n('%d day ago', '%d days ago', $past, 'backstage-venue-manager'), $past);
    }
}

if (!function_exists('bvmgr_event_command_center_clean_text')) {
    function bvmgr_event_command_center_clean_text(string $text): string
    {
        if (function_exists('bvmgr_event_plan_review_clean_text')) {
            return (string) bvmgr_event_plan_review_clean_text($text);
        }

        $text = wp_strip_all_tags((string) $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('bvmgr_event_command_center_status_tone')) {
    function bvmgr_event_command_center_status_tone(string $status): string
    {
        $status = sanitize_key($status);
        if (in_array($status, array('published', 'confirmed', 'ready'), true)) {
            return 'good';
        }
        if ($status === 'cancelled') {
            return 'critical';
        }
        return 'muted';
    }
}

if (!function_exists('bvmgr_event_command_center_health_tone')) {
    function bvmgr_event_command_center_health_tone(string $health): string
    {
        $health = sanitize_key($health);
        if ($health === 'critical') {
            return 'critical';
        }
        if ($health === 'at-risk') {
            return 'warning';
        }
        if ($health === 'needs-review') {
            return 'warning';
        }
        return 'good';
    }
}

if (!function_exists('bvmgr_event_command_center_render_chip')) {
    function bvmgr_event_command_center_render_chip(string $label, string $tone = 'muted'): string
    {
        $tone = sanitize_html_class($tone ?: 'muted');
        return '<span class="vms-cc-chip is-' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }
}

if (!function_exists('bvmgr_event_command_center_allowed_markup')) {
    function bvmgr_event_command_center_allowed_markup(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['blockquote'] = array(
            'cite' => true,
            'class' => true,
            'data-instgrm-captioned' => true,
            'data-instgrm-permalink' => true,
            'data-instgrm-version' => true,
        );
        $allowed['iframe'] = array(
            'allow' => true,
            'allowfullscreen' => true,
            'class' => true,
            'frameborder' => true,
            'height' => true,
            'loading' => true,
            'referrerpolicy' => true,
            'scrolling' => true,
            'src' => true,
            'title' => true,
            'width' => true,
        );
        $allowed['script'] = array(
            'async' => true,
            'charset' => true,
            'crossorigin' => true,
            'defer' => true,
            'src' => true,
        );
        $allowed['section'] = array(
            'class' => true,
            'id' => true,
        );
        $allowed['source'] = array(
            'src' => true,
            'type' => true,
        );
        $allowed['video'] = array(
            'class' => true,
            'controls' => true,
            'playsinline' => true,
            'preload' => true,
        );

        return $allowed;
    }
}

if (!function_exists('bvmgr_event_command_center_get_highlight_chips')) {
    function bvmgr_event_command_center_get_highlight_chips(array $alerts): array
    {
        $chips = array();
        $severity_map = array(
            'red' => 'critical',
            'yellow' => 'warning',
            'informational' => 'muted',
        );

        foreach ($alerts as $alert) {
            $severity = sanitize_key((string) ($alert['severity'] ?? 'yellow'));
            if (!in_array($severity, array('red', 'yellow'), true)) {
                continue;
            }

            $title = trim((string) ($alert['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $chips[] = bvmgr_event_command_center_render_chip($title, (string) ($severity_map[$severity] ?? 'muted'));
            if (count($chips) >= 2) {
                break;
            }
        }

        return $chips;
    }
}

if (!function_exists('bvmgr_event_command_center_get_plan_ids')) {
    function bvmgr_event_command_center_get_plan_ids(): array
    {
        $ids = get_posts(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'posts_per_page' => 200,
            'fields' => 'ids',
            'orderby' => 'meta_value',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This admin selector is capped at 200 plan IDs and uses the canonical date key only for ordering.
            'meta_key' => function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        return array_values(array_filter(array_map('absint', (array) $ids)));
    }
}

if (!function_exists('bvmgr_event_command_center_resolve_plan_id')) {
    function bvmgr_event_command_center_resolve_plan_id(): int
    {
        $plan_id = absint(bvmgr_event_command_center_query_arg('plan_id'));
        if ($plan_id <= 0) {
            $plan_id = absint(bvmgr_event_command_center_query_arg('event_plan_id'));
        }
        if ($plan_id <= 0) {
            $candidate = absint(bvmgr_event_command_center_query_arg('post'));
            if ($candidate > 0 && get_post_type($candidate) === 'vms_event_plan') {
                $plan_id = $candidate;
            }
        }

        if ($plan_id > 0 && get_post_type($plan_id) === 'vms_event_plan') {
            return $plan_id;
        }

        $ids = bvmgr_event_command_center_get_plan_ids();
        return !empty($ids) ? (int) $ids[0] : 0;
    }
}

if (!function_exists('bvmgr_event_command_center_is_weather_addon_active')) {
    function bvmgr_event_command_center_is_weather_addon_active(): bool
    {
        if (function_exists('bvmgr_admin_ui_registered_page_url')) {
            return bvmgr_admin_ui_registered_page_url('vmsx-weather-risk-settings') !== '';
        }

        return false;
    }
}

if (!function_exists('bvmgr_event_command_center_get_weather_url')) {
    function bvmgr_event_command_center_get_weather_url(): string
    {
        if (function_exists('bvmgr_admin_ui_registered_page_url')) {
            $registered = bvmgr_admin_ui_registered_page_url('vmsx-weather-risk-settings');
            if ($registered !== '') {
                return $registered;
            }
        }

        return '';
    }
}

if (!function_exists('bvmgr_event_command_center_get_plan_header')) {
    function bvmgr_event_command_center_get_plan_header(int $plan_id): array
    {
        $status = function_exists('bvmgr_event_plan_get_status')
            ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
            : 'draft';
        $status = sanitize_key($status ?: 'draft');

        $event_date = (string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date', true);
        $start_time = (string) get_post_meta($plan_id, '_vms_start_time', true);
        $end_time = (string) get_post_meta($plan_id, '_vms_end_time', true);
        $venue_id = absint(get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'venue_id') : '_vms_venue_id', true));
        $venue_label = $venue_id > 0 ? trim((string) get_the_title($venue_id)) : __('Unassigned venue', 'backstage-venue-manager');
        $date_label = function_exists('bvmgr_event_plan_review_format_date')
            ? (string) bvmgr_event_plan_review_format_date($event_date)
            : $event_date;

        $start_label = function_exists('bvmgr_event_plan_review_format_time')
            ? (string) bvmgr_event_plan_review_format_time($start_time)
            : $start_time;
        $end_label = function_exists('bvmgr_event_plan_review_format_time')
            ? (string) bvmgr_event_plan_review_format_time($end_time)
            : $end_time;

        $time_label = trim($start_label);
        if ($start_label !== '' && $end_label !== '' && $end_label !== __('Not set', 'backstage-venue-manager')) {
            $time_label = trim($start_label . ' - ' . $end_label);
        } elseif ($time_label === '' || $time_label === __('Not set', 'backstage-venue-manager')) {
            $time_label = __('Time not set', 'backstage-venue-manager');
        }

        $days_until = bvmgr_event_command_center_days_until($event_date);
        $tec_event_id = absint(get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id', true));
        $tec_event_url = (string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'tec_event_url') : '_vms_tec_event_url', true);
        $ticket_url = function_exists('bvmgr_tec_get_ticket_url_for_plan') ? (string) bvmgr_tec_get_ticket_url_for_plan($plan_id) : '';

        return array(
            'plan_id' => $plan_id,
            'title' => (string) get_the_title($plan_id),
            'status' => $status,
            'status_label' => function_exists('bvmgr_event_plan_status_label') ? (string) bvmgr_event_plan_status_label($status) : ucfirst($status),
            'status_tone' => bvmgr_event_command_center_status_tone($status),
            'date_raw' => $event_date,
            'date_label' => $date_label,
            'time_label' => $time_label,
            'venue_id' => $venue_id,
            'venue_label' => $venue_label,
            'days_until' => $days_until,
            'days_until_label' => bvmgr_event_command_center_days_until_label($days_until),
            'edit_url' => (string) get_edit_post_link($plan_id, ''),
            'public_event_url' => $tec_event_url,
            'edit_event_url' => $tec_event_id > 0 ? (string) get_edit_post_link($tec_event_id, '') : '',
            'ticket_url' => $ticket_url,
            'tec_event_id' => $tec_event_id,
            'modified_label' => get_post_modified_time('M j, Y g:i A', false, $plan_id, true),
            'marketing_url' => function_exists('bvmgr_admin_ui_meta_ads_urls')
                ? (string) (bvmgr_admin_ui_meta_ads_urls(bvmgr_admin_ui_page_url('vms-marketing-social'))['builder'] ?? bvmgr_admin_ui_page_url('vms-marketing-social'))
                : bvmgr_admin_ui_page_url('vms-marketing-social'),
            'social_url' => bvmgr_admin_ui_page_url('vms-social-sharing'),
            'weather_url' => bvmgr_event_command_center_get_weather_url(),
        );
    }
}


if (!function_exists('bvmgr_event_command_center_get_ticket_reporting_truth')) {
    /**
     * Resolve the best available ticket-sales truth for Event Command Center.
     *
     * Preference order:
     * 1. Data Tools reporting model when active, because it can combine website + door/onsite ticket sales.
     * 2. Core VMS ticket revenue rows, because they read Woo order lines directly and avoid stale ticket_stats cache.
     * 3. Existing cached goal/ticket stats as a last-resort fallback in the caller.
     */
    function bvmgr_event_command_center_get_ticket_reporting_truth(int $plan_id): array
    {
        return (array) bvmgr_event_command_center_request_cache($plan_id, 'ticket_reporting_truth', static function () use ($plan_id): array {
            $plan_id = absint($plan_id);
            if (function_exists('bvmgr_resource_fingerprint_flag')) {
                bvmgr_resource_fingerprint_flag('ecc_calculation', array(
                    'plan_id' => $plan_id,
                    'step' => 'ticket_reporting_truth',
                ));
            }
            if (function_exists('bvmgr_resource_fingerprint_span_start')) {
                bvmgr_resource_fingerprint_span_start('ecc.ticket_reporting_truth', array('plan_id' => $plan_id));
            }
            $empty = array(
                'available' => false,
                'source' => '',
                'source_label' => '',
                'paid_qty' => 0,
                'free_qty' => 0,
                'total_qty' => 0,
                'revenue_cents' => 0,
                'warnings' => array(),
            );

            try {
                if ($plan_id <= 0) {
                    return $empty;
                }

                if (function_exists('vms_dt_reporting_build_event_model')) {
                    try {
                        $model = (array) vms_dt_reporting_build_event_model(array(
                            'event_plan_id' => $plan_id,
                            'event_from' => '',
                            'event_to' => '',
                            'sold_from' => '',
                            'sold_to' => '',
                            'venue_id' => 0,
                            'square_location_id' => '',
                            'square_scope_mode' => 'full_day',
                            'compare' => 0,
                        ));
                        if (function_exists('bvmgr_resource_fingerprint_add_marker')) {
                            bvmgr_resource_fingerprint_add_marker('ecc.ticket_source.dt_model', 0.0, array('plan_id' => $plan_id));
                        }
                        $costs = isset($model['costs']) && is_array($model['costs']) ? (array) $model['costs'] : array();
                        $summary = isset($model['summary']) && is_array($model['summary']) ? (array) $model['summary'] : array();
                        $row = isset($model['row']) && is_array($model['row']) ? (array) $model['row'] : array();

                        $paid_qty = max(0, (int) ($costs['paid_ticket_qty_total'] ?? 0));
                        if ($paid_qty <= 0) {
                            $paid_qty = max(0, (int) (($row['website_paid_ticket_qty'] ?? 0) + ($row['square_paid_ticket_qty'] ?? 0)));
                        }

                        $free_qty = max(0, (int) ($costs['free_ticket_qty_excluded'] ?? 0));
                        if ($free_qty <= 0) {
                            $free_qty = max(0, (int) (($row['website_free_ticket_qty'] ?? 0) + ($row['square_free_ticket_qty'] ?? 0)));
                        }

                        $total_qty = max(0, (int) ($costs['ticket_qty_total'] ?? 0));
                        if ($total_qty <= 0) {
                            $total_qty = max(0, (int) ($summary['total_ticket_qty'] ?? 0));
                        }
                        if ($total_qty <= 0 && ($paid_qty > 0 || $free_qty > 0)) {
                            $total_qty = $paid_qty + $free_qty;
                        }

                        $revenue_cents = max(0, (int) ($costs['ticket_sales_total_cents'] ?? 0));
                        if ($revenue_cents <= 0) {
                            $revenue_cents = max(0, (int) ($summary['total_ticket_sales_cents'] ?? 0));
                        }

                        if ($total_qty > 0 || $revenue_cents > 0 || $paid_qty > 0 || $free_qty > 0) {
                            return array(
                                'available' => true,
                                'source' => 'dt_reporting_model',
                                'source_label' => __('Data Tools reporting model', 'backstage-venue-manager'),
                                'paid_qty' => $paid_qty,
                                'free_qty' => $free_qty,
                                'total_qty' => $total_qty,
                                'revenue_cents' => $revenue_cents,
                                'warnings' => array_values(array_unique(array_filter(array_merge(
                                    (array) ($row['confidence_badges'] ?? array()),
                                    (array) ($row['square_warnings'] ?? array()),
                                    (array) ($row['square_errors'] ?? array())
                                )))),
                            );
                        }
                    } catch (Throwable $e) {
                        /* translators: %s: exception message from Data Tools reporting lookup. */
                        $empty['warnings'][] = sprintf(__('Data Tools ticket model could not be read: %s', 'backstage-venue-manager'), $e->getMessage());
                    }
                }

                if (function_exists('bvmgr_ticket_revenue_build_report')) {
                    try {
                        $report = (array) bvmgr_ticket_revenue_build_report(array(
                            'event_from' => '',
                            'event_to' => '',
                            'sold_from' => '',
                            'sold_to' => '',
                            'event_plan_id' => $plan_id,
                            'recognition_status' => 'all',
                            'preview_limit' => 500,
                            'unresolved_limit' => 100,
                        ));
                        if (function_exists('bvmgr_resource_fingerprint_add_marker')) {
                            bvmgr_resource_fingerprint_add_marker('ecc.ticket_source.core_ticket_revenue', 0.0, array('plan_id' => $plan_id));
                        }

                        $paid_qty = 0;
                        $free_qty = 0;
                        $revenue_cents = 0;
                        foreach ((array) ($report['rows'] ?? array()) as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $item_kind = sanitize_key((string) ($row['item_kind'] ?? 'ticket'));
                            if (in_array($item_kind, array('addon', 'entitlement'), true)) {
                                continue;
                            }
                            $qty = max(0, (int) ($row['quantity'] ?? 0));
                            $refunded_qty = max(0, (int) ($row['refunded_quantity'] ?? 0));
                            $net_qty = max(0, $qty - $refunded_qty);
                            if ($net_qty <= 0) {
                                continue;
                            }
                            $net_cents = max(0, (int) ($row['net_subtotal_cents'] ?? 0));
                            $revenue_cents += $net_cents;
                            if ($net_cents > 0) {
                                $paid_qty += $net_qty;
                            } else {
                                $free_qty += $net_qty;
                            }
                        }

                        $total_qty = $paid_qty + $free_qty;
                        if ($total_qty > 0 || $revenue_cents > 0) {
                            return array(
                                'available' => true,
                                'source' => 'core_ticket_revenue',
                                'source_label' => __('VMS ticket revenue rows', 'backstage-venue-manager'),
                                'paid_qty' => $paid_qty,
                                'free_qty' => $free_qty,
                                'total_qty' => $total_qty,
                                'revenue_cents' => $revenue_cents,
                                'warnings' => (array) ($report['warnings'] ?? array()),
                            );
                        }
                    } catch (Throwable $e) {
                        /* translators: %s: exception message from ticket revenue reporting lookup. */
                        $empty['warnings'][] = sprintf(__('Ticket revenue rows could not be read: %s', 'backstage-venue-manager'), $e->getMessage());
                    }
                }

                return $empty;
            } finally {
                if (function_exists('bvmgr_resource_fingerprint_span_finish')) {
                    bvmgr_resource_fingerprint_span_finish('ecc.ticket_reporting_truth', array('plan_id' => $plan_id));
                }
            }
        });
    }
}

if (!function_exists('bvmgr_event_command_center_get_ticket_snapshot')) {
    function bvmgr_event_command_center_get_ticket_snapshot(int $plan_id): array
    {
        $reporting_truth = bvmgr_event_command_center_get_ticket_reporting_truth($plan_id);
        $ticket_stats = function_exists('vms_goals_get_ticket_stats')
            ? (array) vms_goals_get_ticket_stats($plan_id)
            : array('qty_sold' => 0, 'revenue_cents' => 0);

        $sold = !empty($reporting_truth['available'])
            ? max(0, (int) ($reporting_truth['paid_qty'] ?? 0))
            : max(0, (int) ($ticket_stats['qty_sold'] ?? 0));
        $revenue_cents = !empty($reporting_truth['available'])
            ? max(0, (int) ($reporting_truth['revenue_cents'] ?? 0))
            : max(0, (int) ($ticket_stats['revenue_cents'] ?? 0));
        $comp_count = max(0, (int) get_post_meta($plan_id, '_vms_comp_headcount_forecast', true));
        if (!empty($reporting_truth['available'])) {
            $comp_count = max($comp_count, max(0, (int) ($reporting_truth['free_qty'] ?? 0)));
        }
        $true_comp_count = max(0, (int) get_post_meta($plan_id, '_vms_comp_headcount_true', true));
        if ($true_comp_count > 0) {
            $comp_count = $true_comp_count;
        }

        if (function_exists('bvmgr_resource_fingerprint_span_start')) {
            bvmgr_resource_fingerprint_span_start('ecc.ticket_integrity_scan', array('plan_id' => $plan_id));
        }
        $scan = function_exists('bvmgr_ticket_integrity_scan_event_record')
            ? (array) bvmgr_ticket_integrity_scan_event_record($plan_id)
            : array();
        if (function_exists('bvmgr_resource_fingerprint_span_finish')) {
            bvmgr_resource_fingerprint_span_finish('ecc.ticket_integrity_scan', array(
                'plan_id' => $plan_id,
                'ticket_rows' => count((array) ($scan['ticket_snapshots'] ?? array())),
            ));
        }

        $capacity = 0;
        $remaining = 0;
        $remaining_known = false;
        $low_inventory_flag = false;
        $low_inventory_severity = '';
        $event_ts = absint($scan['event_timestamp'] ?? 0);

        foreach ((array) ($scan['ticket_snapshots'] ?? array()) as $ticket_snapshot) {
            if (!is_array($ticket_snapshot) || empty($ticket_snapshot['customer_facing'])) {
                continue;
            }
            $price = isset($ticket_snapshot['config_price']) ? (float) $ticket_snapshot['config_price'] : 0.0;
            if ($price <= 0) {
                continue;
            }

            $capacity += max(0, (int) ($ticket_snapshot['inventory_total'] ?? 0));
            if (function_exists('bvmgr_ticket_integrity_ticket_remaining')) {
                $ticket_remaining = bvmgr_ticket_integrity_ticket_remaining($ticket_snapshot);
                if ($ticket_remaining !== null) {
                    $remaining += max(0, (int) $ticket_remaining);
                    $remaining_known = true;
                }
            }
            if (function_exists('bvmgr_ticket_integrity_low_inventory_signal')) {
                $signal = (array) bvmgr_ticket_integrity_low_inventory_signal($ticket_snapshot, $event_ts);
                if (!empty($signal['flagged'])) {
                    $low_inventory_flag = true;
                    $low_inventory_severity = sanitize_key((string) ($signal['severity'] ?? $low_inventory_severity));
                }
            }
        }

        $sell_through = null;
        if ($capacity > 0) {
            $sell_through = min(100, max(0, (($sold / max(1, $capacity)) * 100)));
        }

        $total_ticket_count = $sold + $comp_count;
        if (!empty($reporting_truth['available'])) {
            $reported_total = max(0, (int) ($reporting_truth['total_qty'] ?? 0));
            $total_ticket_count = max($total_ticket_count, $reported_total);
        }

        return array(
            'sold' => $sold,
            'revenue_cents' => $revenue_cents,
            'comp_count' => $comp_count,
            'total_ticket_count' => $total_ticket_count,
            'ticket_source' => !empty($reporting_truth['available']) ? (string) ($reporting_truth['source'] ?? '') : 'cached_ticket_stats',
            'ticket_source_label' => !empty($reporting_truth['available']) ? (string) ($reporting_truth['source_label'] ?? '') : __('Cached ticket stats', 'backstage-venue-manager'),
            'ticket_source_warnings' => (array) ($reporting_truth['warnings'] ?? array()),
            'capacity' => $capacity > 0 ? $capacity : null,
            'remaining' => $remaining_known ? $remaining : null,
            'sell_through' => $sell_through,
            'integrity_status' => sanitize_key((string) ($scan['status'] ?? '')),
            'issue_summary' => trim((string) ($scan['issue_summary'] ?? '')),
            'issues' => is_array($scan['issues'] ?? null) ? (array) $scan['issues'] : array(),
            'ticket_snapshots' => is_array($scan['ticket_snapshots'] ?? null) ? (array) $scan['ticket_snapshots'] : array(),
            'event_timestamp' => $event_ts,
            'low_inventory_flag' => $low_inventory_flag,
            'low_inventory_severity' => $low_inventory_severity,
            'status_label' => ($scan !== array() && function_exists('bvmgr_ticket_integrity_status_label'))
                ? (string) bvmgr_ticket_integrity_status_label((string) ($scan['status'] ?? ''))
                : __('Unknown', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_get_financial_snapshot')) {
    function bvmgr_event_command_center_get_financial_snapshot(int $plan_id): array
    {
        $manual_actuals = function_exists('vms_goals_get_manual_event_actual_totals')
            ? (array) vms_goals_get_manual_event_actual_totals($plan_id)
            : array();
        $has_actuals = false;
        foreach ($manual_actuals as $value) {
            if ((int) $value !== 0) {
                $has_actuals = true;
                break;
            }
        }

        $mode = $has_actuals ? 'true' : 'forecast';
        $pnl = function_exists('vms_goals_get_event_pnl')
            ? (array) vms_goals_get_event_pnl($plan_id, array('headcount_mode' => $mode, 'include_overhead' => false))
            : array();

        $vendor_cost_cents = $has_actuals
            ? max(0, (int) ($manual_actuals['direct_costs_cents'] ?? 0))
            : (function_exists('vms_goals_get_default_direct_costs_cents') ? max(0, (int) vms_goals_get_default_direct_costs_cents($plan_id)) : 0);
        $labor_cost_cents = function_exists('bvmgr_event_profitability_get_labor_cost_cents')
            ? max(0, (int) bvmgr_event_profitability_get_labor_cost_cents($plan_id))
            : 0;
        $processing_cents = $has_actuals
            ? max(0, (int) ($manual_actuals['processing_fees_cents'] ?? 0))
            : (function_exists('vms_goals_get_default_processing_fees_cents') ? max(0, (int) vms_goals_get_default_processing_fees_cents($plan_id)) : 0);
        $gross_cents = max(0, (int) ($pnl['gross_revenue_cents'] ?? 0));
        $margin_cents = $gross_cents - $vendor_cost_cents - $labor_cost_cents - $processing_cents;

        return array(
            'mode' => $mode,
            'gross_cents' => $gross_cents,
            'vendor_cost_cents' => $vendor_cost_cents,
            'labor_cost_cents' => $labor_cost_cents,
            'processing_cents' => $processing_cents,
            'margin_cents' => $margin_cents,
            'has_actuals' => $has_actuals,
            'ad_spend_cents' => null,
            'ad_spend_available' => false,
            'ad_spend_url' => function_exists('bvmgr_admin_ui_meta_ads_urls')
                ? (string) (bvmgr_admin_ui_meta_ads_urls(bvmgr_admin_ui_page_url('vms-marketing-social'))['performance'] ?? '')
                : '',
        );
    }
}

if (!function_exists('bvmgr_event_command_center_get_lineup_snapshot')) {
    function bvmgr_event_command_center_get_lineup_snapshot(int $plan_id): array
    {
        return (array) bvmgr_event_command_center_request_cache($plan_id, 'lineup_snapshot', static function () use ($plan_id): array {
            $all_meta = get_post_meta($plan_id);
            if (!is_array($all_meta)) {
                $all_meta = array();
            }

            $read_meta = static function (string $key, $default = '') use ($all_meta) {
                if ($key === '' || !isset($all_meta[$key][0])) {
                    return $default;
                }

                return maybe_unserialize($all_meta[$key][0]);
            };

            $lineup_key = function_exists('bvmgr_lineup_schedule_meta_key')
                ? (string) bvmgr_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1')
                : '_vms_lineup_entries_v1';
            $band_key = function_exists('bvmgr_lineup_schedule_meta_key')
                ? (string) bvmgr_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id')
                : '_vms_band_vendor_id';
            $secondary_key = function_exists('bvmgr_meta_key')
                ? (string) bvmgr_meta_key('event_plan', 'secondary_vendor_ids')
                : '_vms_secondary_vendor_ids';

            $raw_entries = $read_meta($lineup_key, array());
            if (!is_array($raw_entries)) {
                $raw_entries = array();
            }

            $secondary_ids = $read_meta($secondary_key, array());
            if (!is_array($secondary_ids)) {
                $secondary_ids = array();
            }
            $secondary_ids = array_values(array_unique(array_filter(array_map('absint', $secondary_ids))));

            $context = array(
                'legacy_primary_vendor_id' => absint($read_meta($band_key, 0)),
                'event_start' => (string) $read_meta('_vms_start_time', ''),
                'event_end' => (string) $read_meta('_vms_end_time', ''),
                'venue_id' => absint($read_meta('_vms_venue_id', 0)),
                'event_date' => (string) $read_meta('_vms_event_date', ''),
            );

            $lineup_vendor_ids = array();
            foreach ($raw_entries as $raw_entry) {
                if (!is_array($raw_entry)) {
                    continue;
                }
                $vendor_id = absint($raw_entry['vendor_id'] ?? 0);
                if ($vendor_id > 0 && !in_array($vendor_id, $lineup_vendor_ids, true)) {
                    $lineup_vendor_ids[] = $vendor_id;
                }
            }
            if (($context['legacy_primary_vendor_id'] ?? 0) > 0 && !in_array((int) $context['legacy_primary_vendor_id'], $lineup_vendor_ids, true)) {
                $lineup_vendor_ids[] = (int) $context['legacy_primary_vendor_id'];
            }
            $vendor_ids = array_values(array_unique(array_filter(array_merge($lineup_vendor_ids, $secondary_ids))));

            if (!empty($vendor_ids)) {
                if (function_exists('_prime_post_caches')) {
                    _prime_post_caches($vendor_ids, true, false);
                }
                if (function_exists('update_object_term_cache')) {
                    update_object_term_cache($vendor_ids, 'vms_vendor');
                }
            }

            $entries = array();
            $summary = array();
            $warnings = array();

            if (
                function_exists('bvmgr_normalize_event_plan_lineup_entries')
                && function_exists('bvmgr_lineup_schedule_enrich_entries')
            ) {
                $normalized = bvmgr_normalize_event_plan_lineup_entries($raw_entries, $context);
                $enriched = (array) bvmgr_lineup_schedule_enrich_entries($normalized, $context);
                $entries = is_array($enriched['entries'] ?? null) ? (array) $enriched['entries'] : array();
                $summary = is_array($enriched['summary'] ?? null) ? (array) $enriched['summary'] : array();
                $warnings = is_array($enriched['warnings'] ?? null) ? (array) $enriched['warnings'] : array();
            } else {
                $entries = function_exists('bvmgr_get_event_plan_lineup_entries')
                    ? (array) bvmgr_get_event_plan_lineup_entries($plan_id)
                    : array();
                $summary = function_exists('bvmgr_get_event_plan_lineup_summary')
                    ? (array) bvmgr_get_event_plan_lineup_summary($plan_id)
                    : array();
                $warnings = function_exists('bvmgr_get_event_plan_lineup_warnings')
                    ? (array) bvmgr_get_event_plan_lineup_warnings($plan_id)
                    : array();
            }

            $primary = array();
            $supporting = array();
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $role = sanitize_key((string) ($entry['role'] ?? 'supporting'));
                if ($role === 'primary' && empty($primary)) {
                    $primary = $entry;
                    continue;
                }
                $supporting[] = $entry;
            }

            $secondary_posts = !empty($secondary_ids)
                ? get_posts(array(
                    'post_type' => 'vms_vendor',
                    'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                    'post__in' => $secondary_ids,
                    'orderby' => 'post__in',
                    'posts_per_page' => count($secondary_ids),
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ))
                : array();

            $secondary_titles = array();
            foreach ($secondary_posts as $secondary_post) {
                if ($secondary_post instanceof WP_Post) {
                    $secondary_titles[(int) $secondary_post->ID] = get_the_title($secondary_post);
                }
            }

            $secondary = array();
            foreach ($secondary_ids as $vendor_id) {
                $secondary[] = array(
                    'vendor_id' => $vendor_id,
                    'display_name' => trim((string) ($secondary_titles[$vendor_id] ?? '')),
                    'status_label' => __('Booked', 'backstage-venue-manager'),
                    'status_tone' => 'good',
                    'role_label' => __('Secondary vendor', 'backstage-venue-manager'),
                );
            }

            return array(
                'entries' => $entries,
                'primary' => $primary,
                'supporting' => $supporting,
                'secondary' => $secondary,
                'summary' => $summary,
                'warnings' => $warnings,
            );
        });
    }
}

if (!function_exists('bvmgr_event_command_center_get_staffing_snapshot')) {
    function bvmgr_event_command_center_get_staffing_snapshot(int $plan_id): array
    {
        $rollup = function_exists('bvmgr_staffing_get_rollup') ? bvmgr_staffing_get_rollup($plan_id) : null;
        $needs_compute = !is_array($rollup) || !isset($rollup['readiness_status']) || !empty($rollup['dirty']);
        if ($needs_compute && function_exists('bvmgr_staffing_compute_rollup')) {
            $computed = (array) bvmgr_staffing_compute_rollup($plan_id);
            if (!empty($computed['ok'])) {
                $rollup = $computed;
            }
        }

        if (!is_array($rollup)) {
            $rollup = array();
        }

        $slots = function_exists('bvmgr_staffing_get_event_slots') ? (array) bvmgr_staffing_get_event_slots($plan_id, false) : array();
        $roles = array();
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $role_name = trim((string) ($slot['role_name'] ?? __('Role', 'backstage-venue-manager')));
            $needed = max(0, (int) ($slot['headcount_needed'] ?? 0));
            $filled = 0;
            foreach ((array) ($slot['assignments'] ?? array()) as $assignment) {
                $status = sanitize_key((string) ($assignment['status'] ?? ''));
                if (in_array($status, array('proposed', 'confirmed'), true)) {
                    $filled++;
                }
            }
            if (!isset($roles[$role_name])) {
                $roles[$role_name] = array(
                    'role_name' => $role_name,
                    'needed' => 0,
                    'filled' => 0,
                );
            }
            $roles[$role_name]['needed'] += $needed;
            $roles[$role_name]['filled'] += $filled;
        }

        return array(
            'rollup' => $rollup,
            'roles' => array_values($roles),
            'readiness_status' => sanitize_key((string) ($rollup['readiness_status'] ?? 'na')),
            'readiness_label' => function_exists('bvmgr_staffing_dashboard_readiness_label')
                ? (string) bvmgr_staffing_dashboard_readiness_label((string) ($rollup['readiness_status'] ?? 'na'))
                : __('N/A', 'backstage-venue-manager'),
            'headcount_needed_total' => max(0, (int) ($rollup['headcount_needed_total'] ?? 0)),
            'headcount_filled_total' => max(0, (int) ($rollup['headcount_filled_total'] ?? 0)),
            'open_headcount_total' => max(0, (int) ($rollup['open_headcount_total'] ?? 0)),
            'critical_open_headcount' => max(0, (int) ($rollup['critical_open_headcount'] ?? 0)),
            'conflict_count' => max(0, (int) ($rollup['conflict_count'] ?? 0)),
            'missing_summary' => is_array($rollup['missing_summary'] ?? null) ? (array) $rollup['missing_summary'] : array(),
            'conflict_summary' => is_array($rollup['conflict_summary'] ?? null) ? (array) $rollup['conflict_summary'] : array(),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_get_marketing_snapshot')) {
    function bvmgr_event_command_center_get_marketing_snapshot(int $plan_id, array $header = array()): array
    {
        $do_not_post = !empty(get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'do_not_post') : '_vms_social_do_not_post', true));
        $promo = function_exists('bvmgr_vendor_portal_get_headliner_promo_video_data')
            ? (array) bvmgr_vendor_portal_get_headliner_promo_video_data($plan_id)
            : array();
        $submitted = function_exists('bvmgr_vendor_portal_get_headliner_promo_video_submission_data')
            ? (array) bvmgr_vendor_portal_get_headliner_promo_video_submission_data($plan_id)
            : array();
        $promo_source = sanitize_key((string) ($promo['source_type'] ?? 'none'));
        $promo_hidden = !empty($promo['hidden']);
        $promo_uploaded_at = (string) ($promo['uploaded_at_gmt'] ?? '');
        $provider_label = (string) ($promo['provider_label'] ?? '');
        if ($promo_source === 'attachment') {
            $promo_label = $promo_hidden ? __('Uploaded (hidden)', 'backstage-venue-manager') : __('Manual upload live', 'backstage-venue-manager');
        } elseif ($promo_source === 'external') {
            $promo_label = $promo_hidden ? __('External link (hidden)', 'backstage-venue-manager') : ($provider_label !== '' ? $provider_label : __('External link live', 'backstage-venue-manager'));
        } elseif (!empty($submitted['attachment_id'])) {
            $promo_label = __('Vendor clip submitted for review', 'backstage-venue-manager');
        } else {
            $promo_label = __('Missing', 'backstage-venue-manager');
        }

        $meta_ads_urls = function_exists('bvmgr_admin_ui_meta_ads_urls')
            ? (array) bvmgr_admin_ui_meta_ads_urls(bvmgr_admin_ui_page_url('vms-marketing-social'))
            : array();
        $meta_ads_builder_url = (string) ($meta_ads_urls['builder'] ?? '');
        $meta_ads_performance_url = (string) ($meta_ads_urls['performance'] ?? '');
        $meta_ads_registered = function_exists('bvmgr_admin_ui_registered_page_url')
            ? (bvmgr_admin_ui_registered_page_url('vms-ma-ads-builder') !== '' || bvmgr_admin_ui_registered_page_url('vms-ma-ads-performance') !== '')
            : false;

        return array(
            'event_page_public' => !empty($header['public_event_url']),
            'event_page_label' => !empty($header['public_event_url']) ? __('Public', 'backstage-venue-manager') : __('Missing public event', 'backstage-venue-manager'),
            'social_ready' => !$do_not_post,
            'social_label' => $do_not_post ? __('Posting suppressed', 'backstage-venue-manager') : __('Social sharing active', 'backstage-venue-manager'),
            'promo_video_id' => (int) ($promo['attachment_id'] ?? 0),
            'promo_source' => $promo_source,
            'promo_video_label' => $promo_label,
            'promo_video_uploaded_label' => $promo_uploaded_at !== '' ? bvmgr_event_command_center_time_ago_label($promo_uploaded_at, true) : '',
            'promo_provider_label' => $provider_label,
            'promo_external_url' => (string) ($promo['external_url'] ?? ''),
            'promo_submission_pending' => !empty($submitted['attachment_id']),
            'promo_submission_uploaded_label' => !empty($submitted['uploaded_at_gmt']) ? bvmgr_event_command_center_time_ago_label((string) $submitted['uploaded_at_gmt'], true) : '',
            'meta_ads_registered' => $meta_ads_registered,
            'meta_ads_label' => $meta_ads_registered ? __('Builder available', 'backstage-venue-manager') : __('Meta Ads Builder not active', 'backstage-venue-manager'),
            'social_url' => bvmgr_admin_ui_page_url('vms-social-sharing'),
            'marketing_hub_url' => bvmgr_admin_ui_page_url('vms-marketing-social'),
            'meta_ads_builder_url' => $meta_ads_builder_url !== '' ? $meta_ads_builder_url : bvmgr_admin_ui_page_url('vms-marketing-social'),
            'meta_ads_performance_url' => $meta_ads_performance_url !== '' ? $meta_ads_performance_url : bvmgr_admin_ui_page_url('vms-marketing-social'),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_render_promo_video_manager')) {
    function bvmgr_event_command_center_render_promo_video_manager(int $plan_id, array $marketing = array()): void
    {
        if ($plan_id <= 0 || !current_user_can('manage_options')) {
            return;
        }

        $current = function_exists('bvmgr_vendor_portal_get_headliner_promo_video_data')
            ? (array) bvmgr_vendor_portal_get_headliner_promo_video_data($plan_id)
            : array();
        $submitted = function_exists('bvmgr_vendor_portal_get_headliner_promo_video_submission_data')
            ? (array) bvmgr_vendor_portal_get_headliner_promo_video_submission_data($plan_id)
            : array();

        echo '<section class="vms-cc-card vms-cc-card--promo-manager">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Promo Video Control', 'backstage-venue-manager') . '</h3></div>';
        echo '<p class="vms-cc-card__note">' . esc_html__('Use this area to choose what actually appears on the public event page. Vendor uploads stay in review until you approve them, replace them, or swap in a link.', 'backstage-venue-manager') . '</p>';
        echo '<div class="vms-cc-promo-grid">';

        echo '<div class="vms-cc-promo-column">';
        echo '<h4>' . esc_html__('Current public source', 'backstage-venue-manager') . '</h4>';
        if (!empty($current['source_type']) && (string) $current['source_type'] !== 'none' && function_exists('bvmgr_vendor_portal_render_headliner_promo_video_markup_from_data')) {
            echo wp_kses(bvmgr_vendor_portal_render_headliner_promo_video_markup_from_data($current, array(
                'context' => 'portal',
                'heading' => __('Live now', 'backstage-venue-manager'),
                'wrap_class' => 'vms-cc-promo-preview',
            )), bvmgr_event_command_center_allowed_markup());
        } else {
            echo '<p class="vms-cc-empty vms-cc-empty--inline">' . esc_html__('No public promo video is live yet for this event.', 'backstage-venue-manager') . '</p>';
        }
        if (!empty($marketing['promo_video_uploaded_label'])) {
            /* translators: %s: formatted last-updated timestamp label. */
            echo '<p class="vms-cc-card__note">' . esc_html(sprintf(__('Last updated: %s', 'backstage-venue-manager'), (string) $marketing['promo_video_uploaded_label'])) . '</p>';
        }
        echo '<form class="vms-cc-promo-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vms_event_command_center_promo_video">';
        echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
        echo '<input type="hidden" name="promo_action" value="clear_live">';
        wp_nonce_field('vms_cc_promo_video_' . $plan_id, '_vms_cc_promo_nonce');
        echo '<button type="submit" class="button button-secondary">' . esc_html__('Clear Current Public Video', 'backstage-venue-manager') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="vms-cc-promo-column">';
        echo '<h4>' . esc_html__('Vendor submission', 'backstage-venue-manager') . '</h4>';
        if (!empty($submitted['attachment_id']) && function_exists('bvmgr_vendor_portal_render_headliner_promo_video_markup_from_data')) {
            echo wp_kses(bvmgr_vendor_portal_render_headliner_promo_video_markup_from_data($submitted, array(
                'context' => 'portal',
                'heading' => __('Waiting for review', 'backstage-venue-manager'),
                'wrap_class' => 'vms-cc-promo-preview',
            )), bvmgr_event_command_center_allowed_markup());
            if (!empty($marketing['promo_submission_uploaded_label'])) {
                /* translators: %s: formatted submission timestamp label. */
                echo '<p class="vms-cc-card__note">' . esc_html(sprintf(__('Submitted: %s', 'backstage-venue-manager'), (string) $marketing['promo_submission_uploaded_label'])) . '</p>';
            }
            echo '<div class="vms-cc-inline-actions">';
            echo '<form class="vms-cc-promo-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="vms_event_command_center_promo_video">';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
            echo '<input type="hidden" name="promo_action" value="use_submission">';
            wp_nonce_field('vms_cc_promo_video_' . $plan_id, '_vms_cc_promo_nonce');
            echo '<button type="submit" class="button button-primary">' . esc_html__('Use Submitted Clip', 'backstage-venue-manager') . '</button>';
            echo '</form>';
            echo '<form class="vms-cc-promo-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="vms_event_command_center_promo_video">';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
            echo '<input type="hidden" name="promo_action" value="remove_submission">';
            wp_nonce_field('vms_cc_promo_video_' . $plan_id, '_vms_cc_promo_nonce');
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Remove Submitted Clip', 'backstage-venue-manager') . '</button>';
            echo '</form>';
            echo '</div>';
        } else {
            echo '<p class="vms-cc-empty vms-cc-empty--inline">' . esc_html__('No vendor-submitted clip is waiting for review on this event.', 'backstage-venue-manager') . '</p>';
        }
        echo '</div>';

        echo '<div class="vms-cc-promo-column">';
        echo '<h4>' . esc_html__('Set the live source yourself', 'backstage-venue-manager') . '</h4>';
        echo '<form class="vms-cc-promo-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="vms_event_command_center_promo_video">';
        echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
        echo '<input type="hidden" name="promo_action" value="upload_public">';
        wp_nonce_field('vms_cc_promo_video_' . $plan_id, '_vms_cc_promo_nonce');
        echo '<label class="vms-cc-promo-field"><span><strong>' . esc_html__('Upload a replacement video', 'backstage-venue-manager') . '</strong></span><input type="file" name="vms_cc_headliner_promo_video" accept="video/mp4,video/quicktime,video/webm,.mp4,.m4v,.mov,.webm" required></label>';
        echo '<button type="submit" class="button">' . esc_html__('Upload Public Video', 'backstage-venue-manager') . '</button>';
        echo '</form>';
        echo '<form class="vms-cc-promo-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vms_event_command_center_promo_video">';
        echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '">';
        echo '<input type="hidden" name="promo_action" value="use_external">';
        wp_nonce_field('vms_cc_promo_video_' . $plan_id, '_vms_cc_promo_nonce');
        echo '<label class="vms-cc-promo-field"><span><strong>' . esc_html__('Use a YouTube, Vimeo, Facebook, or Instagram link', 'backstage-venue-manager') . '</strong></span><input type="url" name="external_url" value="' . esc_attr((string) ($marketing['promo_external_url'] ?? '')) . '" placeholder="https://www.youtube.com/watch?v=..." required></label>';
        echo '<button type="submit" class="button">' . esc_html__('Save External Video Link', 'backstage-venue-manager') . '</button>';
        echo '</form>';
        echo '<p class="vms-cc-card__note">' . esc_html__('Best fallback when a raw phone file is awkward: upload the finished clip to YouTube or Vimeo, then paste the direct video URL here.', 'backstage-venue-manager') . '</p>';
        echo '</div>';

        echo '</div>';
        echo '</section>';
    }
}

if (!function_exists('bvmgr_event_command_center_get_weather_snapshot')) {
    function bvmgr_event_command_center_get_weather_snapshot(int $plan_id): array
    {
        unset($plan_id);

        $active = bvmgr_event_command_center_is_weather_addon_active();
        return array(
            'active' => $active,
            'label' => $active ? __('Weather tracking available', 'backstage-venue-manager') : __('Weather tracking not enabled in this build yet', 'backstage-venue-manager'),
            'summary' => $active
                ? __('Open the weather workspace for live forecast, rain risk, wind, heat, and show-day weather notes.', 'backstage-venue-manager')
                : __('Install or activate the Backstage Venue Manager weather module to show event-level forecast, rain, wind, heat, and weather-watch notes here.', 'backstage-venue-manager'),
            'url' => bvmgr_event_command_center_get_weather_url(),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_get_notes_snapshot')) {
    function bvmgr_event_command_center_get_notes_snapshot(int $plan_id): array
    {
        $notes = (string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'notes_internal') : '_vms_event_plan_notes_internal', true);
        $notes = trim((string) $notes);
        return array(
            'has_notes' => $notes !== '',
            'notes' => $notes,
        );
    }
}

if (!function_exists('bvmgr_event_command_center_collect_activity')) {
    function bvmgr_event_command_center_collect_activity(int $plan_id): array
    {
        $items = array();

        $modified_gmt = get_post_field('post_modified_gmt', $plan_id);
        if (is_string($modified_gmt) && trim($modified_gmt) !== '' && trim($modified_gmt) !== '0000-00-00 00:00:00') {
            $items[] = array(
                'title' => __('Event Plan updated', 'backstage-venue-manager'),
                'detail' => __('The Event Plan record was modified.', 'backstage-venue-manager'),
                'when' => bvmgr_event_command_center_time_ago_label($modified_gmt, true),
                'ts' => bvmgr_event_command_center_parse_datetime($modified_gmt, true) instanceof DateTimeImmutable ? bvmgr_event_command_center_parse_datetime($modified_gmt, true)->getTimestamp() : 0,
            );
        }

        $changes_at = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_at'), true);
        if ($changes_at !== '') {
            $changes = bvmgr_event_plan_review_get_changes($plan_id);
            $summary = bvmgr_event_plan_review_compact_summary((array) ($changes['changes'] ?? array()));
            $items[] = array(
                'title' => __('Unpublished changes tracked', 'backstage-venue-manager'),
                'detail' => $summary !== '' ? $summary : __('This plan changed after the last publish baseline.', 'backstage-venue-manager'),
                'when' => bvmgr_event_command_center_time_ago_label($changes_at, false),
                'ts' => bvmgr_event_command_center_parse_datetime($changes_at, false) instanceof DateTimeImmutable ? bvmgr_event_command_center_parse_datetime($changes_at, false)->getTimestamp() : 0,
            );
        }

        $ticket_stats = get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1', true);
        if (is_array($ticket_stats)) {
            $computed = trim((string) ($ticket_stats['computed_at_gmt'] ?? $ticket_stats['computed_at'] ?? ''));
            if ($computed !== '') {
                $items[] = array(
                    'title' => __('Ticket stats refreshed', 'backstage-venue-manager'),
                    'detail' => __('Cached ticket sales data was refreshed.', 'backstage-venue-manager'),
                    'when' => bvmgr_event_command_center_time_ago_label($computed, true),
                    'ts' => bvmgr_event_command_center_parse_datetime($computed, true) instanceof DateTimeImmutable ? bvmgr_event_command_center_parse_datetime($computed, true)->getTimestamp() : 0,
                );
            }
        }

        $actuals_pulled = (string) get_post_meta($plan_id, '_vms_event_actuals_pulled_at_utc', true);
        if ($actuals_pulled !== '') {
            $items[] = array(
                'title' => __('Actuals refreshed', 'backstage-venue-manager'),
                'detail' => __('Provider-side event actuals were pulled into Backstage Venue Manager.', 'backstage-venue-manager'),
                'when' => bvmgr_event_command_center_time_ago_label($actuals_pulled, true),
                'ts' => bvmgr_event_command_center_parse_datetime($actuals_pulled, true) instanceof DateTimeImmutable ? bvmgr_event_command_center_parse_datetime($actuals_pulled, true)->getTimestamp() : 0,
            );
        }

        $promo_uploaded_at = (string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'headliner_promo_video_uploaded_at_gmt') : '_vms_headliner_promo_video_uploaded_at_gmt', true);
        if ($promo_uploaded_at !== '') {
            $items[] = array(
                'title' => __('Promo video uploaded', 'backstage-venue-manager'),
                'detail' => __('A headliner promo clip is attached to this event.', 'backstage-venue-manager'),
                'when' => bvmgr_event_command_center_time_ago_label($promo_uploaded_at, true),
                'ts' => bvmgr_event_command_center_parse_datetime($promo_uploaded_at, true) instanceof DateTimeImmutable ? bvmgr_event_command_center_parse_datetime($promo_uploaded_at, true)->getTimestamp() : 0,
            );
        }

        usort($items, static function (array $left, array $right): int {
            return (int) ($right['ts'] ?? 0) <=> (int) ($left['ts'] ?? 0);
        });

        return array_slice($items, 0, 8);
    }
}

if (!function_exists('bvmgr_event_command_center_build_alerts')) {
    function bvmgr_event_command_center_build_alerts(int $plan_id, array $header, array $ticket, array $lineup, array $staffing, array $marketing, array $weather): array
    {
        $alerts = array();

        if (absint($header['venue_id'] ?? 0) <= 0) {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Venue missing', 'backstage-venue-manager'),
                'detail' => __('This Event Plan does not have a venue assigned yet.', 'backstage-venue-manager'),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (trim((string) ($header['date_raw'] ?? '')) === '') {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Event date missing', 'backstage-venue-manager'),
                'detail' => __('The event date is not set, so schedule views and readiness calculations are unreliable.', 'backstage-venue-manager'),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (empty($lineup['primary'])) {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Primary vendor missing', 'backstage-venue-manager'),
                'detail' => __('This show does not currently have a primary lineup entry assigned.', 'backstage-venue-manager'),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (function_exists('bvmgr_event_plan_review_has_changes') && bvmgr_event_plan_review_has_changes($plan_id)) {
            $changes = (array) bvmgr_event_plan_review_get_changes($plan_id);
            $count = max(0, (int) ($changes['count'] ?? 0));
            $summary = bvmgr_event_plan_review_compact_summary((array) ($changes['changes'] ?? array()));
            $detail = $summary !== ''
                ? $summary
                /* translators: %d: number of unpublished tracked changes. */
                : sprintf(_n('%d tracked change is waiting for review.', '%d tracked changes are waiting for review.', $count, 'backstage-venue-manager'), $count);
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Needs review before republish', 'backstage-venue-manager'),
                'detail' => bvmgr_event_command_center_clean_text($detail),
                'action_label' => __('Review Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        $integrity_issue = trim((string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue', true));
        if ($integrity_issue !== '') {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Integrity issue flagged', 'backstage-venue-manager'),
                'detail' => bvmgr_event_command_center_clean_text(str_replace(array('_', '-'), ' ', $integrity_issue)),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (($ticket['integrity_status'] ?? '') === 'red') {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Ticket path needs attention', 'backstage-venue-manager'),
                'detail' => trim((string) ($ticket['issue_summary'] ?? '')) ?: __('Ticket integrity checks found a live-risk problem.', 'backstage-venue-manager'),
                'action_label' => __('Open Ticket Integrity', 'backstage-venue-manager'),
                'action_url' => bvmgr_admin_ui_page_url('vms-ticket-integrity'),
            );
        } elseif (($ticket['integrity_status'] ?? '') === 'yellow') {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Ticketing needs review', 'backstage-venue-manager'),
                'detail' => trim((string) ($ticket['issue_summary'] ?? '')) ?: __('Ticket integrity checks found a warning worth reviewing.', 'backstage-venue-manager'),
                'action_label' => __('Open Ticket Integrity', 'backstage-venue-manager'),
                'action_url' => bvmgr_admin_ui_page_url('vms-ticket-integrity'),
            );
        }

        if (!empty($ticket['low_inventory_flag'])) {
            $alerts[] = array(
                'severity' => ($ticket['low_inventory_severity'] ?? '') === 'red' ? 'red' : 'yellow',
                'title' => __('Low ticket inventory', 'backstage-venue-manager'),
                'detail' => __('A paid ticket tier is nearing its configured remaining-inventory threshold.', 'backstage-venue-manager'),
                'action_label' => !empty($header['ticket_url']) ? __('Open Ticket Page', 'backstage-venue-manager') : __('Open Ticket Integrity', 'backstage-venue-manager'),
                'action_url' => !empty($header['ticket_url']) ? (string) $header['ticket_url'] : bvmgr_admin_ui_page_url('vms-ticket-integrity'),
            );
        }

        if (($staffing['critical_open_headcount'] ?? 0) > 0) {
            $alerts[] = array(
                'severity' => 'red',
                'title' => __('Critical staffing gap', 'backstage-venue-manager'),
                /* translators: %d: number of critical staffing seats still open. */
                'detail' => sprintf(_n('%d critical staffing seat is still open.', '%d critical staffing seats are still open.', (int) $staffing['critical_open_headcount'], 'backstage-venue-manager'), (int) $staffing['critical_open_headcount']),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        } elseif (($staffing['open_headcount_total'] ?? 0) > 0) {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Staffing incomplete', 'backstage-venue-manager'),
                /* translators: %d: number of staffing seats still open. */
                'detail' => sprintf(_n('%d staffing seat is still open.', '%d staffing seats are still open.', (int) $staffing['open_headcount_total'], 'backstage-venue-manager'), (int) $staffing['open_headcount_total']),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (($staffing['conflict_count'] ?? 0) > 0) {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Staffing conflict detected', 'backstage-venue-manager'),
                /* translators: %d: number of staffing assignment conflicts. */
                'detail' => sprintf(_n('%d assignment conflict is flagged in staffing.', '%d assignment conflicts are flagged in staffing.', (int) $staffing['conflict_count'], 'backstage-venue-manager'), (int) $staffing['conflict_count']),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (count((array) ($lineup['warnings'] ?? array())) > 0) {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Lineup timing warning', 'backstage-venue-manager'),
                'detail' => bvmgr_event_command_center_clean_text((string) (((array) ($lineup['warnings'] ?? array()))[0]['message'] ?? __('One or more lineup entries need schedule review.', 'backstage-venue-manager'))),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (empty($marketing['event_page_public'])) {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Public event page missing', 'backstage-venue-manager'),
                'detail' => __('This Event Plan does not currently expose a live public event page URL.', 'backstage-venue-manager'),
                'action_label' => __('Open Event Plan', 'backstage-venue-manager'),
                'action_url' => (string) ($header['edit_url'] ?? ''),
            );
        }

        if (empty($marketing['social_ready'])) {
            $alerts[] = array(
                'severity' => 'informational',
                'title' => __('Social posting suppressed', 'backstage-venue-manager'),
                'detail' => __('This event is marked Do Not Post, so social sharing workflows are intentionally muted.', 'backstage-venue-manager'),
                'action_label' => __('Open Social Sharing', 'backstage-venue-manager'),
                'action_url' => (string) ($marketing['social_url'] ?? ''),
            );
        }

        if (empty($marketing['promo_video_id']) && empty($marketing['promo_external_url'])) {
            $alerts[] = array(
                'severity' => 'yellow',
                'title' => __('Promo video missing', 'backstage-venue-manager'),
                'detail' => !empty($marketing['promo_submission_pending'])
                    ? __('A vendor clip has been submitted, but it still needs operator review before it goes live.', 'backstage-venue-manager')
                    : __('No headliner promo clip is attached yet for this event.', 'backstage-venue-manager'),
                'action_label' => __('Open Event Command Center', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_admin_url(array('plan_id' => $plan_id)),
            );
        }


        $normalized = array();
        foreach ($alerts as $alert) {
            $severity = sanitize_key((string) ($alert['severity'] ?? 'yellow'));
            if (!in_array($severity, array('red', 'yellow', 'informational'), true)) {
                $severity = 'yellow';
            }
            $normalized[] = array(
                'severity' => $severity,
                'title' => sanitize_text_field((string) ($alert['title'] ?? '')),
                'detail' => bvmgr_event_command_center_clean_text((string) ($alert['detail'] ?? '')),
                'action_label' => sanitize_text_field((string) ($alert['action_label'] ?? '')),
                'action_url' => esc_url_raw((string) ($alert['action_url'] ?? '')),
            );
        }

        usort($normalized, static function (array $left, array $right): int {
            $rank = array('red' => 3, 'yellow' => 2, 'informational' => 1);
            return ($rank[$right['severity']] ?? 0) <=> ($rank[$left['severity']] ?? 0);
        });

        return $normalized;
    }
}

if (!function_exists('bvmgr_event_command_center_get_health')) {
    function bvmgr_event_command_center_get_health(array $alerts): array
    {
        $red = 0;
        $yellow = 0;
        foreach ($alerts as $alert) {
            $severity = sanitize_key((string) ($alert['severity'] ?? ''));
            if ($severity === 'red') {
                $red++;
            } elseif ($severity === 'yellow') {
                $yellow++;
            }
        }

        if ($red > 0) {
            return array(
                'status' => 'critical',
                'label' => __('Critical', 'backstage-venue-manager'),
                /* translators: %d: number of critical alert items. */
                'summary' => sprintf(_n('%d critical item needs attention now.', '%d critical items need attention now.', $red, 'backstage-venue-manager'), $red),
            );
        }

        if ($yellow >= 3) {
            return array(
                'status' => 'at-risk',
                'label' => __('At Risk', 'backstage-venue-manager'),
                /* translators: %d: number of warning-level review items. */
                'summary' => sprintf(_n('%d review item is stacking up.', '%d review items are stacking up.', $yellow, 'backstage-venue-manager'), $yellow),
            );
        }

        if ($yellow > 0) {
            return array(
                'status' => 'needs-review',
                'label' => __('Needs Review', 'backstage-venue-manager'),
                /* translators: %d: number of items that should be reviewed. */
                'summary' => sprintf(_n('%d item should be reviewed.', '%d items should be reviewed.', $yellow, 'backstage-venue-manager'), $yellow),
            );
        }

        return array(
            'status' => 'on-track',
            'label' => __('On Track', 'backstage-venue-manager'),
            'summary' => __('No open warnings are currently stacked against this show.', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_get_timeline_rows')) {
    function bvmgr_event_command_center_get_timeline_rows(int $plan_id, array $lineup, array $staffing): array
    {
        unset($staffing);

        $rows = array();
        $dt = function_exists('bvmgr_staffing_event_plan_datetime') ? (array) bvmgr_staffing_event_plan_datetime($plan_id) : array();
        $start_hhmm = (string) ($dt['start_hhmm'] ?? get_post_meta($plan_id, '_vms_start_time', true));
        $end_hhmm = (string) ($dt['end_hhmm'] ?? get_post_meta($plan_id, '_vms_end_time', true));

        if ($start_hhmm !== '') {
            $rows[] = array(
                'time' => function_exists('bvmgr_lineup_schedule_format_time_label') ? (string) bvmgr_lineup_schedule_format_time_label($start_hhmm) : $start_hhmm,
                'label' => __('Show start', 'backstage-venue-manager'),
                'detail' => __('Main event time pulled from the Event Plan.', 'backstage-venue-manager'),
            );
        }

        foreach ((array) ($lineup['entries'] ?? array()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $start = trim((string) ($entry['set_start_label'] ?? ''));
            $end = trim((string) ($entry['set_end_label'] ?? ''));
            $display_name = trim((string) ($entry['display_name'] ?? $entry['vendor_title'] ?? __('Lineup entry', 'backstage-venue-manager')));
            if ($start === '' && $end === '') {
                continue;
            }

            $role = sanitize_key((string) ($entry['role'] ?? 'supporting'));
            $detail = $role === 'primary' ? __('Primary lineup slot', 'backstage-venue-manager') : __('Supporting lineup slot', 'backstage-venue-manager');
            if ($start !== '' && $end !== '') {
                /* translators: 1: lineup start time, 2: lineup end time. */
                $detail = sprintf(__('Runs %1$s–%2$s', 'backstage-venue-manager'), $start, $end);
            } elseif ($end !== '') {
                /* translators: %s: lineup end time. */
                $detail = sprintf(__('Scheduled through %s', 'backstage-venue-manager'), $end);
            }

            $rows[] = array(
                'time' => $start !== '' ? $start : $end,
                'label' => $display_name,
                'detail' => $detail,
            );
        }

        if ($end_hhmm !== '') {
            $rows[] = array(
                'time' => function_exists('bvmgr_lineup_schedule_format_time_label') ? (string) bvmgr_lineup_schedule_format_time_label($end_hhmm) : $end_hhmm,
                'label' => __('Show end', 'backstage-venue-manager'),
                'detail' => __('Ending anchor from the Event Plan.', 'backstage-venue-manager'),
            );
        }

        usort($rows, static function (array $left, array $right): int {
            $left_time = (string) ($left['time'] ?? '');
            $right_time = (string) ($right['time'] ?? '');
            $normalize = static function (string $value): int {
                $value = strtolower(trim($value));
                if ($value === '') {
                    return 99999;
                }
                $ts = strtotime($value);
                if ($ts === false) {
                    return 99998;
                }

                $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(wp_timezone());
                return (int) $dt->format('Hi');
            };
            return $normalize($left_time) <=> $normalize($right_time);
        });

        return array_slice($rows, 0, 6);
    }
}

if (!function_exists('bvmgr_event_command_center_get_next_actions')) {
    function bvmgr_event_command_center_get_next_actions(array $alerts): array
    {
        $actions = array();
        foreach ($alerts as $alert) {
            $severity = sanitize_key((string) ($alert['severity'] ?? 'yellow'));
            if (!in_array($severity, array('red', 'yellow'), true)) {
                continue;
            }

            $title = trim((string) ($alert['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $actions[] = array(
                'title' => $title,
                'detail' => trim((string) ($alert['detail'] ?? '')),
                'severity' => $severity,
                'action_label' => trim((string) ($alert['action_label'] ?? '')),
                'action_url' => trim((string) ($alert['action_url'] ?? '')),
            );
        }

        return array_slice($actions, 0, 6);
    }
}

if (!function_exists('bvmgr_event_command_center_build_payload')) {
    function bvmgr_event_command_center_build_payload(int $plan_id): array
    {
        if (function_exists('bvmgr_resource_fingerprint_flag')) {
            bvmgr_resource_fingerprint_flag('ecc_calculation', array(
                'plan_id' => $plan_id,
                'step' => 'build_payload',
            ));
        }
        if (function_exists('bvmgr_resource_fingerprint_span_start')) {
            bvmgr_resource_fingerprint_span_start('ecc.build_payload', array('plan_id' => $plan_id));
        }

        try {
            $header = bvmgr_event_command_center_get_plan_header($plan_id);
            $ticket = bvmgr_event_command_center_get_ticket_snapshot($plan_id);
            $financial = bvmgr_event_command_center_get_financial_snapshot($plan_id);
            $lineup = bvmgr_event_command_center_get_lineup_snapshot($plan_id);
            $staffing = bvmgr_event_command_center_get_staffing_snapshot($plan_id);
            $marketing = bvmgr_event_command_center_get_marketing_snapshot($plan_id, $header);
            $weather = bvmgr_event_command_center_get_weather_snapshot($plan_id);
            $notes = bvmgr_event_command_center_get_notes_snapshot($plan_id);
            $alerts = bvmgr_event_command_center_build_alerts($plan_id, $header, $ticket, $lineup, $staffing, $marketing, $weather);
            $health = bvmgr_event_command_center_get_health($alerts);
            $timeline = bvmgr_event_command_center_get_timeline_rows($plan_id, $lineup, $staffing);
            $actions = bvmgr_event_command_center_get_next_actions($alerts);
            $activity = bvmgr_event_command_center_collect_activity($plan_id);

            return array(
                'header' => $header,
                'ticket' => $ticket,
                'financial' => $financial,
                'lineup' => $lineup,
                'staffing' => $staffing,
                'marketing' => $marketing,
                'weather' => $weather,
                'notes' => $notes,
                'alerts' => $alerts,
                'health' => $health,
                'timeline' => $timeline,
                'actions' => $actions,
                'activity' => $activity,
            );
        } finally {
            if (function_exists('bvmgr_resource_fingerprint_span_finish')) {
                bvmgr_resource_fingerprint_span_finish('ecc.build_payload', array('plan_id' => $plan_id));
            }
        }
    }
}

if (!function_exists('bvmgr_event_command_center_render_picker')) {
    function bvmgr_event_command_center_render_picker(int $current_plan_id = 0): void
    {
        $ids = bvmgr_event_command_center_get_plan_ids();
        $is_compact = $current_plan_id > 0;
        echo '<div class="vms-cc-card vms-cc-card--picker' . ($is_compact ? ' is-compact' : '') . '">';
        if ($is_compact) {
            echo '<div class="vms-cc-picker-bar">';
            echo '<div class="vms-cc-picker-copy">';
            echo '<h2>' . esc_html__('Switch Event', 'backstage-venue-manager') . '</h2>';
            echo '<p>' . esc_html__('Jump straight to another show without giving up the current Command Center layout.', 'backstage-venue-manager') . '</p>';
            echo '</div>';
        } else {
            echo '<h2>' . esc_html__('Choose an Event Plan', 'backstage-venue-manager') . '</h2>';
            echo '<p>' . esc_html__('Pick a show to open its Command Center. This page is designed as the single-glance operational summary for one event at a time.', 'backstage-venue-manager') . '</p>';
        }
        echo '<form method="get" class="vms-cc-picker-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr(bvmgr_event_command_center_page_slug()) . '" />';
        echo '<label class="screen-reader-text" for="vms-cc-plan-id">' . esc_html__('Event Plan', 'backstage-venue-manager') . '</label>';
        echo '<select id="vms-cc-plan-id" name="plan_id">';
        foreach ($ids as $plan_id) {
            $date = (string) get_post_meta($plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'date') : '_vms_event_date', true);
            $title = (string) get_the_title($plan_id);
            $label = $title;
            if ($date !== '') {
                $label .= ' — ' . $date;
            }
            echo '<option value="' . esc_attr((string) $plan_id) . '"' . ((int) $current_plan_id === (int) $plan_id ? ' selected="selected"' : '') . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Open Command Center', 'backstage-venue-manager') . '</button>';
        echo '</form>';
        if ($is_compact) {
            echo '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('bvmgr_event_command_center_render_metric')) {
    function bvmgr_event_command_center_render_metric(string $label, string $value, string $sub = ''): void
    {
        echo '<div class="vms-cc-metric">';
        echo '<span class="vms-cc-metric__label">' . esc_html($label) . '</span>';
        echo '<strong class="vms-cc-metric__value">' . esc_html($value) . '</strong>';
        if ($sub !== '') {
            echo '<span class="vms-cc-metric__sub">' . esc_html($sub) . '</span>';
        }
        echo '</div>';
    }
}

if (!function_exists('bvmgr_event_command_center_render_page_content')) {
    function bvmgr_event_command_center_render_page_content(int $plan_id): void
    {
        $payload = bvmgr_event_command_center_build_payload($plan_id);
        $header = (array) $payload['header'];
        $health = (array) $payload['health'];
        $ticket = (array) $payload['ticket'];
        $financial = (array) $payload['financial'];
        $lineup = (array) $payload['lineup'];
        $staffing = (array) $payload['staffing'];
        $marketing = (array) $payload['marketing'];
        $weather = (array) $payload['weather'];
        $notes = (array) $payload['notes'];
        $alerts = (array) $payload['alerts'];
        $timeline = (array) $payload['timeline'];
        $actions = (array) $payload['actions'];
        $activity = (array) $payload['activity'];

        $status_chip = bvmgr_event_command_center_render_chip((string) ($header['status_label'] ?? __('Unknown', 'backstage-venue-manager')), (string) ($header['status_tone'] ?? 'muted'));
        /* translators: %s: current command center health label. */
        $health_chip = bvmgr_event_command_center_render_chip(sprintf(__('Health: %s', 'backstage-venue-manager'), (string) ($health['label'] ?? __('Needs Review', 'backstage-venue-manager'))), bvmgr_event_command_center_health_tone((string) ($health['status'] ?? 'needs-review')));
        $highlight_chips = bvmgr_event_command_center_get_highlight_chips($alerts);

        echo '<div class="vms-event-command-center">';
        bvmgr_event_command_center_render_notice();

        echo '<section class="vms-cc-overview">';
        echo '<div class="vms-cc-overview__identity">';
        echo '<div class="vms-cc-overview__eyebrow">' . esc_html__('Event Command Center', 'backstage-venue-manager') . '</div>';
        echo '<h2 class="vms-cc-overview__title">' . esc_html((string) ($header['title'] ?? '')) . '</h2>';
        echo '<div class="vms-cc-overview__meta">';
        echo '<span>' . esc_html((string) ($header['date_label'] ?? '')) . '</span>';
        echo '<span>•</span>';
        echo '<span>' . esc_html((string) ($header['time_label'] ?? '')) . '</span>';
        echo '<span>•</span>';
        echo '<span>' . esc_html((string) ($header['venue_label'] ?? '')) . '</span>';
        echo '<span>•</span>';
        echo '<span>' . esc_html((string) ($header['days_until_label'] ?? '')) . '</span>';
        echo '</div>';
        echo '<div class="vms-cc-overview__chips">' . wp_kses($status_chip . $health_chip . implode('', $highlight_chips), bvmgr_event_command_center_allowed_markup()) . '</div>';
        echo '</div>';
        echo '<div class="vms-cc-overview__actions">';
        if (!empty($header['edit_url'])) {
            echo '<a class="button button-primary" href="' . esc_url((string) $header['edit_url']) . '">' . esc_html__('Open Event Plan', 'backstage-venue-manager') . '</a>';
        }
        if (!empty($header['public_event_url'])) {
            echo '<a class="button" href="' . esc_url((string) $header['public_event_url']) . '" target="_blank" rel="noopener">' . esc_html__('View Public Event', 'backstage-venue-manager') . '</a>';
        } elseif (!empty($header['edit_event_url'])) {
            echo '<a class="button" href="' . esc_url((string) $header['edit_event_url']) . '">' . esc_html__('Open Calendar Event', 'backstage-venue-manager') . '</a>';
        }
        if (!empty($header['ticket_url'])) {
            echo '<a class="button" href="' . esc_url((string) $header['ticket_url']) . '" target="_blank" rel="noopener">' . esc_html__('Open Ticket Page', 'backstage-venue-manager') . '</a>';
        }
        if (!empty($marketing['meta_ads_builder_url'])) {
            echo '<a class="button" href="' . esc_url((string) $marketing['meta_ads_builder_url']) . '">' . esc_html__('Open Marketing', 'backstage-venue-manager') . '</a>';
        }
        echo '<a class="button" href="#vms-cc-notes">' . esc_html__('Jump to Notes', 'backstage-venue-manager') . '</a>';
        echo '</div>';
        echo '</section>';

        echo '<div class="vms-cc-grid vms-cc-grid--top">';

        echo '<section class="vms-cc-card vms-cc-card--weather-top">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Weather / Venue Conditions', 'backstage-venue-manager') . '</h3></div>';
        echo '<div class="vms-cc-overview__chips">' . wp_kses(bvmgr_event_command_center_render_chip((string) ($weather['label'] ?? __('Unavailable', 'backstage-venue-manager')), !empty($weather['active']) ? 'good' : 'warning'), bvmgr_event_command_center_allowed_markup()) . '</div>';
        echo '<p class="vms-cc-card__note">' . esc_html((string) ($weather['summary'] ?? '')) . '</p>';
        if (!empty($weather['active']) && !empty($weather['url'])) {
            echo '<div class="vms-cc-inline-actions"><a class="button button-small" href="' . esc_url((string) $weather['url']) . '">' . esc_html__('Open Weather Workspace', 'backstage-venue-manager') . '</a></div>';
        }
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Show Health', 'backstage-venue-manager') . '</h3></div>';
        echo '<div class="vms-cc-health__headline">' . wp_kses($health_chip, bvmgr_event_command_center_allowed_markup()) . '</div>';
        echo '<p class="vms-cc-health__summary">' . esc_html((string) ($health['summary'] ?? '')) . '</p>';
        echo '<div class="vms-cc-metrics">';
        bvmgr_event_command_center_render_metric(__('Action items', 'backstage-venue-manager'), (string) count($actions));
        bvmgr_event_command_center_render_metric(__('Lineup warnings', 'backstage-venue-manager'), (string) count((array) ($lineup['warnings'] ?? array())));
        bvmgr_event_command_center_render_metric(__('Staffing coverage', 'backstage-venue-manager'), sprintf('%1$d/%2$d', (int) ($staffing['headcount_filled_total'] ?? 0), (int) ($staffing['headcount_needed_total'] ?? 0)));
        bvmgr_event_command_center_render_metric(__('Last updated', 'backstage-venue-manager'), (string) ($header['modified_label'] ?? ''));
        echo '</div>';
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Ticket Snapshot', 'backstage-venue-manager') . '</h3></div>';
        echo '<div class="vms-cc-metrics">';
        bvmgr_event_command_center_render_metric(__('Paid tickets', 'backstage-venue-manager'), (string) ($ticket['sold'] ?? 0));
        bvmgr_event_command_center_render_metric(__('Gross sales', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($ticket['revenue_cents'] ?? 0)));
        bvmgr_event_command_center_render_metric(__('Guest list / comps', 'backstage-venue-manager'), (string) ($ticket['comp_count'] ?? 0));
        /* translators: %d: ticket capacity count. */
        bvmgr_event_command_center_render_metric(__('Remaining', 'backstage-venue-manager'), $ticket['remaining'] !== null ? (string) $ticket['remaining'] : '—', $ticket['capacity'] !== null ? sprintf(__('of %d', 'backstage-venue-manager'), (int) $ticket['capacity']) : '');
        echo '</div>';
        if ($ticket['sell_through'] !== null) {
            $sell_through = (float) $ticket['sell_through'];
            echo '<div class="vms-cc-progress">';
            echo '<progress class="vms-cc-progress__bar" max="100" value="' . esc_attr((string) round($sell_through, 1)) . '"></progress>';
            /* translators: %s: sell-through percentage value. */
            echo '<div class="vms-cc-progress__label">' . esc_html(sprintf(__('Sell-through: %s%%', 'backstage-venue-manager'), number_format_i18n($sell_through, 1))) . '</div>';
            echo '</div>';
        }
        $total_count = isset($ticket['total_ticket_count']) ? max(0, (int) $ticket['total_ticket_count']) : ((int) ($ticket['sold'] ?? 0) + (int) ($ticket['comp_count'] ?? 0));
        if ((int) ($ticket['comp_count'] ?? 0) > 0 || $total_count > (int) ($ticket['sold'] ?? 0)) {
            /* translators: 1: total admitted or ticketed count, 2: paid count, 3: complimentary or free count. */
            echo '<p class="vms-cc-card__note">' . esc_html(sprintf(__('Total admitted/ticketed: %1$d (%2$d paid + %3$d comp/free).', 'backstage-venue-manager'), $total_count, (int) ($ticket['sold'] ?? 0), (int) ($ticket['comp_count'] ?? 0))) . '</p>';
        }
        if (!empty($ticket['issue_summary'])) {
            echo '<p class="vms-cc-card__note">' . esc_html((string) $ticket['issue_summary']) . '</p>';
        } elseif ((int) ($ticket['sold'] ?? 0) <= 0) {
            echo '<p class="vms-cc-card__note">' . esc_html__('No paid ticket sales are showing yet for this event.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<p class="vms-cc-card__note">' . esc_html__('No ticketing warnings are currently stacked against this show.', 'backstage-venue-manager') . '</p>';
        }
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Alerts & Next Actions', 'backstage-venue-manager') . '</h3></div>';
        if (empty($alerts)) {
            echo '<p class="vms-cc-empty">' . esc_html__('No alerts or follow-up items are currently stacked.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul class="vms-cc-alert-list">';
            foreach ($alerts as $alert) {
                $severity = sanitize_key((string) ($alert['severity'] ?? 'yellow'));
                echo '<li class="vms-cc-alert is-' . esc_attr($severity) . '">';
                echo '<div class="vms-cc-alert__body">';
                echo '<strong>' . esc_html((string) ($alert['title'] ?? '')) . '</strong>';
                echo '<p>' . esc_html((string) ($alert['detail'] ?? '')) . '</p>';
                echo '</div>';
                if (!empty($alert['action_label']) && !empty($alert['action_url'])) {
                    echo '<a class="button button-small" href="' . esc_url((string) $alert['action_url']) . '">' . esc_html((string) ($alert['action_label'] ?? '')) . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';

        echo '</div>';

        echo '<div class="vms-cc-grid vms-cc-grid--middle">';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Schedule / Timeline', 'backstage-venue-manager') . '</h3></div>';
        if (empty($timeline)) {
            echo '<p class="vms-cc-empty">' . esc_html__('No timeline anchors are stored yet beyond the core event time.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul class="vms-cc-timeline">';
            foreach ($timeline as $row) {
                echo '<li class="vms-cc-timeline__row">';
                echo '<span class="vms-cc-timeline__time">' . esc_html((string) ($row['time'] ?? '')) . '</span>';
                echo '<span class="vms-cc-timeline__label">' . esc_html((string) ($row['label'] ?? '')) . '</span>';
                echo '<span class="vms-cc-timeline__detail">' . esc_html((string) ($row['detail'] ?? '')) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Lineup & Participants', 'backstage-venue-manager') . '</h3></div>';
        echo '<div class="vms-cc-participant-group">';
        echo '<h4>' . esc_html__('Talent', 'backstage-venue-manager') . '</h4>';
        echo '<ul class="vms-cc-participant-list">';
        if (!empty($lineup['primary'])) {
            $primary = (array) $lineup['primary'];
            echo '<li><strong>' . esc_html((string) ($primary['display_name'] ?? $primary['vendor_title'] ?? __('Primary vendor', 'backstage-venue-manager'))) . '</strong><span>' . esc_html__('Primary', 'backstage-venue-manager') . '</span>' . wp_kses(bvmgr_event_command_center_render_chip(__('Booked', 'backstage-venue-manager'), 'good'), bvmgr_event_command_center_allowed_markup()) . '</li>';
        } else {
            echo '<li><strong>' . esc_html__('Primary vendor', 'backstage-venue-manager') . '</strong><span>' . esc_html__('Missing', 'backstage-venue-manager') . '</span>' . wp_kses(bvmgr_event_command_center_render_chip(__('Missing', 'backstage-venue-manager'), 'critical'), bvmgr_event_command_center_allowed_markup()) . '</li>';
        }
        foreach ((array) ($lineup['supporting'] ?? array()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            echo '<li><strong>' . esc_html((string) ($entry['display_name'] ?? $entry['vendor_title'] ?? __('Supporting act', 'backstage-venue-manager'))) . '</strong><span>' . esc_html__('Supporting', 'backstage-venue-manager') . '</span>' . wp_kses(bvmgr_event_command_center_render_chip(__('Booked', 'backstage-venue-manager'), 'good'), bvmgr_event_command_center_allowed_markup()) . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '<div class="vms-cc-participant-group">';
        echo '<h4>' . esc_html__('Secondary Vendors', 'backstage-venue-manager') . '</h4>';
        if (empty($lineup['secondary'])) {
            echo '<p class="vms-cc-empty vms-cc-empty--inline">' . esc_html__('No secondary vendors assigned.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul class="vms-cc-participant-list">';
            foreach ((array) $lineup['secondary'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                echo '<li><strong>' . esc_html((string) ($row['display_name'] ?? __('Secondary vendor', 'backstage-venue-manager'))) . '</strong><span>' . esc_html((string) ($row['role_label'] ?? '')) . '</span>' . wp_kses(bvmgr_event_command_center_render_chip((string) ($row['status_label'] ?? __('Booked', 'backstage-venue-manager')), (string) ($row['status_tone'] ?? 'good')), bvmgr_event_command_center_allowed_markup()) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="vms-cc-participant-group">';
        echo '<h4>' . esc_html__('Staff', 'backstage-venue-manager') . '</h4>';
        if (empty($staffing['roles'])) {
            echo '<p class="vms-cc-empty vms-cc-empty--inline">' . esc_html__('No staffing slots are stored yet for this event.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul class="vms-cc-participant-list">';
            foreach ((array) $staffing['roles'] as $role) {
                if (!is_array($role)) {
                    continue;
                }
                $needed = max(0, (int) ($role['needed'] ?? 0));
                $filled = max(0, (int) ($role['filled'] ?? 0));
                $tone = ($filled >= $needed && $needed > 0) ? 'good' : (($filled > 0) ? 'warning' : 'critical');
                /* translators: 1: filled staffing count, 2: required staffing count. */
                echo '<li><strong>' . esc_html((string) ($role['role_name'] ?? __('Role', 'backstage-venue-manager'))) . '</strong><span>' . esc_html(sprintf(__('%1$d of %2$d filled', 'backstage-venue-manager'), $filled, $needed)) . '</span>' . wp_kses(bvmgr_event_command_center_render_chip(sprintf('%1$d/%2$d', $filled, $needed), $tone), bvmgr_event_command_center_allowed_markup()) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        echo '</section>';

        echo '</div>';

        echo '<div class="vms-cc-grid vms-cc-grid--support">';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Financial Snapshot', 'backstage-venue-manager') . '</h3></div>';
        echo '<div class="vms-cc-metrics">';
        bvmgr_event_command_center_render_metric(__('Gross revenue', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($financial['gross_cents'] ?? 0)), !empty($financial['has_actuals']) ? __('Actuals loaded', 'backstage-venue-manager') : __('No ticket revenue yet', 'backstage-venue-manager'));
        bvmgr_event_command_center_render_metric(__('Vendor pay', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($financial['vendor_cost_cents'] ?? 0)), (int) ($financial['vendor_cost_cents'] ?? 0) > 0 ? __('Compensation loaded', 'backstage-venue-manager') : __('No vendor pay loaded', 'backstage-venue-manager'));
        bvmgr_event_command_center_render_metric(__('Labor', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($financial['labor_cost_cents'] ?? 0)), (int) ($financial['labor_cost_cents'] ?? 0) > 0 ? __('Staffing labor loaded', 'backstage-venue-manager') : __('No staffing labor yet', 'backstage-venue-manager'));
        bvmgr_event_command_center_render_metric(__('Projected margin', 'backstage-venue-manager'), bvmgr_event_command_center_money_signed((int) ($financial['margin_cents'] ?? 0)), ((int) ($financial['gross_cents'] ?? 0) > 0 || !empty($financial['has_actuals'])) ? __('Based on current revenue and loaded costs', 'backstage-venue-manager') : __('Waiting on revenue', 'backstage-venue-manager'));
        echo '</div>';
        echo '<p class="vms-cc-card__note">' . esc_html__('This is a show-ops snapshot, not final accounting truth. Meta ad spend is still waiting on event-level wiring from the Ads side.', 'backstage-venue-manager') . '</p>';
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Marketing Snapshot', 'backstage-venue-manager') . '</h3></div>';
        echo '<ul class="vms-cc-simple-list">';
        echo '<li><span>' . esc_html__('Public event page', 'backstage-venue-manager') . '</span><strong>' . esc_html((string) ($marketing['event_page_label'] ?? '')) . '</strong></li>';
        echo '<li><span>' . esc_html__('Social sharing', 'backstage-venue-manager') . '</span><strong>' . esc_html((string) ($marketing['social_label'] ?? '')) . '</strong></li>';
        echo '<li><span>' . esc_html__('Promo video', 'backstage-venue-manager') . '</span><strong>' . esc_html((string) ($marketing['promo_video_label'] ?? '')) . '</strong></li>';
        echo '</ul>';
        echo '<div class="vms-cc-inline-actions">';
        if (!empty($marketing['social_url'])) {
            echo '<a class="button button-small" href="' . esc_url((string) $marketing['social_url']) . '">' . esc_html__('Open Social Sharing', 'backstage-venue-manager') . '</a>';
        }
        if (!empty($marketing['meta_ads_builder_url'])) {
            echo '<a class="button button-small" href="' . esc_url((string) $marketing['meta_ads_builder_url']) . '">' . esc_html__('Open Ads Workspace', 'backstage-venue-manager') . '</a>';
        }
        echo '</div>';
        if (!empty($marketing['promo_submission_pending'])) {
            $submitted_note = !empty($marketing['promo_submission_uploaded_label'])
                /* translators: %s: formatted vendor promo submission timestamp label. */
                ? sprintf(__('Vendor clip submitted %s and still waiting for review.', 'backstage-venue-manager'), (string) ($marketing['promo_submission_uploaded_label'] ?? ''))
                : __('A vendor clip is waiting for review before it goes live.', 'backstage-venue-manager');
            echo '<p class="vms-cc-card__note">' . esc_html($submitted_note) . '</p>';
        } elseif (!empty($marketing['promo_video_uploaded_label'])) {
            /* translators: %s: formatted promo video update timestamp label. */
            echo '<p class="vms-cc-card__note">' . esc_html(sprintf(__('Promo video update: %s', 'backstage-venue-manager'), (string) $marketing['promo_video_uploaded_label'])) . '</p>';
        } elseif (!empty($marketing['meta_ads_registered'])) {
            echo '<p class="vms-cc-card__note">' . esc_html__('Event-level ad campaign status is not wired into this card yet, but the ads workspace is available.', 'backstage-venue-manager') . '</p>';
        }
        echo '</section>';

        echo '</div>';

        bvmgr_event_command_center_render_promo_video_manager($plan_id, $marketing);

        echo '<div class="vms-cc-grid vms-cc-grid--bottom">';

        echo '<section class="vms-cc-card" id="vms-cc-notes">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Internal Notes', 'backstage-venue-manager') . '</h3></div>';
        if (empty($notes['has_notes'])) {
            echo '<p class="vms-cc-empty">' . esc_html__('No internal notes are saved yet for this event.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<div class="vms-cc-notes">' . nl2br(esc_html((string) ($notes['notes'] ?? ''))) . '</div>';
        }
        echo '</section>';

        echo '<section class="vms-cc-card">';
        echo '<div class="vms-cc-card__header"><h3>' . esc_html__('Recent Activity', 'backstage-venue-manager') . '</h3></div>';
        if (empty($activity)) {
            echo '<p class="vms-cc-empty">' . esc_html__('No recent activity signals were found yet.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul class="vms-cc-activity-list">';
            foreach ($activity as $item) {
                echo '<li>';
                echo '<strong>' . esc_html((string) ($item['title'] ?? '')) . '</strong>';
                echo '<p>' . esc_html((string) ($item['detail'] ?? '')) . '</p>';
                echo '<span>' . esc_html((string) ($item['when'] ?? '')) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';

        echo '</div>';

        echo '</div>';
    }
}



if (!function_exists('bvmgr_event_command_center_edit_fragment_url')) {
    function bvmgr_event_command_center_edit_fragment_url(int $plan_id, string $fragment = ''): string
    {
        $plan_id = absint($plan_id);
        $fragment = ltrim(trim((string) $fragment), '#');
        if ($plan_id <= 0) {
            return '';
        }

        if (function_exists('bvmgr_event_plan_admin_edit_url')) {
            return bvmgr_event_plan_admin_edit_url($plan_id, array(), $fragment, 'raw');
        }

        $url = (string) get_edit_post_link($plan_id, '');
        if ($url !== '' && $fragment !== '') {
            $url .= '#' . $fragment;
        }

        return $url;
    }
}

if (!function_exists('bvmgr_event_command_center_module_hub_status_tone')) {
    function bvmgr_event_command_center_module_hub_status_tone(string $status): string
    {
        $status = sanitize_key($status);
        if (in_array($status, array('complete', 'good', 'ready', 'published', 'active'), true)) {
            return 'good';
        }
        if (in_array($status, array('blocked', 'critical', 'missing'), true)) {
            return 'critical';
        }
        if (in_array($status, array('needs-review', 'warning', 'pending', 'attention'), true)) {
            return 'warning';
        }
        return 'muted';
    }
}


if (!function_exists('bvmgr_event_command_center_ticket_summary_meta_bundle')) {
    function bvmgr_event_command_center_ticket_summary_meta_bundle(int $plan_id): array
    {
        return (array) bvmgr_event_command_center_request_cache($plan_id, 'ticket_summary_meta_bundle', static function () use ($plan_id): array {
            $all_meta = get_post_meta($plan_id);
            if (!is_array($all_meta)) {
                $all_meta = array();
            }

            $ticket_stats_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
            if ($ticket_stats_key === '') {
                $ticket_stats_key = '_vms_ticket_stats_v1';
            }
            $integrity_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
            if ($integrity_key === '') {
                $integrity_key = '_vms_integrity_issue';
            }
            $tec_event_key = function_exists('bvmgr_ticketing_b_meta_key')
                ? (string) bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
                : '_vms_tec_event_id';
            if ($tec_event_key === '') {
                $tec_event_key = '_vms_tec_event_id';
            }
            $ticket_product_ids_key = function_exists('bvmgr_ticketing_b_meta_key')
                ? (string) bvmgr_ticketing_b_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1')
                : '_vms_ticket_product_ids_v1';
            if ($ticket_product_ids_key === '') {
                $ticket_product_ids_key = '_vms_ticket_product_ids_v1';
            }
            $ticketing_config_key = function_exists('bvmgr_ticketing_v2_k')
                ? (string) bvmgr_ticketing_v2_k('config')
                : '_vms_ticketing_v2_config';
            if ($ticketing_config_key === '') {
                $ticketing_config_key = '_vms_ticketing_v2_config';
            }
            $ticketing_sync_key = function_exists('bvmgr_ticketing_v2_k')
                ? (string) bvmgr_ticketing_v2_k('sync')
                : '_vms_ticketing_v2_sync';
            if ($ticketing_sync_key === '') {
                $ticketing_sync_key = '_vms_ticketing_v2_sync';
            }
            $ticketing_stats_v2_key = function_exists('bvmgr_ticketing_v2_k')
                ? (string) bvmgr_ticketing_v2_k('stats')
                : '_vms_ticketing_stats_v2';
            if ($ticketing_stats_v2_key === '') {
                $ticketing_stats_v2_key = '_vms_ticketing_stats_v2';
            }

            $read_meta = static function (string $key, $default = '') use ($all_meta) {
                if ($key === '' || !array_key_exists($key, $all_meta)) {
                    return $default;
                }
                $values = $all_meta[$key];
                if (!is_array($values) || empty($values)) {
                    return $default;
                }
                if (count($values) > 1) {
                    return array_map('maybe_unserialize', $values);
                }
                return maybe_unserialize($values[0]);
            };

            $ticket_stats = $read_meta($ticket_stats_key, array());
            if (!is_array($ticket_stats)) {
                $ticket_stats = array();
            }
            $ticketing_stats_v2 = $read_meta($ticketing_stats_v2_key, array());
            if (!is_array($ticketing_stats_v2)) {
                $ticketing_stats_v2 = array();
            }
            $ticketing_config = $read_meta($ticketing_config_key, array());
            if (!is_array($ticketing_config)) {
                $ticketing_config = array();
            }
            $ticketing_sync = $read_meta($ticketing_sync_key, array());
            if (!is_array($ticketing_sync)) {
                $ticketing_sync = array();
            }
            $ticket_product_ids = array_values(array_unique(array_filter(array_map('absint', (array) $read_meta($ticket_product_ids_key, array())))));

            return array(
                'linked_tec_id' => absint($read_meta($tec_event_key, 0)),
                'ticket_stats' => $ticket_stats,
                'ticketing_stats_v2' => $ticketing_stats_v2,
                'ticketing_config' => $ticketing_config,
                'ticketing_sync' => $ticketing_sync,
                'ticket_product_ids' => $ticket_product_ids,
                'integrity_issue' => sanitize_key((string) $read_meta($integrity_key, '')),
                'comp_forecast' => max(0, (int) $read_meta('_vms_comp_headcount_forecast', 0)),
                'comp_true' => max(0, (int) $read_meta('_vms_comp_headcount_true', 0)),
            );
        });
    }
}

if (!function_exists('bvmgr_event_command_center_ticket_integrity_store_entry')) {
    function bvmgr_event_command_center_ticket_integrity_store_entry(int $plan_id, int $tec_event_id = 0): array
    {
        return (array) bvmgr_event_command_center_request_cache($plan_id, 'ticket_integrity_store_entry', static function () use ($plan_id, $tec_event_id): array {
            if (!function_exists('bvmgr_ticket_integrity_get_results_store') || !function_exists('bvmgr_ticket_integrity_event_store_key')) {
                return array();
            }

            $tec_event_id = absint($tec_event_id);
            if ($tec_event_id <= 0) {
                $bundle = bvmgr_event_command_center_ticket_summary_meta_bundle($plan_id);
                $tec_event_id = absint($bundle['linked_tec_id'] ?? 0);
            }

            $store = bvmgr_ticket_integrity_get_results_store();
            $events = is_array($store['events'] ?? null) ? (array) $store['events'] : array();
            $event_key = bvmgr_ticket_integrity_event_store_key($plan_id, $tec_event_id);
            $entry = $events[$event_key] ?? array();

            return is_array($entry) ? $entry : array();
        });
    }
}

if (!function_exists('bvmgr_event_command_center_ticket_summary_snapshot')) {
    function bvmgr_event_command_center_ticket_summary_snapshot(int $plan_id): array
    {
        static $cache = array();

        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        if (isset($cache[$plan_id]) && is_array($cache[$plan_id])) {
            if (function_exists('bvmgr_event_plan_perf_log')) {
                bvmgr_event_plan_perf_log('command_center_ticket_summary', $plan_id, array(
                    'phase' => 'run',
                    'cache' => 'hit',
                    'summary_mode' => 'summary_only',
                ));
            }
            return $cache[$plan_id];
        }

        if (function_exists('bvmgr_event_plan_perf_log')) {
            bvmgr_event_plan_perf_log('command_center_ticket_summary', $plan_id, array(
                'phase' => 'run',
                'cache' => 'miss',
                'summary_mode' => 'summary_only',
            ));
        }

        $bundle = bvmgr_event_command_center_ticket_summary_meta_bundle($plan_id);
        $cfg = is_array($bundle['ticketing_config'] ?? null) ? (array) $bundle['ticketing_config'] : array();
        $sync = is_array($bundle['ticketing_sync'] ?? null) ? (array) $bundle['ticketing_sync'] : array();
        $ticket_stats = is_array($bundle['ticket_stats'] ?? null) ? (array) ($bundle['ticket_stats'] ?? array()) : array();
        $ticketing_stats_v2 = is_array($bundle['ticketing_stats_v2'] ?? null) ? (array) ($bundle['ticketing_stats_v2'] ?? array()) : array();
        $linked_tec_id = absint($bundle['linked_tec_id'] ?? 0);
        $linked_tec_status = $linked_tec_id > 0 ? sanitize_key((string) get_post_status($linked_tec_id)) : '';
        $ticket_mode = sanitize_key((string) ($cfg['mode'] ?? ''));
        if ($ticket_mode === '') {
            $ticket_mode = 'read_only';
        }

        $enabled_ticket_count = 0;
        foreach ((array) ($cfg['tickets'] ?? array()) as $ticket_row) {
            if (!is_array($ticket_row) || empty($ticket_row['enabled'])) {
                continue;
            }
            if (trim((string) ($ticket_row['title'] ?? '')) === '') {
                continue;
            }
            $enabled_ticket_count++;
        }

        $enabled_entitlement_count = 0;
        foreach ((array) ($cfg['entitlements'] ?? array()) as $entitlement_row) {
            if (!is_array($entitlement_row) || empty($entitlement_row['enabled'])) {
                continue;
            }
            if (trim((string) ($entitlement_row['label'] ?? '')) === '') {
                continue;
            }
            $enabled_entitlement_count++;
        }

        $mapped_entitlement_product_count = 0;
        foreach ((array) ($sync['map']['entitlements'] ?? array()) as $sync_row) {
            if (!is_array($sync_row)) {
                continue;
            }
            if (absint($sync_row['woo_product_id'] ?? 0) > 0) {
                $mapped_entitlement_product_count++;
            }
        }

        $linked_ticket_product_count = count((array) ($bundle['ticket_product_ids'] ?? array()));
        $effective_ticket_count = ($ticket_mode === 'vms_managed')
            ? ($enabled_ticket_count + $enabled_entitlement_count)
            : ($linked_ticket_product_count + $mapped_entitlement_product_count);

        $sold = 0;
        if (array_key_exists('qty_sold', $ticket_stats) && is_numeric($ticket_stats['qty_sold'])) {
            $sold = max(0, (int) $ticket_stats['qty_sold']);
        } elseif (array_key_exists('qty', $ticket_stats) && is_numeric($ticket_stats['qty'])) {
            $sold = max(0, (int) $ticket_stats['qty']);
        }

        $revenue_cents = 0;
        if (array_key_exists('revenue_cents', $ticket_stats) && is_numeric($ticket_stats['revenue_cents'])) {
            $revenue_cents = max(0, (int) $ticket_stats['revenue_cents']);
        } elseif (array_key_exists('revenue', $ticket_stats) && is_numeric($ticket_stats['revenue'])) {
            $revenue_cents = max(0, (int) round(((float) $ticket_stats['revenue']) * 100));
        }

        $comp_count = max(0, (int) ($bundle['comp_forecast'] ?? 0));
        $true_comp_count = max(0, (int) ($bundle['comp_true'] ?? 0));
        if ($true_comp_count > 0) {
            $comp_count = $true_comp_count;
        }

        $computed_at_gmt = 0;
        foreach (array('computed_at_gmt', 'updated_at_gmt', 'pulled_at_gmt') as $stamp_key) {
            if (array_key_exists($stamp_key, $ticket_stats) && is_numeric($ticket_stats[$stamp_key])) {
                $computed_at_gmt = max($computed_at_gmt, (int) $ticket_stats[$stamp_key]);
            }
            if (array_key_exists($stamp_key, $ticketing_stats_v2) && is_numeric($ticketing_stats_v2[$stamp_key])) {
                $computed_at_gmt = max($computed_at_gmt, (int) $ticketing_stats_v2[$stamp_key]);
            }
        }

        $ticket_source_warnings = array_values(array_filter((array) ($ticketing_stats_v2['warnings'] ?? array())));
        $integrity_entry = bvmgr_event_command_center_ticket_integrity_store_entry($plan_id, $linked_tec_id);
        $integrity_status = sanitize_key((string) ($integrity_entry['status'] ?? ''));
        $issue_summary = trim((string) ($integrity_entry['issue_summary'] ?? ''));
        $integrity_issues = is_array($integrity_entry['issues'] ?? null) ? (array) $integrity_entry['issues'] : array();
        $integrity_issue = sanitize_key((string) ($bundle['integrity_issue'] ?? ''));

        if ($integrity_status === '') {
            $integrity_status = $integrity_issue !== '' ? 'yellow' : 'green';
        }
        if ($issue_summary === '' && $integrity_issue !== '') {
            $issue_summary = bvmgr_event_command_center_clean_text(str_replace(array('_', '-'), ' ', $integrity_issue));
        }
        if ($issue_summary === '' && !empty($ticket_source_warnings)) {
            $issue_summary = bvmgr_event_command_center_clean_text((string) $ticket_source_warnings[0]);
        }

        $low_inventory_flag = false;
        $low_inventory_severity = '';
        foreach ($integrity_issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $issue_kind = sanitize_key((string) ($issue['issue_kind'] ?? $issue['key'] ?? ''));
            if ($issue_kind !== 'low_inventory') {
                continue;
            }
            $low_inventory_flag = true;
            $severity = sanitize_key((string) ($issue['severity'] ?? 'yellow'));
            $low_inventory_severity = $severity !== '' ? $severity : 'yellow';
            if ($low_inventory_severity === 'red') {
                break;
            }
        }

        $status_label = __('Not configured', 'backstage-venue-manager');
        if (in_array($integrity_status, array('red', 'yellow'), true) && function_exists('bvmgr_ticket_integrity_status_label')) {
            $status_label = (string) bvmgr_ticket_integrity_status_label($integrity_status);
        } elseif ($effective_ticket_count > 0) {
            $status_label = __('Summary ready', 'backstage-venue-manager');
        } elseif ($linked_tec_id > 0) {
            $status_label = __('Linked only', 'backstage-venue-manager');
        }

        $stats_age_label = '';
        if ($computed_at_gmt > 0) {
            $stats_age_label = sprintf(
                /* translators: %s: human-readable age since the cached ticket summary refresh. */
                __('Refreshed %s ago', 'backstage-venue-manager'),
                human_time_diff($computed_at_gmt, time())
            );
        }

        $cache[$plan_id] = array(
            'linked_tec_id' => $linked_tec_id,
            'linked_tec_status' => $linked_tec_status,
            'ticket_mode' => $ticket_mode,
            'enabled_ticket_count' => $enabled_ticket_count,
            'enabled_entitlement_count' => $enabled_entitlement_count,
            'effective_ticket_count' => $effective_ticket_count,
            'linked_ticket_product_count' => $linked_ticket_product_count,
            'mapped_entitlement_product_count' => $mapped_entitlement_product_count,
            'sold' => $sold,
            'revenue_cents' => $revenue_cents,
            'comp_count' => $comp_count,
            'total_ticket_count' => $sold + $comp_count,
            'ticket_source' => 'edit_screen_cached_summary',
            'ticket_source_label' => __('Edit-screen cached summary', 'backstage-venue-manager'),
            'ticket_source_warnings' => $ticket_source_warnings,
            'ticketing_sync_status' => sanitize_key((string) ($ticketing_stats_v2['sync_status'] ?? '')),
            'integrity_status' => $integrity_status,
            'issue_summary' => $issue_summary,
            'issues' => $integrity_issues,
            'ticket_snapshots' => array(),
            'capacity' => null,
            'remaining' => null,
            'sell_through' => null,
            'event_timestamp' => 0,
            'low_inventory_flag' => $low_inventory_flag,
            'low_inventory_severity' => $low_inventory_severity,
            'status_label' => $status_label,
            'summary_mode' => 'summary_only',
            'full_detail_deferred' => 1,
            'stats_computed_at_gmt' => $computed_at_gmt,
            'stats_age_label' => $stats_age_label,
            'full_detail_url' => bvmgr_event_command_center_admin_url(array('plan_id' => $plan_id)),
            'integrity_url' => function_exists('bvmgr_admin_ui_page_url') ? bvmgr_admin_ui_page_url('vms-ticket-integrity') : '',
        );

        return $cache[$plan_id];
    }
}

if (!function_exists('bvmgr_event_command_center_get_ticket_snapshot_light')) {
    function bvmgr_event_command_center_get_ticket_snapshot_light(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return array();
        }

        $context = array(
            'module' => 'command_center_hub',
            'section' => 'ticket_module',
            'scope' => 'command_center_ticket',
            'summary_mode' => 'summary_only',
        );

        if (function_exists('bvmgr_event_plan_perf_log')) {
            bvmgr_event_plan_perf_log('command_center_module_hub_ticket', $plan_id, $context + array(
                'phase' => 'start',
            ));
        }
        if (function_exists('bvmgr_event_plan_perf_query_checkpoint')) {
            bvmgr_event_plan_perf_query_checkpoint($plan_id, 'command_center_ticket_start', $context, 'command_center_ticket', false);
        }

        $snapshot = bvmgr_event_command_center_ticket_summary_snapshot($plan_id);

        if (function_exists('bvmgr_event_plan_perf_query_checkpoint')) {
            bvmgr_event_plan_perf_query_checkpoint($plan_id, 'command_center_ticket_summary', $context + array(
                'option_count' => absint(($snapshot['enabled_ticket_count'] ?? 0) + ($snapshot['enabled_entitlement_count'] ?? 0)),
            ), 'command_center_ticket');
        }
        if (function_exists('bvmgr_event_plan_perf_log')) {
            bvmgr_event_plan_perf_log('command_center_ticket_meta', $plan_id, $context + array(
                'phase' => 'run',
                'cache' => 'miss',
            ));
            bvmgr_event_plan_perf_log('command_center_ticket_tec_lookup', $plan_id, $context + array(
                'phase' => 'summary_only',
                'linked_tec_present' => absint($snapshot['linked_tec_id'] ?? 0) > 0 ? 1 : 0,
            ));
            bvmgr_event_plan_perf_log('command_center_ticket_woo_lookup', $plan_id, $context + array(
                'phase' => 'summary_only',
                'linked_ticket_product_count' => absint($snapshot['linked_ticket_product_count'] ?? 0),
                'mapped_entitlement_product_count' => absint($snapshot['mapped_entitlement_product_count'] ?? 0),
            ));
            bvmgr_event_plan_perf_log('command_center_ticket_integrity', $plan_id, $context + array(
                'phase' => 'summary_only',
                'integrity_status' => sanitize_key((string) ($snapshot['integrity_status'] ?? '')),
            ));
            bvmgr_event_plan_perf_log('command_center_ticket_full_details', $plan_id, $context + array(
                'phase' => 'lazy_available',
                'reason' => 'initial_edit_screen',
            ));
            bvmgr_event_plan_perf_log('command_center_module_hub_ticket', $plan_id, $context + array(
                'phase' => 'full_detail_deferred',
                'reason' => 'initial_edit_screen',
            ));
            bvmgr_event_plan_perf_log('command_center_module_hub_ticket', $plan_id, $context + array(
                'phase' => 'summary_only',
                'queries_delta' => 0,
            ));
        }

        return $snapshot;
    }
}

if (!function_exists('bvmgr_event_command_center_get_staffing_snapshot_light')) {
    function bvmgr_event_command_center_get_staffing_snapshot_light(int $plan_id): array
    {
        $rollup = function_exists('bvmgr_staffing_get_rollup') ? bvmgr_staffing_get_rollup($plan_id) : null;
        if (!is_array($rollup)) {
            $rollup = array();
        }

        return array(
            'rollup' => $rollup,
            'roles' => array(),
            'readiness_status' => sanitize_key((string) ($rollup['readiness_status'] ?? 'na')),
            'readiness_label' => function_exists('bvmgr_staffing_dashboard_readiness_label')
                ? (string) bvmgr_staffing_dashboard_readiness_label((string) ($rollup['readiness_status'] ?? 'na'))
                : __('N/A', 'backstage-venue-manager'),
            'headcount_needed_total' => max(0, (int) ($rollup['headcount_needed_total'] ?? 0)),
            'headcount_filled_total' => max(0, (int) ($rollup['headcount_filled_total'] ?? 0)),
            'open_headcount_total' => max(0, (int) ($rollup['open_headcount_total'] ?? 0)),
            'critical_open_headcount' => max(0, (int) ($rollup['critical_open_headcount'] ?? 0)),
            'conflict_count' => max(0, (int) ($rollup['conflict_count'] ?? 0)),
            'missing_summary' => is_array($rollup['missing_summary'] ?? null) ? (array) $rollup['missing_summary'] : array(),
            'conflict_summary' => is_array($rollup['conflict_summary'] ?? null) ? (array) $rollup['conflict_summary'] : array(),
        );
    }
}

if (!function_exists('bvmgr_event_command_center_build_module_hub_payload')) {
    function bvmgr_event_command_center_build_module_hub_payload(int $plan_id): array
    {
        return (array) bvmgr_event_command_center_request_cache($plan_id, 'module_hub_payload', static function () use ($plan_id): array {
            $checkpoint = static function (string $phase) use ($plan_id): void {
                if (function_exists('bvmgr_event_plan_perf_query_checkpoint')) {
                    bvmgr_event_plan_perf_query_checkpoint($plan_id, $phase, array(
                        'module' => 'command_center_hub',
                        'section' => 'module_hub_payload',
                    ), 'command_center_hub');
                }
            };

            $checkpoint('command_center_module_hub_start');
            $header = bvmgr_event_command_center_get_plan_header($plan_id);
            $checkpoint('command_center_module_hub_header');
            $ticket = bvmgr_event_command_center_get_ticket_snapshot_light($plan_id);
            $checkpoint('command_center_module_hub_ticket');
            $financial = bvmgr_event_command_center_get_financial_snapshot($plan_id);
            $checkpoint('command_center_module_hub_financial');
            $lineup = bvmgr_event_command_center_get_lineup_snapshot($plan_id);
            $checkpoint('command_center_module_hub_lineup');
            $staffing = bvmgr_event_command_center_get_staffing_snapshot_light($plan_id);
            $checkpoint('command_center_module_hub_staffing');
            $marketing = bvmgr_event_command_center_get_marketing_snapshot($plan_id, $header);
            $checkpoint('command_center_module_hub_marketing');
            $weather = bvmgr_event_command_center_get_weather_snapshot($plan_id);
            $checkpoint('command_center_module_hub_weather');
            $notes = bvmgr_event_command_center_get_notes_snapshot($plan_id);
            $checkpoint('command_center_module_hub_notes');
            $alerts = bvmgr_event_command_center_build_alerts($plan_id, $header, $ticket, $lineup, $staffing, $marketing, $weather);
            $checkpoint('command_center_module_hub_alerts');
            $health = bvmgr_event_command_center_get_health($alerts);
            $checkpoint('command_center_module_hub_health');

            return array(
                'header' => $header,
                'ticket' => $ticket,
                'financial' => $financial,
                'lineup' => $lineup,
                'staffing' => $staffing,
                'marketing' => $marketing,
                'weather' => $weather,
                'notes' => $notes,
                'alerts' => $alerts,
                'health' => $health,
                'timeline' => array(),
                'actions' => array(),
                'activity' => array(),
            );
        });
    }
}

if (!function_exists('bvmgr_event_command_center_module_hub_card')) {
    function bvmgr_event_command_center_module_hub_card(array $card): string
    {
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') {
            return '';
        }

        $status = trim((string) ($card['status'] ?? __('Not configured', 'backstage-venue-manager')));
        $tone = bvmgr_event_command_center_module_hub_status_tone((string) ($card['tone'] ?? 'muted'));
        $summary = array_values(array_filter(array_map('strval', (array) ($card['summary'] ?? array()))));
        $warning = trim((string) ($card['warning'] ?? ''));
        $action_label = trim((string) ($card['action_label'] ?? __('Manage', 'backstage-venue-manager')));
        $action_url = trim((string) ($card['action_url'] ?? ''));
        $secondary_label = trim((string) ($card['secondary_label'] ?? ''));
        $secondary_url = trim((string) ($card['secondary_url'] ?? ''));

        ob_start();
        echo '<section class="vms-ep-module-card">';
        echo '<div class="vms-ep-module-card__header">';
        echo '<h4>' . esc_html($title) . '</h4>';
        echo wp_kses(bvmgr_event_command_center_render_chip($status, $tone), bvmgr_event_command_center_allowed_markup());
        echo '</div>';

        if (!empty($summary)) {
            echo '<ul class="vms-ep-module-card__summary">';
            foreach (array_slice($summary, 0, 5) as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul>';
        }

        if ($warning !== '') {
            echo '<p class="vms-ep-module-card__warning">' . esc_html($warning) . '</p>';
        }

        echo '<div class="vms-ep-module-card__actions">';
        if ($action_url !== '') {
            echo '<a class="button button-small button-primary" href="' . esc_url($action_url) . '">' . esc_html($action_label) . '</a>';
        }
        if ($secondary_label !== '' && $secondary_url !== '') {
            echo '<a class="button button-small" href="' . esc_url($secondary_url) . '">' . esc_html($secondary_label) . '</a>';
        }
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }
}

if (!function_exists('bvmgr_event_command_center_build_module_hub_cards')) {
    function bvmgr_event_command_center_build_module_hub_cards(int $plan_id, array $payload = array()): array
    {
        if (empty($payload)) {
            $payload = bvmgr_event_command_center_build_module_hub_payload($plan_id);
        }

        $header = (array) ($payload['header'] ?? array());
        $ticket = (array) ($payload['ticket'] ?? array());
        $financial = (array) ($payload['financial'] ?? array());
        $lineup = (array) ($payload['lineup'] ?? array());
        $staffing = (array) ($payload['staffing'] ?? array());
        $marketing = (array) ($payload['marketing'] ?? array());
        $alerts = (array) ($payload['alerts'] ?? array());
        $health = (array) ($payload['health'] ?? array());

        $ticket_tone = 'good';
        if (($ticket['integrity_status'] ?? '') === 'red') {
            $ticket_tone = 'critical';
        } elseif (($ticket['integrity_status'] ?? '') === 'yellow' || !empty($ticket['low_inventory_flag'])) {
            $ticket_tone = 'warning';
        }
        $ticket_mode = sanitize_key((string) ($ticket['ticket_mode'] ?? ''));
        $ticket_configured_count = $ticket_mode === 'vms_managed'
            ? max(0, (int) ($ticket['enabled_ticket_count'] ?? 0))
            : max(0, (int) ($ticket['linked_ticket_product_count'] ?? 0));
        $ticket_add_on_count = $ticket_mode === 'vms_managed'
            ? max(0, (int) ($ticket['enabled_entitlement_count'] ?? 0))
            : max(0, (int) ($ticket['mapped_entitlement_product_count'] ?? 0));
        $ticket_linked_status = sanitize_key((string) ($ticket['linked_tec_status'] ?? ''));
        $ticket_linked_status_label = $ticket_linked_status !== ''
            ? ucfirst(str_replace('_', ' ', $ticket_linked_status))
            : __('Missing', 'backstage-venue-manager');
        $ticket_stats_age_label = trim((string) ($ticket['stats_age_label'] ?? ''));
        if ($ticket_stats_age_label === '') {
            $ticket_stats_age_label = __('Full sales report loads on demand in Command Center.', 'backstage-venue-manager');
        }
        $ticket_warning = trim((string) ($ticket['issue_summary'] ?? ''));
        if ($ticket_warning === '') {
            $ticket_warnings = array_values(array_filter((array) ($ticket['ticket_source_warnings'] ?? array())));
            $ticket_warning = trim((string) ($ticket_warnings[0] ?? ''));
        }
        $ticket_secondary_label = __('Open Full Ticket Report', 'backstage-venue-manager');
        $ticket_secondary_url = (string) ($ticket['full_detail_url'] ?? bvmgr_event_command_center_admin_url(array('plan_id' => $plan_id)));

        $staff_open = max(0, (int) ($staffing['open_headcount_total'] ?? 0));
        $staff_conflicts = max(0, (int) ($staffing['conflict_count'] ?? 0));
        $staff_tone = ($staff_open > 0 || $staff_conflicts > 0) ? 'warning' : 'good';
        if (max(0, (int) ($staffing['critical_open_headcount'] ?? 0)) > 0) {
            $staff_tone = 'critical';
        }

        $lineup_warnings = count((array) ($lineup['warnings'] ?? array()));
        $lineup_tone = empty($lineup['primary']) ? 'critical' : ($lineup_warnings > 0 ? 'warning' : 'good');
        $supporting_count = count((array) ($lineup['supporting'] ?? array()));
        $secondary_count = count((array) ($lineup['secondary'] ?? array()));

        $health_tone = bvmgr_event_command_center_health_tone((string) ($health['status'] ?? 'needs-review'));
        $core_tone = $health_tone === 'critical' ? 'critical' : ((trim((string) ($header['date_raw'] ?? '')) === '' || absint($header['venue_id'] ?? 0) <= 0) ? 'warning' : 'good');
        $core_warning = '';
        foreach ($alerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }
            $title = trim((string) ($alert['title'] ?? ''));
            if (in_array($title, array(__('Venue missing', 'backstage-venue-manager'), __('Event date missing', 'backstage-venue-manager'), __('Needs review before republish', 'backstage-venue-manager')), true)) {
                $core_warning = trim((string) ($alert['detail'] ?? ''));
                break;
            }
        }

        $cards = array(
            array(
                'title' => __('Core Event Details', 'backstage-venue-manager'),
                'status' => (string) ($header['status_label'] ?? __('Draft', 'backstage-venue-manager')),
                'tone' => $core_tone,
                'summary' => array(
                    (string) ($header['date_label'] ?? __('Date not set', 'backstage-venue-manager')) . ' • ' . (string) ($header['time_label'] ?? __('Time not set', 'backstage-venue-manager')),
                    /* translators: %s: venue label for the event. */
                    sprintf(__('Venue: %s', 'backstage-venue-manager'), (string) ($header['venue_label'] ?? __('Unassigned venue', 'backstage-venue-manager'))),
                    /* translators: %s: formatted last-updated label for the event plan. */
                    sprintf(__('Last updated: %s', 'backstage-venue-manager'), (string) ($header['modified_label'] ?? __('Unknown', 'backstage-venue-manager'))),
                ),
                'warning' => $core_warning,
                'action_label' => __('Edit Core Details', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms_event_plan_details'),
            ),
            array(
                'title' => __('Tickets & Add-ons', 'backstage-venue-manager'),
                'status' => (string) ($ticket['status_label'] ?? __('Unknown', 'backstage-venue-manager')),
                'tone' => $ticket_tone,
                'summary' => array(
                    /* translators: %d: number of configured tickets. */
                    sprintf(__('Configured tickets: %d', 'backstage-venue-manager'), $ticket_configured_count),
                    /* translators: %d: number of configured add-ons. */
                    sprintf(__('Configured add-ons: %d', 'backstage-venue-manager'), $ticket_add_on_count),
                    /* translators: %s: linked calendar status label. */
                    sprintf(__('Linked calendar status: %s', 'backstage-venue-manager'), $ticket_linked_status_label),
                    /* translators: 1: paid ticket count, 2: formatted gross sales amount. */
                    sprintf(__('Cached sales: %1$d paid / %2$s', 'backstage-venue-manager'), (int) ($ticket['sold'] ?? 0), bvmgr_event_command_center_money((int) ($ticket['revenue_cents'] ?? 0))),
                    $ticket_stats_age_label,
                ),
                'warning' => $ticket_warning,
                'action_label' => __('Manage Tickets', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms_event_plan_ticketing_v2'),
                'secondary_label' => $ticket_secondary_label,
                'secondary_url' => $ticket_secondary_url,
            ),
            array(
                'title' => __('Lineup & Vendors', 'backstage-venue-manager'),
                'status' => empty($lineup['primary']) ? __('Missing primary', 'backstage-venue-manager') : __('Assigned', 'backstage-venue-manager'),
                'tone' => $lineup_tone,
                'summary' => array(
                    /* translators: %s: primary lineup participant label. */
                    !empty($lineup['primary']) ? sprintf(__('Primary: %s', 'backstage-venue-manager'), (string) (($lineup['primary']['display_name'] ?? $lineup['primary']['vendor_title'] ?? __('Assigned', 'backstage-venue-manager')))) : __('Primary: missing', 'backstage-venue-manager'),
                    /* translators: %d: number of supporting lineup entries. */
                    sprintf(__('Supporting: %d', 'backstage-venue-manager'), $supporting_count),
                    /* translators: %d: number of secondary vendors. */
                    sprintf(__('Secondary vendors: %d', 'backstage-venue-manager'), $secondary_count),
                ),
                /* translators: %d: number of lineup warnings. */
                'warning' => $lineup_warnings > 0 ? sprintf(_n('%d lineup warning needs review.', '%d lineup warnings need review.', $lineup_warnings, 'backstage-venue-manager'), $lineup_warnings) : '',
                'action_label' => __('Edit Lineup', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms-lineup-schedule-section'),
            ),
            array(
                'title' => __('Staffing', 'backstage-venue-manager'),
                'status' => (string) ($staffing['readiness_label'] ?? __('N/A', 'backstage-venue-manager')),
                'tone' => $staff_tone,
                'summary' => array(
                    /* translators: 1: filled staffing count, 2: required staffing count. */
                    sprintf(__('Coverage: %1$d/%2$d filled', 'backstage-venue-manager'), (int) ($staffing['headcount_filled_total'] ?? 0), (int) ($staffing['headcount_needed_total'] ?? 0)),
                    /* translators: %d: number of open staffing roles. */
                    sprintf(__('Open roles: %d', 'backstage-venue-manager'), $staff_open),
                    /* translators: %d: number of staffing conflicts. */
                    sprintf(__('Conflicts: %d', 'backstage-venue-manager'), $staff_conflicts),
                ),
                /* translators: %d: number of staffing slots still open. */
                'warning' => $staff_open > 0 ? sprintf(_n('%d staffing slot remains open.', '%d staffing slots remain open.', $staff_open, 'backstage-venue-manager'), $staff_open) : '',
                'action_label' => __('Manage Staffing', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms-ep-staff-headcount-summary'),
            ),
            array(
                'title' => __('Compensation / Finance', 'backstage-venue-manager'),
                'status' => ((int) ($financial['vendor_cost_cents'] ?? 0) > 0) ? __('Comp loaded', 'backstage-venue-manager') : __('Needs review', 'backstage-venue-manager'),
                'tone' => ((int) ($financial['vendor_cost_cents'] ?? 0) > 0) ? 'good' : 'warning',
                'summary' => array(
                    /* translators: %s: formatted vendor pay amount. */
                    sprintf(__('Vendor pay: %s', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($financial['vendor_cost_cents'] ?? 0))),
                    /* translators: %s: formatted labor amount. */
                    sprintf(__('Labor: %s', 'backstage-venue-manager'), bvmgr_event_command_center_money((int) ($financial['labor_cost_cents'] ?? 0))),
                    /* translators: %s: formatted projected margin amount. */
                    sprintf(__('Projected margin: %s', 'backstage-venue-manager'), bvmgr_event_command_center_money_signed((int) ($financial['margin_cents'] ?? 0))),
                ),
                'warning' => '',
                'action_label' => __('Edit Compensation', 'backstage-venue-manager'),
                'action_url' => bvmgr_event_command_center_edit_fragment_url($plan_id, 'vms-compensation'),
            ),
            array(
                'title' => __('Marketing / Promo', 'backstage-venue-manager'),
                'status' => (string) ($marketing['meta_ads_label'] ?? __('Marketing', 'backstage-venue-manager')),
                'tone' => !empty($marketing['event_page_public']) ? 'good' : 'warning',
                'summary' => array(
                    /* translators: %s: public event page status label. */
                    sprintf(__('Event page: %s', 'backstage-venue-manager'), (string) ($marketing['event_page_label'] ?? __('Unknown', 'backstage-venue-manager'))),
                    /* translators: %s: social-sharing status label. */
                    sprintf(__('Social: %s', 'backstage-venue-manager'), (string) ($marketing['social_label'] ?? __('Unknown', 'backstage-venue-manager'))),
                    /* translators: %s: promo video status label. */
                    sprintf(__('Promo video: %s', 'backstage-venue-manager'), (string) ($marketing['promo_video_label'] ?? __('Unknown', 'backstage-venue-manager'))),
                ),
                'warning' => !empty($marketing['promo_submission_pending']) ? __('A vendor promo clip is waiting for review.', 'backstage-venue-manager') : '',
                'action_label' => __('Open Ads Workspace', 'backstage-venue-manager'),
                'action_url' => (string) ($marketing['meta_ads_builder_url'] ?? ''),
                'secondary_label' => __('Open Social Sharing', 'backstage-venue-manager'),
                'secondary_url' => (string) ($marketing['social_url'] ?? ''),
            ),
        );

        return (array) apply_filters('vms_event_command_center_module_hub_cards', $cards, $plan_id, $payload);
    }
}

if (!function_exists('bvmgr_event_command_center_render_event_plan_module_hub_metabox')) {
    function bvmgr_event_command_center_render_event_plan_module_hub_metabox(WP_Post $post): void
    {
        if (!$post || $post->post_type !== 'vms_event_plan') {
            return;
        }
        $plan_id = (int) $post->ID;
        if ($plan_id <= 0) {
            echo '<p>' . esc_html__('Save this Event Plan once to enable the module hub.', 'backstage-venue-manager') . '</p>';
            return;
        }

        $payload = bvmgr_event_command_center_build_module_hub_payload($plan_id);
        $header = (array) ($payload['header'] ?? array());
        $health = (array) ($payload['health'] ?? array());
        $cards = bvmgr_event_command_center_build_module_hub_cards($plan_id, $payload);

        echo '<div class="vms-ep-module-hub">';
        echo '<div class="vms-ep-module-hub__intro">';
        echo '<div>';
        echo '<h3>' . esc_html__('Event Plan Module Hub', 'backstage-venue-manager') . '</h3>';
        echo '<p>' . esc_html__('At-a-glance module summaries stay visible here while each heavy workspace can be managed without turning every Event Plan update into a full rebuild.', 'backstage-venue-manager') . '</p>';
        echo '</div>';
        echo '<div class="vms-ep-module-hub__intro-actions">';
        /* translators: %s: current command center health label. */
        echo wp_kses(bvmgr_event_command_center_render_chip(sprintf(__('Health: %s', 'backstage-venue-manager'), (string) ($health['label'] ?? __('Needs Review', 'backstage-venue-manager'))), bvmgr_event_command_center_health_tone((string) ($health['status'] ?? 'needs-review'))), bvmgr_event_command_center_allowed_markup());
        echo '<a class="button" href="' . esc_url(bvmgr_event_command_center_admin_url(array('plan_id' => $plan_id))) . '">' . esc_html__('Open Full Command Center', 'backstage-venue-manager') . '</a>';
        if (!empty($header['public_event_url'])) {
            echo '<a class="button" href="' . esc_url((string) $header['public_event_url']) . '" target="_blank" rel="noopener">' . esc_html__('View Public Event', 'backstage-venue-manager') . '</a>';
        }
        echo '</div>';
        echo '</div>';
        if (function_exists('bvmgr_event_plan_save_profiler_render_hub_summary')) {
            bvmgr_event_plan_save_profiler_render_hub_summary($plan_id);
        }
        echo '<div class="vms-ep-module-hub__grid">';
        foreach ($cards as $card) {
            echo wp_kses(bvmgr_event_command_center_module_hub_card((array) $card), bvmgr_event_command_center_allowed_markup());
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('bvmgr_event_command_center_register_event_plan_module_hub_metabox')) {
    function bvmgr_event_command_center_register_event_plan_module_hub_metabox(): void
    {
        add_meta_box(
            'vms_event_plan_module_hub',
            __('Event Module Hub', 'backstage-venue-manager'),
            'bvmgr_event_command_center_render_event_plan_module_hub_metabox',
            'vms_event_plan',
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes_vms_event_plan', 'bvmgr_event_command_center_register_event_plan_module_hub_metabox', 5);


if (!function_exists('bvmgr_render_event_command_center_page')) {
    function bvmgr_render_event_command_center_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $plan_id = bvmgr_event_command_center_resolve_plan_id();
        $header = $plan_id > 0 ? bvmgr_event_command_center_get_plan_header($plan_id) : array();
        $actions = '';
        if ($plan_id > 0) {
            $actions .= '<a class="button button-primary" href="' . esc_url(bvmgr_event_command_center_admin_url(array('plan_id' => $plan_id))) . '">' . esc_html__('Refresh', 'backstage-venue-manager') . '</a>';
            if (!empty($header['edit_url'])) {
                $actions .= '<a class="button" href="' . esc_url((string) $header['edit_url']) . '">' . esc_html__('Open Event Plan', 'backstage-venue-manager') . '</a>';
            }
        }

        bvmgr_admin_ui_render_shell(
            array(
                'title' => __('Event Command Center', 'backstage-venue-manager'),
                'subtitle' => __('A single-glance operations view for one event plan, pulling lineup, staffing, ticketing, marketing, and review risk into one place.', 'backstage-venue-manager'),
                'actions_html' => $actions,
                'shell_id' => 'vms-event-command-center-shell',
                'content_class' => 'vms-event-command-center-shell',
            ),
            static function () use ($plan_id): void {
                bvmgr_event_command_center_render_picker($plan_id);
                if ($plan_id > 0) {
                    bvmgr_event_command_center_render_page_content($plan_id);
                }
            }
        );
    }
}

if (!function_exists('bvmgr_event_command_center_register_admin_page')) {
    function bvmgr_event_command_center_register_admin_page(): void
    {
        add_submenu_page(
            'vms-dashboard',
            __('Event Command Center', 'backstage-venue-manager'),
            __('Event Command Center', 'backstage-venue-manager'),
            'manage_options',
            bvmgr_event_command_center_page_slug(),
            'bvmgr_render_event_command_center_page'
        );
    }
}
add_action('admin_menu', 'bvmgr_event_command_center_register_admin_page', 35);

if (!function_exists('bvmgr_event_command_center_add_shell_page')) {
    function bvmgr_event_command_center_add_shell_page(array $shell_pages): array
    {
        $shell_pages[] = bvmgr_event_command_center_page_slug();
        return array_values(array_unique(array_filter($shell_pages)));
    }
}
add_filter('vms_admin_ui_shell_pages', 'bvmgr_event_command_center_add_shell_page');

if (!function_exists('bvmgr_event_command_center_enqueue_assets')) {
    function bvmgr_event_command_center_enqueue_assets(): void
    {
        $page = sanitize_key(bvmgr_event_command_center_query_arg('page'));
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_event_plan_editor = $screen
            && in_array((string) ($screen->base ?? ''), array('post', 'post-new'), true)
            && (string) ($screen->post_type ?? '') === 'vms_event_plan';
        if ($page !== bvmgr_event_command_center_page_slug() && !$is_event_plan_editor) {
            return;
        }

        wp_enqueue_style(
            'vms-event-command-center',
            BVMGR_PLUGIN_URL . 'assets/css/vms-event-command-center.css',
            array('vms-admin-ui'),
            function_exists('bvmgr_asset_version') ? bvmgr_asset_version() : (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '')
        );
    }
}
add_action('admin_enqueue_scripts', 'bvmgr_event_command_center_enqueue_assets', 50);

if (!function_exists('bvmgr_event_command_center_row_action')) {
    function bvmgr_event_command_center_row_action(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== 'vms_event_plan' || !current_user_can('manage_options')) {
            return $actions;
        }

        $actions['vms_command_center'] = '<a href="' . esc_url(bvmgr_event_command_center_admin_url(array('plan_id' => (int) $post->ID))) . '">' . esc_html__('Command Center', 'backstage-venue-manager') . '</a>';
        return $actions;
    }
}
add_filter('post_row_actions', 'bvmgr_event_command_center_row_action', 12, 2);

if (!function_exists('bvmgr_event_command_center_submitbox_link')) {
    function bvmgr_event_command_center_submitbox_link(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || (string) ($screen->post_type ?? '') !== 'vms_event_plan') {
            return;
        }

        $post_id = absint(bvmgr_event_command_center_query_arg('post'));
        if ($post_id <= 0) {
            return;
        }

        echo '<div class="misc-pub-section misc-pub-vms-command-center">';
        echo '<a class="button button-small" href="' . esc_url(bvmgr_event_command_center_admin_url(array('plan_id' => $post_id))) . '">' . esc_html__('Open Command Center', 'backstage-venue-manager') . '</a>';
        echo '<span class="description vms-cc-submitbox-note">' . esc_html__('Open the event-level dashboard view for this show.', 'backstage-venue-manager') . '</span>';
        echo '</div>';
    }
}
add_action('post_submitbox_misc_actions', 'bvmgr_event_command_center_submitbox_link');
