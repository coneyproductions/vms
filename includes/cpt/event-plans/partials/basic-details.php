<?php
/**
 * Event Plans editor partial: basic details + integrity/prefill notices.
 *
 * Expected variables are provided via capture_event_plan_partial(get_defined_vars()).
 *
 * @var WP_Post $post
 */

if (!defined('ABSPATH')) {
    exit;
}

$k_issue = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_issue') : '_vms_integrity_issue';
$issue = (string) get_post_meta($post->ID, $k_issue, true);
if ($issue === 'missing_vendor') {
    $k_vt = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
    $vendor_title = (string) get_post_meta($post->ID, $k_vt, true);
    if ($vendor_title === '') {
        $vendor_title = __('(unknown vendor)', 'backstage-venue-manager');
    }

    echo '<div class="notice notice-error inline vms-notice vms-notice--critical"><p>' .
        esc_html__('🚩 This event plan lost its vendor (the vendor was deleted) and needs attention.', 'backstage-venue-manager') .
        /* translators: %s: previous vendor. */
        ' ' . sprintf(esc_html__('Previous vendor: %s', 'backstage-venue-manager'), esc_html($vendor_title)) .
        ' ' . esc_html__('Select a new Primary Vendor, then mark Ready again.', 'backstage-venue-manager') .
        '</p></div>';
} elseif ($issue === 'missing_secondary_vendor') {
    $k_vt = function_exists('vms_meta_key') ? vms_meta_key('event_plan', 'integrity_vendor_title') : '_vms_integrity_vendor_title';
    $vendor_title = (string) get_post_meta($post->ID, $k_vt, true);
    if ($vendor_title === '') {
        $vendor_title = __('(unknown vendor)', 'backstage-venue-manager');
    }

    echo '<div class="notice notice-warning inline vms-notice vms-notice--warning"><p>' .
        esc_html__('🚩 This event plan lost a secondary vendor (the vendor was deleted) and needs attention.', 'backstage-venue-manager') .
        /* translators: %s: removed vendor. */
        ' ' . sprintf(esc_html__('Removed vendor: %s', 'backstage-venue-manager'), esc_html($vendor_title)) .
        ' ' . esc_html__('Review the Secondary Vendors section below, then mark Ready again if needed.', 'backstage-venue-manager') .
        '</p></div>';
}

