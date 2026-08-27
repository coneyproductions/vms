<?php
/**
 * Public TEC event details + Google-readable Event schema.
 *
 * Goal: restore the useful event facts Google and first-time guests need without
 * bringing back the default TEC details block styling that was hidden for UX.
 */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', 'vms_event_details_enqueue_assets', 35);
add_action('wp_head', 'vms_event_details_print_json_ld', 30);
add_action('tribe_events_single_event_after_the_meta', 'vms_event_details_render_after_tec_meta', 20);
add_shortcode('vms_plan_your_visit', 'vms_event_details_shortcode');
add_filter('tribe_json_ld_event_object', 'vms_event_details_filter_tec_event_schema', 99, 3);
add_filter('tribe_json_ld_markup', 'vms_event_details_filter_tec_json_ld_markup', 99);

if (!function_exists('vms_event_details_sidebar_rendered')) {
    function vms_event_details_sidebar_rendered(int $event_id): bool
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return false;
        }

        $rendered = isset($GLOBALS['bvmgr_event_details_sidebar_rendered']) && is_array($GLOBALS['bvmgr_event_details_sidebar_rendered'])
            ? $GLOBALS['bvmgr_event_details_sidebar_rendered']
            : array();

        return !empty($rendered[$event_id]);
    }
}

if (!function_exists('vms_event_details_mark_sidebar_rendered')) {
    function vms_event_details_mark_sidebar_rendered(int $event_id): void
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return;
        }

        if (!isset($GLOBALS['bvmgr_event_details_sidebar_rendered']) || !is_array($GLOBALS['bvmgr_event_details_sidebar_rendered'])) {
            $GLOBALS['bvmgr_event_details_sidebar_rendered'] = array();
        }

        $GLOBALS['bvmgr_event_details_sidebar_rendered'][$event_id] = true;
    }
}

if (!function_exists('vms_event_details_sidebar_manual_rendered')) {
    function vms_event_details_sidebar_manual_rendered(int $event_id): bool
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return false;
        }

        $rendered = isset($GLOBALS['bvmgr_event_details_sidebar_manual_rendered']) && is_array($GLOBALS['bvmgr_event_details_sidebar_manual_rendered'])
            ? $GLOBALS['bvmgr_event_details_sidebar_manual_rendered']
            : array();

        return !empty($rendered[$event_id]);
    }
}

if (!function_exists('vms_event_details_mark_sidebar_manual_rendered')) {
    function vms_event_details_mark_sidebar_manual_rendered(int $event_id): void
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return;
        }

        if (!isset($GLOBALS['bvmgr_event_details_sidebar_manual_rendered']) || !is_array($GLOBALS['bvmgr_event_details_sidebar_manual_rendered'])) {
            $GLOBALS['bvmgr_event_details_sidebar_manual_rendered'] = array();
        }

        $GLOBALS['bvmgr_event_details_sidebar_manual_rendered'][$event_id] = true;
    }
}

if (!function_exists('vms_event_details_enqueue_assets')) {
    function vms_event_details_enqueue_assets(): void
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $asset_path = defined('BVMGR_PLUGIN_PATH') ? BVMGR_PLUGIN_PATH . 'assets/css/vms-event-details.css' : '';
        $asset_url = defined('BVMGR_PLUGIN_URL') ? BVMGR_PLUGIN_URL . 'assets/css/vms-event-details.css' : '';
        if ($asset_path === '' || $asset_url === '' || !is_readable($asset_path)) {
            return;
        }

        $ver = (string) (defined('BVMGR_VERSION') ? BVMGR_VERSION : '');
        $file_ver = @filemtime($asset_path);
        if ($file_ver) {
            $ver = (string) $file_ver;
        }

        wp_enqueue_style('vms-event-details', $asset_url, array('vms-ui'), $ver);
    }
}

if (!function_exists('vms_event_details_get_published_event_id')) {
    function vms_event_details_get_published_event_id(int $event_id): int
    {
        $event_id = absint($event_id);
        if ($event_id <= 0) {
            return 0;
        }

        $event_post = get_post($event_id);
        if (!$event_post instanceof WP_Post) {
            return 0;
        }

        if ($event_post->post_type !== 'tribe_events' || $event_post->post_status !== 'publish') {
            return 0;
        }

        return (int) $event_post->ID;
    }
}

