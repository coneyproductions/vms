<?php
defined('ABSPATH') || exit;

/**
 * Public-facing Vendor Profiles (v1)
 *
 * URL pattern:
 *   /{base}/{vendor-slug}/
 */

add_action('init', 'bvmgr_vendor_profiles_register_rewrite', 11);
add_action('init', 'bvmgr_vendor_profiles_register_shortcodes', 12);
add_filter('query_vars', 'bvmgr_vendor_profiles_register_query_vars', 11);
add_filter('template_include', 'bvmgr_vendor_profiles_template_include', 99);
add_action('wp_enqueue_scripts', 'bvmgr_vendor_profiles_public_assets', 11);
add_action('admin_init', 'bvmgr_vendor_profiles_maybe_flush_rewrites', 20);
add_filter('the_content', 'bvmgr_vendor_profiles_append_event_vendor_teaser', 22);

function bvmgr_vendor_profiles_register_rewrite(): void
{
    $base = trim(function_exists('bvmgr_vendor_profile_base_slug') ? bvmgr_vendor_profile_base_slug() : 'vendor', '/');
    $base = $base !== '' ? $base : 'vendor';

    add_rewrite_rule(
        '^' . $base . '/([^/]+)/?$',
        'index.php?bvmgr_vendor_profile=$matches[1]',
        'top'
    );
}

function bvmgr_vendor_profiles_register_query_vars(array $vars): array
{
    $vars[] = 'bvmgr_vendor_profile';
    $vars[] = 'vms_vendor_profile';
    return $vars;
}

function bvmgr_vendor_profiles_register_shortcodes(): void
{
    add_shortcode('vms_vendor_teaser', 'bvmgr_vendor_profiles_shortcode_vendor_teaser');
    add_shortcode('vms_secondary_vendor_teaser', 'bvmgr_vendor_profiles_shortcode_secondary_vendor_teaser');
    add_shortcode('vms_vendor_next_show', 'bvmgr_vendor_profiles_shortcode_next_show');
}

function bvmgr_vendor_profiles_public_assets(): void
{
    if (!bvmgr_get_query_var_compat('bvmgr_vendor_profile') && !is_singular('tribe_events') && !is_singular('page') && !is_singular('post')) {
        return;
    }

    bvmgr_enqueue_public_style_stack();
    bvmgr_enqueue_style_asset(
        'bvmgr-vendor-profile-public',
        'assets/css/vendor-profile-public.css',
        array('bvmgr-ui')
    );
}

function bvmgr_vendor_profiles_template_include(string $template): string
{
    $raw = bvmgr_get_query_var_compat('bvmgr_vendor_profile');
    if (!$raw) {
        return $template;
    }

    $slug = sanitize_title((string) $raw);
    if ($slug === '') {
        return bvmgr_vendor_profiles_404($template);
    }

    $q = new WP_Query([
        'post_type'           => defined('BVMGR_CPT_VENDOR') ? BVMGR_CPT_VENDOR : 'vms_vendor',
        'name'                => $slug,
        'posts_per_page'      => 1,
        'post_status'         => 'publish',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);

    if (empty($q->posts) || !($q->posts[0] instanceof WP_Post)) {
        return bvmgr_vendor_profiles_404($template);
    }

    $vendor = $q->posts[0];

    if (function_exists('bvmgr_vendor_profile_is_enabled') && !bvmgr_vendor_profile_is_enabled((int) $vendor->ID)) {
        return bvmgr_vendor_profiles_404($template);
    }

    $GLOBALS['bvmgr_vendor_profile_post'] = $vendor;

    global $post;
    $post = $vendor;
    setup_postdata($post);

    $tpl = (defined('BVMGR_PLUGIN_PATH') ? BVMGR_PLUGIN_PATH : plugin_dir_path(__FILE__) . '../../') . 'includes/public/templates/vendor-profile.php';
    if (file_exists($tpl)) {
        return $tpl;
    }

    return $template;
}

function bvmgr_vendor_profiles_404(string $template): string
{
    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $wp_query->set_404();
    }
    status_header(404);
    nocache_headers();

    $t404 = get_404_template();
    return $t404 ? $t404 : $template;
}

function bvmgr_vendor_profiles_maybe_flush_rewrites(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $flag = get_option(defined('BVMGR_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1') ? BVMGR_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1 : 'vms_rewrite_flushed_vendor_profiles_v1', '');
    if ($flag === '1') {
        return;
    }

    flush_rewrite_rules(false);
    update_option(defined('BVMGR_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1') ? BVMGR_OPT_REWRITE_FLUSH_VENDOR_PROFILES_V1 : 'vms_rewrite_flushed_vendor_profiles_v1', '1', false);
}

if (!function_exists('bvmgr_vendor_profiles_today_ymd')) {
    function bvmgr_vendor_profiles_today_ymd(): string
    {
        if (function_exists('wp_date') && function_exists('wp_timezone')) {
            return (string) wp_date('Y-m-d', time(), wp_timezone());
        }

        return gmdate('Y-m-d');
    }
}

if (!function_exists('bvmgr_vendor_profiles_get_primary_vendor_for_tec_event')) {
    function bvmgr_vendor_profiles_get_primary_vendor_for_tec_event(int $tec_event_id): int
    {
        $tec_event_id = (int) $tec_event_id;
        if ($tec_event_id <= 0) {
            return 0;
        }

        $plan_id = function_exists('bvmgr_get_event_plan_for_tec_event') ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id) : 0;
        if ($plan_id <= 0) {
            return 0;
        }

        $band_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
        return (int) get_post_meta($plan_id, $band_key, true);
    }
}


