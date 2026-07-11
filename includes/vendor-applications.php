<?php

/**
 * Vendor Applications (Procedural)
 * - CPT: vms_vendor_app
 * - Shortcode: [vms_vendor_apply]
 * - Admin UI: list + pending bubble + approve/reject
 */

if (!defined('ABSPATH')) exit;

/**
 * Parent menu slug used by VMS (your existing top-level menu).
 * If this ever changes, update this constant in ONE place.
 */
if (!defined('VMS_ADMIN_PARENT_SLUG')) {
    define('VMS_ADMIN_PARENT_SLUG', 'vms-season-board');
}

/**
 * Post type slug for vendor applications.
 */
if (!defined('VMS_VENDOR_APP_CPT')) {
    define('VMS_VENDOR_APP_CPT', 'vms_vendor_app');
}

if (!defined('VMS_VENDOR_APP_CPT_LEGACY')) {
    // Back-compat: older admin menu + bookmarks referenced this slug.
    define('VMS_VENDOR_APP_CPT_LEGACY', 'vms_vendor_application');
}

/**
 * Vendor CPT slug (must match your vendors system).
 */
if (!defined('VMS_VENDOR_CPT')) {
    define('VMS_VENDOR_CPT', 'vms_vendor');
}

/**
 * Supported vendor application CPT slugs (canonical + legacy).
 * Returns only slugs that are non-empty.
 */
function vms_vendor_app_cpt_slugs(): array
{
    $slugs = array();
    if (defined('VMS_VENDOR_APP_CPT') && VMS_VENDOR_APP_CPT) {
        $slugs[] = VMS_VENDOR_APP_CPT;
    }
    if (defined('VMS_VENDOR_APP_CPT_LEGACY') && VMS_VENDOR_APP_CPT_LEGACY && VMS_VENDOR_APP_CPT_LEGACY !== VMS_VENDOR_APP_CPT) {
        $slugs[] = VMS_VENDOR_APP_CPT_LEGACY;
    }
    return $slugs;
}

if (!function_exists('vms_vendor_app_meta_key')) {
    function vms_vendor_app_meta_key(string $field): string
    {
        $mapped = function_exists('vms_meta_key')
            ? (string) vms_meta_key('vendor_application', $field)
            : '';
        if ($mapped !== '') {
            return $mapped;
        }

        $fallbacks = array(
            'submitted_user_id' => '_vms_app_submitted_user_id',
            'confirmation_state' => '_vms_app_confirmation_state',
            'email_confirmed_at' => '_vms_app_email_confirmed_at',
            'review_ready_at' => '_vms_app_review_ready_at',
            'confirmation_last_sent_at' => '_vms_app_confirmation_last_sent_at',
            'confirmation_send_count' => '_vms_app_confirmation_send_count',
            'confirmation_send_window_started_at' => '_vms_app_confirmation_send_window_started_at',
            'confirmation_source' => '_vms_app_confirmation_source',
            'public_lookup_key' => '_vms_app_public_lookup_key',
            'review_ready_notified_at' => '_vms_app_review_ready_notified_at',
        );

        return (string) ($fallbacks[$field] ?? '');
    }
}

if (!function_exists('vms_vendor_app_get_submitting_user_id')) {
    function vms_vendor_app_get_submitting_user_id(int $app_id): int
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return 0;
        }

        $key = vms_vendor_app_meta_key('submitted_user_id');
        if ($key === '') {
            return 0;
        }

        return absint(get_post_meta($app_id, $key, true));
    }
}

if (!function_exists('vms_vendor_app_set_submitting_user_id')) {
    function vms_vendor_app_set_submitting_user_id(int $app_id, int $user_id): void
    {
        $app_id = (int) $app_id;
        $user_id = (int) $user_id;
        if ($app_id <= 0 || $user_id <= 0) {
            return;
        }

        $key = vms_vendor_app_meta_key('submitted_user_id');
        if ($key === '') {
            return;
        }

        update_post_meta($app_id, $key, $user_id);
    }
}

if (!function_exists('vms_vendor_app_get_application_page_url')) {
    function vms_vendor_app_get_application_page_url(array $query_args = array()): string
    {
        $slug = 'vendor-application';
        if (function_exists('vms_required_public_pages')) {
            $pages = (array) vms_required_public_pages();
            $slug = sanitize_title((string) ($pages['vendor_application']['slug'] ?? $slug));
            if ($slug === '') {
                $slug = 'vendor-application';
            }
        }

        $url = '';
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
        }
        if (!is_string($url) || $url === '') {
            $url = home_url('/' . trim($slug, '/') . '/');
        }

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return (string) $url;
    }
}

if (!function_exists('vms_vendor_app_get_portal_page_url')) {
    function vms_vendor_app_get_portal_page_url(int $vendor_id = 0, array $query_args = array()): string
    {
        $vendor_id = (int) $vendor_id;

        if (function_exists('vms_vendor_booking_onboarding_portal_url')) {
            $url = (string) vms_vendor_booking_onboarding_portal_url($vendor_id);
            if (!empty($query_args)) {
                $url = add_query_arg($query_args, $url);
            }
            return $url;
        }

        $default_args = array('tab' => 'dashboard');
        if ($vendor_id > 0) {
            $default_args['vendor_id'] = $vendor_id;
        }
        if (!empty($query_args)) {
            $default_args = array_merge($default_args, $query_args);
        }

        if (function_exists('vms_vendor_portal_page_url')) {
            return (string) vms_vendor_portal_page_url($default_args);
        }

        return (string) add_query_arg($default_args, home_url('/vendor-portal/'));
    }
}

if (!function_exists('vms_vendor_app_get_login_user')) {
    function vms_vendor_app_get_login_user(int $app_id, int $vendor_id = 0)
    {
        $app_id = (int) $app_id;
        $vendor_id = (int) $vendor_id;

        $submitted_user_id = function_exists('vms_vendor_app_get_submitting_user_id')
            ? (int) vms_vendor_app_get_submitting_user_id($app_id)
            : 0;
        if ($submitted_user_id > 0) {
            $submitted_user = get_userdata($submitted_user_id);
            if ($submitted_user instanceof WP_User) {
                return $submitted_user;
            }
        }

        if ($vendor_id <= 0) {
            $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
        }

        if ($vendor_id > 0 && function_exists('vms_vendor_user_links_get_by_vendor')) {
            foreach ((array) vms_vendor_user_links_get_by_vendor($vendor_id, false) as $row) {
                $user_id = absint($row['user_id'] ?? 0);
                if ($user_id <= 0) {
                    continue;
                }

                $linked_user = get_userdata($user_id);
                if ($linked_user instanceof WP_User) {
                    return $linked_user;
                }
            }
        }

        if ($vendor_id > 0) {
            $legacy_user_id = (int) get_post_meta($vendor_id, '_vms_vendor_user_id', true);
            if ($legacy_user_id > 0) {
                $legacy_user = get_userdata($legacy_user_id);
                if ($legacy_user instanceof WP_User) {
                    return $legacy_user;
                }
            }
        }

        return null;
    }
}


if (!function_exists('vms_vendor_app_normalize_vendor_type')) {
    function vms_vendor_app_normalize_vendor_type(string $raw): string
    {
        if (function_exists('vms_vendor_type_normalize_slug')) {
            return (string) vms_vendor_type_normalize_slug($raw);
        }

        return sanitize_key(str_replace('-', '_', strtolower(trim($raw))));
    }
}

if (!function_exists('vms_vendor_app_vendor_type_label')) {
    function vms_vendor_app_vendor_type_label(string $raw): string
    {
        $slug = vms_vendor_app_normalize_vendor_type($raw);

        if (function_exists('vms_vendor_type_get_term')) {
            $term = vms_vendor_type_get_term($slug !== '' ? $slug : $raw);
            if ($term instanceof WP_Term && trim((string) $term->name) !== '') {
                return trim((string) $term->name);
            }
        } elseif (taxonomy_exists('vms_vendor_type')) {
            $term = null;
            if ($slug !== '') {
                $term = get_term_by('slug', $slug, 'vms_vendor_type');
            }
            if ((!$term || is_wp_error($term)) && trim($raw) !== '') {
                $term = get_term_by('name', trim($raw), 'vms_vendor_type');
            }
            if ($term instanceof WP_Term && trim((string) $term->name) !== '') {
                return trim((string) $term->name);
            }
        }

        if (function_exists('vms_vendor_type_label')) {
            return (string) vms_vendor_type_label($raw);
        }

        if ($slug === 'food_truck') {
            return __('Food Vendor', 'backstage-venue-manager');
        }
        if ($slug === 'band') {
            return __('Music Vendor', 'backstage-venue-manager');
        }

        return ucwords(str_replace(['_', '-'], ' ', $slug !== '' ? $slug : trim($raw)));
    }
}

if (!function_exists('vms_vendor_app_selectable_vendor_types')) {
    function vms_vendor_app_selectable_vendor_types(): array
    {
        $options = array();

        if (taxonomy_exists('vms_vendor_type')) {
            $terms = get_terms(array(
                'taxonomy' => 'vms_vendor_type',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            if (is_array($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term) {
                        continue;
                    }

                    $slug = sanitize_key((string) $term->slug);
                    $label = trim((string) $term->name);
                    if ($slug === '' || $label === '') {
                        continue;
                    }

                    $options[$slug] = $label;
                }
            }
        }

        if (function_exists('vms_vendor_type_select_options')) {
            foreach ((array) vms_vendor_type_select_options() as $type_slug => $type_label) {
                $type_slug = sanitize_key((string) $type_slug);
                $type_label = trim((string) $type_label);
                if ($type_slug === '' || $type_label === '' || isset($options[$type_slug])) {
                    continue;
                }

                $options[$type_slug] = $type_label;
            }
        }

        if (empty($options)) {
            $options = array(
                'band' => __('Music Vendor', 'backstage-venue-manager'),
                'food_truck' => __('Food Vendor', 'backstage-venue-manager'),
                'dessert_truck' => __('Dessert Vendor', 'backstage-venue-manager'),
                'drink_truck' => __('Drink Vendor', 'backstage-venue-manager'),
                'photographer' => __('Photographer', 'backstage-venue-manager'),
                'market_vendor' => __('Market Vendor', 'backstage-venue-manager'),
            );
        }

        if (!empty($options)) {
            natcasesort($options);
        }

        return (array) apply_filters('vms_vendor_app_selectable_vendor_types', $options);
    }
}


if (!function_exists('vms_vendor_app_form_variant_map')) {
    function vms_vendor_app_form_variant_map(): array
    {
        return array(
            'default' => array(
                'name_label' => __('Business / Vendor Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social links (optional)', 'backstage-venue-manager'),
                'show_concession' => false,
                'concession_label' => __('Cuisine / Food Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Tacos, BBQ, Burgers, Coffee, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram'),
            ),
            'band' => array(
                'name_label' => __('Music Vendor / Artist Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social & music links (optional)', 'backstage-venue-manager'),
                'show_concession' => false,
                'concession_label' => __('Cuisine / Food Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Tacos, BBQ, Burgers, Coffee, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram', 'x', 'tiktok', 'youtube', 'spotify'),
            ),
            'food_truck' => array(
                'name_label' => __('Business Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social links (optional)', 'backstage-venue-manager'),
                'show_concession' => true,
                'concession_label' => __('Cuisine / Food Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Tacos, BBQ, Burgers, Coffee, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram', 'tiktok'),
            ),
            'drink_truck' => array(
                'name_label' => __('Business Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social links (optional)', 'backstage-venue-manager'),
                'show_concession' => true,
                'concession_label' => __('Beverage / Drink Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Coffee, tea, mocktails, soda, lemonade, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu / Service Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram', 'tiktok'),
            ),
            'dessert_truck' => array(
                'name_label' => __('Business Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social links (optional)', 'backstage-venue-manager'),
                'show_concession' => true,
                'concession_label' => __('Dessert Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Ice cream, cookies, pastries, funnel cakes, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram', 'tiktok'),
            ),
            'photographer' => array(
                'name_label' => __('Photographer / Business Name', 'backstage-venue-manager'),
                'website_label' => __('Portfolio / Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social / portfolio links (optional)', 'backstage-venue-manager'),
                'show_concession' => false,
                'concession_label' => __('Cuisine / Food Type', 'backstage-venue-manager'),
                'concession_placeholder' => __('Tacos, BBQ, Burgers, Coffee, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Menu Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram'),
            ),
            'market_vendor' => array(
                'name_label' => __('Business / Vendor Name', 'backstage-venue-manager'),
                'website_label' => __('Website URL (optional)', 'backstage-venue-manager'),
                'social_heading' => __('Social links (optional)', 'backstage-venue-manager'),
                'show_concession' => false,
                'concession_label' => __('Products / Offerings', 'backstage-venue-manager'),
                'concession_placeholder' => __('Handmade goods, retail products, local makers, etc.', 'backstage-venue-manager'),
                'concession_menu_label' => __('Catalog / Shop Link (optional)', 'backstage-venue-manager'),
                'visible_socials' => array('facebook', 'instagram', 'tiktok'),
            ),
        );
    }
}

if (!function_exists('vms_vendor_app_social_field_map')) {
    function vms_vendor_app_social_field_map(): array
    {
        return array(
            'facebook' => array(
                'label' => __('Facebook URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_facebook',
                'vendor_key' => '_vms_vendor_social_facebook',
                'placeholder' => 'https://facebook.com/…',
            ),
            'instagram' => array(
                'label' => __('Instagram URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_instagram',
                'vendor_key' => '_vms_vendor_social_instagram',
                'placeholder' => 'https://instagram.com/…',
            ),
            'x' => array(
                'label' => __('X / Twitter URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_x',
                'vendor_key' => '_vms_vendor_social_x',
                'placeholder' => 'https://x.com/…',
            ),
            'tiktok' => array(
                'label' => __('TikTok URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_tiktok',
                'vendor_key' => '_vms_vendor_social_tiktok',
                'placeholder' => 'https://tiktok.com/@…',
            ),
            'youtube' => array(
                'label' => __('YouTube URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_youtube',
                'vendor_key' => '_vms_vendor_social_youtube',
                'placeholder' => 'https://youtube.com/…',
            ),
            'spotify' => array(
                'label' => __('Spotify URL', 'backstage-venue-manager'),
                'app_key' => '_vms_app_social_spotify',
                'vendor_key' => '_vms_vendor_social_spotify',
                'placeholder' => 'https://open.spotify.com/…',
            ),
        );
    }
}

if (!function_exists('vms_vendor_app_sanitize_url_input')) {
    function vms_vendor_app_sanitize_url_input(string $raw): string
    {
        $raw = trim(wp_strip_all_tags($raw));
        if ($raw === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }

        return esc_url_raw($raw);
    }
}

if (!function_exists('vms_vendor_app_compose_social_legacy_blob')) {
    function vms_vendor_app_compose_social_legacy_blob(array $social_inputs): string
    {
        $lines = array();
        foreach ((array) $social_inputs as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        return implode("\n", array_values(array_unique($lines)));
    }
}


if (!function_exists('vms_vendor_app_turnout_options')) {
    function vms_vendor_app_turnout_options(): array
    {
        return array(
            'under_25' => __('Under 25', 'backstage-venue-manager'),
            '25_50'    => __('25–50', 'backstage-venue-manager'),
            '50_100'   => __('50–100', 'backstage-venue-manager'),
            '100_200'  => __('100–200', 'backstage-venue-manager'),
            '200_plus' => __('200+', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_app_turnout_label')) {
    function vms_vendor_app_turnout_label(string $value): string
    {
        $value = sanitize_key($value);
        $options = vms_vendor_app_turnout_options();
        return (string) ($options[$value] ?? '');
    }
}

if (!function_exists('vms_vendor_app_get_social_values')) {
    function vms_vendor_app_get_social_values(int $app_id): array
    {
        $app_id = (int) $app_id;
        $values = array();
        $legacy_raw = trim((string) get_post_meta($app_id, '_vms_app_social', true));
        $legacy_map = $legacy_raw !== '' ? vms_vendor_app_parse_social_links($legacy_raw) : array();

        foreach (vms_vendor_app_social_field_map() as $slug => $field) {
            $value = trim((string) get_post_meta($app_id, (string) $field['app_key'], true));
            if ($value === '' && !empty($legacy_map[(string) $field['vendor_key']])) {
                $value = (string) $legacy_map[(string) $field['vendor_key']];
            }
            $values[$slug] = esc_url_raw($value);
        }

        return $values;
    }
}

if (!function_exists('vms_vendor_app_get_website_value')) {
    function vms_vendor_app_get_website_value(int $app_id): string
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return '';
        }

        $website = esc_url_raw((string) get_post_meta($app_id, '_vms_app_website', true));
        if ($website !== '') {
            return $website;
        }

        $website = esc_url_raw((string) get_post_meta($app_id, '_vms_app_epk', true));
        if ($website !== '') {
            return $website;
        }

        $website = esc_url_raw((string) get_post_meta($app_id, '_vms_app_menu', true));
        if ($website !== '') {
            return $website;
        }

        $legacy_raw = trim((string) get_post_meta($app_id, '_vms_app_social', true));
        if ($legacy_raw !== '') {
            $legacy_map = vms_vendor_app_parse_social_links($legacy_raw);
            if (!empty($legacy_map['_vms_vendor_website'])) {
                return esc_url_raw((string) $legacy_map['_vms_vendor_website']);
            }
        }

        return '';
    }
}

if (!function_exists('vms_vendor_app_parse_city_state')) {
    function vms_vendor_app_parse_city_state(string $raw): array
    {
        $raw = trim(wp_strip_all_tags($raw));
        if ($raw === '') {
            return array('city' => '', 'state' => '');
        }

        $states = array(
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
            'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'florida' => 'FL', 'georgia' => 'GA',
            'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA',
            'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS', 'missouri' => 'MO',
            'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ',
            'new mexico' => 'NM', 'new york' => 'NY', 'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
            'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT',
            'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
            'district of columbia' => 'DC'
        );

        if (preg_match('/^\s*(.+?)\s*[,\-|\/]\s*([A-Za-z]{2})\s*$/', $raw, $m)) {
            return array(
                'city' => sanitize_text_field((string) $m[1]),
                'state' => strtoupper(sanitize_text_field((string) $m[2])),
            );
        }

        if (preg_match('/^\s*(.+?)\s*,\s*([A-Za-z ]{3,})\s*$/', $raw, $m)) {
            $city = sanitize_text_field((string) $m[1]);
            $state_raw = strtolower(trim((string) $m[2]));
            $state = $states[$state_raw] ?? strtoupper(substr(sanitize_text_field((string) $m[2]), 0, 2));
            return array(
                'city' => $city,
                'state' => $state,
            );
        }

        return array(
            'city' => sanitize_text_field($raw),
            'state' => '',
        );
    }
}

if (!function_exists('vms_vendor_app_extract_urls')) {
    function vms_vendor_app_extract_urls(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return array();
        }

        if (function_exists('wp_extract_urls')) {
            $urls = wp_extract_urls($raw);
            if (is_array($urls)) {
                return array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));
            }
        }

        if (preg_match_all('/https?:\/\/[^\s,]+/i', $raw, $m) && !empty($m[0])) {
            return array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $m[0]))));
        }

        return array();
    }
}

if (!function_exists('vms_vendor_app_parse_social_links')) {
    function vms_vendor_app_parse_social_links(string $raw): array
    {
        $map = array(
            '_vms_vendor_social_facebook' => '',
            '_vms_vendor_social_instagram' => '',
            '_vms_vendor_social_x' => '',
            '_vms_vendor_social_tiktok' => '',
            '_vms_vendor_social_youtube' => '',
            '_vms_vendor_social_spotify' => '',
            '_vms_vendor_featured_video_url' => '',
            '_vms_vendor_website' => '',
        );

        foreach (vms_vendor_app_extract_urls($raw) as $url) {
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }
            $host = preg_replace('/^www\./', '', $host);

            if (strpos($host, 'facebook.com') !== false && $map['_vms_vendor_social_facebook'] === '') {
                $map['_vms_vendor_social_facebook'] = $url;
                continue;
            }
            if (strpos($host, 'instagram.com') !== false && $map['_vms_vendor_social_instagram'] === '') {
                $map['_vms_vendor_social_instagram'] = $url;
                continue;
            }
            if ((strpos($host, 'twitter.com') !== false || $host === 'x.com' || strpos($host, '.x.com') !== false) && $map['_vms_vendor_social_x'] === '') {
                $map['_vms_vendor_social_x'] = $url;
                continue;
            }
            if (strpos($host, 'tiktok.com') !== false && $map['_vms_vendor_social_tiktok'] === '') {
                $map['_vms_vendor_social_tiktok'] = $url;
                continue;
            }
            if ((strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) && $map['_vms_vendor_social_youtube'] === '') {
                $map['_vms_vendor_social_youtube'] = $url;
                if ($map['_vms_vendor_featured_video_url'] === '') {
                    $map['_vms_vendor_featured_video_url'] = $url;
                }
                continue;
            }
            if (strpos($host, 'spotify.com') !== false && $map['_vms_vendor_social_spotify'] === '') {
                $map['_vms_vendor_social_spotify'] = $url;
                continue;
            }
            if ($map['_vms_vendor_website'] === '') {
                $map['_vms_vendor_website'] = $url;
            }
        }

        return $map;
    }
}