if (!function_exists('vms_event_details_shortcode')) {
    function vms_event_details_shortcode($atts = array()): string
    {
        $raw_atts = (array) $atts;
        $a = shortcode_atts(array(
            'event_id' => '0',
            'id'       => '0',
            'event'    => '0',
            'heading'  => '',
            'layout'   => 'sidebar',
        ), $raw_atts, 'vms_plan_your_visit');

        $has_explicit_event_id = array_key_exists('event_id', $raw_atts) || array_key_exists('id', $raw_atts) || array_key_exists('event', $raw_atts);
        $event_id = 0;

        if ($has_explicit_event_id) {
            $event_id = vms_event_details_get_published_event_id((int) ($a['event_id'] ?? 0));
            if ($event_id <= 0) {
                $event_id = vms_event_details_get_published_event_id((int) ($a['id'] ?? 0));
            }
            if ($event_id <= 0) {
                $event_id = vms_event_details_get_published_event_id((int) ($a['event'] ?? 0));
            }
        } else {
            if (!function_exists('is_singular') || !is_singular('tribe_events') || !function_exists('get_queried_object_id')) {
                return '';
            }

            $event_id = vms_event_details_get_published_event_id((int) get_queried_object_id());
        }

        if ($event_id <= 0) {
            return '';
        }

        $heading = trim(wp_strip_all_tags((string) ($a['heading'] ?? '')));
        $layout = sanitize_key((string) ($a['layout'] ?? 'sidebar'));
        $queried_event_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        $is_current_event_sidebar = (
            $layout === 'sidebar'
            && function_exists('is_singular')
            && is_singular('tribe_events')
            && $event_id === $queried_event_id
        );
        $is_target_sidebar_context = (
            $is_current_event_sidebar
            && function_exists('vms_public_event_sidebar_is_rendering_target')
            && vms_public_event_sidebar_is_rendering_target($event_id)
        );

        if ($is_target_sidebar_context && vms_event_details_sidebar_rendered($event_id)) {
            return '';
        }

        $markup = vms_event_details_render_card($event_id, $is_target_sidebar_context, $heading, $layout);
        if ($markup !== '' && $is_current_event_sidebar) {
            vms_event_details_mark_sidebar_manual_rendered($event_id);
            if ($is_target_sidebar_context) {
                vms_event_details_mark_sidebar_rendered($event_id);
            }
        }

        return $markup;
    }
}

