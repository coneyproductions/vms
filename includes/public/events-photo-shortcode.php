<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_events_photo_parse_id_list')) {
    /**
     * @return array<int,int>
     */
    function vms_events_photo_parse_id_list($raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return array();
            }
            $parts = preg_split('/\s*,\s*/', $raw) ?: array();
        }

        $ids = array_map('absint', $parts);
        $ids = array_values(array_unique(array_filter($ids, static function ($id): bool {
            return $id > 0;
        })));

        return $ids;
    }
}


if (!function_exists('vms_events_photo_calendar_url')) {
    function vms_events_photo_calendar_url(array $venue_ids = array()): string
    {
        $url = '';

        $page = get_page_by_path('events-calendar');
        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
        }

        if ($url === '' && function_exists('bvmgr_required_public_pages')) {
            $required = (array) bvmgr_required_public_pages();
            $slug = isset($required['public_calendar']['slug']) ? (string) $required['public_calendar']['slug'] : 'events-calendar';
            $page = get_page_by_path($slug);
            if ($page instanceof WP_Post) {
                $url = get_permalink($page);
            }
        }

        if ($url === '') {
            return '';
        }

        $venue_ids = array_values(array_filter(array_map('absint', $venue_ids)));
        if (count($venue_ids) === 1) {
            $url = add_query_arg(array('venue_id' => (int) $venue_ids[0]), $url);
        }

        return $url;
    }
}

if (!function_exists('vms_events_photo_overlay_context')) {
    /**
     * @return array<string,mixed>
     */
    function vms_events_photo_overlay_context(int $plan_id, string $plan_status): array
    {
        $plan_id = absint($plan_id);
        $plan_status = sanitize_key($plan_status);
        $is_cancelled = ($plan_status === 'cancelled');
        $rescheduled = array();

        if ($is_cancelled && $plan_id > 0 && function_exists('bvmgr_event_plan_get_public_reschedule_destination')) {
            $rescheduled = (array) bvmgr_event_plan_get_public_reschedule_destination($plan_id);
        }

        $is_rescheduled = $is_cancelled && !empty($rescheduled['url']);
        $overlay_label = $is_cancelled
            ? ($is_rescheduled ? __('Rescheduled', 'backstage-venue-manager') : __('Cancelled', 'backstage-venue-manager'))
            : '';

        return array(
            'is_cancelled' => $is_cancelled,
            'is_rescheduled' => $is_rescheduled,
            'overlay_label' => $overlay_label,
            'overlay_state_class' => $overlay_label !== ''
                ? ($is_rescheduled ? ' vms-cancelled-thumb--rescheduled' : ' vms-cancelled-thumb--cancelled')
                : '',
            'overlay_label_class' => $is_rescheduled ? ' vms-cancelled-thumb__label--rescheduled' : '',
            'replacement' => $rescheduled,
        );
    }
}

if (!function_exists('vms_events_photo_title_for_event')) {
    function vms_events_photo_title_for_event(array $event): string
    {
        $title = trim((string) ($event['title'] ?? ''));
        $plan_id = absint($event['event_plan_id'] ?? 0);

        if ($plan_id > 0 && function_exists('bvmgr_event_plan_get_public_event_payload')) {
            $payload = (array) bvmgr_event_plan_get_public_event_payload($plan_id);
            $payload_title = trim((string) ($payload['title'] ?? ''));
            if ($payload_title !== '') {
                $title = $payload_title;
            }
        }

        if ($title === '') {
            $title = __('Event', 'backstage-venue-manager');
        }

        return $title;
    }
}

if (!function_exists('vms_events_photo_format_time_label')) {
    function vms_events_photo_format_time_label(array $event): string
    {
        $start_label = function_exists('vms_public_calendar_time_label')
            ? vms_public_calendar_time_label((string) ($event['start_local'] ?? ''))
            : '';
        $end_label = function_exists('vms_public_calendar_time_label')
            ? vms_public_calendar_time_label((string) ($event['end_local'] ?? ''))
            : '';

        if ($start_label !== '' && $end_label !== '') {
            return $start_label . ' - ' . $end_label;
        }

        return $start_label;
    }
}