if (!function_exists('vms_vendor_app_get_or_create_vendor')) {
    function vms_vendor_app_get_or_create_vendor(int $app_id): int
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return 0;
        }

        $app = get_post($app_id);
        if (!$app || empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) {
            return 0;
        }

        $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
        if ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT) {
            update_post_meta($vendor_id, '_vms_application_id', $app_id);
            vms_vendor_app_sync_vendor_from_application($app_id, $vendor_id);
            return $vendor_id;
        }

        $vendor_id = wp_insert_post(array(
            'post_type'   => VMS_VENDOR_CPT,
            'post_title'  => $app->post_title,
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($vendor_id) || $vendor_id <= 0) {
            $error_message = is_wp_error($vendor_id) ? $vendor_id->get_error_message() : 'unknown error';
            error_log('[VMS] vendor-applications: failed creating vendor for app_id ' . $app_id . ' (' . $error_message . ')');
            return 0;
        }

        update_post_meta($app_id, '_vms_vendor_id', (int) $vendor_id);
        update_post_meta((int) $vendor_id, '_vms_application_id', $app_id);
        vms_vendor_app_sync_vendor_from_application($app_id, (int) $vendor_id);

        return (int) $vendor_id;
    }
}

if (!function_exists('vms_vendor_app_link_submitting_user_to_vendor')) {
    function vms_vendor_app_link_submitting_user_to_vendor(int $app_id, int $vendor_id, int $actor_user_id = 0)
    {
        $app_id = (int) $app_id;
        $vendor_id = (int) $vendor_id;
        $actor_user_id = (int) $actor_user_id;

        if ($app_id <= 0 || $vendor_id <= 0) {
            return new WP_Error('vms_vendor_app_invalid_link_target', __('Invalid application or vendor target.', 'backstage-venue-manager'));
        }

        $user_id = vms_vendor_app_get_submitting_user_id($app_id);
        if ($user_id <= 0) {
            return true;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            error_log('[VMS] vendor-applications: submitting user missing for app_id ' . $app_id . ' (user_id ' . $user_id . ')');
            return new WP_Error('vms_vendor_app_missing_user', __('The submitting website account no longer exists.', 'backstage-venue-manager'));
        }

        if (function_exists('vms_vendor_user_link_upsert')) {
            $ok = (bool) vms_vendor_user_link_upsert($vendor_id, $user_id, array(
                'role' => 'primary_contact',
                'status' => 'active',
                'set_primary_for_user' => false,
                'source' => 'vendor_application',
            ), $actor_user_id);

            if ($ok) {
                return true;
            }
        } else {
            $vendor_primary_key = defined('VMS_VENDOR_PRIMARY_USER_META_KEY') ? VMS_VENDOR_PRIMARY_USER_META_KEY : '_vms_vendor_user_id';
            $user_primary_key = defined('VMS_USER_PRIMARY_VENDOR_META_KEY') ? VMS_USER_PRIMARY_VENDOR_META_KEY : '_vms_vendor_id';
            $existing_vendor_primary = (int) get_post_meta($vendor_id, $vendor_primary_key, true);
            if ($existing_vendor_primary <= 0) {
                update_post_meta($vendor_id, $vendor_primary_key, $user_id);
            }

            $existing_user_primary = (int) get_user_meta($user_id, $user_primary_key, true);
            if ($existing_user_primary <= 0) {
                update_user_meta($user_id, $user_primary_key, $vendor_id);
            }

            return true;
        }

        error_log('[VMS] vendor-applications: failed linking submitting user ' . $user_id . ' to vendor ' . $vendor_id . ' for app_id ' . $app_id);
        return new WP_Error('vms_vendor_app_link_failed', __('The website account link could not be saved.', 'backstage-venue-manager'));
    }
}

/**
 * Application statuses stored in post meta.
 */
function vms_vendor_app_statuses(): array
{
    return array(
        'pending'  => __('Pending', 'backstage-venue-manager'),
        'holding'  => __('Holding / Keep on File', 'backstage-venue-manager'),
        'approved' => __('Approved', 'backstage-venue-manager'),
        'rejected' => __('Rejected', 'backstage-venue-manager'),
    );
}

if (!function_exists('vms_vendor_app_status_pill_class')) {
    function vms_vendor_app_status_pill_class(string $status): string
    {
        if ($status === 'pending') {
            return 'vms-pill-yellow';
        }
        if ($status === 'holding') {
            return 'vms-pill-blue';
        }
        if ($status === 'approved') {
            return 'vms-pill-green';
        }
        if ($status === 'rejected') {
            return 'vms-pill-red';
        }

        return 'vms-pill-grey';
    }
}

if (!function_exists('vms_vendor_app_default_response_message')) {
    function vms_vendor_app_default_response_message(int $app_id, string $status): string
    {
        $name = trim((string) get_the_title($app_id));
        if ($name === '') {
            $name = __('your application', 'backstage-venue-manager');
        }

        if ($status === 'holding') {
            return sprintf(
                /* translators: %s: submitted vendor or act name. */
                __('Thank you for reaching out to Serenade Range. We reviewed %s and are keeping it on file for future consideration. At this time, we are being selective with new bookings and generally need stronger evidence that an act can draw enough ticket-paying guests to cover its fee and the event overhead. This is not a rejection, but it does mean we are not moving forward right now and Vendor Portal access is not active yet. Feel free to reply with updated draw history, ticket sales history, recent show results, or audience information any time.', 'backstage-venue-manager'),
                $name
            );
        }

        if ($status === 'approved') {
            return sprintf(
                /* translators: %s: submitted vendor or act name. */
                __('Thank you for reaching out to Serenade Range. We reviewed %s and approved it for our vendor records. This does not guarantee an immediate booking, but it means we may consider you for future dates. For paid entertainment bookings, we still need enough confidence that expected ticket sales can cover the artist/vendor fee and event overhead before confirming a date. If your vendor profile is already linked to a website account, your Vendor Portal details are listed below. If not, reply to this email and we will help connect the correct account.', 'backstage-venue-manager'),
                $name
            );
        }

        if ($status === 'rejected') {
            return sprintf(
                /* translators: %s: submitted vendor or act name. */
                __('Thank you for reaching out to Serenade Range. We reviewed %s and do not think it is the right fit for our current programming needs. We appreciate you taking the time to send your information and wish you the best with future opportunities.', 'backstage-venue-manager'),
                $name
            );
        }

        return '';
    }
}

if (!function_exists('vms_vendor_app_response_guidance')) {
    function vms_vendor_app_response_guidance(int $app_id, string $status): string
    {
        $status = sanitize_key($status);
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return '';
        }

        if ($status === 'holding') {
            return implode("\n", array(
                __('Next step:', 'backstage-venue-manager'),
                __('Please watch your email, including spam or junk folders, for future follow-up. Vendor Portal access is only available after approval.', 'backstage-venue-manager'),
            ));
        }

        if ($status !== 'approved') {
            return '';
        }

        $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
        $portal_url = function_exists('vms_vendor_app_get_portal_page_url')
            ? vms_vendor_app_get_portal_page_url($vendor_id)
            : home_url('/vendor-portal/?tab=dashboard');
        $portal_login_url = function_exists('vms_vendor_portal_login_redirect_url')
            ? vms_vendor_portal_login_redirect_url(true)
            : add_query_arg('vms_vendor_portal_login', '1', $portal_url);
        $reset_url = wp_lostpassword_url($portal_login_url);
        $login_user = function_exists('vms_vendor_app_get_login_user')
            ? vms_vendor_app_get_login_user($app_id, $vendor_id)
            : null;

        $lines = array(
            __('Vendor Portal details:', 'backstage-venue-manager'),
            /* translators: %s: vendor portal URL. */
            sprintf(__('Vendor Portal: %s', 'backstage-venue-manager'), $portal_url),
            __('Vendor tools and updates live in the Vendor Portal. WooCommerce My Account is still your normal customer/ticket area and may show customer information there.', 'backstage-venue-manager'),
        );

        if ($login_user instanceof WP_User) {
            $login_email = sanitize_email((string) $login_user->user_email);
            if ($login_email !== '') {
                /* translators: %s: email address. */
                $lines[] = sprintf(__('Login email: %s', 'backstage-venue-manager'), $login_email);
            }
            /* translators: %s: username. */
            $lines[] = sprintf(__('Username: %s', 'backstage-venue-manager'), $login_user->user_login);
            /* translators: %s: password reset. */
            $lines[] = sprintf(__('Password reset: %s', 'backstage-venue-manager'), $reset_url);
            $lines[] = __('If you do not remember your password, use the reset link above and then sign in through the Vendor Portal URL.', 'backstage-venue-manager');
        } else {
            $lines[] = __('We do not appear to have a linked website account recorded for this vendor yet. Reply to this email so we can connect the correct account before you try to use the Vendor Portal.', 'backstage-venue-manager');
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('vms_vendor_app_send_response_email')) {
    function vms_vendor_app_send_response_email(int $app_id, string $status, string $message, int $actor_user_id = 0): bool
    {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            return false;
        }

        $email = sanitize_email((string) get_post_meta($app_id, '_vms_app_email', true));
        if ($email === '') {
            return false;
        }

        $message = trim($message);
        if ($message === '') {
            $message = vms_vendor_app_default_response_message($app_id, $status);
        }
        if ($message === '') {
            return false;
        }

        $title = trim((string) get_the_title($app_id));
        if ($title === '') {
            $title = __('Vendor Application', 'backstage-venue-manager');
        }

        $subject_prefix = __('Vendor Application Update', 'backstage-venue-manager');
        if ($status === 'approved') {
            $subject_prefix = __('Vendor Application Approved', 'backstage-venue-manager');
        } elseif ($status === 'holding') {
            $subject_prefix = __('Vendor Application Update', 'backstage-venue-manager');
        } elseif ($status === 'rejected') {
            $subject_prefix = __('Vendor Application Update', 'backstage-venue-manager');
        }

        $body = trim($message);
        $guidance = function_exists('vms_vendor_app_response_guidance')
            ? trim(vms_vendor_app_response_guidance($app_id, $status))
            : '';
        if ($guidance !== '') {
            $body .= "\n\n" . $guidance;
        }
        $body .= "\n\n" . __('-- Serenade Range', 'backstage-venue-manager');
        $sent = (bool) wp_mail($email, $subject_prefix . ': ' . $title, $body);

        update_post_meta($app_id, '_vms_app_last_response_status', sanitize_key($status));
        update_post_meta($app_id, '_vms_app_last_response_message', $message);
        update_post_meta($app_id, '_vms_app_last_response_sent_at', current_time('mysql'));
        update_post_meta($app_id, '_vms_app_last_response_sent_to', $email);
        if ($actor_user_id > 0) {
            update_post_meta($app_id, '_vms_app_last_response_sent_by', (int) $actor_user_id);
        }
        update_post_meta($app_id, '_vms_app_last_response_email_sent', $sent ? '1' : '0');

        return $sent;
    }
}