if (!function_exists('bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event')) {
    function bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event(int $tec_event_id, string $type_filter = ''): array
    {
        $tec_event_id = (int) $tec_event_id;
        if ($tec_event_id <= 0) {
            return array();
        }

        $plan_id = function_exists('bvmgr_get_event_plan_for_tec_event') ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id) : 0;
        if ($plan_id <= 0) {
            return array();
        }

        $secondary_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
            ? (array) bvmgr_event_plan_get_secondary_vendor_assignments($plan_id)
            : array();
        $secondary_ids = !empty($secondary_assignments) && function_exists('bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments')
            ? bvmgr_event_plan_get_secondary_vendor_flat_ids_from_assignments($secondary_assignments)
            : array();
        if (empty($secondary_ids)) {
            return array();
        }

        $type_filter = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($type_filter)
            : sanitize_title($type_filter);
        if ($type_filter !== '') {
            if (!empty($secondary_assignments) && function_exists('bvmgr_event_plan_get_secondary_vendor_ids_for_type')) {
                $secondary_ids = bvmgr_event_plan_get_secondary_vendor_ids_for_type($secondary_assignments, $type_filter);
            } else {
                $secondary_ids = array_values(array_filter($secondary_ids, static function (int $vendor_id) use ($type_filter): bool {
                    return function_exists('bvmgr_vendor_has_type') ? bvmgr_vendor_has_type($vendor_id, $type_filter) : (function_exists('has_term') ? has_term($type_filter, 'vms_vendor_type', $vendor_id) : true);
                }));
            }
        }

        return $secondary_ids;
    }
}

if (!function_exists('bvmgr_vendor_profiles_get_related_vendor_for_tec_event')) {
    function bvmgr_vendor_profiles_get_related_vendor_for_tec_event(int $tec_event_id, string $role = 'primary', string $type_filter = ''): int
    {
        $tec_event_id = (int) $tec_event_id;
        $role = sanitize_key($role);
        $type_filter = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($type_filter)
            : sanitize_title($type_filter);

        if ($tec_event_id <= 0) {
            return 0;
        }

        if ($role === 'secondary') {
            $secondary_ids = bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event($tec_event_id, $type_filter);
            return !empty($secondary_ids) ? (int) reset($secondary_ids) : 0;
        }

        return bvmgr_vendor_profiles_get_primary_vendor_for_tec_event($tec_event_id);
    }
}

if (!function_exists('bvmgr_vendor_profiles_get_event_plan_for_tec_event')) {
    function bvmgr_vendor_profiles_get_event_plan_for_tec_event(int $tec_event_id): int
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0 || !function_exists('bvmgr_get_event_plan_for_tec_event')) {
            return 0;
        }

        return (int) bvmgr_get_event_plan_for_tec_event($tec_event_id);
    }
}

if (!function_exists('bvmgr_vendor_profiles_event_sidebar_rendered')) {
    function bvmgr_vendor_profiles_event_sidebar_rendered(int $tec_event_id): bool
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0) {
            return false;
        }

        $rendered = isset($GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered']) && is_array($GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered'])
            ? $GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered']
            : array();

        return !empty($rendered[$tec_event_id]);
    }
}

if (!function_exists('bvmgr_vendor_profiles_mark_event_sidebar_rendered')) {
    function bvmgr_vendor_profiles_mark_event_sidebar_rendered(int $tec_event_id): void
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id > 0) {
            if (!isset($GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered']) || !is_array($GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered'])) {
                $GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered'] = array();
            }

            $GLOBALS['bvmgr_vendor_profiles_event_sidebar_rendered'][$tec_event_id] = true;
        }
    }
}

if (!function_exists('bvmgr_vendor_profiles_vendor_exists')) {
    function bvmgr_vendor_profiles_vendor_exists(int $vendor_id): bool
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return false;
        }

        $vendor = get_post($vendor_id);
        return ($vendor instanceof WP_Post) && $vendor->post_type === (defined('BVMGR_CPT_VENDOR') ? BVMGR_CPT_VENDOR : 'vms_vendor') && $vendor->post_status !== 'trash';
    }
}

if (!function_exists('bvmgr_vendor_profiles_shortcode_targets_event_sidebar')) {
    function bvmgr_vendor_profiles_shortcode_targets_event_sidebar($vendor, int $tec_event_id, array $atts = array()): bool
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0) {
            return false;
        }

        $vendor = trim((string) $vendor);
        if ($vendor === '' || strtolower($vendor) === 'current') {
            return (bool) apply_filters('vms_vendor_profiles_use_grouped_event_sidebar', true, $tec_event_id, $atts);
        }

        if (is_numeric($vendor)) {
            return false;
        }

        return false;
    }
}

if (!function_exists('bvmgr_vendor_profiles_public_vendor_url')) {
    function bvmgr_vendor_profiles_public_vendor_url(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return '';
        }

        if (function_exists('bvmgr_vendor_profile_is_enabled') && !bvmgr_vendor_profile_is_enabled($vendor_id)) {
            return '';
        }

        return function_exists('bvmgr_vendor_profile_url') ? (string) bvmgr_vendor_profile_url($vendor_id) : '';
    }
}

if (!function_exists('bvmgr_vendor_profiles_is_food_group_type')) {
    function bvmgr_vendor_profiles_is_food_group_type(string $type_slug): bool
    {
        $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($type_slug)
            : sanitize_key($type_slug);

        return in_array($type_slug, array('food_truck', 'dessert_truck', 'drink_truck'), true);
    }
}

if (!function_exists('bvmgr_vendor_profiles_group_key_for_type')) {
    function bvmgr_vendor_profiles_group_key_for_type(string $type_slug): string
    {
        $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($type_slug)
            : sanitize_key($type_slug);

        if (bvmgr_vendor_profiles_is_food_group_type($type_slug)) {
            return 'food_vendors';
        }

        return $type_slug !== '' ? $type_slug : 'vendor';
    }
}

if (!function_exists('bvmgr_vendor_profiles_group_sort_order')) {
    function bvmgr_vendor_profiles_group_sort_order(string $group_key): int
    {
        $group_key = sanitize_key($group_key);
        $order = array(
            'band' => 10,
            'food_vendors' => 20,
            'market_vendor' => 30,
            'photographer' => 40,
            'vendor' => 90,
        );

        return (int) ($order[$group_key] ?? 50);
    }
}

if (!function_exists('bvmgr_vendor_profiles_group_heading')) {
    function bvmgr_vendor_profiles_group_heading(string $group_key, string $type_slug, int $count): string
    {
        $group_key = sanitize_key($group_key);
        $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($type_slug)
            : sanitize_key($type_slug);
        $count = max(1, $count);

        if ($group_key === 'food_vendors') {
            return __('Food Vendors', 'backstage-venue-manager');
        }

        $label = function_exists('bvmgr_vendor_type_label')
            ? trim((string) bvmgr_vendor_type_label($type_slug))
            : '';
        if ($label === '') {
            $label = __('Vendor', 'backstage-venue-manager');
        }

        if ($count === 1) {
            return $label;
        }

        if (preg_match('/vendor$/i', $label)) {
            return preg_replace('/vendor$/i', 'Vendors', $label) ?: $label;
        }

        if (preg_match('/s$/i', $label)) {
            return $label;
        }

        return $label . 's';
    }
}