if (!function_exists('vms_events_photo_cta_context')) {
    /**
     * @param array<string,mixed> $event
     * @param array<string,mixed> $overlay
     * @return array<string,string>
     */
    function vms_events_photo_cta_context(array $event, array $overlay): array
    {
        $event_url = trim((string) ($event['public_url'] ?? ''));
        $label = __('Get Tickets', 'backstage-venue-manager');
        $url = $event_url;

        $replacement = isset($overlay['replacement']) && is_array($overlay['replacement']) ? $overlay['replacement'] : array();
        $replacement_url = trim((string) ($replacement['url'] ?? ''));

        if (!empty($overlay['is_rescheduled']) && $replacement_url !== '') {
            $label = __('View New Date', 'backstage-venue-manager');
            $url = $replacement_url;
        } elseif (!empty($overlay['is_cancelled'])) {
            $label = __('View Details', 'backstage-venue-manager');
        }

        return array(
            'label' => $label,
            'url' => $url,
        );
    }
}

if (!function_exists('vms_events_photo_register_shortcodes')) {
    function vms_events_photo_register_shortcodes(): void
    {
        add_shortcode('vms_events_photo', 'vms_events_photo_shortcode');
        add_shortcode('vms_events_photo_grid', 'vms_events_photo_shortcode');
    }
    add_action('init', 'vms_events_photo_register_shortcodes', 12);
}