/**
 * Get application status (meta-based).
 */
function vms_vendor_app_get_status(int $app_id): string
{
    $s = (string) get_post_meta($app_id, '_vms_app_status', true);
    if (!$s) $s = 'pending';
    $all = vms_vendor_app_statuses();
    return isset($all[$s]) ? $s : 'pending';
}

/**
 * Set application status.
 */
function vms_vendor_app_set_status(int $app_id, string $status): void
{
    $all = vms_vendor_app_statuses();
    if (!isset($all[$status])) $status = 'pending';
    update_post_meta($app_id, '_vms_app_status', $status);
}

/**
 * Count pending applications.
 */
function vms_vendor_app_count_pending(): int
{
    $confirmation_key = vms_vendor_app_meta_key('confirmation_state');
    if ($confirmation_key === '') {
        $confirmation_key = '_vms_app_confirmation_state';
    }

    $q = new WP_Query(array(
        'post_type'      => vms_vendor_app_cpt_slugs(),
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_vms_app_status',
                'value'   => 'pending',
                'compare' => '=',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => $confirmation_key,
                    'value' => 'confirmed',
                    'compare' => '=',
                ),
                array(
                    'key' => $confirmation_key,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ),
    ));
    return (int) $q->found_posts;
}

if (!function_exists('vms_vendor_app_count_by_review_filter')) {
    function vms_vendor_app_count_by_review_filter(string $filter): int
    {
        $filter = sanitize_key($filter);
        $confirmation_key = vms_vendor_app_meta_key('confirmation_state');
        if ($confirmation_key === '') {
            $confirmation_key = '_vms_app_confirmation_state';
        }

        $meta_query = array();
        if ($filter === 'ready') {
            $meta_query = array(
                'relation' => 'AND',
                array(
                    'key' => '_vms_app_status',
                    'value' => 'pending',
                    'compare' => '=',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => $confirmation_key,
                        'value' => 'confirmed',
                        'compare' => '=',
                    ),
                    array(
                        'key' => $confirmation_key,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            );
        } elseif ($filter === 'awaiting_confirmation') {
            $meta_query = array(
                array(
                    'key' => $confirmation_key,
                    'value' => 'unconfirmed',
                    'compare' => '=',
                ),
            );
        } elseif ($filter === 'expired_confirmation') {
            $meta_query = array(
                array(
                    'key' => $confirmation_key,
                    'value' => 'expired',
                    'compare' => '=',
                ),
            );
        } else {
            return 0;
        }

        $q = new WP_Query(array(
            'post_type' => vms_vendor_app_cpt_slugs(),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ));

        return (int) $q->found_posts;
    }
}

/**
 * Register the Vendor Applications CPT.
 */
add_action('init', 'vms_register_vendor_applications_cpt');
function vms_register_vendor_applications_cpt(): void
{
    $labels = array(
        'name'               => __('Vendor Applications', 'backstage-venue-manager'),
        'singular_name'      => __('Vendor Application', 'backstage-venue-manager'),
        'add_new'            => __('Add New', 'backstage-venue-manager'),
        'add_new_item'       => __('Add New Application', 'backstage-venue-manager'),
        'edit_item'          => __('Edit Application', 'backstage-venue-manager'),
        'new_item'           => __('New Application', 'backstage-venue-manager'),
        'view_item'          => __('View Application', 'backstage-venue-manager'),
        'search_items'       => __('Search Applications', 'backstage-venue-manager'),
        'not_found'          => __('No applications found.', 'backstage-venue-manager'),
        'not_found_in_trash' => __('No applications found in Trash.', 'backstage-venue-manager'),
        'menu_name'          => __('Applications', 'backstage-venue-manager'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        // 'show_in_menu'       => VMS_ADMIN_PARENT_SLUG, // attach under VMS menu
        'capability_type'    => 'post',
        'supports'           => array('title'),
        'menu_position'      => 25,
        'has_archive'        => false,
        'rewrite'            => false,
    );

    // Register canonical slug only. Legacy slug exceeds WP CPT length limit (20 chars).
    register_post_type(VMS_VENDOR_APP_CPT, $args);
}

if (!function_exists('vms_vendor_applications_migrate_legacy_post_type_once')) {
    function vms_vendor_applications_migrate_legacy_post_type_once(): void
    {
        $marker = 'vms_vendor_app_pt_migrated_v1';
        if ((string) get_option($marker, '') === '1') {
            return;
        }

        if (!defined('VMS_VENDOR_APP_CPT') || !defined('VMS_VENDOR_APP_CPT_LEGACY')) {
            update_option($marker, '1', false);
            return;
        }

        $canonical = sanitize_key((string) VMS_VENDOR_APP_CPT);
        $legacy = sanitize_key((string) VMS_VENDOR_APP_CPT_LEGACY);
        if ($canonical === '' || $legacy === '' || $canonical === $legacy) {
            update_option($marker, '1', false);
            return;
        }

        global $wpdb;
        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            $canonical,
            $legacy
        );
        $wpdb->query($sql);
        update_option($marker, '1', false);
    }
}
add_action('init', 'vms_vendor_applications_migrate_legacy_post_type_once', 11);

/**
 * Ensure submenu label shows pending bubble count.
 * (This runs on admin_menu and rewrites the submenu label.)
 */
add_action('admin_menu', 'vms_vendor_applications_add_pending_bubble', 999);
function vms_vendor_applications_add_pending_bubble(): void
{
    // Phase-in: centralized approvals framework owns badge rendering.
    if (function_exists('vms_approvals_queue_collect_snapshot')) {
        return;
    }

    global $submenu;

    if (!isset($submenu[VMS_ADMIN_PARENT_SLUG]) || !is_array($submenu[VMS_ADMIN_PARENT_SLUG])) {
        return;
    }

    $pending = vms_vendor_app_count_pending();
    if ($pending <= 0) return;

    foreach ($submenu[VMS_ADMIN_PARENT_SLUG] as $i => $item) {
        // $item[2] is the slug
        // For CPT submenus, slug often looks like "edit.php?post_type=xxx"
        if (!empty($item[2]) && strpos($item[2], 'edit.php?post_type=' . VMS_VENDOR_APP_CPT) !== false) {
            $submenu[VMS_ADMIN_PARENT_SLUG][$i][0] = $item[0] . ' <span class="awaiting-mod count-' . (int)$pending . '"><span class="pending-count">' . (int)$pending . '</span></span>';
            break;
        }
    }
}

/**
 * Admin columns for applications list.
 */
foreach (vms_vendor_app_cpt_slugs() as $pt) {
    add_filter('manage_' . $pt . '_posts_columns', 'vms_vendor_applications_columns');
}
function vms_vendor_applications_columns($cols)
{
    $new = array();
    foreach ($cols as $k => $label) {
        if ($k === 'title') {
            $new[$k] = $label;
            $new['vms_app_type']   = __('Type', 'backstage-venue-manager');
            $new['vms_app_email']  = __('Email', 'backstage-venue-manager');
            $new['vms_app_confirmation'] = __('Confirmation', 'backstage-venue-manager');
            $new['vms_app_status'] = __('Status', 'backstage-venue-manager');
        } else {
            $new[$k] = $label;
        }
    }
    return $new;
}

foreach (vms_vendor_app_cpt_slugs() as $pt) {
    add_action('manage_' . $pt . '_posts_custom_column', 'vms_vendor_applications_render_columns', 10, 2);
}
function vms_vendor_applications_render_columns($col, $post_id)
{
    if ($col === 'vms_app_type') {
        $raw_type = (string) get_post_meta($post_id, '_vms_app_vendor_type', true);
        echo esc_html(vms_vendor_app_vendor_type_label($raw_type));
        return;
    }
    if ($col === 'vms_app_email') {
        $email = (string) get_post_meta($post_id, '_vms_app_email', true);
        if ($email) echo esc_html($email);
        return;
    }
    if ($col === 'vms_app_confirmation') {
        if (function_exists('vms_vendor_app_get_confirmation_state') && function_exists('vms_vendor_app_confirmation_state_label')) {
            echo esc_html(vms_vendor_app_confirmation_state_label(vms_vendor_app_get_confirmation_state((int) $post_id)));
        } else {
            echo esc_html__('Confirmed', 'backstage-venue-manager');
        }
        return;
    }
    if ($col === 'vms_app_status') {
        $status = vms_vendor_app_get_status((int)$post_id);
        $labels = vms_vendor_app_statuses();
        $label  = $labels[$status] ?? ucfirst($status);

        $class = vms_vendor_app_status_pill_class($status);

        echo '<span class="vms-status-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        return;
    }
}

if (!function_exists('vms_vendor_applications_current_review_filter')) {
    function vms_vendor_applications_current_review_filter(): string
    {
        return isset($_GET['vms_app_review_filter']) ? sanitize_key((string) wp_unslash($_GET['vms_app_review_filter'])) : '';
    }
}

foreach (vms_vendor_app_cpt_slugs() as $pt) {
    add_filter('views_edit-' . $pt, 'vms_vendor_applications_review_filter_views');
}
if (!function_exists('vms_vendor_applications_review_filter_views')) {
    function vms_vendor_applications_review_filter_views(array $views): array
    {
        $current = vms_vendor_applications_current_review_filter();
        $base_url = admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT);

        $items = array(
            'ready' => array(
                'label' => __('Ready for Review', 'backstage-venue-manager'),
                'count' => vms_vendor_app_count_by_review_filter('ready'),
            ),
            'awaiting_confirmation' => array(
                'label' => __('Awaiting Email Confirmation', 'backstage-venue-manager'),
                'count' => vms_vendor_app_count_by_review_filter('awaiting_confirmation'),
            ),
            'expired_confirmation' => array(
                'label' => __('Confirmation Expired', 'backstage-venue-manager'),
                'count' => vms_vendor_app_count_by_review_filter('expired_confirmation'),
            ),
        );

        foreach ($items as $slug => $item) {
            $class = ($current === $slug) ? ' class="current"' : '';
            $url = add_query_arg('vms_app_review_filter', $slug, $base_url);
            $views['vms_' . $slug] = '<a href="' . esc_url($url) . '"' . $class . '>' . esc_html($item['label']) . ' <span class="count">(' . absint($item['count']) . ')</span></a>';
        }

        return $views;
    }
}