if (!function_exists('vms_event_details_render_after_tec_meta')) {
    function vms_event_details_render_after_tec_meta(): void
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $event_id = (int) get_queried_object_id();
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return;
        }

        // Keep visible Event Details placement opt-in. The preferred production
        // placement is the sidebar shortcode/widget; existing TEC details
        // suppression remains owned by the site's current snippet/plugin so this
        // module does not double-handle TEC template output.
        if (!(bool) apply_filters('vms_event_details_auto_render_card', false, $event_id)) {
            return;
        }

        $layout = sanitize_key((string) apply_filters('vms_event_details_auto_render_layout', 'inline', $event_id));
        echo vms_event_details_render_card($event_id, true, '', $layout); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if (!function_exists('vms_event_details_render_card')) {
    function vms_event_details_render_card(int $event_id, bool $guard_once = true, string $heading_override = '', string $layout = 'inline'): string
    {
        $event_id = absint($event_id);
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return '';
        }

        if ($guard_once) {
            static $rendered = array();
            if (!empty($rendered[$event_id])) {
                return '';
            }
            $rendered[$event_id] = true;
        }

        $ctx = vms_event_details_context($event_id);
        if (empty($ctx)) {
            return '';
        }

        $heading = $heading_override !== '' ? $heading_override : (string) apply_filters('vms_event_details_card_heading', __('Event Details', 'backstage-venue-manager'), $event_id, $ctx);
        $event_status = sanitize_key((string) ($ctx['status'] ?? 'scheduled'));
        $is_cancelled = ($event_status === 'cancelled');

        $layout = sanitize_key($layout);
        if (!in_array($layout, array('inline', 'sidebar'), true)) {
            $layout = 'inline';
        }

        $classes = array('vms-event-details-card', 'vms-event-details-card--' . $layout);
        if ($is_cancelled) {
            $classes[] = 'vms-event-details-card--cancelled';
        }

        $date_label = trim((string) ($ctx['date_label'] ?? ''));
        $time_label = trim((string) ($ctx['time_label'] ?? ''));
        $gates_label = trim((string) ($ctx['gates_label'] ?? ''));
        $venue_name = trim((string) ($ctx['venue_name'] ?? ''));
        $address_lines = isset($ctx['address_lines']) && is_array($ctx['address_lines']) ? $ctx['address_lines'] : array();
        $ticket_label = trim((string) ($ctx['ticket_label'] ?? ''));
        $directions_url = trim((string) ($ctx['directions_url'] ?? ''));
        $calendar_url = trim((string) ($ctx['calendar_url'] ?? ''));
        $questions_url = trim((string) ($ctx['questions_url'] ?? ''));


        ob_start();
        ?>
        <section class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $classes))); ?>" aria-label="<?php echo esc_attr($heading); ?>" data-vms-event-details-card="1">
            <h2 class="vms-event-details-card__title"><?php echo esc_html($heading); ?></h2>

            <dl class="vms-event-details-card__list">
                <?php if ($date_label !== '' || $time_label !== '' || $gates_label !== '') : ?>
                    <div class="vms-event-details-card__row vms-event-details-card__row--time">
                        <dt class="vms-event-details-card__label"><?php esc_html_e('Date & Time', 'backstage-venue-manager'); ?></dt>
                        <dd class="vms-event-details-card__value">
                            <?php if ($date_label !== '') : ?><span class="vms-event-details-card__strong"><?php echo esc_html($date_label); ?></span><?php endif; ?>
                            <?php if ($time_label !== '') : ?><span><?php echo esc_html($time_label); ?></span><?php endif; ?>
                            <?php /* translators: %s: gate opening time label. */ ?>
                            <?php if ($gates_label !== '') : ?><span><?php echo esc_html(sprintf(__('Gates open %s', 'backstage-venue-manager'), $gates_label)); ?></span><?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if ($venue_name !== '' || !empty($address_lines)) : ?>
                    <div class="vms-event-details-card__row vms-event-details-card__row--location">
                        <dt class="vms-event-details-card__label"><?php esc_html_e('Location', 'backstage-venue-manager'); ?></dt>
                        <dd class="vms-event-details-card__value">
                            <?php if ($venue_name !== '') : ?><span class="vms-event-details-card__strong"><?php echo esc_html($venue_name); ?></span><?php endif; ?>
                            <?php foreach ($address_lines as $line) : ?>
                                <?php $line = trim((string) $line); if ($line === '') { continue; } ?>
                                <span><?php echo esc_html($line); ?></span>
                            <?php endforeach; ?>
                            <?php if ($directions_url !== '') : ?>
                                <a class="vms-event-details-card__inline-link" href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get directions', 'backstage-venue-manager'); ?></a>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if ($ticket_label !== '') : ?>
                    <div class="vms-event-details-card__row vms-event-details-card__row--tickets">
                        <dt class="vms-event-details-card__label"><?php esc_html_e('Tickets', 'backstage-venue-manager'); ?></dt>
                        <dd class="vms-event-details-card__value"><span class="vms-event-details-card__strong"><?php echo esc_html($ticket_label); ?></span></dd>
                    </div>
                <?php endif; ?>

                <?php if ($questions_url !== '') : ?>
                    <div class="vms-event-details-card__row vms-event-details-card__row--questions">
                        <dt class="vms-event-details-card__label"><?php esc_html_e('Questions', 'backstage-venue-manager'); ?></dt>
                        <dd class="vms-event-details-card__value"><a class="vms-event-details-card__inline-link" href="<?php echo esc_url($questions_url); ?>"><?php esc_html_e('View common questions', 'backstage-venue-manager'); ?></a></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('vms_event_details_context')) {
    function vms_event_details_context(int $event_id): array
    {
        $event_id = absint($event_id);
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return array();
        }

        $plan_id = function_exists('bvmgr_get_event_plan_for_tec_event') ? (int) bvmgr_get_event_plan_for_tec_event($event_id) : 0;
        $start = vms_event_details_event_datetime($event_id, 'start', $plan_id);
        $end = vms_event_details_event_datetime($event_id, 'end', $plan_id);
        if (!$end && $start instanceof DateTimeInterface) {
            $end = DateTimeImmutable::createFromInterface($start)->modify('+2 hours');
        }

        $venue = vms_event_details_venue_context($event_id, $plan_id);
        $ticket = vms_event_details_ticket_context($event_id, $plan_id);
        $event_url = (string) get_permalink($event_id);
        $title = vms_event_details_normalize_schema_name((string) get_the_title($event_id));
        $status = function_exists('bvmgr_tec_is_cancelled_event') && bvmgr_tec_is_cancelled_event($event_id) ? 'cancelled' : 'scheduled';

        $date_label = '';
        $time_label = '';
        $gates_label = '';
        if ($start instanceof DateTimeInterface) {
            $date_label = function_exists('wp_date') ? wp_date('l, F j', $start->getTimestamp(), $start->getTimezone()) : $start->format('l, F j');
            $start_time = function_exists('wp_date') ? wp_date('g:i A', $start->getTimestamp(), $start->getTimezone()) : $start->format('g:i A');
            $end_time = $end instanceof DateTimeInterface ? (function_exists('wp_date') ? wp_date('g:i A', $end->getTimestamp(), $end->getTimezone()) : $end->format('g:i A')) : '';
            $time_label = $start_time;
            if ($end_time !== '' && $end_time !== $start_time) {
                $time_label .= '–' . $end_time;
            }

            $offset_minutes = (int) apply_filters('vms_event_details_gate_open_offset_minutes', 60, $event_id, $plan_id);
            if ($offset_minutes > 0) {
                $gate_time = DateTimeImmutable::createFromInterface($start)->modify('-' . $offset_minutes . ' minutes');
                $gates_label = function_exists('wp_date') ? wp_date('g:i A', $gate_time->getTimestamp(), $gate_time->getTimezone()) : $gate_time->format('g:i A');
            }
        }

        $calendar_url = '';
        if (function_exists('tribe_get_single_ical_link')) {
            $calendar_url = (string) tribe_get_single_ical_link($event_id);
        }
        if ($calendar_url === '') {
            $calendar_url = add_query_arg('ical', '1', $event_url);
        }

        $address_query_parts = array_filter(array(
            (string) ($venue['name'] ?? ''),
            (string) ($venue['address'] ?? ''),
            trim((string) ($venue['city'] ?? '') . ' ' . (string) ($venue['state'] ?? '') . ' ' . (string) ($venue['zip'] ?? '')),
        ));
        $directions_url = '';
        if (!empty($address_query_parts)) {
            $directions_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $address_query_parts));
        }

        return array(
            'event_id' => $event_id,
            'plan_id' => $plan_id,
            'title' => $title,
            'description' => vms_event_details_plain_description($event_id),
            'url' => $event_url,
            'status' => $status,
            'start' => $start,
            'end' => $end,
            'date_label' => $date_label,
            'time_label' => $time_label,
            'gates_label' => $gates_label,
            'venue_name' => (string) ($venue['name'] ?? ''),
            'address' => (string) ($venue['address'] ?? ''),
            'address_2' => (string) ($venue['address_2'] ?? ''),
            'city' => (string) ($venue['city'] ?? ''),
            'state' => (string) ($venue['state'] ?? ''),
            'zip' => (string) ($venue['zip'] ?? ''),
            'country' => (string) ($venue['country'] ?? 'US'),
            'address_lines' => vms_event_details_address_lines($venue),
            'directions_url' => $directions_url,
            'calendar_url' => $calendar_url,
            'questions_url' => (string) apply_filters('vms_event_details_questions_url', home_url('/questions/'), $event_id, $plan_id),
            'tickets_url' => $event_url,
            'ticket_label' => (string) ($ticket['label'] ?? ''),
            'min_ticket_price' => isset($ticket['min_price']) ? (float) $ticket['min_price'] : null,
            'free_ticket_labels' => isset($ticket['free_labels']) && is_array($ticket['free_labels']) ? $ticket['free_labels'] : array(),
            'performer_name' => vms_event_details_performer_name($plan_id),
            'image_url' => vms_event_details_image_url($event_id),
        );
    }
}