if (!function_exists('bvmgr_vendor_profiles_vendor_subtitle')) {
    function bvmgr_vendor_profiles_vendor_subtitle(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return '';
        }

        $labels = array();
        if (function_exists('bvmgr_vendor_get_category_terms')) {
            foreach ((array) bvmgr_vendor_get_category_terms($vendor_id) as $term) {
                if ($term instanceof WP_Term) {
                    $name = trim((string) $term->name);
                    if ($name !== '') {
                        $labels[] = $name;
                    }
                }
            }
        }

        if (empty($labels)) {
            $legacy = trim((string) get_post_meta($vendor_id, '_vms_vendor_cuisine', true));
            if ($legacy !== '') {
                if (function_exists('bvmgr_vendor_categories_parse_legacy_list')) {
                    $labels = array_values(array_filter(array_map('trim', (array) bvmgr_vendor_categories_parse_legacy_list($legacy))));
                } else {
                    $labels[] = $legacy;
                }
            }
        }

        $labels = array_values(array_unique(array_filter(array_map('sanitize_text_field', $labels))));
        return !empty($labels) ? implode(' · ', $labels) : '';
    }
}

if (!function_exists('bvmgr_vendor_profiles_build_event_vendor_groups')) {
    /**
     * Public event vendor output must read only finalized Event Plan-owned vendor assignment data.
     * ADD and admin workflows may write/update Event Plan data, but this renderer must not read
     * ADD workflow state, review queues, logs, or temporary dispatch records directly. Legacy
     * Event Plan fallback fields remain in use here for compatibility when canonical EP data is missing.
     *
     * @return array<int,array<string,mixed>>
     */
    function bvmgr_vendor_profiles_build_event_vendor_groups(int $tec_event_id): array
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
            return array();
        }

        $plan_id = bvmgr_vendor_profiles_get_event_plan_for_tec_event($tec_event_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $visibility_map = function_exists('bvmgr_calendar_public_vendor_visibility_map')
            ? (array) bvmgr_calendar_public_vendor_visibility_map()
            : array();
        $groups = array();
        $group_index = 0;
        $has_lineup = false;

        $ensure_group = static function (string $type_slug) use (&$groups, &$group_index): string {
            $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
                ? bvmgr_vendor_type_normalize_slug($type_slug)
                : sanitize_key($type_slug);
            $group_key = bvmgr_vendor_profiles_group_key_for_type($type_slug);
            if (!isset($groups[$group_key])) {
                $groups[$group_key] = array(
                    'group_key' => $group_key,
                    'type_slug' => $type_slug,
                    'sort_order' => bvmgr_vendor_profiles_group_sort_order($group_key),
                    'insert_order' => $group_index++,
                    'cards' => array(),
                );
            }

            return $group_key;
        };

        $is_type_public = static function (string $type_slug) use ($visibility_map): bool {
            $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
                ? bvmgr_vendor_type_normalize_slug($type_slug)
                : sanitize_key($type_slug);
            if ($type_slug === '') {
                return true;
            }

            if (array_key_exists($type_slug, $visibility_map)) {
                return (bool) $visibility_map[$type_slug];
            }

            return true;
        };

        $append_card = static function (string $type_slug, int $vendor_id, string $display_name = '') use (&$groups, $ensure_group, $is_type_public): void {
            $vendor_id = absint($vendor_id);
            if ($vendor_id <= 0 || !bvmgr_vendor_profiles_vendor_exists($vendor_id)) {
                return;
            }

            $type_slug = function_exists('bvmgr_vendor_type_normalize_slug')
                ? bvmgr_vendor_type_normalize_slug($type_slug)
                : sanitize_key($type_slug);
            if (!$is_type_public($type_slug)) {
                return;
            }

            $group_key = $ensure_group($type_slug);
            $dedupe_key = (string) $vendor_id;
            if (isset($groups[$group_key]['cards'][$dedupe_key])) {
                return;
            }

            $display_name = trim($display_name);
            if ($display_name === '') {
                $display_name = trim((string) get_the_title($vendor_id));
            }
            if ($display_name === '') {
                $display_name = __('Vendor', 'backstage-venue-manager');
            }

            $groups[$group_key]['cards'][$dedupe_key] = array(
                'vendor_id' => $vendor_id,
                'display_name' => $display_name,
                'subtitle' => bvmgr_vendor_profiles_vendor_subtitle($vendor_id),
                'profile_url' => bvmgr_vendor_profiles_public_vendor_url($vendor_id),
            );
        };

        if (function_exists('bvmgr_get_event_plan_lineup_entries')) {
            $lineup_entries = (array) bvmgr_get_event_plan_lineup_entries($plan_id);
            $has_lineup = !empty($lineup_entries);
            foreach ($lineup_entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if ((string) ($entry['show_public'] ?? '') !== '1') {
                    continue;
                }

                $vendor_id = absint($entry['vendor_id'] ?? 0);
                if ($vendor_id <= 0) {
                    continue;
                }

                $type_slug = function_exists('bvmgr_vendor_primary_type_slug')
                    ? (string) bvmgr_vendor_primary_type_slug($vendor_id)
                    : '';
                $display_name = trim((string) ($entry['display_name'] ?? $entry['vendor_title'] ?? ''));
                $append_card($type_slug, $vendor_id, $display_name);
            }
        }

        $primary_vendor_id = bvmgr_vendor_profiles_get_primary_vendor_for_tec_event($tec_event_id);
        if (!$has_lineup && $primary_vendor_id > 0) {
            $primary_type = function_exists('bvmgr_vendor_primary_type_slug')
                ? (string) bvmgr_vendor_primary_type_slug($primary_vendor_id)
                : '';
            $append_card($primary_type, $primary_vendor_id);
        }

        $secondary_assignments = function_exists('bvmgr_event_plan_get_secondary_vendor_assignments')
            ? (array) bvmgr_event_plan_get_secondary_vendor_assignments($plan_id, array(
                'primary_vendor_id' => $primary_vendor_id,
            ))
            : array();

        if (!empty($secondary_assignments)) {
            foreach ($secondary_assignments as $type_slug => $assignment) {
                $assignment = is_array($assignment) ? $assignment : array();
                foreach ((array) ($assignment['vendor_ids'] ?? array()) as $vendor_id) {
                    $append_card((string) $type_slug, (int) $vendor_id);
                }
            }
        } else {
            foreach (bvmgr_vendor_profiles_get_secondary_vendors_for_tec_event($tec_event_id) as $vendor_id) {
                $vendor_id = absint($vendor_id);
                if ($vendor_id <= 0) {
                    continue;
                }

                $type_slug = function_exists('bvmgr_vendor_primary_type_slug')
                    ? (string) bvmgr_vendor_primary_type_slug($vendor_id)
                    : '';
                $append_card($type_slug, $vendor_id);
            }
        }

        $groups = array_values(array_filter($groups, static function (array $group): bool {
            return !empty($group['cards']);
        }));

        foreach ($groups as &$group) {
            $group['cards'] = array_values((array) $group['cards']);
            $group['heading'] = bvmgr_vendor_profiles_group_heading(
                (string) ($group['group_key'] ?? ''),
                (string) ($group['type_slug'] ?? ''),
                count($group['cards'])
            );
            $group['is_food_group'] = ((string) ($group['group_key'] ?? '')) === 'food_vendors';
        }
        unset($group);

        usort($groups, static function (array $left, array $right): int {
            $left_sort = (int) ($left['sort_order'] ?? 50);
            $right_sort = (int) ($right['sort_order'] ?? 50);
            if ($left_sort !== $right_sort) {
                return $left_sort <=> $right_sort;
            }

            return ((int) ($left['insert_order'] ?? 0)) <=> ((int) ($right['insert_order'] ?? 0));
        });

        return (array) apply_filters('vms_vendor_profiles_event_vendor_groups', $groups, $tec_event_id, $plan_id);
    }
}