if (!function_exists('vms_vendor_applications_status_filter_dropdown')) {
    add_action('restrict_manage_posts', 'vms_vendor_applications_status_filter_dropdown');
    function vms_vendor_applications_status_filter_dropdown($post_type): void
    {
        if (!in_array((string) $post_type, vms_vendor_app_cpt_slugs(), true)) {
            return;
        }

        $selected = vms_request_read_key($_GET, 'vms_app_status_filter');
        echo '<select name="vms_app_status_filter">';
        echo '<option value="">' . esc_html__('All application statuses', 'backstage-venue-manager') . '</option>';
        foreach (vms_vendor_app_statuses() as $status => $label) {
            echo '<option value="' . esc_attr((string) $status) . '" ' . selected($selected, (string) $status, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select>';
    }
}

if (!function_exists('vms_vendor_applications_apply_status_filter')) {
    add_action('pre_get_posts', 'vms_vendor_applications_apply_status_filter');
    function vms_vendor_applications_apply_status_filter($query): void
    {
        if (!is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        $post_types = is_array($post_type) ? $post_type : array((string) $post_type);
        $is_app_screen = false;
        foreach ($post_types as $pt) {
            if (in_array((string) $pt, vms_vendor_app_cpt_slugs(), true)) {
                $is_app_screen = true;
                break;
            }
        }
        if (!$is_app_screen) {
            return;
        }

        $status = vms_request_read_key($_GET, 'vms_app_status_filter');
        $statuses = vms_vendor_app_statuses();
        if ($status === '' || !isset($statuses[$status])) {
            $status = '';
        }

        $meta_query = (array) $query->get('meta_query');
        if ($status !== '') {
            $meta_query[] = array(
                'key' => '_vms_app_status',
                'value' => $status,
                'compare' => '=',
            );
        }

        $review_filter = vms_vendor_applications_current_review_filter();
        $confirmation_key = vms_vendor_app_meta_key('confirmation_state');
        if ($confirmation_key === '') {
            $confirmation_key = '_vms_app_confirmation_state';
        }
        if ($review_filter === 'ready') {
            $meta_query[] = array(
                'key' => '_vms_app_status',
                'value' => 'pending',
                'compare' => '=',
            );
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => $confirmation_key,
                    'value' => 'confirmed',
                    'compare' => '=',
                ),
                array(
                    'key' => $confirmation_key,
                    'compare' => 'NOT EXISTS',
                ),
            );
        } elseif ($review_filter === 'awaiting_confirmation') {
            $meta_query[] = array(
                'key' => $confirmation_key,
                'value' => 'unconfirmed',
                'compare' => '=',
            );
        } elseif ($review_filter === 'expired_confirmation') {
            $meta_query[] = array(
                'key' => $confirmation_key,
                'value' => 'expired',
                'compare' => '=',
            );
        }

        if (empty($meta_query)) {
            return;
        }
        $query->set('meta_query', $meta_query);
    }
}

/**
 * Admin CSS for pills.
 */
add_action('admin_head-edit.php', 'vms_vendor_applications_admin_css');
function vms_vendor_applications_admin_css(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || empty($screen->post_type) || !in_array($screen->post_type, vms_vendor_app_cpt_slugs(), true)) return;
?>
    <style>
        .vms-status-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; line-height:1.6; font-weight:600; }
        .vms-pill-blue { background:#dbeafe; color:#1e3a8a; }
        .vms-pill-yellow { background:#fef3c7; color:#92400e; }
        .vms-pill-green { background:#dcfce7; color:#166534; }
        .vms-pill-red { background:#fee2e2; color:#991b1b; }
        .vms-pill-grey { background:#f3f4f6; color:#374151; }
    </style>
<?php
}

/**
 * Add Approve/Reject row actions for pending applications.
 */
add_filter('post_row_actions', 'vms_vendor_applications_row_actions', 10, 2);
function vms_vendor_applications_row_actions($actions, $post)
{
    if (empty($post->post_type) || !in_array($post->post_type, vms_vendor_app_cpt_slugs(), true)) return $actions;

    $app_id  = (int) $post->ID;
    if ($app_id <= 0 || !current_user_can('edit_post', $app_id)) return $actions;

    $status  = vms_vendor_app_get_status($app_id);

    $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    // Keep decisions on the edit screen so operators can include a message.
    if ($status === 'pending' || $status === 'holding') {
        $review_url = get_edit_post_link($app_id, '');
        if ($review_url) {
            $actions['vms_review_response'] = '<a href="' . esc_url($review_url) . '">' . esc_html__('Review / Respond', 'backstage-venue-manager') . '</a>';
        }
        return $actions;
    }

    // NEW: repair action if approved but vendor missing
    if ($status === 'approved' && !$vendor_ok) {
        $repair_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_repair_vendor&app_id=' . $app_id),
            'vms_vendor_app_repair_vendor_' . $app_id
        );

        $actions['vms_repair_vendor'] = '<a href="' . esc_url($repair_url) . '">' . esc_html__('Create Vendor', 'backstage-venue-manager') . '</a>';
    }

    // NEW: resync action if approved and vendor exists
    if ($status === 'approved' && $vendor_ok) {
        $resync_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_resync_vendor&app_id=' . $app_id),
            'vms_vendor_app_resync_vendor_' . $app_id
        );
        $actions['vms_resync_vendor'] = '<a href="' . esc_url($resync_url) . '">' . esc_html__('Re-sync Vendor Data', 'backstage-venue-manager') . '</a>';
    }

    // Optional: if approved but vendor missing, you can still offer resync (it will create + sync)
    if ($status === 'approved' && !$vendor_ok) {
        $resync_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_resync_vendor&app_id=' . $app_id),
            'vms_vendor_app_resync_vendor_' . $app_id
        );
        $actions['vms_resync_vendor'] = '<a href="' . esc_url($resync_url) . '">' . esc_html__('Create + Sync Vendor', 'backstage-venue-manager') . '</a>';
    }

    return $actions;
}


/**
 * Metabox on application edit screen (Approve/Reject).
 */
add_action('add_meta_boxes', 'vms_vendor_applications_metaboxes');
function vms_vendor_applications_metaboxes(): void
{
    foreach (vms_vendor_app_cpt_slugs() as $pt) {
        add_meta_box(
            'vms_app_details',
            __('Application Details', 'backstage-venue-manager'),
            'vms_vendor_applications_metabox_details',
            $pt,
            'normal',
            'high'
        );

        add_meta_box(
            'vms_app_actions',
            __('Actions', 'backstage-venue-manager'),
            'vms_vendor_applications_metabox_actions',
            $pt,
            'side',
            'high'
        );
    }
}

function vms_vendor_applications_metabox_details($post): void
{
    $fields = array(
        'Vendor Type' => '_vms_app_vendor_type',
        'Primary Contact Name' => '_vms_app_contact_name',
        'Email'       => '_vms_app_email',
        'Phone'       => '_vms_app_phone',
        'Location'    => '_vms_app_location',
        'Website'     => '_vms_app_website',
        'Typical Turnout' => '_vms_app_turnout',
        'Requested Compensation' => '_vms_app_rate',
        'Compensation Notes' => '_vms_app_compensation_notes',
        'Audience / Following Notes' => '_vms_app_audience_notes',
        'EPK'         => '_vms_app_epk',
        'Cuisine'     => '_vms_app_cuisine',
        'Menu'        => '_vms_app_menu',
        'Notes'       => '_vms_app_notes',
    );

    echo '<table class="widefat striped" style="margin-top:8px;">';
    foreach ($fields as $label => $key) {
        $val = $key === '_vms_app_website'
            ? vms_vendor_app_get_website_value((int) $post->ID)
            : get_post_meta($post->ID, $key, true);
        if ($val === '' || $val === null) continue;

        if ($key === '_vms_app_vendor_type') {
            $val = vms_vendor_app_vendor_type_label((string) $val);
        } elseif ($key === '_vms_app_turnout') {
            $val = vms_vendor_app_turnout_label((string) $val);
        }

        echo '<tr><th style="width:160px;">' . esc_html($label) . '</th><td>';
        if (in_array($key, array('_vms_app_website', '_vms_app_epk', '_vms_app_menu'), true) && filter_var($val, FILTER_VALIDATE_URL)) {
            echo '<a href="' . esc_url($val) . '" target="_blank" rel="noopener">' . esc_html($val) . '</a>';
        } else {
            echo nl2br(esc_html((string)$val));
        }
        echo '</td></tr>';
    }

    foreach (vms_vendor_app_get_social_values((int) $post->ID) as $slug => $value) {
        if ($value === '') {
            continue;
        }

        $field = vms_vendor_app_social_field_map()[$slug] ?? null;
        if (!is_array($field)) {
            continue;
        }

        echo '<tr><th style="width:160px;">' . esc_html((string) ($field['label'] ?? $slug)) . '</th><td>';
        echo '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
        echo '</td></tr>';
    }

    $submitted_user_id = function_exists('vms_vendor_app_get_submitting_user_id')
        ? vms_vendor_app_get_submitting_user_id((int) $post->ID)
        : 0;
    if ($submitted_user_id > 0) {
        $user = get_userdata($submitted_user_id);
        /* translators: %d: user ID. */
        $user_label = sprintf(__('User #%d', 'backstage-venue-manager'), $submitted_user_id);
        $user_url = '';

        if ($user) {
            $display_name = trim((string) $user->display_name);
            $email = trim((string) $user->user_email);
            if ($display_name === '') {
                $display_name = trim((string) $user->user_login);
            }
            if ($display_name !== '') {
                $user_label = $display_name;
                if ($email !== '') {
                    $user_label .= ' <' . $email . '>';
                }
            } elseif ($email !== '') {
                $user_label = $email;
            }
            if (current_user_can('edit_users')) {
                $user_url = admin_url('user-edit.php?user_id=' . $submitted_user_id);
            }
        } else {
            /* translators: %d: user ID. */
            $user_label = sprintf(__('User #%d (account no longer exists)', 'backstage-venue-manager'), $submitted_user_id);
        }

        echo '<tr><th style="width:160px;">' . esc_html__('Website Account', 'backstage-venue-manager') . '</th><td>';
        if ($user_url !== '') {
            echo '<a href="' . esc_url($user_url) . '">' . esc_html($user_label) . '</a>';
        } else {
            echo esc_html($user_label);
        }
        echo '</td></tr>';
    }

    if (function_exists('vms_vendor_app_get_confirmation_state')) {
        $confirmation_state = vms_vendor_app_get_confirmation_state((int) $post->ID);
        $confirmation_label = function_exists('vms_vendor_app_confirmation_state_label')
            ? vms_vendor_app_confirmation_state_label($confirmation_state)
            : ucfirst($confirmation_state);
        echo '<tr><th style="width:160px;">' . esc_html__('Email Confirmation', 'backstage-venue-manager') . '</th><td>' . esc_html($confirmation_label) . '</td></tr>';

        $confirmed_at_key = vms_vendor_app_meta_key('email_confirmed_at') ?: '_vms_app_email_confirmed_at';
        $review_ready_key = vms_vendor_app_meta_key('review_ready_at') ?: '_vms_app_review_ready_at';
        $confirmed_at = trim((string) get_post_meta((int) $post->ID, $confirmed_at_key, true));
        $review_ready_at = trim((string) get_post_meta((int) $post->ID, $review_ready_key, true));
        if ($confirmed_at !== '') {
            echo '<tr><th style="width:160px;">' . esc_html__('Email Confirmed At', 'backstage-venue-manager') . '</th><td>' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $confirmed_at, true)) . '</td></tr>';
        }
        if ($review_ready_at !== '') {
            echo '<tr><th style="width:160px;">' . esc_html__('Review Ready At', 'backstage-venue-manager') . '</th><td>' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $review_ready_at, true)) . '</td></tr>';
        }
    }

    echo '</table>';
}

function vms_vendor_applications_metabox_actions($post): void
{
    $post_id = ($post instanceof WP_Post) ? (int) $post->ID : 0;
    if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
        echo '<p>' . esc_html__('You do not have permission to update applications.', 'backstage-venue-manager') . '</p>';
        return;
    }

    $status = vms_vendor_app_get_status($post_id);
    $labels = vms_vendor_app_statuses();
    $vendor_id = (int) get_post_meta($post_id, '_vms_vendor_id', true);

    echo '<p><strong>' . esc_html__('Status:', 'backstage-venue-manager') . '</strong> ';
    echo '<span class="vms-status-pill ' . esc_attr(vms_vendor_app_status_pill_class($status)) . '">' . esc_html($labels[$status] ?? $status) . '</span></p>';

    $confirmation_state = function_exists('vms_vendor_app_get_confirmation_state')
        ? vms_vendor_app_get_confirmation_state((int) $post->ID)
        : 'confirmed';
    $confirmation_label = function_exists('vms_vendor_app_confirmation_state_label')
        ? vms_vendor_app_confirmation_state_label($confirmation_state)
        : ucfirst($confirmation_state);
    echo '<p><strong>' . esc_html__('Email Confirmation:', 'backstage-venue-manager') . '</strong> ' . esc_html($confirmation_label) . '</p>';

    if ($vendor_id > 0) {
        $edit_vendor = get_edit_post_link($vendor_id, '');
        if ($edit_vendor) {
            echo '<p><strong>' . esc_html__('Linked Vendor:', 'backstage-venue-manager') . '</strong><br>';
            echo '<a href="' . esc_url($edit_vendor) . '">' . esc_html(get_the_title($vendor_id)) . '</a></p>';
        }
    }

    $vendor_ok = ($vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT);

    if ($status === 'approved' && !$vendor_ok) {
        $repair_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_vendor_app_repair_vendor&app_id=' . $post_id),
            'vms_vendor_app_repair_vendor_' . $post_id
        );
        echo '<p><a class="button button-primary" href="' . esc_url($repair_url) . '">'
            . esc_html__('Create Vendor Now', 'backstage-venue-manager')
            . '</a></p>';
        echo '<p style="color:#92400e;margin-top:-6px;">'
            . esc_html__('This application is approved, but no vendor exists yet.', 'backstage-venue-manager')
            . '</p>';
    }

    $email = sanitize_email((string) get_post_meta($post_id, '_vms_app_email', true));
    $last_message = trim((string) get_post_meta($post_id, '_vms_app_last_response_message', true));
    $last_sent_at = trim((string) get_post_meta($post_id, '_vms_app_last_response_sent_at', true));
    $last_sent_to = sanitize_email((string) get_post_meta($post_id, '_vms_app_last_response_sent_to', true));
    $internal_note = trim((string) get_post_meta($post_id, '_vms_app_operator_internal_note', true));

    echo '<hr>';
    echo '<p><strong>' . esc_html__('Operator response', 'backstage-venue-manager') . '</strong></p>';
    echo '<p class="description">' . esc_html__('Use Holding / Keep on File when the applicant may be a fit later but should not be moved into active vendor records yet. Approving creates or syncs the vendor profile.', 'backstage-venue-manager') . '</p>';
    if ($confirmation_state !== 'confirmed') {
        echo '<p class="description" style="color:#92400e;">' . esc_html__('This application is not review-ready yet. The applicant must confirm their email before operators can approve, hold, or reject it.', 'backstage-venue-manager') . '</p>';
    }

    wp_nonce_field('vms_vendor_app_decision_' . $post_id, 'vms_vendor_app_decision_nonce');
    echo '<input type="hidden" name="vms_vendor_app_admin_fields_present" value="1">';

    echo '<p><label for="vms-app-decision-message"><strong>' . esc_html__('Message to applicant', 'backstage-venue-manager') . '</strong></label></p>';
    echo '<textarea id="vms-app-decision-message" name="vms_vendor_app_decision_message" rows="7" style="width:100%;">' . esc_textarea(vms_vendor_app_default_response_message($post_id, $status === 'pending' ? 'holding' : $status)) . '</textarea>';

    if ($email !== '') {
        /* translators: %s: email address. */
        echo '<p><label><input type="checkbox" name="vms_vendor_app_send_response_email" value="1" checked> ' . esc_html(sprintf(__('Email this message to %s', 'backstage-venue-manager'), $email)) . '</label></p>';
    } else {
        echo '<p class="description">' . esc_html__('No applicant email is saved, so this decision can be recorded but not emailed automatically.', 'backstage-venue-manager') . '</p>';
    }

    echo '<p><label for="vms-app-internal-note"><strong>' . esc_html__('Internal note', 'backstage-venue-manager') . '</strong></label></p>';
    echo '<textarea id="vms-app-internal-note" name="vms_vendor_app_operator_internal_note" rows="4" style="width:100%;" placeholder="' . esc_attr__('Private operator note. Not emailed to applicant.', 'backstage-venue-manager') . '">' . esc_textarea($internal_note) . '</textarea>';

    if ($confirmation_state === 'confirmed') {
        echo '<p style="display:flex;gap:6px;flex-wrap:wrap;">';
        if ($status !== 'holding') {
            echo '<button type="submit" class="button" name="vms_vendor_app_decision" value="holding">' . esc_html__('Move to Holding', 'backstage-venue-manager') . '</button>';
        }
        if ($status !== 'approved') {
            echo '<button type="submit" class="button button-primary" name="vms_vendor_app_decision" value="approved">' . esc_html__('Approve', 'backstage-venue-manager') . '</button>';
        }
        if ($status !== 'rejected') {
            echo '<button type="submit" class="button" style="border-color:#b91c1c;color:#b91c1c;" name="vms_vendor_app_decision" value="rejected">' . esc_html__('Reject', 'backstage-venue-manager') . '</button>';
        }
        if ($status !== 'pending') {
            echo '<button type="submit" class="button" name="vms_vendor_app_decision" value="pending">' . esc_html__('Return to Pending', 'backstage-venue-manager') . '</button>';
        }
        echo '</p>';
    }

    echo '<p><button type="submit" class="button" name="vms_vendor_app_save_operator_note" value="1">' . esc_html__('Save Note Only', 'backstage-venue-manager') . '</button></p>';

    if ($last_message !== '' || $last_sent_at !== '') {
        echo '<hr>';
        echo '<p><strong>' . esc_html__('Last response', 'backstage-venue-manager') . '</strong></p>';
        if ($last_sent_at !== '') {
            $sent_label = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_sent_at, true);
            /* translators: %s: human-readable value used in this message. */
            echo '<p class="description">' . esc_html(sprintf(__('Recorded %s', 'backstage-venue-manager'), $sent_label));
            if ($last_sent_to !== '') {
                /* translators: %s: to. */
                echo esc_html(' - ' . sprintf(__('To: %s', 'backstage-venue-manager'), $last_sent_to));
            }
            echo '</p>';
        }
        if ($last_message !== '') {
            echo '<div style="border-left:3px solid #d1d5db;padding-left:8px;white-space:pre-wrap;max-height:160px;overflow:auto;">' . esc_html($last_message) . '</div>';
        }
    }
}

