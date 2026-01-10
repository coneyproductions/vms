<?php
if (!defined('ABSPATH')) exit;

/**
 * VMS – Vendor Defaults (Admin)
 *
 * Purpose:
 * - Stores default compensation settings for this vendor
 * - These values are copied into Event Plans when:
 *   - "Apply Band Defaults" is clicked
 *   - OR auto-applied if enabled
 * 
 */

/**
 * Register metabox
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_vendor_defaults',
        __('Event Plan Defaults', 'vms'),
        'vms_render_vendor_defaults_metabox',
        'vms_vendor',
        'normal',
        'default'
    );
});

/**
 * Save handler
 */
add_action('save_post_vms_vendor', function ($post_id, $post) {

    if ($post->post_type !== 'vms_vendor') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (
        empty($_POST['vms_vendor_defaults_nonce']) ||
        !wp_verify_nonce($_POST['vms_vendor_defaults_nonce'], 'vms_save_vendor_defaults')
    ) {
        return;
    }

    $get = function ($key) {
        return isset($_POST[$key])
            ? sanitize_text_field(wp_unslash($_POST[$key]))
            : '';
    };

    // Compensation defaults
    update_post_meta($post_id, '_vms_default_comp_structure', $get('vms_default_comp_structure'));

    $flat = $get('vms_default_flat_fee_amount');
    update_post_meta($post_id, '_vms_default_flat_fee_amount', $flat === '' ? '' : (float) $flat);

    $split = $get('vms_default_door_split_percent');
    update_post_meta($post_id, '_vms_default_door_split_percent', $split === '' ? '' : (float) $split);

    $comm = $get('vms_default_commission_percent');
    update_post_meta($post_id, '_vms_default_commission_percent', $comm === '' ? '' : (float) $comm);

    $mode = $get('vms_default_commission_mode');
    if (!in_array($mode, ['artist_fee', 'gross'], true)) {
        $mode = 'artist_fee';
    }
    update_post_meta($post_id, '_vms_default_commission_mode', $mode);

}, 10, 2);

/**
 * Render metabox
 */
function vms_render_vendor_defaults_metabox($post)
{
    wp_nonce_field('vms_save_vendor_defaults', 'vms_vendor_defaults_nonce');

    $m = function ($key, $default = '') use ($post) {
        $v = get_post_meta($post->ID, $key, true);
        return ($v === '' || $v === null) ? $default : $v;
    };

    $structure = $m('_vms_default_comp_structure', 'flat_fee');
    $flat_fee = $m('_vms_default_flat_fee_amount');
    $split    = $m('_vms_default_door_split_percent');
    $comm     = $m('_vms_default_commission_percent', '15');
    $mode     = $m('_vms_default_commission_mode', 'artist_fee');
    ?>

    <style>
        .vms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
            max-width: 760px;
        }
        .vms-field label {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }
        .vms-field input,
        .vms-field select {
            width: 100%;
            max-width: 260px;
        }
        .vms-help {
            font-size: 12px;
            color: #646970;
            margin-top: 4px;
        }
    </style>

    <p class="description">
        <?php esc_html_e(
            'These defaults are applied to new Event Plans when you click “Apply Band Defaults”. They do not retroactively change existing plans.',
            'vms'
        ); ?>
    </p>

    <div class="vms-grid">

        <div class="vms-field">
            <label for="vms_default_comp_structure"><?php esc_html_e('Comp Structure', 'vms'); ?></label>
            <select id="vms_default_comp_structure" name="vms_default_comp_structure">
                <option value="flat_fee" <?php selected($structure, 'flat_fee'); ?>>Flat Fee Only</option>
                <option value="flat_fee_door_split" <?php selected($structure, 'flat_fee_door_split'); ?>>Flat Fee + Door Split</option>
                <option value="door_split" <?php selected($structure, 'door_split'); ?>>Door Split Only</option>
            </select>
        </div>

        <div class="vms-field">
            <label for="vms_default_flat_fee_amount"><?php esc_html_e('Flat Fee ($)', 'vms'); ?></label>
            <input type="number" step="0.01" min="0"
                   id="vms_default_flat_fee_amount"
                   name="vms_default_flat_fee_amount"
                   value="<?php echo esc_attr($flat_fee); ?>">
        </div>

        <div class="vms-field">
            <label for="vms_default_door_split_percent"><?php esc_html_e('Door Split %', 'vms'); ?></label>
            <input type="number" step="0.01" min="0" max="100"
                   id="vms_default_door_split_percent"
                   name="vms_default_door_split_percent"
                   value="<?php echo esc_attr($split); ?>">
        </div>

        <div class="vms-field">
            <label for="vms_default_commission_percent"><?php esc_html_e('Agency Commission %', 'vms'); ?></label>
            <input type="number" step="0.01" min="0" max="100"
                   id="vms_default_commission_percent"
                   name="vms_default_commission_percent"
                   value="<?php echo esc_attr($comm); ?>">
            <div class="vms-help">
                <?php esc_html_e('Default is 15%.', 'vms'); ?>
            </div>
        </div>

        <div class="vms-field">
            <label for="vms_default_commission_mode"><?php esc_html_e('Commission Mode', 'vms'); ?></label>
            <select id="vms_default_commission_mode" name="vms_default_commission_mode">
                <option value="artist_fee" <?php selected($mode, 'artist_fee'); ?>>
                    Added to artist fee
                </option>
                <option value="gross" <?php selected($mode, 'gross'); ?>>
                    Taken from gross
                </option>
            </select>
        </div>

    </div>

    <?php
}