<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Public Venue Calendar (Shortcode)
 * ==========================================================
 *
 * Usage:
 *  [vms_venue_calendar]
 *  [vms_venue_calendar venue="123"]
 *
 * Optional URL params supported:
 *  ?venue_id=123&ym=2026-01
 *
 * Security options (we’ll add token next):
 *  - If you want "public", do nothing.
 *  - If you want "restricted", we add a venue share token.
 */

add_shortcode('vms_venue_calendar', function ($atts) {
    $atts = shortcode_atts([
        'venue' => '',
        'month' => '',
    ], $atts, 'vms_venue_calendar');

    $venue_id = $atts['venue'] !== '' ? absint($atts['venue']) : 0;
    $ym = $atts['month'] !== '' ? sanitize_text_field($atts['month']) : '';

    // Allow URL override (nice UX)
    if (isset($_GET['venue_id'])) $venue_id = absint($_GET['venue_id']);
    if (isset($_GET['ym'])) $ym = sanitize_text_field(wp_unslash($_GET['ym']));

    if ($ym === '') $ym = gmdate('Y-m');

    // If venue not specified, show a picker
    $venues = get_posts([
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    if ($venue_id <= 0 && !empty($venues)) $venue_id = (int) $venues[0]->ID;

    if ($venue_id <= 0) {
        return '<div>No venues found.</div>';
    }

    $data = vms_get_event_plans_for_venue_month($venue_id, $ym);
    $month = $data['month'];
    $days  = $data['days'];
    $nav   = vms_calendar_prev_next($ym);

    // Build a “self URL” (same page) for nav
    $self = get_permalink();
    $base = add_query_arg(['venue_id' => $venue_id], $self);

    ob_start();
    ?>
    <div class="vms-public-cal">
        <form method="get" style="margin: 12px 0 14px;">
            <label style="font-weight:700;margin-right:8px;">Venue</label>
            <select name="venue_id" style="min-width:280px;">
                <?php foreach ($venues as $v): ?>
                    <option value="<?php echo esc_attr($v->ID); ?>" <?php selected($venue_id, $v->ID); ?>>
                        <?php echo esc_html($v->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label style="font-weight:700;margin:0 8px 0 14px;">Month</label>
            <input type="month" name="ym" value="<?php echo esc_attr($month['ym']); ?>" />
            <button type="submit">Go</button>
        </form>

        <div style="display:flex;align-items:center;justify-content:space-between;max-width:1100px;margin:10px 0 12px;">
            <a href="<?php echo esc_url(add_query_arg(['ym' => $nav['prev']], $base)); ?>">← <?php echo esc_html($nav['prev']); ?></a>
            <div style="font-weight:800;"><?php echo esc_html(date_i18n('F Y', strtotime($month['start']))); ?></div>
            <a href="<?php echo esc_url(add_query_arg(['ym' => $nav['next']], $base)); ?>"><?php echo esc_html($nav['next']); ?> →</a>
        </div>

        <?php
        // Reuse the same renderer, but in public mode (no edit links)
        echo vms_render_month_grid($month, $days, false);
        ?>
    </div>
    <?php

    return ob_get_clean();
});