if (!function_exists('vms_vendor_applications_handle_edit_screen_decision')) {
    add_action('save_post_' . VMS_VENDOR_APP_CPT, 'vms_vendor_applications_handle_edit_screen_decision', 20, 3);
    function vms_vendor_applications_handle_edit_screen_decision(int $post_id, WP_Post $post, bool $update): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (empty($_POST['vms_vendor_app_admin_fields_present'])) {
            return;
        }
        $nonce = (isset($_POST['vms_vendor_app_decision_nonce']) && !is_array($_POST['vms_vendor_app_decision_nonce']))
            ? sanitize_text_field(wp_unslash((string) $_POST['vms_vendor_app_decision_nonce']))
            : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'vms_vendor_app_decision_' . (int) $post_id)) {
            return;
        }

        $internal_note = vms_request_read_textarea_field($_POST, 'vms_vendor_app_operator_internal_note');
        update_post_meta($post_id, '_vms_app_operator_internal_note', $internal_note);

        $decision = vms_request_read_key($_POST, 'vms_vendor_app_decision');
        $statuses = vms_vendor_app_statuses();
        if ($decision === '' || !isset($statuses[$decision])) {
            return;
        }

        $confirmation_state = function_exists('vms_vendor_app_get_confirmation_state')
            ? vms_vendor_app_get_confirmation_state($post_id)
            : 'confirmed';
        if ($confirmation_state !== 'confirmed' && in_array($decision, array('holding', 'approved', 'rejected'), true)) {
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('This application cannot be reviewed yet because the applicant has not confirmed the application email.', 'backstage-venue-manager'), 'error');
            }
            return;
        }

        $from_status = vms_vendor_app_get_status($post_id);
        $message = vms_request_read_textarea_field($_POST, 'vms_vendor_app_decision_message');
        $shown_default_status = ($from_status === 'pending') ? 'holding' : $from_status;
        $shown_default_message = vms_vendor_app_default_response_message($post_id, $shown_default_status);
        if (trim($message) === '' || trim($message) === trim($shown_default_message)) {
            $message = vms_vendor_app_default_response_message($post_id, $decision);
        }
        $send_email = vms_request_read_bool_flag($_POST, 'vms_vendor_app_send_response_email');
        $block_approved_email = false;

        if ($decision === 'approved') {
            $vendor_id = vms_vendor_app_get_or_create_vendor($post_id);
            if ($vendor_id <= 0) {
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(__('Vendor approval could not complete because the vendor profile could not be created.', 'backstage-venue-manager'), 'error');
                }
                return;
            }

            $resolved_user_id = function_exists('vms_vendor_app_get_submitting_user_id')
                ? vms_vendor_app_get_submitting_user_id($post_id)
                : 0;
            $resolved_user = $resolved_user_id > 0 ? get_userdata($resolved_user_id) : false;
            $link_result = ($resolved_user instanceof WP_User)
                ? vms_vendor_app_link_submitting_user_to_vendor($post_id, $vendor_id, (int) get_current_user_id())
                : new WP_Error('vms_vendor_app_missing_confirmed_user', __('Vendor was created, but no confirmed website account is attached to this application yet.', 'backstage-venue-manager'));
            if (is_wp_error($link_result)) {
                $block_approved_email = true;
                if (function_exists('vms_add_admin_notice')) {
                    vms_add_admin_notice(
                        __('Vendor was created, but the confirmed website account could not be linked automatically. Review the vendor user link before notifying the applicant.', 'backstage-venue-manager'),
                        'error'
                    );
                }
            }
        }

        vms_vendor_app_set_status($post_id, $decision);
        update_post_meta($post_id, '_vms_app_last_operator_decision_at', current_time('mysql'));
        update_post_meta($post_id, '_vms_app_last_operator_decision_by', (int) get_current_user_id());

        if (function_exists('vms_approvals_queue_record_transition') && $from_status !== $decision) {
            vms_approvals_queue_record_transition('vendor_applications', $post_id, $from_status, $decision);
        }

        if ($decision === 'approved' && $block_approved_email) {
            $send_email = false;
            if (function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Approval was recorded, but the approved email was blocked because portal access is not linked to a valid confirmed website account yet.', 'backstage-venue-manager'), 'warning');
            }
        }

        if ($send_email) {
            $sent = vms_vendor_app_send_response_email($post_id, $decision, $message, (int) get_current_user_id());
            if (!$sent && function_exists('vms_add_admin_notice')) {
                vms_add_admin_notice(__('Application status was updated, but the response email could not be sent. Check the applicant email and site mail settings.', 'backstage-venue-manager'), 'warning');
            }
        } else {
            update_post_meta($post_id, '_vms_app_last_response_status', sanitize_key($decision));
            update_post_meta($post_id, '_vms_app_last_response_message', $message);
            update_post_meta($post_id, '_vms_app_last_response_sent_at', current_time('mysql'));
            update_post_meta($post_id, '_vms_app_last_response_email_sent', '0');
            update_post_meta($post_id, '_vms_app_last_response_sent_by', (int) get_current_user_id());
        }
    }
}

/**
 * Admin-post handlers: approve / reject
 */
add_action('admin_post_vms_vendor_app_approve', 'vms_vendor_applications_handle_approve');
function vms_vendor_applications_handle_approve(): void
{
    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');
    if (!current_user_can('edit_post', $app_id)) wp_die('Forbidden');

    check_admin_referer('vms_vendor_app_approve_' . $app_id);

    $app = get_post($app_id);
    if (!$app || empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) wp_die('Invalid application');
    if (function_exists('vms_vendor_app_get_confirmation_state') && vms_vendor_app_get_confirmation_state($app_id) !== 'confirmed') {
        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('This application cannot be approved until the applicant confirms the application email.', 'backstage-venue-manager'), 'error');
        }
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }
    $from_status = vms_vendor_app_get_status($app_id);

    $vendor_id = vms_vendor_app_get_or_create_vendor($app_id);
    if ($vendor_id <= 0) {
        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('Vendor approval could not complete because the vendor profile could not be created.', 'backstage-venue-manager'), 'error');
        }
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    $resolved_user_id = function_exists('vms_vendor_app_get_submitting_user_id')
        ? vms_vendor_app_get_submitting_user_id($app_id)
        : 0;
    $resolved_user = $resolved_user_id > 0 ? get_userdata($resolved_user_id) : false;
    $link_result = ($resolved_user instanceof WP_User)
        ? vms_vendor_app_link_submitting_user_to_vendor($app_id, $vendor_id, (int) get_current_user_id())
        : new WP_Error('vms_vendor_app_missing_confirmed_user', __('Vendor was created, but no confirmed website account is attached to this application yet.', 'backstage-venue-manager'));
    if (is_wp_error($link_result) && function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            __('Vendor was created, but the confirmed website account could not be linked automatically. Review the vendor user link before notifying the applicant.', 'backstage-venue-manager'),
            'error'
        );
    }

    vms_vendor_app_set_status($app_id, 'approved');
    if (function_exists('vms_approvals_queue_record_transition')) {
        vms_approvals_queue_record_transition('vendor_applications', $app_id, $from_status, 'approved');
    }

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_reject', 'vms_vendor_applications_handle_reject');
function vms_vendor_applications_handle_reject(): void
{
    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');
    if (!current_user_can('edit_post', $app_id)) wp_die('Forbidden');

    check_admin_referer('vms_vendor_app_reject_' . $app_id);

    $app = get_post($app_id);
    if (!$app || empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) wp_die('Invalid application');
    if (function_exists('vms_vendor_app_get_confirmation_state') && vms_vendor_app_get_confirmation_state($app_id) !== 'confirmed') {
        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('This application cannot be rejected until the applicant confirms the application email.', 'backstage-venue-manager'), 'error');
        }
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }
    $from_status = vms_vendor_app_get_status($app_id);

    vms_vendor_app_set_status($app_id, 'rejected');
    if (function_exists('vms_approvals_queue_record_transition')) {
        vms_approvals_queue_record_transition('vendor_applications', $app_id, $from_status, 'rejected');
    }

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_repair_vendor', 'vms_vendor_applications_handle_repair_vendor');
function vms_vendor_applications_handle_repair_vendor(): void
{
    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');
    if (!current_user_can('edit_post', $app_id)) wp_die('Forbidden');

    check_admin_referer('vms_vendor_app_repair_vendor_' . $app_id);

    $app = get_post($app_id);
    if (!$app || empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) wp_die('Invalid application');

    // Only makes sense for approved apps
    $status = vms_vendor_app_get_status($app_id);
    if ($status !== 'approved') {
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    $vendor_id = vms_vendor_app_get_or_create_vendor($app_id);
    if ($vendor_id <= 0) {
        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('Vendor repair could not create the missing vendor profile.', 'backstage-venue-manager'), 'error');
        }
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    $link_result = vms_vendor_app_link_submitting_user_to_vendor($app_id, $vendor_id, (int) get_current_user_id());
    if (is_wp_error($link_result) && function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            __('Vendor profile was repaired, but the submitting website account could not be linked automatically.', 'backstage-venue-manager'),
            'error'
        );
    }

    // Back to the applications list
    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

add_action('admin_post_vms_vendor_app_resync_vendor', 'vms_vendor_applications_handle_resync_vendor');
function vms_vendor_applications_handle_resync_vendor(): void
{
    $app_id = isset($_GET['app_id']) ? (int) $_GET['app_id'] : 0;
    if ($app_id <= 0) wp_die('Missing app_id');
    if (!current_user_can('edit_post', $app_id)) wp_die('Forbidden');

    check_admin_referer('vms_vendor_app_resync_vendor_' . $app_id);

    $app = get_post($app_id);
    if (!$app || empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) wp_die('Invalid application');

    $status = vms_vendor_app_get_status($app_id);
    if ($status !== 'approved') {
        // Only resync approved applications (keeps workflow clean)
        wp_safe_redirect(admin_url('post.php?post=' . $app_id . '&action=edit'));
        exit;
    }

    $vendor_id = vms_vendor_app_get_or_create_vendor($app_id);
    if ($vendor_id <= 0) {
        if (function_exists('vms_add_admin_notice')) {
            vms_add_admin_notice(__('Failed to create vendor during sync.', 'backstage-venue-manager'), 'error');
        }
        wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
        exit;
    }

    // Copy app meta -> vendor meta (idempotent)
    $copied = vms_vendor_app_sync_vendor_from_application($app_id, (int)$vendor_id);

    $link_result = vms_vendor_app_link_submitting_user_to_vendor($app_id, (int) $vendor_id, (int) get_current_user_id());
    if (is_wp_error($link_result) && function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            __('Vendor data synced, but the submitting website account could not be linked automatically.', 'backstage-venue-manager'),
            'error'
        );
    }

    // Optional admin notice
    if (function_exists('vms_add_admin_notice')) {
        vms_add_admin_notice(
            sprintf('Vendor data synced. %d fields updated.', (int)$copied),
            'success'
        );
    }

    wp_safe_redirect(admin_url('edit.php?post_type=' . VMS_VENDOR_APP_CPT));
    exit;
}

/**
 * Turnstile integration for [vms_vendor_apply]
 * Lightweight: no plugin dependency.
 *
 * Cloudflare docs: you must verify tokens server-side via siteverify.
 */
function vms_vendor_apply_turnstile_site_key(): string
{
    $k = trim((string) get_option('vms_turnstile_site_key', ''));

    // Back-compat: allow keys saved inside the main vms_settings array.
    if ($k === '') {
        $settings = get_option('vms_settings', array());
        if (is_array($settings)) {
            foreach (array('vms_turnstile_site_key', 'turnstile_site_key', 'cf_turnstile_site_key', 'cloudflare_turnstile_site_key', 'cfturnstile_key') as $settings_key) {
                if (!isset($settings[$settings_key]) || !is_scalar($settings[$settings_key])) {
                    continue;
                }
                $candidate = trim((string) $settings[$settings_key]);
                if ($candidate !== '') {
                    $k = $candidate;
                    break;
                }
            }
        }
    }

    // Fallbacks for sites that store these as standalone options.
    if ($k === '') {
        foreach (array('cf_turnstile_site_key', 'turnstile_site_key', 'cloudflare_turnstile_site_key', 'cfturnstile_key') as $opt_key) {
            $raw = get_option($opt_key, '');
            if (!is_scalar($raw)) {
                continue;
            }
            $candidate = trim((string) $raw);
            if ($candidate !== '') {
                $k = $candidate;
                break;
            }
        }
    }

    /**
     * Optional override via wp-config.php:
     * define('VMS_TURNSTILE_SITE_KEY', '…');
     */
    if (defined('VMS_TURNSTILE_SITE_KEY') && VMS_TURNSTILE_SITE_KEY) {
        $k = trim((string) VMS_TURNSTILE_SITE_KEY);
    } elseif (defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY) {
        // Back-compat: support Cloudflare-style constant names used by some setups.
        $k = trim((string) CF_TURNSTILE_SITE_KEY);
    }

    return (string) apply_filters('vms_vendor_apply_turnstile_site_key', $k);
}

function vms_vendor_apply_turnstile_secret_key(): string
{
    $k = trim((string) get_option('vms_turnstile_secret_key', ''));

    // Back-compat: allow keys saved inside the main vms_settings array.
    if ($k === '') {
        $settings = get_option('vms_settings', array());
        if (is_array($settings)) {
            foreach (array('vms_turnstile_secret_key', 'turnstile_secret_key', 'cf_turnstile_secret_key', 'cloudflare_turnstile_secret_key', 'cfturnstile_secret') as $settings_key) {
                if (!isset($settings[$settings_key]) || !is_scalar($settings[$settings_key])) {
                    continue;
                }
                $candidate = trim((string) $settings[$settings_key]);
                if ($candidate !== '') {
                    $k = $candidate;
                    break;
                }
            }
        }
    }

    // Fallbacks for sites that store these as standalone options.
    if ($k === '') {
        foreach (array('cf_turnstile_secret_key', 'turnstile_secret_key', 'cloudflare_turnstile_secret_key', 'cfturnstile_secret') as $opt_key) {
            $raw = get_option($opt_key, '');
            if (!is_scalar($raw)) {
                continue;
            }
            $candidate = trim((string) $raw);
            if ($candidate !== '') {
                $k = $candidate;
                break;
            }
        }
    }

    /**
     * Optional override via wp-config.php:
     * define('VMS_TURNSTILE_SECRET_KEY', '…');
     */
    if (defined('VMS_TURNSTILE_SECRET_KEY') && VMS_TURNSTILE_SECRET_KEY) {
        $k = trim((string) VMS_TURNSTILE_SECRET_KEY);
    } elseif (defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY) {
        // Back-compat: support Cloudflare-style constant names used by some setups.
        $k = trim((string) CF_TURNSTILE_SECRET_KEY);
    }

    return (string) apply_filters('vms_vendor_apply_turnstile_secret_key', $k);
}

/**
 * Simple rate limit: blocks rapid-fire spam even if CAPTCHA is bypassed.
 * Default: max 3 submissions per IP per hour.
 */
function vms_vendor_apply_is_rate_limited(): bool
{
    $ip = vms_request_remote_addr();

    if ($ip === '') return false;

    $key = 'vms_vapp_rl_' . md5($ip);
    $count = (int) get_transient($key);

    $count++;
    set_transient($key, $count, HOUR_IN_SECONDS);

    $limit = (int) apply_filters('vms_vendor_apply_rate_limit_per_hour', 3);

    return ($count > $limit);
}

/**
 * Verify Turnstile token with Cloudflare siteverify.
 * Expects token posted as cf-turnstile-response (auto-added by widget in a <form>).
 */