if (!function_exists('bvmgr_vendor_profiles_render_event_vendor_sidebar')) {
    function bvmgr_vendor_profiles_render_event_vendor_sidebar(int $tec_event_id): string
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0 || bvmgr_vendor_profiles_event_sidebar_rendered($tec_event_id)) {
            return '';
        }

        $groups = bvmgr_vendor_profiles_build_event_vendor_groups($tec_event_id);
        if (empty($groups)) {
            return '';
        }

        ob_start();
        ?>
        <div class="vms-event-vendor-groups" data-vms-event-vendor-groups="1">
            <?php foreach ($groups as $group) : ?>
                <?php
                $heading = trim((string) ($group['heading'] ?? ''));
                $cards = isset($group['cards']) && is_array($group['cards']) ? $group['cards'] : array();
                $group_key = sanitize_html_class((string) ($group['group_key'] ?? 'vendors'));
                $group_classes = array(
                    'vms-event-vendor-group',
                    'vms-event-vendor-group--' . $group_key,
                );
                if (!empty($group['is_food_group'])) {
                    $group_classes[] = 'is-food-group';
                }
                $cards_classes = array('vms-event-vendor-group__cards');
                if (!empty($group['is_food_group'])) {
                    $cards_classes[] = 'vms-event-vendor-group__cards--grid';
                }
                ?>
                <section class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $group_classes))); ?>"<?php echo $heading !== '' ? ' aria-label="' . esc_attr($heading) . '"' : ''; ?>>
                    <?php if ($heading !== '') : ?>
                        <h2 class="vms-event-vendor-group__title"><?php echo esc_html($heading); ?></h2>
                    <?php endif; ?>

                    <div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $cards_classes))); ?>">
                        <?php foreach ($cards as $card) : ?>
                            <?php
                            $vendor_id = absint($card['vendor_id'] ?? 0);
                            $display_name = trim((string) ($card['display_name'] ?? ''));
                            $subtitle = trim((string) ($card['subtitle'] ?? ''));
                            $profile_url = trim((string) ($card['profile_url'] ?? ''));
                            ?>
                            <article class="vms-event-vendor-card vms-vp-card">
                                <div class="vms-event-vendor-card__media">
                                    <?php if (has_post_thumbnail($vendor_id)) : ?>
                                        <?php if ($profile_url !== '') : ?>
                                            <a class="vms-event-vendor-card__thumb" href="<?php echo esc_url($profile_url); ?>">
                                                <?php echo get_the_post_thumbnail($vendor_id, 'medium', array('alt' => $display_name)); ?>
                                            </a>
                                        <?php else : ?>
                                            <div class="vms-event-vendor-card__thumb">
                                                <?php echo get_the_post_thumbnail($vendor_id, 'medium', array('alt' => $display_name)); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <?php if ($profile_url !== '') : ?>
                                            <a class="vms-event-vendor-card__thumb vms-event-vendor-card__thumb--placeholder" href="<?php echo esc_url($profile_url); ?>" aria-hidden="true"></a>
                                        <?php else : ?>
                                            <div class="vms-event-vendor-card__thumb vms-event-vendor-card__thumb--placeholder" aria-hidden="true"></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="vms-event-vendor-card__body">
                                    <?php if ($display_name !== '') : ?>
                                        <h3 class="vms-event-vendor-card__name">
                                            <?php if ($profile_url !== '') : ?>
                                                <a class="vms-event-vendor-card__name-link" href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html($display_name); ?></a>
                                            <?php else : ?>
                                                <?php echo esc_html($display_name); ?>
                                            <?php endif; ?>
                                        </h3>
                                    <?php endif; ?>

                                    <?php if ($subtitle !== '') : ?>
                                        <p class="vms-event-vendor-card__meta"><?php echo esc_html($subtitle); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
        $markup = (string) ob_get_clean();
        if ($markup !== '') {
            bvmgr_vendor_profiles_mark_event_sidebar_rendered($tec_event_id);
        }

        return $markup;
    }
}