if (!function_exists('vms_event_details_event_datetime')) {
    function vms_event_details_event_datetime(int $event_id, string $which = 'start', int $plan_id = 0): ?DateTimeImmutable
    {
        $which = $which === 'end' ? 'end' : 'start';
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone((string) get_option('timezone_string', 'UTC'));

        $meta_keys = $which === 'start'
            ? array('_EventStartDate', function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'start_datetime') : '_vms_event_plan_start_datetime')
            : array('_EventEndDate', function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'end_datetime') : '_vms_event_plan_end_datetime');

        foreach ($meta_keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $source_post_id = ($key === '_EventStartDate' || $key === '_EventEndDate') ? $event_id : $plan_id;
            if ($source_post_id <= 0) {
                continue;
            }

            $raw = trim((string) get_post_meta($source_post_id, $key, true));
            if ($raw === '') {
                continue;
            }

            try {
                return new DateTimeImmutable($raw, $timezone);
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }
}

if (!function_exists('vms_event_details_venue_context')) {
    function vms_event_details_venue_context(int $event_id, int $plan_id = 0): array
    {
        $venue = array(
            'name' => '',
            'address' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => 'US',
        );

        if (function_exists('tribe_get_venue')) {
            $venue['name'] = trim(wp_strip_all_tags((string) tribe_get_venue($event_id)));
        }
        foreach (array(
            'address' => 'tribe_get_address',
            'city' => 'tribe_get_city',
            'state' => 'tribe_get_region',
            'zip' => 'tribe_get_zip',
            'country' => 'tribe_get_country',
        ) as $field => $fn) {
            if (function_exists($fn)) {
                $value = trim(wp_strip_all_tags((string) call_user_func($fn, $event_id)));
                if ($value !== '') {
                    $venue[$field] = $value;
                }
            }
        }

        if (($venue['name'] === '' || $venue['address'] === '') && $plan_id > 0) {
            $venue_id_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'venue_id') ?: '_vms_venue_id') : '_vms_venue_id';
            $venue_id = absint(get_post_meta($plan_id, $venue_id_key, true));
            if ($venue_id > 0) {
                if ($venue['name'] === '') {
                    $venue['name'] = trim(wp_strip_all_tags((string) get_the_title($venue_id)));
                }
                foreach (array(
                    'address' => 'address',
                    'address_2' => 'address_2',
                    'city' => 'city',
                    'state' => 'state',
                    'zip' => 'zip',
                    'country' => 'country',
                ) as $field => $meta_field) {
                    if ($venue[$field] !== '') {
                        continue;
                    }
                    $key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('venue', $meta_field) ?: '') : '';
                    if ($key === '') {
                        $fallback = array(
                            'address' => '_vms_address',
                            'address_2' => '_vms_address_2',
                            'city' => '_vms_city',
                            'state' => '_vms_state',
                            'zip' => '_vms_zip',
                            'country' => '_vms_country',
                        );
                        $key = (string) ($fallback[$field] ?? '');
                    }
                    if ($key !== '') {
                        $value = trim(wp_strip_all_tags((string) get_post_meta($venue_id, $key, true)));
                        if ($value !== '') {
                            $venue[$field] = $value;
                        }
                    }
                }
            }
        }

        $fallback = (array) apply_filters('vms_event_details_default_venue', array(
            'name' => 'Serenade Range',
            'address' => '12290 FM 344 E',
            'address_2' => '',
            'city' => 'Whitehouse',
            'state' => 'TX',
            'zip' => '75791',
            'country' => 'US',
        ), $event_id, $plan_id);

        foreach ($fallback as $field => $value) {
            if (array_key_exists($field, $venue) && trim((string) $venue[$field]) === '') {
                $venue[$field] = trim(wp_strip_all_tags((string) $value));
            }
        }

        return $venue;
    }
}

if (!function_exists('vms_event_details_address_lines')) {
    function vms_event_details_address_lines(array $venue): array
    {
        $line1 = trim((string) ($venue['address'] ?? ''));
        $line2 = trim((string) ($venue['address_2'] ?? ''));
        $city_state_zip = trim(implode(' ', array_filter(array(
            trim((string) ($venue['city'] ?? '')) . (trim((string) ($venue['city'] ?? '')) !== '' && trim((string) ($venue['state'] ?? '')) !== '' ? ',' : ''),
            trim((string) ($venue['state'] ?? '')),
            trim((string) ($venue['zip'] ?? '')),
        ))));

        return array_values(array_filter(array($line1, $line2, $city_state_zip)));
    }
}