function vms_vendor_apply_verify_turnstile(): bool
{
    $site_key = vms_vendor_apply_turnstile_site_key();
    $secret   = vms_vendor_apply_turnstile_secret_key();

    // If keys are not configured, fail closed to stop spam.
    if ($site_key === '' || $secret === '') {
        error_log('[VMS] vendor-apply: Turnstile keys missing; blocking submission.');
        return false;
    }

    $token = vms_request_read_scalar($_POST, 'cf-turnstile-response');
    if (strlen($token) > 4096) {
        $token = substr($token, 0, 4096);
    }

    if ($token === '') {
        return false;
    }

    $ip = vms_request_remote_addr();

    $resp = wp_remote_post(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        array(
            'timeout' => 8,
            'body'    => array(
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ),
        )
    );

    if (is_wp_error($resp)) {
        error_log('[VMS] vendor-apply: Turnstile siteverify request failed: ' . $resp->get_error_message());
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = (string) wp_remote_retrieve_body($resp);

    if ($code < 200 || $code >= 300 || $body === '') {
        error_log('[VMS] vendor-apply: Turnstile siteverify non-2xx or empty body. HTTP ' . $code);
        return false;
    }

    $json = json_decode($body, true);
    if (!is_array($json)) return false;

    return !empty($json['success']);
}

/**
 * Store basic request fingerprint for admin review.
 */
function vms_vendor_apply_request_fingerprint(): array
{
    return array(
        'ip' => vms_request_remote_addr(),
        'ua' => vms_request_user_agent(),
    );
}

if (!function_exists('vms_vendor_apply_render_notice')) {
    function vms_vendor_apply_render_notice(string $type, string $headline, string $body = ''): string
    {
        $type = ($type === 'success' || $type === 'warning') ? $type : 'error';
        $html = '<div class="vms-notice vms-notice-' . esc_attr($type) . ' vms-vendor-apply-notice">';
        $html .= '<p><strong>' . esc_html($headline) . '</strong></p>';
        if (trim($body) !== '') {
            $html .= '<p>' . esc_html($body) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('vms_vendor_apply_render_success_screen')) {
    function vms_vendor_apply_render_success_screen(bool $is_logged_in_submitter, bool $is_portal_add_flow): string
    {
        $portal_url = function_exists('vms_vendor_app_get_portal_page_url')
            ? vms_vendor_app_get_portal_page_url()
            : home_url('/vendor-portal/?tab=dashboard');
        $apply_url = function_exists('vms_vendor_app_get_application_page_url')
            ? vms_vendor_app_get_application_page_url()
            : home_url('/vendor-application/');

        $headline = $is_logged_in_submitter
            ? __('Application received. Your email is already confirmed, so the application is now ready for review.', 'backstage-venue-manager')
            : __('Application received. Your application is now ready for review.', 'backstage-venue-manager');
        $intro = $is_logged_in_submitter
            ? __('Your submission was saved successfully and is already in the operator review queue. Approval is still manual, so Vendor Portal access does not change until an operator approves the application.', 'backstage-venue-manager')
            : __('Your submission was saved successfully and is already in the operator review queue. Approval is still manual, so Vendor Portal access does not change until an operator approves the application.', 'backstage-venue-manager');

        $steps = array(
            __('An operator will review your application details.', 'backstage-venue-manager'),
            __('Please watch your email, including spam or junk folders, for the review outcome and next-step instructions.', 'backstage-venue-manager'),
            __('If you are approved, we will email you with Vendor Portal guidance. Vendor tools live in the Vendor Portal, not WooCommerce My Account.', 'backstage-venue-manager'),
        );

        if ($is_logged_in_submitter && $is_portal_add_flow) {
            $steps[] = __('Because you submitted this while signed in, the approval-time vendor linking can use your current website account if the operator approves the application.', 'backstage-venue-manager');
        } elseif ($is_logged_in_submitter) {
            $steps[] = __('Because you submitted this while signed in, the application is already tied to your current website account for approval-time linking if the operator approves it.', 'backstage-venue-manager');
        }

        ob_start();
        ?>
        <section class="vms-vendor-apply-confirmation">
            <div class="vms-vendor-apply-confirmation__notice vms-notice vms-notice-success">
                <p><strong><?php echo esc_html($headline); ?></strong></p>
                <p><?php echo esc_html($intro); ?></p>
            </div>

            <div class="vms-vendor-apply-confirmation__card">
                <span class="vms-vendor-apply-confirmation__kicker"><?php echo esc_html__('What happens next', 'backstage-venue-manager'); ?></span>
                <h2><?php echo esc_html__('Next Steps', 'backstage-venue-manager'); ?></h2>
                <p><?php echo esc_html__('You do not need to re-submit unless we ask for something else. Keep an eye on the email address entered on the application.', 'backstage-venue-manager'); ?></p>
                <ol class="vms-vendor-apply-confirmation__steps">
                    <?php foreach ($steps as $step) : ?>
                        <li><?php echo esc_html($step); ?></li>
                    <?php endforeach; ?>
                </ol>
                <div class="vms-vendor-apply-confirmation__actions">
                    <a class="button" href="<?php echo esc_url($portal_url); ?>"><?php echo esc_html__('Open Vendor Portal', 'backstage-venue-manager'); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url($apply_url); ?>"><?php echo esc_html__('View Application Form', 'backstage-venue-manager'); ?></a>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Shortcode: [vms_vendor_apply]
 */
add_shortcode('vms_vendor_apply', 'vms_vendor_apply_shortcode');
function vms_vendor_apply_shortcode($atts = array(), $content = ''): string
{
    // Handle submission first
    if (vms_request_method() === 'post' && isset($_POST['vms_vendor_apply_submit'])) {
        return vms_vendor_apply_handle_frontend_post();
    }

    $msg = '';
    $flag = vms_request_read_key($_GET, 'vms_app');
    $is_logged_in_submitter = is_user_logged_in();
    $is_portal_add_flow = $is_logged_in_submitter && vms_request_read_bool_flag($_GET, 'vms_from_portal');
    $prefill_email = '';
    if ($is_logged_in_submitter) {
        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User) {
            $prefill_email = sanitize_email((string) $current_user->user_email);
        }
    }

    if ($flag === 'success') {
        return vms_vendor_apply_render_success_screen($is_logged_in_submitter, $is_portal_add_flow);
    } elseif (($flag === 'confirm_pending' || $flag === 'confirm_sent' || $flag === 'confirm_resent') && !empty($_GET['vms_app_ref']) && function_exists('vms_vendor_app_find_application_by_public_lookup_key') && function_exists('vms_vendor_apply_render_confirmation_pending_screen')) {
        $app_id = vms_vendor_app_find_application_by_public_lookup_key((string) wp_unslash($_GET['vms_app_ref']));
        $notice = isset($_GET['vms_app_notice']) ? sanitize_key((string) wp_unslash($_GET['vms_app_notice'])) : '';
        if ($notice === '') {
            if ($flag === 'confirm_resent') {
                $notice = 'resent';
            } elseif ($flag === 'confirm_pending') {
                $notice = 'pending';
            } else {
                $notice = 'sent';
            }
        }
        return vms_vendor_apply_render_confirmation_pending_screen((int) $app_id, array('notice' => $notice));
    } elseif (($flag === 'already_pending' || $flag === 'already_holding' || $flag === 'already_approved') && !empty($_GET['vms_app_ref']) && function_exists('vms_vendor_app_find_application_by_public_lookup_key') && function_exists('vms_vendor_apply_render_existing_status_screen')) {
        $app_id = vms_vendor_app_find_application_by_public_lookup_key((string) wp_unslash($_GET['vms_app_ref']));
        $kind = ($flag === 'already_holding') ? 'holding' : (($flag === 'already_approved') ? 'approved' : 'pending');
        return vms_vendor_apply_render_existing_status_screen((int) $app_id, $kind);
    } elseif ($flag === 'error') {
        $msg = vms_vendor_apply_render_notice('error', __('Something went wrong.', 'backstage-venue-manager'), __('Please try again or email us.', 'backstage-venue-manager'));
    } elseif ($flag === 'nonce') {
        $msg = vms_vendor_apply_render_notice('error', __('Security check failed.', 'backstage-venue-manager'), __('Please refresh and try again.', 'backstage-venue-manager'));
    } elseif ($flag === 'captcha') {
        $msg = vms_vendor_apply_render_notice('error', __('Verification failed.', 'backstage-venue-manager'), __('Please try again.', 'backstage-venue-manager'));
    } elseif ($flag === 'rate') {
        $msg = vms_vendor_apply_render_notice('error', __('Please slow down.', 'backstage-venue-manager'), __('Try again in a bit.', 'backstage-venue-manager'));
    } elseif ($flag === 'band_required') {
        $msg = vms_vendor_apply_render_notice('error', __('Please complete the band booking details.', 'backstage-venue-manager'), __('Add your turnout estimate and requested compensation to continue.', 'backstage-venue-manager'));
    }

    if ($is_logged_in_submitter && $flag !== 'success') {
        $context_copy = $is_portal_add_flow
            ? __('You’re adding another business while signed in. If you submit the same email as your current website account, the application can move straight into review. If you submit a different email, that email must be confirmed first.', 'backstage-venue-manager')
            : __('You are signed in. If the application uses the same email as your current website account, that login is enough to move it into review. If you submit a different email, that email must be confirmed before review can start.', 'backstage-venue-manager');
        $msg .= vms_vendor_apply_render_notice('success', __('Signed-in submission detected.', 'backstage-venue-manager'), $context_copy);
    }

    // Turnstile: load script and render widget only when configured
    $site_key = vms_vendor_apply_turnstile_site_key();
    if ($site_key !== '') {
        wp_enqueue_script(
            'cf-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            array(),
            null,
            true
        );
    } else {
        // Optional: show admins a hint (public visitors do not need to see this)
        if (current_user_can('manage_options')) {
            $msg .= vms_vendor_apply_render_notice(
                'error',
                __('Turnstile is not configured.', 'backstage-venue-manager'),
                __('Set keys via vms_turnstile_* options, or add VMS_TURNSTILE_* / CF_TURNSTILE_* constants in wp-config.php.', 'backstage-venue-manager')
            );
        }
    }

    ob_start();
    echo '<div class="vms-vendor-apply-flow">';
    echo $msg;
    ?>
    <form method="post" class="vms-vendor-apply-form">
        <?php wp_nonce_field('vms_vendor_apply', 'vms_vendor_apply_nonce'); ?>

        <p>
            <label><strong><?php echo esc_html__('Vendor Type', 'backstage-venue-manager'); ?></strong></label><br>
            <select name="vms_app_vendor_type" id="vms-app-vendor-type" required>
                <option value=""><?php echo esc_html__('Select…', 'backstage-venue-manager'); ?></option>
                <?php foreach (vms_vendor_app_selectable_vendor_types() as $type_slug => $type_label) : ?>
                    <option value="<?php echo esc_attr((string) $type_slug); ?>"><?php echo esc_html((string) $type_label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label><strong><span id="vms-app-name-label"><?php echo esc_html__('Business / Vendor Name', 'backstage-venue-manager'); ?></span></strong></label><br>
            <input type="text" name="vms_app_name" required class="vms-app-input-standard">
        </p>

        <p>
            <label><strong><?php echo esc_html__('Primary Contact Name (optional)', 'backstage-venue-manager'); ?></strong></label><br>
            <input type="text" name="vms_app_contact_name" class="vms-app-input-standard">
        </p>

        <p>
            <label><strong>Email</strong></label><br>
            <input type="email" name="vms_app_email" required class="vms-app-input-standard" value="<?php echo esc_attr($prefill_email); ?>">
        </p>

        <p>
            <label><strong><?php echo esc_html__('Primary Contact Phone (optional)', 'backstage-venue-manager'); ?></strong></label><br>
            <input type="text" name="vms_app_phone" class="vms-app-input-standard">
        </p>

        <p>
            <label><strong>Home Base (City/State)</strong></label><br>
            <input type="text" name="vms_app_location" class="vms-app-input-standard">
        </p>

        <p>
            <label><strong><span id="vms-app-website-label"><?php echo esc_html__('Website URL (optional)', 'backstage-venue-manager'); ?></span></strong></label><br>
            <input type="url" name="vms_app_website" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('https://…', 'backstage-venue-manager'); ?>">
        </p>

        <div class="vms-app-fields vms-app-band" hidden>
            <div class="vms-app-band-intro">
                <div class="vms-app-band-intro__title"><?php echo esc_html__('Performance details', 'backstage-venue-manager'); ?></div>
                <p class="vms-app-band-intro__copy"><?php echo esc_html__('Performances here include:', 'backstage-venue-manager'); ?></p>
                <ul class="vms-app-band-intro__list" aria-label="<?php echo esc_attr__('Included production support', 'backstage-venue-manager'); ?>">
                    <li><?php echo esc_html__('Full concert sound', 'backstage-venue-manager'); ?></li>
                    <li><?php echo esc_html__('Stage lighting', 'backstage-venue-manager'); ?></li>
                    <li><?php echo esc_html__('An experienced sound engineer', 'backstage-venue-manager'); ?></li>
                </ul>
                <p class="vms-app-band-intro__copy vms-app-band-intro__copy--muted"><?php echo esc_html__('We review requests based on fit, availability, expected turnout, promotion, and requested compensation. Quick estimates are fine.', 'backstage-venue-manager'); ?></p>
            </div>

            <p>
                <label><strong><?php echo esc_html__('Typical turnout for your shows in this region', 'backstage-venue-manager'); ?></strong></label><br>
                <select name="vms_app_turnout" data-vms-band-required="1" class="vms-app-input-standard">
                    <option value=""><?php echo esc_html__('Select…', 'backstage-venue-manager'); ?></option>
                    <?php foreach (vms_vendor_app_turnout_options() as $turnout_value => $turnout_label) : ?>
                        <option value="<?php echo esc_attr((string) $turnout_value); ?>"><?php echo esc_html((string) $turnout_label); ?></option>
                    <?php endforeach; ?>
                </select><br>
                <small><?php echo esc_html__('A best estimate is fine. Venue, market, and promotion can all affect attendance.', 'backstage-venue-manager'); ?></small>
            </p>

            <p>
                <label><strong><?php echo esc_html__('Requested compensation for a show like this', 'backstage-venue-manager'); ?></strong></label><br>
                <input type="text" name="vms_app_rate" data-vms-band-required="1" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('e.g. $800 guarantee or your usual range', 'backstage-venue-manager'); ?>"><br>
                <small><?php echo esc_html__('A rough range or usual offer is fine. Final details can vary by date, setup, lineup, and expected turnout.', 'backstage-venue-manager'); ?></small>
            </p>

            <p>
                <label><strong><?php echo esc_html__('Compensation notes (optional)', 'backstage-venue-manager'); ?></strong></label><br>
                <textarea name="vms_app_compensation_notes" rows="3" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('Anything that may affect your rate or offer structure?', 'backstage-venue-manager'); ?>"></textarea>
            </p>

            <p>
                <label><strong><?php echo esc_html__('Audience / following notes (optional)', 'backstage-venue-manager'); ?></strong></label><br>
                <textarea name="vms_app_audience_notes" rows="3" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('Tell us anything helpful about your audience, local reach, or similar past shows.', 'backstage-venue-manager'); ?>"></textarea>
            </p>

            <p>
                <label><strong>EPK Link (optional)</strong></label><br>
                <input type="url" name="vms_app_epk" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('https://…', 'backstage-venue-manager'); ?>">
            </p>
        </div>

        <div class="vms-app-fields vms-app-concession" hidden>
            <p>
                <label><strong><span id="vms-app-concession-label"><?php echo esc_html__('Cuisine / Food Type', 'backstage-venue-manager'); ?></span></strong></label><br>
                <input type="text" name="vms_app_cuisine" id="vms-app-concession-input" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('Tacos, BBQ, Burgers, Coffee, etc.', 'backstage-venue-manager'); ?>">
            </p>

            <p>
                <label><strong><span id="vms-app-concession-menu-label"><?php echo esc_html__('Menu Link (optional)', 'backstage-venue-manager'); ?></span></strong></label><br>
                <input type="url" name="vms_app_menu" id="vms-app-concession-menu-input" class="vms-app-input-standard" placeholder="<?php echo esc_attr__('https://…', 'backstage-venue-manager'); ?>">
            </p>
        </div>

        <div id="vms-app-social-group" hidden>
            <p class="vms-app-social-group-title"><strong id="vms-app-social-heading"><?php echo esc_html__('Social links (optional)', 'backstage-venue-manager'); ?></strong></p>
            <?php foreach (vms_vendor_app_social_field_map() as $slug => $field) : ?>
                <p class="vms-app-social-field" data-vms-social-slug="<?php echo esc_attr((string) $slug); ?>" hidden>
                    <label><strong><?php echo esc_html((string) $field['label']); ?></strong></label><br>
                    <input
                        type="url"
                        name="vms_app_social[<?php echo esc_attr((string) $slug); ?>]"
                        class="vms-app-input-standard"
                        placeholder="<?php echo esc_attr((string) ($field['placeholder'] ?? 'https://…')); ?>"
                        disabled
                    >
                </p>
            <?php endforeach; ?>
        </div>

        <p>
            <label><strong>Anything else we should know?</strong></label><br>
            <textarea name="vms_app_notes" rows="4" class="vms-app-input-standard"></textarea>
        </p>

        <?php if ($site_key !== ''): ?>
            <div class="vms-turnstile vms-vendor-apply-turnstile">
                <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
            </div>
        <?php endif; ?>

        <p>
            <button class="button button-primary" type="submit" name="vms_vendor_apply_submit" value="1">
                Submit Application
            </button>
        </p>

        <script>
            (function() {
                var sel = document.getElementById('vms-app-vendor-type');
                if (!sel) return;

                var variantMap = <?php echo wp_json_encode(vms_vendor_app_form_variant_map()); ?> || {};
                var bandSections = document.querySelectorAll('.vms-app-band');
                var concessionSections = document.querySelectorAll('.vms-app-concession');
                var socialGroup = document.getElementById('vms-app-social-group');
                var socialHeading = document.getElementById('vms-app-social-heading');
                var nameLabel = document.getElementById('vms-app-name-label');
                var websiteLabel = document.getElementById('vms-app-website-label');
                var concessionLabel = document.getElementById('vms-app-concession-label');
                var concessionInput = document.getElementById('vms-app-concession-input');
                var concessionMenuLabel = document.getElementById('vms-app-concession-menu-label');
                var socialFields = document.querySelectorAll('.vms-app-social-field');

                function setGroupState(elements, visible) {
                    elements.forEach(function(el) {
                        el.hidden = !visible;
                        el.querySelectorAll('input, select, textarea').forEach(function(field) {
                            field.disabled = !visible;
                            if (!visible) {
                                field.required = false;
                            }
                        });
                    });
                }

                function toggle() {
                    var v = sel.value;
                    var config = variantMap[v] || variantMap.default || {};
                    var visibleSocials = Array.isArray(config.visible_socials) ? config.visible_socials : [];
                    var socialCount = 0;

                    if (nameLabel) {
                        nameLabel.textContent = config.name_label || 'Business / Vendor Name';
                    }
                    if (websiteLabel) {
                        websiteLabel.textContent = config.website_label || 'Website URL (optional)';
                    }
                    if (socialHeading) {
                        socialHeading.textContent = config.social_heading || 'Social links (optional)';
                    }
                    if (concessionLabel) {
                        concessionLabel.textContent = config.concession_label || 'Cuisine / Food Type';
                    }
                    if (concessionInput) {
                        concessionInput.placeholder = config.concession_placeholder || 'Tacos, BBQ, Burgers, Coffee, etc.';
                    }
                    if (concessionMenuLabel) {
                        concessionMenuLabel.textContent = config.concession_menu_label || 'Menu Link (optional)';
                    }

                    setGroupState(bandSections, v === 'band');
                    document.querySelectorAll('[data-vms-band-required]').forEach(function(el) {
                        el.required = (v === 'band');
                    });

                    setGroupState(concessionSections, !!config.show_concession);

                    socialFields.forEach(function(wrapper) {
                        var slug = wrapper.getAttribute('data-vms-social-slug') || '';
                        var visible = v !== '' && visibleSocials.indexOf(slug) !== -1;
                        wrapper.hidden = !visible;
                        wrapper.querySelectorAll('input, select, textarea').forEach(function(field) {
                            field.disabled = !visible;
                        });
                        if (visible) {
                            socialCount += 1;
                        }
                    });

                    if (socialGroup) {
                        socialGroup.hidden = !(v !== '' && socialCount > 0);
                    }
                }

                sel.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    </form>
    <?php
    echo '</div>';

    return (string) ob_get_clean();
}
/**
 * Handle POST from frontend shortcode and redirect back with success/error flags.
 * IMPORTANT: do NOT echo here—shortcode context needs redirect to avoid resubmit.
 */
function vms_vendor_apply_handle_frontend_post(): string
{
    $nonce = (isset($_POST['vms_vendor_apply_nonce']) && !is_array($_POST['vms_vendor_apply_nonce']))
        ? sanitize_text_field(wp_unslash((string) $_POST['vms_vendor_apply_nonce']))
        : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'vms_vendor_apply')) {
        vms_vendor_apply_frontend_redirect('nonce');
        return '';
    }

    if (vms_vendor_apply_is_rate_limited()) {
        vms_vendor_apply_frontend_redirect('rate');
        return '';
    }

    // Cloudflare requires server-side validation via siteverify
    if (!vms_vendor_apply_verify_turnstile()) {
        vms_vendor_apply_frontend_redirect('captcha');
        return '';
    }

    $vendor_type = vms_vendor_app_normalize_vendor_type(vms_request_read_scalar($_POST, 'vms_app_vendor_type'));
    $name        = vms_request_read_text_field($_POST, 'vms_app_name');
    $email       = vms_request_read_email($_POST, 'vms_app_email');

    if (!$vendor_type || !$name || !$email) {
        vms_vendor_apply_frontend_redirect('error');
        return '';
    }

    $duplicate = function_exists('vms_vendor_app_find_duplicate_open_application')
        ? (array) vms_vendor_app_find_duplicate_open_application($email, $name)
        : array();
    if (!empty($duplicate['app_id']) && !empty($duplicate['duplicate_kind'])) {
        $duplicate_app_id = (int) $duplicate['app_id'];
        $duplicate_kind = sanitize_key((string) $duplicate['duplicate_kind']);
        if ($duplicate_kind === 'unconfirmed') {
            $notice = 'pending';
            if (function_exists('vms_vendor_app_send_confirmation_email')) {
                $resent = vms_vendor_app_send_confirmation_email($duplicate_app_id, array(
                    'source' => 'duplicate_submit',
                    'invalidate_reason' => 'duplicate_submit_rotation',
                ));
                if (!is_wp_error($resent)) {
                    $notice = 'resent';
                } elseif ($resent instanceof WP_Error) {
                    $map = array(
                        'vms_vendor_app_confirm_cooldown' => 'cooldown',
                        'vms_vendor_app_confirm_daily_cap' => 'daily_cap',
                        'vms_vendor_app_confirm_ip_throttle' => 'ip_throttle',
                        'vms_vendor_app_confirm_mail_failed' => 'mail_failed',
                    );
                    $notice = (string) ($map[$resent->get_error_code()] ?? 'pending');
                }
            }

            vms_vendor_apply_frontend_redirect('confirm_pending', array(
                'vms_app_ref' => function_exists('vms_vendor_app_get_public_lookup_key') ? vms_vendor_app_get_public_lookup_key($duplicate_app_id) : '',
                'vms_app_notice' => $notice,
            ));
            return '';
        }

        if ($duplicate_kind === 'pending') {
            vms_vendor_apply_frontend_redirect('already_pending', array(
                'vms_app_ref' => function_exists('vms_vendor_app_get_public_lookup_key') ? vms_vendor_app_get_public_lookup_key($duplicate_app_id) : '',
            ));
            return '';
        }

        if ($duplicate_kind === 'holding') {
            vms_vendor_apply_frontend_redirect('already_holding', array(
                'vms_app_ref' => function_exists('vms_vendor_app_get_public_lookup_key') ? vms_vendor_app_get_public_lookup_key($duplicate_app_id) : '',
            ));
            return '';
        }

        if ($duplicate_kind === 'approved') {
            vms_vendor_apply_frontend_redirect('already_approved', array(
                'vms_app_ref' => function_exists('vms_vendor_app_get_public_lookup_key') ? vms_vendor_app_get_public_lookup_key($duplicate_app_id) : '',
            ));
            return '';
        }
    }

    $contact_name = vms_request_read_text_field($_POST, 'vms_app_contact_name');
    $location = vms_request_read_text_field($_POST, 'vms_app_location');
    $phone    = vms_request_read_text_field($_POST, 'vms_app_phone');
    $website  = vms_vendor_app_sanitize_url_input(vms_request_read_scalar($_POST, 'vms_app_website'));
    $rate     = vms_request_read_text_field($_POST, 'vms_app_rate');
    $turnout  = vms_request_read_key($_POST, 'vms_app_turnout');
    if ($turnout !== '' && !array_key_exists($turnout, vms_vendor_app_turnout_options())) {
        $turnout = '';
    }
    $compensation_notes = vms_request_read_textarea_field($_POST, 'vms_app_compensation_notes');
    $audience_notes = vms_request_read_textarea_field($_POST, 'vms_app_audience_notes');
    if ($vendor_type === 'band' && ($rate === '' || $turnout === '')) {
        vms_vendor_apply_frontend_redirect('band_required');
        return '';
    }
    $epk      = vms_vendor_app_sanitize_url_input(vms_request_read_scalar($_POST, 'vms_app_epk'));
    $cuisine  = vms_request_read_text_field($_POST, 'vms_app_cuisine');
    $menu     = vms_vendor_app_sanitize_url_input(vms_request_read_scalar($_POST, 'vms_app_menu'));
    $social_inputs = array();
    $posted_social = array();
    if (isset($_POST['vms_app_social']) && is_array($_POST['vms_app_social'])) {
        $posted_social = wp_unslash($_POST['vms_app_social']);
        $posted_social = is_array($posted_social) ? $posted_social : array();
    }
    foreach (vms_vendor_app_social_field_map() as $slug => $field) {
        $social_inputs[$slug] = (isset($posted_social[$slug]) && is_scalar($posted_social[$slug]))
            ? vms_vendor_app_sanitize_url_input(trim((string) $posted_social[$slug]))
            : '';
    }
    $social   = vms_vendor_app_compose_social_legacy_blob($social_inputs);
    $notes    = vms_request_read_textarea_field($_POST, 'vms_app_notes');

    $app_id = wp_insert_post(array(
        'post_type'   => VMS_VENDOR_APP_CPT,
        'post_title'  => $name,
        'post_status' => 'publish',
    ), true);

    if (is_wp_error($app_id) || !$app_id) {
        vms_vendor_apply_frontend_redirect('error');
        return '';
    }

    update_post_meta($app_id, '_vms_app_vendor_type', $vendor_type);
    update_post_meta($app_id, '_vms_app_contact_name', $contact_name);
    update_post_meta($app_id, '_vms_app_email', $email);
    update_post_meta($app_id, '_vms_app_phone', $phone);
    update_post_meta($app_id, '_vms_app_location', $location);
    update_post_meta($app_id, '_vms_app_website', $website);
    update_post_meta($app_id, '_vms_app_rate', $rate);
    update_post_meta($app_id, '_vms_app_turnout', $turnout);
    update_post_meta($app_id, '_vms_app_compensation_notes', $compensation_notes);
    update_post_meta($app_id, '_vms_app_audience_notes', $audience_notes);
    update_post_meta($app_id, '_vms_app_epk', $epk);
    update_post_meta($app_id, '_vms_app_cuisine', $cuisine);
    update_post_meta($app_id, '_vms_app_menu', $menu);
    update_post_meta($app_id, '_vms_app_social', $social);
    foreach (vms_vendor_app_social_field_map() as $slug => $field) {
        update_post_meta($app_id, (string) $field['app_key'], (string) ($social_inputs[$slug] ?? ''));
    }
    update_post_meta($app_id, '_vms_app_notes', $notes);
    if (function_exists('vms_vendor_app_maybe_ensure_public_lookup_key')) {
        vms_vendor_app_maybe_ensure_public_lookup_key((int) $app_id);
    }

    vms_vendor_app_set_status((int) $app_id, 'pending');
    update_post_meta($app_id, '_vms_app_submitted_at', current_time('mysql'));

    $f = vms_vendor_apply_request_fingerprint();
    update_post_meta($app_id, '_vms_app_ip', $f['ip']);
    update_post_meta($app_id, '_vms_app_user_agent', $f['ua']);
    update_post_meta($app_id, '_vms_app_turnstile', 'pass');

    $auto_confirm_user_id = 0;
    $confirmation_source = 'email_token';
    if (function_exists('vms_vendor_app_current_user_matches_email') && vms_vendor_app_current_user_matches_email($email)) {
        $auto_confirm_user_id = (int) get_current_user_id();
        if ($auto_confirm_user_id > 0) {
            vms_vendor_app_set_submitting_user_id((int) $app_id, $auto_confirm_user_id);
            $confirmation_source = 'login_match';
        }
    }

    if ($auto_confirm_user_id > 0 || (function_exists('vms_vendor_app_confirmation_bypass_enabled') && vms_vendor_app_confirmation_bypass_enabled())) {
        if (function_exists('vms_vendor_app_mark_review_ready')) {
            vms_vendor_app_mark_review_ready((int) $app_id, $auto_confirm_user_id > 0 ? $confirmation_source : 'kill_switch', $auto_confirm_user_id);
        }
        if (function_exists('vms_vendor_app_maybe_notify_review_ready')) {
            vms_vendor_app_maybe_notify_review_ready((int) $app_id);
        }

        vms_vendor_apply_frontend_redirect('success');
        return '';
    }

    if (function_exists('vms_vendor_app_set_confirmation_state')) {
        vms_vendor_app_set_confirmation_state((int) $app_id, 'unconfirmed', array('source' => 'confirmation_email'));
    } else {
        update_post_meta((int) $app_id, '_vms_app_confirmation_state', 'unconfirmed');
    }

    $sent = function_exists('vms_vendor_app_send_confirmation_email')
        ? vms_vendor_app_send_confirmation_email((int) $app_id, array(
            'source' => 'confirmation_email',
            'invalidate_reason' => 'initial_send',
        ))
        : new WP_Error('vms_vendor_app_confirmation_unavailable', __('Confirmation email support is unavailable in this build.', 'backstage-venue-manager'));

    $notice = 'sent';
    if (is_wp_error($sent)) {
        $map = array(
            'vms_vendor_app_confirm_cooldown' => 'cooldown',
            'vms_vendor_app_confirm_daily_cap' => 'daily_cap',
            'vms_vendor_app_confirm_ip_throttle' => 'ip_throttle',
            'vms_vendor_app_confirm_mail_failed' => 'mail_failed',
        );
        $notice = (string) ($map[$sent->get_error_code()] ?? 'mail_failed');
    }

    vms_vendor_apply_frontend_redirect('confirm_pending', array(
        'vms_app_ref' => function_exists('vms_vendor_app_get_public_lookup_key') ? vms_vendor_app_get_public_lookup_key((int) $app_id) : '',
        'vms_app_notice' => $notice,
    ));
    return '';
}
/**
 * Redirect back to the application page with a status flag.
 */
function vms_vendor_apply_frontend_redirect(string $flag, array $extra_args = array()): void
{
    $ref = wp_get_referer();

    if (!$ref) {
        $qid = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        $ref = $qid ? get_permalink($qid) : home_url('/');
    }

    $args = array_merge(array('vms_app' => $flag), $extra_args);
    $url = add_query_arg($args, $ref);
    wp_safe_redirect($url);
    exit;
}

/**
 * Sync vendor fields from an approved application into the vendor post.
 * Returns number of fields updated.
 */
function vms_vendor_app_sync_vendor_from_application(int $app_id, int $vendor_id): int
{
    if ($app_id <= 0 || $vendor_id <= 0) return 0;

    $app = get_post($app_id);
    $vendor = get_post($vendor_id);
    if (!$app || !$vendor) return 0;
    if (empty($app->post_type) || !in_array($app->post_type, vms_vendor_app_cpt_slugs(), true)) return 0;
    if ($vendor->post_type !== VMS_VENDOR_CPT) return 0;

    $updated = 0;

    $write_meta = static function (int $vendor_id, string $meta_key, $value) use (&$updated): void {
        $meta_key = trim((string) $meta_key);
        if ($meta_key === '') {
            return;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return;
        }

        $existing = get_post_meta($vendor_id, $meta_key, true);
        if ((string) $existing !== (string) $value) {
            update_post_meta($vendor_id, $meta_key, $value);
            $updated++;
        }
    };

    // 1) Keep vendor title aligned.
    $app_title = trim((string) $app->post_title);
    if ($app_title !== '' && $vendor->post_title !== $app_title) {
        wp_update_post(array(
            'ID'         => $vendor_id,
            'post_title' => $app_title,
            'post_name'  => sanitize_title($app_title),
        ));
    }

    // 2) Canonical vendor meta hydration.
    $k_primary_email = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
    $k_vendor_email  = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'email') : '_vms_vendor_email';
    $k_contact_email = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_email') : '_vms_contact_email';
    $k_website       = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'website') : '_vms_vendor_website';
    $k_city          = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'city') : '_vms_city';
    $k_state         = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'state') : '_vms_state';
    $k_notes         = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'notes_internal') : '_vms_vendor_notes_internal';

    $contact_name = sanitize_text_field((string) get_post_meta($app_id, '_vms_app_contact_name', true));
    if ($contact_name !== '') {
        $write_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_name') : '_vms_contact_name', $contact_name);
    }

    $email = sanitize_email((string) get_post_meta($app_id, '_vms_app_email', true));
    if ($email !== '') {
        $write_meta($vendor_id, $k_primary_email, $email);
        $write_meta($vendor_id, $k_vendor_email, $email);
        $write_meta($vendor_id, $k_contact_email, $email);
    }

    $phone = sanitize_text_field((string) get_post_meta($app_id, '_vms_app_phone', true));
    if ($phone !== '') {
        $write_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone', $phone);
        $write_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'phone') : '_vms_vendor_phone', $phone);
        $write_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_phone') : '_vms_contact_phone', $phone);
    }

    $location_raw = sanitize_text_field((string) get_post_meta($app_id, '_vms_app_location', true));
    if ($location_raw !== '') {
        $write_meta($vendor_id, '_vms_vendor_location', $location_raw);
        $parsed_location = vms_vendor_app_parse_city_state($location_raw);
        if (!empty($parsed_location['city'])) {
            $write_meta($vendor_id, $k_city, sanitize_text_field((string) $parsed_location['city']));
        }
        if (!empty($parsed_location['state'])) {
            $write_meta($vendor_id, $k_state, strtoupper(sanitize_text_field((string) $parsed_location['state'])));
        }
    }

    $rate = sanitize_text_field((string) get_post_meta($app_id, '_vms_app_rate', true));
    if ($rate !== '') {
        $write_meta($vendor_id, '_vms_vendor_rate', $rate);
    }

    $turnout = sanitize_key((string) get_post_meta($app_id, '_vms_app_turnout', true));
    if ($turnout !== '' && array_key_exists($turnout, vms_vendor_app_turnout_options())) {
        $write_meta($vendor_id, '_vms_vendor_turnout_range', $turnout);
    }

    $compensation_notes = trim((string) get_post_meta($app_id, '_vms_app_compensation_notes', true));
    if ($compensation_notes !== '') {
        $write_meta($vendor_id, '_vms_vendor_compensation_notes', $compensation_notes);
    }

    $audience_notes = trim((string) get_post_meta($app_id, '_vms_app_audience_notes', true));
    if ($audience_notes !== '') {
        $write_meta($vendor_id, '_vms_vendor_audience_notes', $audience_notes);
    }

    $website = vms_vendor_app_get_website_value($app_id);
    $epk = esc_url_raw((string) get_post_meta($app_id, '_vms_app_epk', true));
    $menu = esc_url_raw((string) get_post_meta($app_id, '_vms_app_menu', true));
    if ($epk !== '') {
        $write_meta($vendor_id, '_vms_vendor_epk', $epk);
    }
    if ($menu !== '') {
        $write_meta($vendor_id, '_vms_vendor_menu', $menu);
    }
    if ($website !== '') {
        $write_meta($vendor_id, $k_website, $website);
        $write_meta($vendor_id, '_vms_website_url', $website);
    }

    $cuisine = sanitize_text_field((string) get_post_meta($app_id, '_vms_app_cuisine', true));
    if ($cuisine !== '') {
        $write_meta($vendor_id, '_vms_vendor_cuisine', $cuisine);
    }

    $social_values = vms_vendor_app_get_social_values($app_id);
    $social_blob = vms_vendor_app_compose_social_legacy_blob($social_values);
    if ($social_blob !== '') {
        $write_meta($vendor_id, '_vms_vendor_social', $social_blob);
    }
    foreach (vms_vendor_app_social_field_map() as $slug => $field) {
        $value = trim((string) ($social_values[$slug] ?? ''));
        if ($value === '') {
            continue;
        }

        $write_meta($vendor_id, (string) $field['vendor_key'], $value);
        if ($slug === 'youtube') {
            $write_meta($vendor_id, '_vms_vendor_featured_video_url', $value);
        }
    }

    $notes = trim((string) get_post_meta($app_id, '_vms_app_notes', true));
    if ($notes !== '') {
        $write_meta($vendor_id, '_vms_vendor_notes', $notes);
        $write_meta($vendor_id, $k_notes, $notes);
    }

    // 2b) Canonical vendor type taxonomy.
    $raw_type = get_post_meta($app_id, '_vms_app_vendor_type', true);
    $type_slugs = array();

    if (is_array($raw_type)) {
        $type_slugs = $raw_type;
    } else {
        $raw_type = trim((string) $raw_type);
        if ($raw_type !== '') {
            $type_slugs = preg_split('/\s*[,|]\s*/', $raw_type);
        }
    }

    $type_slugs = array_values(array_unique(array_filter(array_map('vms_vendor_app_normalize_vendor_type', array_map('strval', (array) $type_slugs)))));

    if (!empty($type_slugs) && taxonomy_exists('vms_vendor_type')) {
        $term_ids = array();

        foreach ($type_slugs as $slug) {
            $term = get_term_by('slug', $slug, 'vms_vendor_type');
            if (!$term || is_wp_error($term)) {
                $label = vms_vendor_app_vendor_type_label($slug);
                $created = wp_insert_term($label !== '' ? $label : ucwords(str_replace('_', ' ', $slug)), 'vms_vendor_type', array('slug' => $slug));
                if (!is_wp_error($created)) {
                    $term = get_term((int) ($created['term_id'] ?? 0), 'vms_vendor_type');
                }
            }

            if ($term && !is_wp_error($term)) {
                $term_ids[] = (int) $term->term_id;
                continue;
            }

            error_log('[VMS] vendor-applications: unknown vendor type slug "' . $slug . '" on app_id ' . $app_id . '; not assigning taxonomy term.');
        }

        if (!empty($term_ids)) {
            $set = wp_set_object_terms($vendor_id, $term_ids, 'vms_vendor_type', false);
            if (is_wp_error($set)) {
                error_log('[VMS] vendor-applications: failed setting vms_vendor_type terms for vendor_id ' . $vendor_id . ' (app_id ' . $app_id . ')');
            }
        }
    }

    $app_cuisine = trim((string) get_post_meta($app_id, '_vms_app_cuisine', true));
    if ($app_cuisine !== '' && function_exists('vms_vendor_categories_parse_legacy_list') && taxonomy_exists('vms_vendor_category')) {
        $existing_vendor_categories = function_exists('vms_vendor_get_category_terms') ? vms_vendor_get_category_terms($vendor_id) : array();
        if (empty($existing_vendor_categories)) {
            $category_term_ids = array();
            foreach (vms_vendor_categories_parse_legacy_list($app_cuisine) as $category_name) {
                $term = get_term_by('name', $category_name, 'vms_vendor_category');
                if (!$term || is_wp_error($term)) {
                    $term = get_term_by('slug', sanitize_title($category_name), 'vms_vendor_category');
                }
                if (!$term || is_wp_error($term)) {
                    $created = wp_insert_term($category_name, 'vms_vendor_category');
                    if (is_wp_error($created)) {
                        continue;
                    }
                    $term_id = absint($created['term_id'] ?? 0);
                } else {
                    $term_id = absint($term->term_id);
                }
                if ($term_id > 0) {
                    $category_term_ids[] = $term_id;
                }
            }

            $category_term_ids = array_values(array_unique(array_filter(array_map('absint', $category_term_ids))));
            if (!empty($category_term_ids)) {
                wp_set_object_terms($vendor_id, $category_term_ids, 'vms_vendor_category', false);
            }
        }
    }

    // 3) Always ensure cross-link meta exists.
    $existing_app_link = (int) get_post_meta($vendor_id, '_vms_application_id', true);
    if ($existing_app_link !== $app_id) {
        update_post_meta($vendor_id, '_vms_application_id', $app_id);
        $updated++;
    }

    return $updated;
}