if ($post->post_status === 'auto-draft' && isset($_GET['vms_prefill_vendor_id'], $_GET['vms_prefill_vendor_mode'])) {
    $prefill_vendor_id = absint($_GET['vms_prefill_vendor_id']);
    $prefill_mode = sanitize_key(wp_unslash((string) $_GET['vms_prefill_vendor_mode']));
    $prefill_vendor_label = isset($_GET['vms_prefill_vendor_label']) ? sanitize_text_field(wp_unslash((string) $_GET['vms_prefill_vendor_label'])) : '';
    if ($prefill_vendor_id > 0) {
        $resolved_vendor_label = $prefill_vendor_label !== '' ? $prefill_vendor_label : (string) get_the_title($prefill_vendor_id);
        if ($resolved_vendor_label === '') {
            $resolved_vendor_label = __('Selected vendor', 'backstage-venue-manager');
        }

        if ($prefill_mode === 'secondary') {
            $prefill_type_label = '';
            if (isset($_GET['vms_prefill_vendor_type'])) {
                $prefill_type_slug = function_exists('vms_vendor_type_normalize_slug')
                    ? vms_vendor_type_normalize_slug((string) wp_unslash((string) $_GET['vms_prefill_vendor_type']))
                    : sanitize_title(wp_unslash((string) $_GET['vms_prefill_vendor_type']));
                if ($prefill_type_slug !== '') {
                    $prefill_type_term = function_exists('vms_vendor_type_get_term')
                        ? vms_vendor_type_get_term($prefill_type_slug)
                        : get_term_by('slug', $prefill_type_slug, 'vms_vendor_type');
                    if ($prefill_type_term instanceof WP_Term) {
                        $prefill_type_label = (string) $prefill_type_term->name;
                    }
                }
            }

            $secondary_message = ($prefill_type_label !== '')
                /* translators: 1: value 1 used in this message, 2: value 2 used in this message. */
                ? sprintf(__('Booking prefill: %1$s was added as a secondary vendor (%2$s). Review the Secondary Vendors section below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label, $prefill_type_label)
                /* translators: %s: booking prefill. */
                : sprintf(__('Booking prefill: %s was added as a secondary vendor. Review the Secondary Vendors section below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label);
            echo '<div class="notice notice-info inline vms-notice"><p>' . esc_html($secondary_message) . '</p></div>';
        } elseif ($prefill_mode === 'primary') {
            /* translators: %s: booking prefill. */
            $primary_message = sprintf(__('Booking prefill: %s was added as the primary vendor. Review below, then save the Event Plan.', 'backstage-venue-manager'), $resolved_vendor_label);
            echo '<div class="notice notice-info inline vms-notice"><p>' . esc_html($primary_message) . '</p></div>';
        }
    }
}
?>

<div class="vms-ep-basic-grid">
    <p class="vms-ep-basic-item">
        <label for="vms_event_date"><strong><?php esc_html_e('Event Date', 'backstage-venue-manager'); ?></strong></label><br />
        <input type="date" id="vms_event_date" name="vms_event_date" value="<?php echo esc_attr($event_date); ?>" />
    </p>

    <p class="vms-ep-basic-item">
        <label for="vms_venue_id"><strong><?php esc_html_e('Venue', 'backstage-venue-manager'); ?></strong></label><br />
        <select id="vms_venue_id" name="vms_venue_id" class="vms-ep-select-md" required>
            <option value=""><?php esc_html_e('-- Select a Venue --', 'backstage-venue-manager'); ?></option>
            <?php foreach ($venues as $venue): ?>
                <option value="<?php echo esc_attr($venue->ID); ?>" <?php selected($venue_id_effective, $venue->ID); ?>>
                    <?php echo esc_html($venue->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br /><span class="description"><?php esc_html_e('Required. This scopes the event plan to a specific venue.', 'backstage-venue-manager'); ?></span>
    </p>

    <?php
    $holiday = null;
    if ($venue_id_effective > 0 && $event_date && function_exists('vms_get_venue_holiday_for_date')) {
        $holiday = vms_get_venue_holiday_for_date($venue_id_effective, $event_date);
    }
    ?>
    <div class="vms-ep-basic-item vms-ep-basic-span">
        <h4><?php esc_html_e('Holiday', 'backstage-venue-manager'); ?></h4>
        <div class="vms-ep-holiday-card">
            <?php if ($venue_id_effective <= 0 || !$event_date): ?>
                <p class="description vms-m0"><?php esc_html_e('Select a Venue and Event Date to see holiday status.', 'backstage-venue-manager'); ?></p>
            <?php elseif (!$holiday): ?>
                <p class="description vms-m0"><?php esc_html_e('No holiday is configured for this venue on the selected date.', 'backstage-venue-manager'); ?></p>
                <p class="description vms-mt-8 vms-mb-0"><?php esc_html_e('Holiday pay is role-dependent and will apply automatically once holidays are configured.', 'backstage-venue-manager'); ?></p>
            <?php else: ?>
                <?php $badge_class = (($holiday['status'] ?? '') === 'closed') ? 'vms-ep-badge vms-ep-badge--closed' : 'vms-ep-badge vms-ep-badge--open'; ?>
                <p class="vms-m0 vms-mb-8">
                    <span class="<?php echo esc_attr($badge_class); ?>">
                        <?php echo (($holiday['status'] ?? '') === 'closed') ? esc_html__('CLOSED', 'backstage-venue-manager') : esc_html__('OPEN', 'backstage-venue-manager'); ?>
                    </span>
                    <?php
                    $holiday_name = trim((string) ($holiday['name'] ?? ''));
                    if ($holiday_name !== '') {
                        echo ' <strong class="vms-ml-8">' . esc_html($holiday_name) . '</strong>';
                    }
                    ?>
                </p>
                <?php if (($holiday['status'] ?? '') === 'closed'): ?>
                    <p class="description vms-m0"><?php esc_html_e('This venue is marked CLOSED on this holiday. This Event Plan cannot be marked READY or Published.', 'backstage-venue-manager'); ?></p>
                <?php else: ?>
                    <p class="description vms-m0"><?php esc_html_e('Holiday pay/hours are role-dependent and will be applied automatically (once holiday rules are configured).', 'backstage-venue-manager'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