if (!function_exists('bvmgr_vendor_profiles_find_next_upcoming_event')) {
    function bvmgr_vendor_profiles_find_next_upcoming_event(int $vendor_id): array
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return array();
        }

        $band_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'band_vendor_id') ?: '_vms_band_vendor_id') : '_vms_band_vendor_id';
        $date_key = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'date') ?: '_vms_event_date') : '_vms_event_date';
        $tec_key  = function_exists('bvmgr_meta_key') ? (bvmgr_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';

        $today = bvmgr_vendor_profiles_today_ymd();

        $q = new WP_Query(array(
            'post_type'      => 'vms_event_plan',
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 12,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => $date_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The public Vendor profile intentionally orders its bounded 12-plan candidate query by canonical event-date metadata to select the next valid linked event.
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The public Vendor profile limits this vendor/date/linked-event metadata filter to 12 upcoming Event Plan candidates before validating the first public event.
                'relation' => 'AND',
                array(
                    'key'   => $band_key,
                    'value' => (string) $vendor_id,
                ),
                array(
                    'key'     => $date_key,
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => $tec_key,
                    'value'   => '0',
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ),
            ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $result = array();

        if (!empty($q->posts) && is_array($q->posts)) {
            foreach ($q->posts as $plan_id) {
                $plan_id = (int) $plan_id;
                if ($plan_id <= 0) {
                    continue;
                }

                $tec_event_id = (int) get_post_meta($plan_id, $tec_key, true);
                if ($tec_event_id <= 0) {
                    continue;
                }

                $tec_post = get_post($tec_event_id);
                if (!($tec_post instanceof WP_Post) || $tec_post->post_type !== 'tribe_events' || $tec_post->post_status !== 'publish') {
                    continue;
                }

                if (function_exists('bvmgr_tec_is_cancelled_event') && bvmgr_tec_is_cancelled_event($tec_event_id)) {
                    continue;
                }

                $event_date = trim((string) get_post_meta($plan_id, $date_key, true));
                $date_label = $event_date !== ''
                    ? (function_exists('bvmgr_format_local_ymd') ? bvmgr_format_local_ymd($event_date, 'F j, Y') : $event_date)
                    : '';

                $result = array(
                    'plan_id'      => $plan_id,
                    'tec_event_id' => $tec_event_id,
                    'url'          => (string) get_permalink($tec_event_id),
                    'title'        => (string) get_the_title($tec_event_id),
                    'date'         => $event_date,
                    'date_label'   => $date_label,
                );
                break;
            }
        }

        wp_reset_postdata();

        return $result;
    }
}

if (!function_exists('bvmgr_vendor_profiles_resolve_vendor_id')) {
    function bvmgr_vendor_profiles_resolve_vendor_id($vendor = 'current', int $event_id = 0, string $role = 'primary', string $type_filter = ''): int
    {
        if (is_numeric($vendor)) {
            return max(0, (int) $vendor);
        }

        $vendor = sanitize_text_field((string) $vendor);
        if ($vendor === '' || $vendor === 'current') {
            $global_vendor = isset($GLOBALS['bvmgr_vendor_profile_post']) && $GLOBALS['bvmgr_vendor_profile_post'] instanceof WP_Post ? (int) $GLOBALS['bvmgr_vendor_profile_post']->ID : 0;
            if ($global_vendor > 0) {
                return $global_vendor;
            }

            if (bvmgr_get_query_var_compat('bvmgr_vendor_profile')) {
                $slug = sanitize_title(bvmgr_get_query_var_compat('bvmgr_vendor_profile'));
                if ($slug !== '') {
                    $post = get_page_by_path($slug, OBJECT, defined('BVMGR_CPT_VENDOR') ? BVMGR_CPT_VENDOR : 'vms_vendor');
                    if ($post instanceof WP_Post) {
                        return (int) $post->ID;
                    }
                }
            }

            if ($event_id > 0) {
                return bvmgr_vendor_profiles_get_related_vendor_for_tec_event($event_id, $role, $type_filter);
            }

            if (is_singular('tribe_events')) {
                return bvmgr_vendor_profiles_get_related_vendor_for_tec_event((int) get_queried_object_id(), $role, $type_filter);
            }

            return 0;
        }

        $slug = sanitize_title($vendor);
        if ($slug === '') {
            return 0;
        }

        $post = get_page_by_path($slug, OBJECT, defined('BVMGR_CPT_VENDOR') ? BVMGR_CPT_VENDOR : 'vms_vendor');
        return ($post instanceof WP_Post) ? (int) $post->ID : 0;
    }
}

if (!function_exists('bvmgr_vendor_profiles_get_social_links')) {
    function bvmgr_vendor_profiles_get_social_links(int $vendor_id): array
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return array();
        }

        $map = array(
            'facebook'  => '_vms_vendor_social_facebook',
            'instagram' => '_vms_vendor_social_instagram',
            'x'         => '_vms_vendor_social_x',
            'tiktok'    => '_vms_vendor_social_tiktok',
            'youtube'   => '_vms_vendor_social_youtube',
            'spotify'   => '_vms_vendor_social_spotify',
        );

        $out = array();
        foreach ($map as $key => $meta_key) {
            $value = trim((string) get_post_meta($vendor_id, $meta_key, true));
            if ($value === '') {
                continue;
            }
            $out[$key] = esc_url($value);
        }

        return $out;
    }
}


if (!function_exists('bvmgr_vendor_profiles_primary_type_name')) {
    function bvmgr_vendor_profiles_primary_type_name(int $vendor_id): string
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return '';
        }

        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
            return '';
        }

        $first = reset($terms);
        if (!is_object($first) || empty($first->name)) {
            return '';
        }

        return trim((string) $first->name);
    }
}

if (!function_exists('bvmgr_vendor_profiles_teaser_label')) {
    function bvmgr_vendor_profiles_teaser_label(int $vendor_id): string
    {
        $type_name = bvmgr_vendor_profiles_primary_type_name($vendor_id);
        if ($type_name !== '') {
            /* translators: %s: vendor type label in lowercase. */
            return sprintf(__('Meet the %s', 'backstage-venue-manager'), strtolower($type_name));
        }

        return __('Meet this vendor', 'backstage-venue-manager');
    }
}