if (!function_exists('vms_vendor_app_backfill_application_shape_once')) {
    function vms_vendor_app_backfill_application_shape_once(): void
    {
        $marker = 'vms_vendor_app_application_shape_backfill_0242432';
        if (get_option($marker)) {
            return;
        }

        $stats = array(
            'apps_scanned' => 0,
            'apps_updated' => 0,
            'meta_updates' => 0,
            'ran_at' => (string) wp_date('Y-m-d H:i:s'),
        );
        $guard = function_exists('vms_admin_guard_begin')
            ? vms_admin_guard_begin('admin_init.vendor_app_application_shape_backfill', array(
                'task' => 'vendor_app_application_shape_backfill',
                'allow_action' => 'vendor_app_application_shape_backfill',
                'lock_name' => $marker,
                'lock_ttl' => 300,
            ))
            : true;
        if ($guard === false) {
            return;
        }

        try {
            $app_ids = get_posts(array(
                'post_type' => vms_vendor_app_cpt_slugs(),
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));

            foreach ((array) $app_ids as $app_id) {
                $app_id = (int) $app_id;
                if ($app_id <= 0) {
                    continue;
                }

                $stats['apps_scanned']++;
                $updates = 0;
                $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
                $vendor_id = $vendor_id > 0 && get_post_type($vendor_id) === VMS_VENDOR_CPT ? $vendor_id : 0;

                $contact_name = trim((string) get_post_meta($app_id, '_vms_app_contact_name', true));
                if ($contact_name === '' && $vendor_id > 0) {
                    $contact_name = trim((string) get_post_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_name') : '_vms_contact_name', true));
                    if ($contact_name !== '') {
                        update_post_meta($app_id, '_vms_app_contact_name', $contact_name);
                        $updates++;
                    }
                }

                $phone = trim((string) get_post_meta($app_id, '_vms_app_phone', true));
                if ($phone === '' && $vendor_id > 0) {
                    $phone_candidates = array(
                        get_post_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone', true),
                        get_post_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'contact_phone') : '_vms_contact_phone', true),
                        get_post_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'phone') : '_vms_vendor_phone', true),
                    );
                    foreach ($phone_candidates as $candidate) {
                        $candidate = trim((string) $candidate);
                        if ($candidate !== '') {
                            update_post_meta($app_id, '_vms_app_phone', $candidate);
                            $updates++;
                            break;
                        }
                    }
                }

                $website = trim((string) get_post_meta($app_id, '_vms_app_website', true));
                if ($website === '') {
                    $website = vms_vendor_app_get_website_value($app_id);
                    if ($website === '' && $vendor_id > 0) {
                        $website = esc_url_raw((string) get_post_meta($vendor_id, function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'website') : '_vms_vendor_website', true));
                        if ($website === '') {
                            $website = esc_url_raw((string) get_post_meta($vendor_id, '_vms_website_url', true));
                        }
                    }
                    if ($website !== '') {
                        update_post_meta($app_id, '_vms_app_website', $website);
                        $updates++;
                    }
                }

                $social_values = vms_vendor_app_get_social_values($app_id);
                foreach (vms_vendor_app_social_field_map() as $slug => $field) {
                    $current = trim((string) get_post_meta($app_id, (string) $field['app_key'], true));
                    $value = trim((string) ($social_values[$slug] ?? ''));
                    if ($current === '' && $value === '' && $vendor_id > 0) {
                        $value = esc_url_raw((string) get_post_meta($vendor_id, (string) $field['vendor_key'], true));
                    }
                    if ($current === '' && $value !== '') {
                        update_post_meta($app_id, (string) $field['app_key'], $value);
                        $updates++;
                        $social_values[$slug] = $value;
                    }
                }

                $social_blob = trim((string) get_post_meta($app_id, '_vms_app_social', true));
                if ($social_blob === '') {
                    $social_blob = vms_vendor_app_compose_social_legacy_blob($social_values);
                    if ($social_blob !== '') {
                        update_post_meta($app_id, '_vms_app_social', $social_blob);
                        $updates++;
                    }
                }

                if ($updates > 0) {
                    $stats['apps_updated']++;
                    $stats['meta_updates'] += $updates;
                }
            }

            add_option($marker, $stats, '', false);
        } finally {
            if (is_array($guard) && function_exists('vms_admin_guard_finish')) {
                vms_admin_guard_finish($guard, $stats);
            }
        }
    }
}
add_action('admin_init', 'vms_vendor_app_backfill_application_shape_once', 19);