if (!function_exists('vms_event_details_decode_schema_text')) {
    function vms_event_details_decode_schema_text(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('vms_event_details_normalize_schema_whitespace')) {
    function vms_event_details_normalize_schema_whitespace(string $value): string
    {
        $value = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $value);
        $value = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        return trim((string) $value);
    }
}

if (!function_exists('vms_event_details_normalize_schema_name')) {
    function vms_event_details_normalize_schema_name(string $value): string
    {
        $value = vms_event_details_decode_schema_text($value);
        $value = wp_strip_all_tags($value);
        return vms_event_details_normalize_schema_whitespace($value);
    }
}

if (!function_exists('vms_event_details_normalize_schema_description_text')) {
    function vms_event_details_normalize_schema_description_text(string $value): string
    {
        $value = vms_event_details_decode_schema_text($value);
        $value = strip_shortcodes($value);
        $value = wp_strip_all_tags($value);
        return vms_event_details_normalize_schema_whitespace($value);
    }
}

if (!function_exists('vms_event_details_parse_schema_price')) {
    function vms_event_details_parse_schema_price($value): ?float
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = vms_event_details_decode_schema_text((string) $value);
        $text = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $text);
        $text = vms_event_details_normalize_schema_whitespace((string) $text);
        if ($text === '') {
            return null;
        }

        preg_match_all('/(?:^|[^\pL\pN])(?:[$£€¥]\s*)?((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)(?=$|[^\pL\pN])/u', $text, $matches);

        $remainder = preg_replace('/(?:[$£€¥]\s*)?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?/u', ' ', $text);
        $remainder = preg_replace('/[^\p{L}\s]+/u', ' ', (string) $remainder);
        $remainder = vms_event_details_normalize_schema_whitespace((string) $remainder);

        $allowed_words = array(
            'admission',
            'at',
            'cover',
            'dollar',
            'dollars',
            'entry',
            'event',
            'free',
            'from',
            'online',
            'show',
            'starting',
            'ticket',
            'tickets',
            'usd',
        );

        $words = $remainder === '' ? array() : preg_split('/\s+/u', strtolower($remainder));
        $has_unexpected_words = false;
        foreach ($words as $word) {
            if ($word === '' || in_array($word, $allowed_words, true)) {
                continue;
            }

            $has_unexpected_words = true;
            break;
        }

        $prices = array();
        foreach ($matches[1] ?? array() as $match) {
            $candidate = str_replace(',', '', (string) $match);
            if ($candidate === '' || !is_numeric($candidate)) {
                continue;
            }

            $prices[] = (float) $candidate;
        }

        $positive_prices = array_values(array_filter($prices, static function (float $price): bool {
            return $price > 0;
        }));
        if (!$has_unexpected_words && !empty($positive_prices)) {
            return min($positive_prices);
        }

        if (!$has_unexpected_words && !empty($prices)) {
            return 0.0;
        }

        if (
            !$has_unexpected_words
            && empty($prices)
            && preg_match('/\bfree\b/i', $text)
        ) {
            return 0.0;
        }

        return null;
    }
}

if (!function_exists('vms_event_details_ticket_context')) {
    function vms_event_details_ticket_context(int $event_id, int $plan_id = 0): array
    {
        if (function_exists('bvmgr_tec_is_cancelled_event') && bvmgr_tec_is_cancelled_event($event_id)) {
            return array('label' => __('Ticket sales are closed for this cancelled event.', 'backstage-venue-manager'), 'min_price' => null, 'free_labels' => array());
        }

        $prices = array();
        $free_labels = array();

        if ($plan_id > 0 && function_exists('vms_ticketing_v2_get_config')) {
            $cfg = vms_ticketing_v2_get_config($plan_id);
            $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
            foreach ($tickets as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (array_key_exists('enabled', $row) && empty($row['enabled'])) {
                    continue;
                }
                $visibility = sanitize_key((string) ($row['visibility_mode'] ?? 'public'));
                $title = trim(wp_strip_all_tags((string) ($row['title'] ?? $row['label'] ?? '')));
                $price = isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : 0.0;
                if (function_exists('vms_ticketing_v2_get_ticket_effective_price')) {
                    $price = (float) vms_ticketing_v2_get_ticket_effective_price($row);
                }
                if ($price > 0 && $visibility === 'public') {
                    $prices[] = $price;
                } elseif ($price <= 0 && $title !== '') {
                    $free_labels[] = $title;
                }
            }
        }

        if (empty($prices) && function_exists('tribe_get_cost')) {
            $cost = trim(wp_strip_all_tags((string) tribe_get_cost($event_id, true)));
            if ($cost !== '') {
                $fallback_price = vms_event_details_parse_schema_price($cost);
                if ($fallback_price !== null) {
                    $prices[] = $fallback_price;
                }
            }
        }

        $min_price = !empty($prices) ? min($prices) : null;
        $free_labels = array_values(array_unique(array_filter(array_map(static function ($label): string {
            return trim(wp_strip_all_tags((string) $label));
        }, $free_labels))));

        if ($min_price !== null && $min_price > 0) {
            /* translators: %s: human-readable value used in this message. */
            $label = sprintf(__('From %s online', 'backstage-venue-manager'), function_exists('wc_price') ? wp_strip_all_tags(wc_price($min_price)) : '$' . number_format_i18n($min_price, 2));
        } else {
            $label = __('Tickets are available on this page.', 'backstage-venue-manager');
        }

        $label = (string) apply_filters('vms_event_details_ticket_label', $label, $event_id, $plan_id, $min_price, $free_labels);

        return array(
            'label' => $label,
            'min_price' => $min_price,
            'free_labels' => $free_labels,
        );
    }
}