if (!function_exists('bvmgr_vendor_profiles_teaser_heading')) {
    function bvmgr_vendor_profiles_teaser_heading(int $vendor_id): string
    {
        $title = trim((string) get_the_title($vendor_id));
        if ($title === '') {
            return __('Meet this vendor', 'backstage-venue-manager');
        }

        $type_name = strtolower(bvmgr_vendor_profiles_primary_type_name($vendor_id));
        if ($type_name !== '') {
            if (strpos($type_name, 'music vendor') !== false) {
                /* translators: %s: vendor title. */
                return sprintf(__('Meet %s', 'backstage-venue-manager'), $title);
            }

            if (strpos($type_name, 'food vendor') !== false) {
                /* translators: %s: vendor title. */
                return sprintf(__('Meet the food vendor: %s', 'backstage-venue-manager'), $title);
            }

            if (strpos($type_name, 'artist') !== false) {
                /* translators: %s: vendor title. */
                return sprintf(__('Meet the music vendor: %s', 'backstage-venue-manager'), $title);
            }

            /* translators: 1: vendor type label in lowercase, 2: vendor title. */
            return sprintf(__('Meet the %1$s: %2$s', 'backstage-venue-manager'), $type_name, $title);
        }

        /* translators: %s: vendor title. */
        return sprintf(__('Meet %s', 'backstage-venue-manager'), $title);
    }
}

if (!function_exists('bvmgr_vendor_profiles_social_label')) {
    function bvmgr_vendor_profiles_social_label(string $key): string
    {
        $labels = array(
            'facebook'  => __('Facebook', 'backstage-venue-manager'),
            'instagram' => __('Instagram', 'backstage-venue-manager'),
            'x'         => __('X', 'backstage-venue-manager'),
            'tiktok'    => __('TikTok', 'backstage-venue-manager'),
            'youtube'   => __('YouTube', 'backstage-venue-manager'),
            'spotify'   => __('Spotify', 'backstage-venue-manager'),
        );
        return isset($labels[$key]) ? (string) $labels[$key] : ucfirst($key);
    }
}

if (!function_exists('bvmgr_vendor_profiles_social_icon_allowed_html')) {
    function bvmgr_vendor_profiles_social_icon_allowed_html(): array
    {
        return array(
            'svg' => array(
                'aria-hidden' => true,
                'focusable' => true,
                'viewbox' => true,
            ),
            'path' => array(
                'd' => true,
                'fill' => true,
            ),
            'span' => array(
                'aria-hidden' => true,
                'class' => true,
            ),
        );
    }
}

if (!function_exists('bvmgr_vendor_profiles_promo_allowed_html')) {
    function bvmgr_vendor_profiles_promo_allowed_html(): array
    {
        if (function_exists('bvmgr_vendor_portal_headliner_promo_video_allowed_html')) {
            return bvmgr_vendor_portal_headliner_promo_video_allowed_html();
        }

        $allowed = wp_kses_allowed_html('post');
        $allowed['iframe'] = array(
            'allow' => true,
            'allowfullscreen' => true,
            'class' => true,
            'frameborder' => true,
            'height' => true,
            'loading' => true,
            'name' => true,
            'referrerpolicy' => true,
            'sandbox' => true,
            'src' => true,
            'title' => true,
            'width' => true,
        );
        $allowed['video'] = array(
            'class' => true,
            'controls' => true,
            'loop' => true,
            'muted' => true,
            'playsinline' => true,
            'poster' => true,
            'preload' => true,
        );
        $allowed['source'] = array(
            'src' => true,
            'type' => true,
        );

        return $allowed;
    }
}


if (!function_exists('bvmgr_vendor_profiles_social_svg')) {
    function bvmgr_vendor_profiles_social_svg(string $key): string
    {
        $svg_map = array(
            'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.4l.4-3h-2.8V9.2c0-.9.3-1.5 1.6-1.5H16V5.1c-.2 0-1-.1-2-.1-2 0-3.4 1.2-3.4 3.5V11H8v3h2.6v7h2.9Z"/></svg>',
            'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7.5 3h9A4.5 4.5 0 0 1 21 7.5v9a4.5 4.5 0 0 1-4.5 4.5h-9A4.5 4.5 0 0 1 3 16.5v-9A4.5 4.5 0 0 1 7.5 3Zm0 1.8A2.7 2.7 0 0 0 4.8 7.5v9a2.7 2.7 0 0 0 2.7 2.7h9a2.7 2.7 0 0 0 2.7-2.7v-9a2.7 2.7 0 0 0-2.7-2.7h-9Zm9.75 1.35a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1ZM12 7.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5Zm0 1.8A2.7 2.7 0 1 0 14.7 12 2.7 2.7 0 0 0 12 9.3Z"/></svg>',
            'x' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.9 3H21l-6.2 7.1L22 21h-5.6l-4.4-6.4L6.4 21H4.3l6.6-7.5L2 3h5.7l4 5.9L18.9 3Zm-2 16h1.6L7.2 4.9H5.5L16.9 19Z"/></svg>',
            'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14.7 3c.3 2 1.5 3.3 3.4 3.7v2.3c-1.4 0-2.7-.4-3.8-1.2v6.1a5 5 0 1 1-5-5c.3 0 .7 0 1 .1v2.4a2.9 2.9 0 1 0 1.7 2.6V3h2.7Z"/></svg>',
            'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.2 7.2a2.9 2.9 0 0 0-2-2C17.4 4.7 12 4.7 12 4.7s-5.4 0-7.2.5a2.9 2.9 0 0 0-2 2A30 30 0 0 0 2.3 12c0 1.6.2 3.2.5 4.8a2.9 2.9 0 0 0 2 2c1.8.5 7.2.5 7.2.5s5.4 0 7.2-.5a2.9 2.9 0 0 0 2-2c.3-1.6.5-3.2.5-4.8s-.2-3.2-.5-4.8ZM10.2 15.8V8.2l6 3.8-6 3.8Z"/></svg>',
            'spotify' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.5a9.5 9.5 0 1 0 9.5 9.5A9.5 9.5 0 0 0 12 2.5Zm4.3 13.7a1 1 0 0 1-1.4.3 8.2 8.2 0 0 0-8.5-.5 1 1 0 1 1-1-1.8 10.2 10.2 0 0 1 10.6.6 1 1 0 0 1 .3 1.4Zm1.4-3.1a1.2 1.2 0 0 1-1.6.4 10.7 10.7 0 0 0-10.7-.7 1.2 1.2 0 1 1-1-2.2 13 13 0 0 1 12.9.8 1.2 1.2 0 0 1 .4 1.7Zm.2-3.2a1.4 1.4 0 0 1-1.9.5 13 13 0 0 0-12.2-.8 1.4 1.4 0 1 1-1.2-2.5 15.7 15.7 0 0 1 14.9.9 1.4 1.4 0 0 1 .4 1.9Z"/></svg>',
        );

        return isset($svg_map[$key]) ? (string) $svg_map[$key] : '<span class="vms-vp-social__fallback" aria-hidden="true">' . esc_html(strtoupper(substr((string) $key, 0, 1))) . '</span>';
    }
}