if (!function_exists('vms_vendor_app_backfill_vendor_sot_once')) {
    function vms_vendor_app_backfill_vendor_sot_once(): void
    {
        $marker = 'vms_vendor_app_vendor_sot_backfill_0242431';
        if (get_option($marker)) {
            return;
        }

        $stats = array(
            'apps_scanned' => 0,
            'vendors_synced' => 0,
            'meta_updates' => 0,
            'links_created' => 0,
            'ran_at' => (string) wp_date('Y-m-d H:i:s'),
        );
        $guard = function_exists('vms_admin_guard_begin')
            ? vms_admin_guard_begin('admin_init.vendor_app_vendor_sot_backfill', array(
                'task' => 'vendor_app_vendor_sot_backfill',
                'allow_action' => 'vendor_app_vendor_sot_backfill',
                'lock_name' => $marker,
                'lock_ttl' => 300,
            ))
            : true;
        if ($guard === false) {
            return;
        }

        try {
            $app_ids = get_posts(array(
                'post_type' => vms_vendor_app_cpt_slugs(),
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => array(
                    array(
                        'key' => '_vms_app_status',
                        'value' => 'approved',
                        'compare' => '=',
                    ),
                ),
            ));

            foreach ((array) $app_ids as $app_id) {
                $app_id = (int) $app_id;
                if ($app_id <= 0) {
                    continue;
                }

                $stats['apps_scanned']++;
                $vendor_id = (int) get_post_meta($app_id, '_vms_vendor_id', true);
                if ($vendor_id <= 0 || get_post_type($vendor_id) !== VMS_VENDOR_CPT) {
                    continue;
                }

                $stats['meta_updates'] += (int) vms_vendor_app_sync_vendor_from_application($app_id, $vendor_id);
                $stats['vendors_synced']++;

                $link_result = vms_vendor_app_link_submitting_user_to_vendor($app_id, $vendor_id, 0);
                if (!is_wp_error($link_result)) {
                    $stats['links_created']++;
                }
            }

            add_option($marker, $stats, '', false);
        } finally {
            if (is_array($guard) && function_exists('vms_admin_guard_finish')) {
                vms_admin_guard_finish($guard, $stats);
            }
        }
    }
}
add_action('admin_init', 'vms_vendor_app_backfill_vendor_sot_once', 20);