if (!function_exists('vms_event_details_performer_name')) {
    function vms_event_details_performer_name(int $plan_id): string
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0) {
            return '';
        }
        $band_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
        $vendor_id = absint(get_post_meta($plan_id, $band_key, true));
        if ($vendor_id <= 0) {
            return '';
        }
        return trim(wp_strip_all_tags((string) get_the_title($vendor_id)));
    }
}

if (!function_exists('vms_event_details_image_url')) {
    function vms_event_details_image_url(int $event_id): string
    {
        $image = '';
        if (has_post_thumbnail($event_id)) {
            $src = wp_get_attachment_image_src((int) get_post_thumbnail_id($event_id), 'full');
            if (is_array($src) && !empty($src[0])) {
                $image = (string) $src[0];
            }
        }
        return $image;
    }
}

if (!function_exists('vms_event_details_plain_description')) {
    function vms_event_details_plain_description(int $event_id): string
    {
        $excerpt = trim((string) get_the_excerpt($event_id));
        if ($excerpt === '') {
            $post = get_post($event_id);
            $excerpt = $post instanceof WP_Post ? (string) $post->post_content : '';
        }
        $excerpt = vms_event_details_normalize_schema_description_text($excerpt);
        if (function_exists('mb_substr')) {
            return mb_substr($excerpt, 0, 500);
        }
        return substr($excerpt, 0, 500);
    }
}

if (!function_exists('vms_event_details_encode_fallback_json_ld')) {
    function vms_event_details_encode_fallback_json_ld(array $schema): string
    {
        $json = wp_json_encode(
            $schema,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '';
    }
}

if (!function_exists('vms_event_details_print_json_ld')) {
    function vms_event_details_print_json_ld(): void
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $event_id = (int) get_queried_object_id();
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return;
        }

        /**
         * VMS now prefers enriching TEC's own Event JSON-LD instead of printing a
         * duplicate full Event object. Keep the full VMS schema available as a
         * fallback for sites/tests that explicitly opt into it.
         */
        $default_print = !vms_event_details_tec_schema_filters_available();
        if (!(bool) apply_filters('vms_event_details_print_json_ld', $default_print, $event_id)) {
            return;
        }

        $schema = vms_event_details_schema($event_id);
        if (empty($schema)) {
            return;
        }

        $json = vms_event_details_encode_fallback_json_ld($schema);
        if ($json === '') {
            return;
        }

        echo "\n" . '<script type="application/ld+json" class="vms-event-json-ld" data-vms-schema-mode="fallback">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}


if (!function_exists('vms_event_details_tec_schema_filters_available')) {
    function vms_event_details_tec_schema_filters_available(): bool
    {
        return has_filter('tribe_json_ld_event_object') !== false || has_filter('tribe_json_ld_event_data') !== false || has_filter('tribe_json_ld_markup') !== false;
    }
}

if (!function_exists('vms_event_details_filter_tec_event_schema')) {
    /**
     * Clean up TEC's native Event JSON-LD rather than emitting a duplicate Event.
     *
     * TEC/Event Tickets already owns the base schema and tickets. VMS only trims
     * the venue-specific rough edges that hurt Google clarity for SR events:
     * qualified/free ticket offers, early offer expiry, organizer type, and a
     * placeholder performer value.
     *
     * @param object|array $data TEC JSON-LD Event object.
     * @param array        $args TEC JSON-LD args.
     * @param WP_Post      $post Event post.
     * @return object|array
     */
    function vms_event_details_filter_tec_event_schema($data, $args = array(), $post = null)
    {
        $event_id = 0;
        if ($post instanceof WP_Post) {
            $event_id = (int) $post->ID;
        } elseif (is_object($post) && isset($post->ID)) {
            $event_id = (int) $post->ID;
        } elseif (function_exists('get_queried_object_id')) {
            $event_id = (int) get_queried_object_id();
        }

        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return $data;
        }

        $ctx = vms_event_details_context($event_id);
        if (empty($ctx)) {
            return $data;
        }

        $is_object = is_object($data);
        $event = $is_object ? (array) $data : (is_array($data) ? $data : array());
        if (empty($event)) {
            return $data;
        }

        $url = (string) ($ctx['url'] ?? get_permalink($event_id));
        if ($url !== '') {
            $event['@id'] = trailingslashit($url) . '#event';
            $event['url'] = $url;
        }

        $name = vms_event_details_normalize_schema_name((string) ($event['name'] ?? ''));
        if ($name === '') {
            $name = vms_event_details_normalize_schema_name((string) ($ctx['title'] ?? get_the_title($event_id)));
        }
        if ($name !== '') {
            $event['name'] = $name;
        }

        $description = vms_event_details_normalize_schema_description_text((string) ($event['description'] ?? ''));
        if ($description === '') {
            $description = vms_event_details_normalize_schema_description_text((string) ($ctx['description'] ?? ''));
        }
        if ($description !== '') {
            $event['description'] = $description;
        } elseif (isset($event['description'])) {
            unset($event['description']);
        }

        $event = vms_event_details_clean_tec_location_schema($event, $ctx);
        $event = vms_event_details_clean_tec_organizer_schema($event);
        $event = vms_event_details_clean_tec_performer_schema($event, $ctx);
        $event = vms_event_details_clean_tec_offers_schema($event, $ctx, $event_id);

        $event = (array) apply_filters('vms_event_details_tec_event_schema', $event, $event_id, $ctx, $data, $args, $post);
        return $is_object ? (object) $event : $event;
    }
}