if (!function_exists('bvmgr_vendor_profiles_render_social_links')) {
    function bvmgr_vendor_profiles_render_social_links(int $vendor_id): string
    {
        $links = bvmgr_vendor_profiles_get_social_links($vendor_id);
        if (empty($links)) {
            return '';
        }

        ob_start();
        echo '<div class="vms-vp-socials" aria-label="' . esc_attr__('Social links', 'backstage-venue-manager') . '">';
        foreach ($links as $key => $url) {
            echo '<a class="vms-vp-social vms-vp-social--' . esc_attr($key) . '" href="' . esc_url($url) . '" target="_blank" rel="noopener" aria-label="' . esc_attr(bvmgr_vendor_profiles_social_label((string) $key)) . '"><span class="vms-vp-social__glyph" aria-hidden="true">' . wp_kses(bvmgr_vendor_profiles_social_svg((string) $key), bvmgr_vendor_profiles_social_icon_allowed_html()) . '</span></a>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}

if (!function_exists('bvmgr_vendor_profiles_render_event_teaser')) {
    function bvmgr_vendor_profiles_render_event_teaser(int $vendor_id, int $tec_event_id = 0, array $args = array()): string
    {
        $vendor_id = (int) $vendor_id;
        $tec_event_id = (int) $tec_event_id;

        if ($vendor_id <= 0) {
            return '';
        }

        if (function_exists('bvmgr_vendor_profile_is_enabled') && !bvmgr_vendor_profile_is_enabled($vendor_id)) {
            return '';
        }

        $profile_url = function_exists('bvmgr_vendor_profile_url') ? (string) bvmgr_vendor_profile_url($vendor_id) : '';
        if ($profile_url === '') {
            return '';
        }

        $vendor = get_post($vendor_id);
        if (!($vendor instanceof WP_Post)) {
            return '';
        }

        $about = '';
        $raw_content = trim((string) $vendor->post_content);
        if ($raw_content !== '') {
            $about = wp_strip_all_tags($raw_content);
            if (function_exists('wp_trim_words')) {
                $about = (string) wp_trim_words($about, 34, '…');
            }
        }

        $layout = isset($args['layout']) ? sanitize_key((string) $args['layout']) : 'full';
        $classes = 'vms-vendor-teaser vms-vp-card';
        if ($layout === 'compact') {
            $classes .= ' vms-vendor-teaser--compact';
        }

        $title_text = get_the_title($vendor_id);
        $teaser_label = bvmgr_vendor_profiles_teaser_label($vendor_id);
        $teaser_heading = bvmgr_vendor_profiles_teaser_heading($vendor_id);

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classes); ?>" aria-label="<?php echo esc_attr($teaser_label); ?>">
            <div class="vms-vendor-teaser__media">
                <?php if (has_post_thumbnail($vendor_id)) : ?>
                    <a class="vms-vendor-teaser__thumb" href="<?php echo esc_url($profile_url); ?>">
                        <?php echo get_the_post_thumbnail($vendor_id, 'medium_large'); ?>
                    </a>
                <?php else : ?>
                    <a class="vms-vendor-teaser__thumb vms-vendor-teaser__thumb--placeholder" href="<?php echo esc_url($profile_url); ?>" aria-hidden="true"></a>
                <?php endif; ?>
            </div>
            <div class="vms-vendor-teaser__body">
                <p class="vms-vendor-teaser__eyebrow"><?php echo esc_html($teaser_label); ?></p>
                <h2 class="vms-vendor-teaser__title"><a class="vms-vendor-teaser__title-link" href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html($teaser_heading); ?></a></h2>

                <?php if ($layout !== 'compact' && $about !== '') : ?>
                    <p class="vms-vendor-teaser__about"><?php echo esc_html($about); ?></p>
                <?php endif; ?>

                <?php
                $promo_plan_id = $tec_event_id > 0 && function_exists('bvmgr_get_event_plan_for_tec_event')
                    ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id)
                    : 0;
                $promo_markup = ($promo_plan_id > 0 && function_exists('bvmgr_vendor_portal_render_headliner_promo_video_player'))
                    ? (string) bvmgr_vendor_portal_render_headliner_promo_video_player($promo_plan_id, array(
                        'context' => 'public',
                        /* translators: %s: vendor title. */
                        'heading' => sprintf(__('A quick hello from %s', 'backstage-venue-manager'), $title_text),
                        'wrap_class' => 'vms-vendor-teaser__promo-video',
                    ))
                    : '';
                ?>
                <?php if ($promo_markup !== '') : ?>
                    <?php echo wp_kses($promo_markup, bvmgr_vendor_profiles_promo_allowed_html()); ?>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('bvmgr_vendor_profiles_render_next_show_card')) {
    function bvmgr_vendor_profiles_render_next_show_card(int $vendor_id, array $args = array()): string
    {
        $vendor_id = (int) $vendor_id;
        if ($vendor_id <= 0) {
            return '';
        }

        $next_event = bvmgr_vendor_profiles_find_next_upcoming_event($vendor_id);
        if (empty($next_event)) {
            return '';
        }

        $layout = isset($args['layout']) ? sanitize_key((string) $args['layout']) : 'full';
        $classes = 'vms-vp-card vms-vp-next-show';
        if ($layout === 'compact') {
            $classes .= ' vms-vp-next-show--compact';
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr($classes); ?>">
            <p class="vms-vp-next-show__eyebrow"><?php echo esc_html__('Next show', 'backstage-venue-manager'); ?></p>
            <h2 class="vms-vp-h2 vms-vp-next-show__title"><?php echo esc_html($next_event['title'] ?? ''); ?></h2>
            <?php if (!empty($next_event['date_label'])) : ?>
                <p class="vms-vp-next-show__date"><?php echo esc_html($next_event['date_label']); ?></p>
            <?php endif; ?>
            <?php
            $promo_markup = (!empty($next_event['plan_id']) && function_exists('bvmgr_vendor_portal_render_headliner_promo_video_player'))
                ? (string) bvmgr_vendor_portal_render_headliner_promo_video_player((int) $next_event['plan_id'], array(
                    'context' => 'public',
                    'heading' => __('Promo video', 'backstage-venue-manager'),
                    'wrap_class' => 'vms-vp-next-show__promo-video',
                ))
                : '';
            ?>
            <?php if ($promo_markup !== '') : ?>
                <?php echo wp_kses($promo_markup, bvmgr_vendor_profiles_promo_allowed_html()); ?>
            <?php endif; ?>
            <?php if (!empty($next_event['url'])) : ?>
                <div class="vms-vp-actions vms-vp-next-show__actions">
                    <a class="vms-vp-btn" href="<?php echo esc_url((string) $next_event['url']); ?>"><?php echo esc_html__('Get tickets to our show', 'backstage-venue-manager'); ?></a>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

function bvmgr_vendor_profiles_shortcode_vendor_teaser(array $atts = array()): string
{
    $atts = shortcode_atts(array(
        'vendor' => 'current',
        'event'  => 0,
        'layout' => 'compact',
        'role'   => 'primary',
        'type'   => '',
    ), $atts, 'vms_vendor_teaser');

    $event_id = (int) $atts['event'];
    $role = sanitize_key((string) $atts['role']);
    if ($role !== 'secondary') {
        $role = 'primary';
    }

    $type_filter = sanitize_title((string) $atts['type']);
    if ($event_id <= 0 && is_singular('tribe_events')) {
        $event_id = (int) get_queried_object_id();
    }

    if (bvmgr_vendor_profiles_shortcode_targets_event_sidebar($atts['vendor'] ?? 'current', $event_id, $atts)) {
        if (bvmgr_vendor_profiles_event_sidebar_rendered($event_id)) {
            return '';
        }

        $grouped_markup = bvmgr_vendor_profiles_render_event_vendor_sidebar($event_id);
        if ($grouped_markup !== '') {
            return $grouped_markup;
        }
    }

    $vendor_id = bvmgr_vendor_profiles_resolve_vendor_id($atts['vendor'], $event_id, $role, $type_filter);
    if ($vendor_id <= 0 && is_singular('tribe_events')) {
        $vendor_id = bvmgr_vendor_profiles_get_related_vendor_for_tec_event((int) get_queried_object_id(), $role, $type_filter);
    }

    return bvmgr_vendor_profiles_render_event_teaser($vendor_id, $event_id, array('layout' => $atts['layout']));
}

function bvmgr_vendor_profiles_shortcode_secondary_vendor_teaser(array $atts = array()): string
{
    $atts = shortcode_atts(array(
        'vendor' => 'current',
        'event'  => 0,
        'layout' => 'compact',
        'type'   => '',
    ), $atts, 'vms_secondary_vendor_teaser');

    $atts['role'] = 'secondary';
    return bvmgr_vendor_profiles_shortcode_vendor_teaser($atts);
}

function bvmgr_vendor_profiles_shortcode_next_show(array $atts = array()): string
{
    $atts = shortcode_atts(array(
        'vendor' => 'current',
        'layout' => 'compact',
    ), $atts, 'vms_vendor_next_show');

    $vendor_id = bvmgr_vendor_profiles_resolve_vendor_id($atts['vendor']);
    return bvmgr_vendor_profiles_render_next_show_card($vendor_id, array('layout' => $atts['layout']));
}

if (!function_exists('bvmgr_vendor_profiles_render_event_promo_video_section')) {
    function bvmgr_vendor_profiles_render_event_promo_video_section(int $vendor_id, int $tec_event_id): string
    {
        $vendor_id = (int) $vendor_id;
        $tec_event_id = (int) $tec_event_id;
        if ($vendor_id <= 0 || $tec_event_id <= 0) {
            return '';
        }

        $promo_plan_id = function_exists('bvmgr_get_event_plan_for_tec_event')
            ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id)
            : 0;
        if ($promo_plan_id <= 0 || !function_exists('bvmgr_vendor_portal_render_headliner_promo_video_player')) {
            return '';
        }

        $vendor_name = trim((string) get_the_title($vendor_id));
        $heading = $vendor_name !== ''
            /* translators: %s: vendor title. */
            ? sprintf(__('A quick hello from %s', 'backstage-venue-manager'), $vendor_name)
            : __('A quick hello from the artist', 'backstage-venue-manager');

        $promo_markup = (string) bvmgr_vendor_portal_render_headliner_promo_video_player($promo_plan_id, array(
            'context' => 'public',
            'heading' => $heading,
            'wrap_class' => 'vms-event-promo-video__player-wrap',
        ));
        if ($promo_markup === '') {
            return '';
        }

        ob_start();
        ?>
        <section class="vms-event-promo-video vms-vp-card" aria-label="<?php echo esc_attr($heading); ?>">
            <?php echo wp_kses($promo_markup, bvmgr_vendor_profiles_promo_allowed_html()); ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

function bvmgr_vendor_profiles_append_event_vendor_teaser(string $content): string
{
    if (is_admin()) {
        return $content;
    }

    if (!is_singular('tribe_events')) {
        return $content;
    }

    if (function_exists('is_main_query') && !is_main_query()) {
        return $content;
    }

    if (function_exists('in_the_loop') && !in_the_loop()) {
        return $content;
    }

    $tec_event_id = (int) get_the_ID();
    if ($tec_event_id <= 0 || $tec_event_id !== (int) get_queried_object_id()) {
        return $content;
    }

    if (!(bool) apply_filters('vms_vendor_profiles_auto_append_event_teaser', true, $tec_event_id)) {
        return $content;
    }

    if (strpos($content, 'vms-event-promo-video') !== false) {
        return $content;
    }

    $vendor_id = bvmgr_vendor_profiles_get_primary_vendor_for_tec_event($tec_event_id);
    if ($vendor_id <= 0) {
        return $content;
    }

    $promo_section = bvmgr_vendor_profiles_render_event_promo_video_section($vendor_id, $tec_event_id);
    if ($promo_section === '') {
        return $content;
    }

    return $content . $promo_section;
}