if (!function_exists('vms_events_photo_shortcode')) {
    function vms_events_photo_shortcode(array $atts = array()): string
    {
        if (!function_exists('bvmgr_get_calendar_events')) {
            return '';
        }

        $atts = shortcode_atts(array(
            'limit' => '4',
            'columns' => '3',
            'venue_id' => '',
            'venue_ids' => '',
            'include_cancelled' => '0',
            'include_past' => '0',
            'days_ahead' => '365',
            'start_date' => '',
            'end_date' => '',
            'show_time' => '0',
            'show_venue' => '0',
            'show_excerpt' => '0',
            'more_link' => '0',
            'more_label' => __('View Full Concert Calendar', 'backstage-venue-manager'),
            'more_url' => '',
            'class' => '',
            'empty_message' => __('No upcoming events found.', 'backstage-venue-manager'),
        ), $atts, 'vms_events_photo');

        $limit = max(1, absint($atts['limit']));
        $columns = absint($atts['columns']);
        if (!in_array($columns, array(1, 2, 3, 4), true)) {
            $columns = 3;
        }

        $include_cancelled = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['include_cancelled'], true)
            : !empty($atts['include_cancelled']);
        $include_past = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['include_past'], false)
            : !empty($atts['include_past']);
        $show_time = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['show_time'], false)
            : !empty($atts['show_time']);
        $show_venue = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['show_venue'], false)
            : !empty($atts['show_venue']);
        $show_excerpt = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['show_excerpt'], false)
            : !empty($atts['show_excerpt']);
        $show_more_link = function_exists('vms_calendar_boolish')
            ? vms_calendar_boolish($atts['more_link'], false)
            : !empty($atts['more_link']);

        $timezone = function_exists('bvmgr_get_timezone') ? bvmgr_get_timezone() : wp_timezone();
        $today = (string) wp_date('Y-m-d', time(), $timezone);

        $start_date = trim((string) $atts['start_date']);
        if (!function_exists('vms_calendar_is_valid_ymd') || !vms_calendar_is_valid_ymd($start_date)) {
            if ($include_past) {
                $start_date = (string) wp_date('Y-m-d', strtotime('-30 days', time()), $timezone);
            } else {
                $start_date = $today;
            }
        }

        $end_date = trim((string) $atts['end_date']);
        if (!function_exists('vms_calendar_is_valid_ymd') || !vms_calendar_is_valid_ymd($end_date)) {
            $days_ahead = max(1, absint($atts['days_ahead']));
            $end_date = (string) wp_date('Y-m-d', strtotime('+' . $days_ahead . ' days', time()), $timezone);
        }

        $venue_ids = vms_events_photo_parse_id_list($atts['venue_ids']);
        if (empty($venue_ids)) {
            $venue_ids = vms_events_photo_parse_id_list($atts['venue_id']);
        }

        $statuses = array('published');
        if ($include_cancelled) {
            $statuses[] = 'cancelled';
        }

        $query_args = array(
            'context' => 'public',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'include_past' => $include_past,
            'include_statuses' => $statuses,
            'include_open_close_shading' => false,
        );
        if (!empty($venue_ids)) {
            $query_args['venue_ids'] = $venue_ids;
        }

        $events = (array) bvmgr_get_calendar_events($query_args);
        if (empty($events)) {
            return '<div class="vms-events-photo vms-events-photo--empty"><p class="vms-events-photo__empty">' . esc_html((string) $atts['empty_message']) . '</p></div>';
        }

        $items = array();
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event_url = trim((string) ($event['public_url'] ?? ''));
            if ($event_url === '') {
                continue;
            }

            $items[] = $event;
            if (count($items) >= $limit) {
                break;
            }
        }

        if (empty($items)) {
            return '<div class="vms-events-photo vms-events-photo--empty"><p class="vms-events-photo__empty">' . esc_html((string) $atts['empty_message']) . '</p></div>';
        }

        $wrapper_classes = array(
            'vms-events-photo',
            'vms-events-photo-grid',
            'vms-events-photo-grid--cols-' . $columns,
        );
        $extra_class = trim((string) $atts['class']);
        if ($extra_class !== '') {
            $extra_parts = preg_split('/\s+/', $extra_class) ?: array();
            foreach ($extra_parts as $extra_part) {
                $extra_part = sanitize_html_class((string) $extra_part);
                if ($extra_part !== '') {
                    $wrapper_classes[] = $extra_part;
                }
            }
        }

        $more_url = trim((string) $atts['more_url']);
        if ($show_more_link && $more_url === '') {
            $more_url = vms_events_photo_calendar_url($venue_ids);
        }
        $more_label = trim((string) $atts['more_label']);
        if ($more_label === '') {
            $more_label = __('View Full Concert Calendar', 'backstage-venue-manager');
        }

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';
        foreach ($items as $event) {
            $plan_id = absint($event['event_plan_id'] ?? 0);
            $plan_status = sanitize_key((string) ($event['plan_status'] ?? ''));
            $overlay = vms_events_photo_overlay_context($plan_id, $plan_status);
            $event_url = trim((string) ($event['public_url'] ?? ''));
            $image_url = trim((string) ($event['image_url'] ?? ''));
            $title = vms_events_photo_title_for_event($event);
            $venue_name = trim((string) ($event['venue_name'] ?? ''));
            $excerpt = trim((string) ($event['excerpt'] ?? ''));
            $date_key = trim((string) ($event['date_key'] ?? ''));
            $month_label = '';
            $day_label = '';
            if ($date_key !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_key)) {
                $timestamp = strtotime($date_key . ' 12:00:00');
                if ($timestamp !== false) {
                    $month_label = strtoupper((string) wp_date('M', $timestamp, $timezone));
                    $day_label = (string) wp_date('j', $timestamp, $timezone);
                }
            }
            $time_label = $show_time ? vms_events_photo_format_time_label($event) : '';
            $replacement = isset($overlay['replacement']) && is_array($overlay['replacement']) ? $overlay['replacement'] : array();
            $replacement_date = trim((string) ($replacement['date_label'] ?? $replacement['date_raw'] ?? ''));
            $cta = vms_events_photo_cta_context($event, $overlay);

            $card_classes = array('vms-events-photo-card');
            if (!empty($overlay['is_cancelled'])) {
                $card_classes[] = 'is-cancelled';
            }
            if (!empty($overlay['is_rescheduled'])) {
                $card_classes[] = 'is-rescheduled';
            }

            echo '<article class="' . esc_attr(implode(' ', $card_classes)) . '">';
            echo '<div class="vms-events-photo-card__media-wrap">';
            if ($image_url !== '') {
                $media_html = '<a class="vms-events-photo-card__media" href="' . esc_url($event_url) . '"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" loading="lazy"></a>';
                if (!empty($overlay['overlay_label'])) {
                    echo '<div class="vms-cancelled-thumb' . esc_attr((string) ($overlay['overlay_state_class'] ?? '')) . '">' . $media_html . '<div class="vms-cancelled-thumb__label' . esc_attr((string) ($overlay['overlay_label_class'] ?? '')) . '">' . esc_html((string) $overlay['overlay_label']) . '</div></div>';
                } else {
                    echo $media_html;
                }
            } else {
                echo '<a class="vms-events-photo-card__media vms-events-photo-card__media--empty" href="' . esc_url($event_url) . '"><span>' . esc_html($title) . '</span></a>';
            }
            echo '</div>';

            echo '<div class="vms-events-photo-card__body">';
            echo '<h3 class="vms-events-photo-card__title"><a href="' . esc_url($event_url) . '">' . esc_html($title) . '</a></h3>';
            if ($month_label !== '' || $day_label !== '') {
                echo '<div class="vms-events-photo-card__date-row">';
                echo '<div class="vms-events-photo-card__date-badge">';
                if ($month_label !== '') {
                    echo '<span class="vms-events-photo-card__month">' . esc_html($month_label) . '</span>';
                }
                if ($day_label !== '') {
                    echo '<span class="vms-events-photo-card__day">' . esc_html($day_label) . '</span>';
                }
                echo '</div>';
                echo '</div>';
            }
            if ($time_label !== '' || ($show_venue && $venue_name !== '')) {
                echo '<div class="vms-events-photo-card__meta">';
                if ($time_label !== '') {
                    echo '<div class="vms-events-photo-card__time">' . esc_html($time_label) . '</div>';
                }
                if ($show_venue && $venue_name !== '') {
                    echo '<div class="vms-events-photo-card__venue">' . esc_html($venue_name) . '</div>';
                }
                echo '</div>';
            }
            if (!empty($cta['url'])) {
                echo '<a class="vms-events-photo-card__cta" href="' . esc_url((string) $cta['url']) . '">' . esc_html((string) ($cta['label'] ?? __('Get Tickets', 'backstage-venue-manager'))) . '</a>';
            }

            if (!empty($overlay['is_rescheduled']) && $replacement_date !== '') {
                /* translators: %s: human-readable value used in this message. */
                echo '<div class="vms-events-photo-card__status-note">' . esc_html(sprintf(__('Rescheduled to %s', 'backstage-venue-manager'), $replacement_date)) . '</div>';
            } elseif (!empty($overlay['is_cancelled'])) {
                echo '<div class="vms-events-photo-card__status-note">' . esc_html__('Cancelled', 'backstage-venue-manager') . '</div>';
            }

            if ($show_excerpt && $excerpt !== '') {
                echo '<div class="vms-events-photo-card__excerpt">' . esc_html(wp_trim_words($excerpt, 18, '…')) . '</div>';
            }
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        if ($show_more_link && $more_url !== '') {
            echo '<div class="vms-events-photo__footer">';
            echo '<a class="vms-events-photo__more-link" href="' . esc_url($more_url) . '">' . esc_html($more_label) . '</a>';
            echo '</div>';
        }

        return (string) ob_get_clean();
    }
}