if (!function_exists('vms_event_details_clean_tec_location_schema')) {
    function vms_event_details_clean_tec_location_schema(array $event, array $ctx): array
    {
        $location = isset($event['location']) ? $event['location'] : array();
        $location = is_object($location) ? (array) $location : (is_array($location) ? $location : array());
        if (empty($location)) {
            $location = array('@type' => 'Place');
        }

        $location['@type'] = 'Place';
        if (!empty($ctx['venue_name'])) {
            $location['name'] = (string) $ctx['venue_name'];
        }

        $address = isset($location['address']) ? $location['address'] : array();
        $address = is_object($address) ? (array) $address : (is_array($address) ? $address : array());
        $address = array_merge(array(
            '@type' => 'PostalAddress',
            'streetAddress' => '',
            'addressLocality' => '',
            'addressRegion' => '',
            'postalCode' => '',
            'addressCountry' => '',
        ), $address);

        $street = trim((string) ($ctx['address'] ?? ''));
        $address_2 = trim((string) ($ctx['address_2'] ?? ''));
        if ($street !== '' || $address_2 !== '') {
            $address['streetAddress'] = trim($street . ($address_2 !== '' ? ' ' . $address_2 : ''));
        }
        foreach (array(
            'city' => 'addressLocality',
            'state' => 'addressRegion',
            'zip' => 'postalCode',
            'country' => 'addressCountry',
        ) as $ctx_key => $schema_key) {
            $value = trim((string) ($ctx[$ctx_key] ?? ''));
            if ($value !== '') {
                $address[$schema_key] = $value;
            }
        }

        $location['address'] = array_filter($address, static function ($value): bool {
            return $value !== '' && $value !== null;
        });
        $event['location'] = $location;
        return $event;
    }
}

if (!function_exists('vms_event_details_clean_tec_organizer_schema')) {
    function vms_event_details_clean_tec_organizer_schema(array $event): array
    {
        $organizer = isset($event['organizer']) ? $event['organizer'] : array();
        $organizer = is_object($organizer) ? (array) $organizer : (is_array($organizer) ? $organizer : array());
        $organizer['@type'] = 'Organization';
        $organizer['name'] = trim((string) ($organizer['name'] ?? '')) !== '' ? (string) $organizer['name'] : 'Serenade Range';
        if (empty($organizer['url'])) {
            $organizer['url'] = home_url('/');
        }
        $event['organizer'] = $organizer;
        return $event;
    }
}

if (!function_exists('vms_event_details_clean_tec_performer_schema')) {
    function vms_event_details_clean_tec_performer_schema(array $event, array $ctx): array
    {
        $performer = trim((string) ($ctx['performer_name'] ?? ''));
        if ($performer !== '') {
            $event['performer'] = array('@type' => 'MusicGroup', 'name' => $performer);
            return $event;
        }

        if (isset($event['performer']) && is_string($event['performer']) && strtolower(trim($event['performer'])) === 'organization') {
            unset($event['performer']);
        }
        return $event;
    }
}

if (!function_exists('vms_event_details_clean_tec_offers_schema')) {
    function vms_event_details_clean_tec_offers_schema(array $event, array $ctx, int $event_id): array
    {
        $is_cancelled = sanitize_key((string) ($ctx['status'] ?? 'scheduled')) === 'cancelled';
        $price = isset($ctx['min_ticket_price']) && is_numeric($ctx['min_ticket_price']) ? (float) $ctx['min_ticket_price'] : null;

        if ($price === null) {
            $price = vms_event_details_min_paid_price_from_schema_offers($event['offers'] ?? null);
        }

        if ($price === null || $price < 0) {
            unset($event['offers']);
            return $event;
        }

        $start = $ctx['start'] instanceof DateTimeInterface ? $ctx['start'] : null;
        $end = $ctx['end'] instanceof DateTimeInterface ? $ctx['end'] : null;
        if (!$end && $start) {
            $end = DateTimeImmutable::createFromInterface($start)->modify('+2 hours');
        }

        $offer = array(
            '@type' => 'Offer',
            'url' => (string) ($ctx['tickets_url'] ?? $ctx['url'] ?? get_permalink($event_id)),
            'price' => number_format($price, 2, '.', ''),
            'priceCurrency' => 'USD',
            'availability' => $is_cancelled ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
        );

        $existing_dates = vms_event_details_offer_date_bounds($event['offers'] ?? null);
        if (!empty($existing_dates['validFrom'])) {
            $offer['validFrom'] = $existing_dates['validFrom'];
        } elseif ($start) {
            $offer['validFrom'] = DateTimeImmutable::createFromInterface($start)->modify('-6 months')->format(DATE_ATOM);
        }
        if ($end) {
            $offer['validThrough'] = $end->format(DATE_ATOM);
        } elseif (!empty($existing_dates['validThrough'])) {
            $offer['validThrough'] = $existing_dates['validThrough'];
        }

        $event['offers'] = array($offer);
        return $event;
    }
}

if (!function_exists('vms_event_details_min_paid_price_from_schema_offers')) {
    function vms_event_details_min_paid_price_from_schema_offers($offers): ?float
    {
        if (empty($offers)) {
            return null;
        }
        if (is_object($offers)) {
            $offers = array($offers);
        }
        if (!is_array($offers)) {
            return null;
        }

        $prices = array();
        foreach ($offers as $offer) {
            $offer = is_object($offer) ? (array) $offer : (is_array($offer) ? $offer : array());
            $price = vms_event_details_parse_schema_price($offer['price'] ?? null);
            if ($price === null) {
                continue;
            }

            $prices[] = $price;
        }
        return !empty($prices) ? min($prices) : null;
    }
}

if (!function_exists('vms_event_details_offer_date_bounds')) {
    function vms_event_details_offer_date_bounds($offers): array
    {
        $bounds = array('validFrom' => '', 'validThrough' => '');
        if (empty($offers)) {
            return $bounds;
        }
        if (is_object($offers)) {
            $offers = array($offers);
        }
        if (!is_array($offers)) {
            return $bounds;
        }

        foreach ($offers as $offer) {
            $offer = is_object($offer) ? (array) $offer : (is_array($offer) ? $offer : array());
            $price = isset($offer['price']) && is_numeric($offer['price']) ? (float) $offer['price'] : null;
            if ($price !== null && $price <= 0) {
                continue;
            }
            foreach (array('validFrom', 'validThrough') as $key) {
                $value = trim((string) ($offer[$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                if ($bounds[$key] === '') {
                    $bounds[$key] = $value;
                    continue;
                }
                try {
                    $current = new DateTimeImmutable($bounds[$key]);
                    $candidate = new DateTimeImmutable($value);
                    if ($key === 'validFrom' && $candidate < $current) {
                        $bounds[$key] = $value;
                    }
                    if ($key === 'validThrough' && $candidate > $current) {
                        $bounds[$key] = $value;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        return $bounds;
    }
}

if (!function_exists('vms_event_details_filter_tec_json_ld_markup')) {
    function vms_event_details_filter_tec_json_ld_markup(string $html): string
    {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events') || trim($html) === '') {
            return $html;
        }
        if (strpos($html, 'VMS Google schema: TEC enriched') !== false) {
            return $html;
        }
        return "\n<!-- VMS Google schema: TEC enriched -->\n" . $html;
    }
}

if (!function_exists('vms_event_details_schema')) {
    function vms_event_details_schema(int $event_id): array
    {
        $ctx = vms_event_details_context($event_id);
        if (empty($ctx) || !($ctx['start'] instanceof DateTimeInterface)) {
            return array();
        }

        $start = $ctx['start'];
        $end = $ctx['end'] instanceof DateTimeInterface ? $ctx['end'] : DateTimeImmutable::createFromInterface($start)->modify('+2 hours');
        $is_cancelled = sanitize_key((string) ($ctx['status'] ?? 'scheduled')) === 'cancelled';

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => trailingslashit((string) ($ctx['url'] ?? get_permalink($event_id))) . '#event',
            'name' => vms_event_details_normalize_schema_name((string) ($ctx['title'] ?? get_the_title($event_id))),
            'url' => (string) ($ctx['url'] ?? get_permalink($event_id)),
            'startDate' => $start->format(DATE_ATOM),
            'endDate' => $end->format(DATE_ATOM),
            'eventStatus' => $is_cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => array(
                '@type' => 'Place',
                'name' => (string) ($ctx['venue_name'] ?? 'Serenade Range'),
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => trim((string) ($ctx['address'] ?? '') . (trim((string) ($ctx['address_2'] ?? '')) !== '' ? ' ' . trim((string) ($ctx['address_2'] ?? '')) : '')),
                    'addressLocality' => (string) ($ctx['city'] ?? ''),
                    'addressRegion' => (string) ($ctx['state'] ?? ''),
                    'postalCode' => (string) ($ctx['zip'] ?? ''),
                    'addressCountry' => (string) ($ctx['country'] ?? 'US'),
                ),
            ),
            'organizer' => array(
                '@type' => 'Organization',
                'name' => 'Serenade Range',
                'url' => home_url('/'),
            ),
        );

        $description = vms_event_details_normalize_schema_description_text((string) ($ctx['description'] ?? ''));
        if ($description !== '') {
            $schema['description'] = $description;
        }

        $image = trim((string) ($ctx['image_url'] ?? ''));
        if ($image !== '') {
            $schema['image'] = array($image);
        }

        $performer = trim((string) ($ctx['performer_name'] ?? ''));
        if ($performer !== '') {
            $schema['performer'] = array('@type' => 'MusicGroup', 'name' => $performer);
        }

        $price = isset($ctx['min_ticket_price']) && is_numeric($ctx['min_ticket_price']) ? (float) $ctx['min_ticket_price'] : null;
        if ($price !== null && $price >= 0) {
            $schema['offers'] = array(
                '@type' => 'Offer',
                'url' => (string) ($ctx['tickets_url'] ?? $ctx['url'] ?? get_permalink($event_id)),
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => 'USD',
                'availability' => $is_cancelled ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                'validFrom' => $start->modify('-6 months')->format(DATE_ATOM),
            );
        }

        return (array) apply_filters('vms_event_details_schema', $schema, $event_id, $ctx);
    }
}
