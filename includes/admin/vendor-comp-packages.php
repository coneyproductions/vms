<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Comp Packages (Venue-scoped)
 * CPT: vms_comp_package
 *
 * Goal: UI-driven, flexible comp rules; avoid hardcoded assumptions.
 */

add_action('init', function () {

    register_post_type('vms_comp_package', array(
        'labels' => array(
            'name'          => __('Comp Packages', 'vms'),
            'singular_name' => __('Comp Package', 'vms'),
            'menu_name'     => __('Comp Packages', 'vms'),
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'vms',
        'menu_icon'     => 'dashicons-money-alt',
        'supports'      => array('title'),
        'has_archive'   => false,
        'rewrite'       => false,
    ));
});

add_action('add_meta_boxes', function () {
    add_meta_box(
        'vms_comp_package_details',
        __('Comp Package Details', 'vms'),
        'vms_render_comp_package_meta_box',
        'vms_comp_package',
        'normal',
        'default'
    );
});

function vms_render_comp_package_meta_box($post)
{
    wp_nonce_field('vms_save_comp_package', 'vms_comp_package_nonce');

    // Venue scope
    $venue_id = (int) get_post_meta($post->ID, '_vms_venue_id', true);

    // Core type
    $type = (string) get_post_meta($post->ID, '_vms_comp_type', true);
    if (!$type) $type = 'flat'; // flat | flat_plus_split | door_split

    // Flat fee
    $flat_fee = get_post_meta($post->ID, '_vms_flat_fee', true);

    // Door split
    $split_basis = (string) get_post_meta($post->ID, '_vms_split_basis', true);
    if (!$split_basis) $split_basis = 'gross'; // gross | net

    $split_percent_artist = get_post_meta($post->ID, '_vms_split_percent_artist', true); // 0-100

    // Commission (abstract)
    $commission_percent = get_post_meta($post->ID, '_vms_commission_percent', true); // 0-100
    $commission_mode = (string) get_post_meta($post->ID, '_vms_commission_mode', true);
    if (!$commission_mode) $commission_mode = 'none'; // none | add_on_top | deduct_from_artist

    $commission_base = (string) get_post_meta($post->ID, '_vms_commission_base', true);
    if (!$commission_base) $commission_base = 'flat_fee'; // flat_fee | gross | net

    // Optional guardrails
    $min_guarantee = get_post_meta($post->ID, '_vms_min_guarantee', true);
    $cap_amount    = get_post_meta($post->ID, '_vms_cap_amount', true);

    $notes = (string) get_post_meta($post->ID, '_vms_notes', true);

    // Venue list
    $venues = get_posts(array(
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    ?>
    <p>
        <label for="vms_venue_id"><strong><?php esc_html_e('Venue Scope', 'vms'); ?></strong></label><br />
        <select id="vms_venue_id" name="vms_venue_id" style="min-width:280px;">
            <option value="0" <?php selected($venue_id, 0); ?>>
                <?php esc_html_e('— Global Template (optional) —', 'vms'); ?>
            </option>
            <?php foreach ($venues as $v) : ?>
                <option value="<?php echo esc_attr($v->ID); ?>" <?php selected($venue_id, (int)$v->ID); ?>>
                    <?php echo esc_html($v->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><span class="description">
            <?php esc_html_e('Typically set to a specific venue. Global templates are optional and can be ignored.', 'vms'); ?>
        </span>
    </p>

    <hr />

    <p>
        <label for="vms_comp_type"><strong><?php esc_html_e('Comp Type', 'vms'); ?></strong></label><br />
        <select id="vms_comp_type" name="vms_comp_type" style="min-width:280px;">
            <option value="flat" <?php selected($type, 'flat'); ?>>
                <?php esc_html_e('Flat Fee', 'vms'); ?>
            </option>
            <option value="flat_plus_split" <?php selected($type, 'flat_plus_split'); ?>>
                <?php esc_html_e('Flat Fee + Door Split', 'vms'); ?>
            </option>
            <option value="door_split" <?php selected($type, 'door_split'); ?>>
                <?php esc_html_e('Door Split Only', 'vms'); ?>
            </option>
        </select>
    </p>

    <p>
        <label for="vms_flat_fee"><strong><?php esc_html_e('Flat Fee Amount', 'vms'); ?></strong></label><br />
        <input type="number" step="0.01" id="vms_flat_fee" name="vms_flat_fee" style="width:160px;"
               value="<?php echo esc_attr($flat_fee); ?>" />
    </p>

    <hr />

    <h4 style="margin:0 0 6px;"><?php esc_html_e('Door Split', 'vms'); ?></h4>

    <p>
        <label for="vms_split_basis"><strong><?php esc_html_e('Split Basis', 'vms'); ?></strong></label><br />
        <select id="vms_split_basis" name="vms_split_basis" style="min-width:280px;">
            <option value="gross" <?php selected($split_basis, 'gross'); ?>>
                <?php esc_html_e('Gross (simpler)', 'vms'); ?>
            </option>
            <option value="net" <?php selected($split_basis, 'net'); ?>>
                <?php esc_html_e('Net (more accurate)', 'vms'); ?>
            </option>
        </select>
    </p>

    <p>
        <label for="vms_split_percent_artist"><strong><?php esc_html_e('Artist Split %', 'vms'); ?></strong></label><br />
        <input type="number" step="0.01" min="0" max="100"
               id="vms_split_percent_artist" name="vms_split_percent_artist" style="width:160px;"
               value="<?php echo esc_attr($split_percent_artist); ?>" /> %
        <br><span class="description">
            <?php esc_html_e('Venue split is implicitly (100 - Artist%).', 'vms'); ?>
        </span>
    </p>

    <hr />

    <h4 style="margin:0 0 6px;"><?php esc_html_e('Agency Commission (Abstract)', 'vms'); ?></h4>

    <p>
        <label for="vms_commission_mode"><strong><?php esc_html_e('Commission Mode', 'vms'); ?></strong></label><br />
        <select id="vms_commission_mode" name="vms_commission_mode" style="min-width:280px;">
            <option value="none" <?php selected($commission_mode, 'none'); ?>>
                <?php esc_html_e('None', 'vms'); ?>
            </option>
            <option value="add_on_top" <?php selected($commission_mode, 'add_on_top'); ?>>
                <?php esc_html_e('Add on top (venue pays artist fee + commission)', 'vms'); ?>
            </option>
            <option value="deduct_from_artist" <?php selected($commission_mode, 'deduct_from_artist'); ?>>
                <?php esc_html_e('Deduct from artist (commission taken from payout)', 'vms'); ?>
            </option>
        </select>
    </p>

    <p>
        <label for="vms_commission_percent"><strong><?php esc_html_e('Commission %', 'vms'); ?></strong></label><br />
        <input type="number" step="0.01" min="0" max="100"
               id="vms_commission_percent" name="vms_commission_percent" style="width:160px;"
               value="<?php echo esc_attr($commission_percent); ?>" /> %
    </p>

    <p>
        <label for="vms_commission_base"><strong><?php esc_html_e('Commission Base', 'vms'); ?></strong></label><br />
        <select id="vms_commission_base" name="vms_commission_base" style="min-width:280px;">
            <option value="flat_fee" <?php selected($commission_base, 'flat_fee'); ?>>
                <?php esc_html_e('Flat Fee', 'vms'); ?>
            </option>
            <option value="gross" <?php selected($commission_base, 'gross'); ?>>
                <?php esc_html_e('Gross', 'vms'); ?>
            </option>
            <option value="net" <?php selected($commission_base, 'net'); ?>>
                <?php esc_html_e('Net', 'vms'); ?>
            </option>
        </select>
    </p>

    <hr />

    <h4 style="margin:0 0 6px;"><?php esc_html_e('Guardrails (Optional)', 'vms'); ?></h4>

    <p>
        <label for="vms_min_guarantee"><strong><?php esc_html_e('Minimum Guarantee', 'vms'); ?></strong></label><br />
        <input type="number" step="0.01" id="vms_min_guarantee" name="vms_min_guarantee" style="width:160px;"
               value="<?php echo esc_attr($min_guarantee); ?>" />
    </p>

    <p>
        <label for="vms_cap_amount"><strong><?php esc_html_e('Cap Amount', 'vms'); ?></strong></label><br />
        <input type="number" step="0.01" id="vms_cap_amount" name="vms_cap_amount" style="width:160px;"
               value="<?php echo esc_attr($cap_amount); ?>" />
    </p>

    <p>
        <label for="vms_notes"><strong><?php esc_html_e('Internal Notes', 'vms'); ?></strong></label><br />
        <textarea id="vms_notes" name="vms_notes" rows="4" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
    </p>

    <script>
        // Tiny UI helper: show/hide relevant sections based on type (no backend dependency)
        (function(){
            const typeSel = document.getElementById('vms_comp_type');
            const flatFee = document.getElementById('vms_flat_fee')?.closest('p');
            const splitBlocks = [
                document.getElementById('vms_split_basis')?.closest('p'),
                document.getElementById('vms_split_percent_artist')?.closest('p'),
            ].filter(Boolean);

            function refresh(){
                const t = typeSel.value;
                if (flatFee) flatFee.style.display = (t === 'door_split') ? 'none' : '';
                splitBlocks.forEach(el => {
                    el.style.display = (t === 'flat') ? 'none' : '';
                });
            }
            if (typeSel) {
                typeSel.addEventListener('change', refresh);
                refresh();
            }
        })();
    </script>
    <?php
}

add_action('save_post_vms_comp_package', function ($post_id, $post) {

    if (!isset($_POST['vms_comp_package_nonce']) || !wp_verify_nonce($_POST['vms_comp_package_nonce'], 'vms_save_comp_package')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $venue_id = isset($_POST['vms_venue_id']) ? absint($_POST['vms_venue_id']) : 0;

    $type = isset($_POST['vms_comp_type']) ? sanitize_text_field($_POST['vms_comp_type']) : 'flat';
    if (!in_array($type, array('flat', 'flat_plus_split', 'door_split'), true)) $type = 'flat';

    $flat_fee = isset($_POST['vms_flat_fee']) ? (float) $_POST['vms_flat_fee'] : '';

    $split_basis = isset($_POST['vms_split_basis']) ? sanitize_text_field($_POST['vms_split_basis']) : 'gross';
    if (!in_array($split_basis, array('gross', 'net'), true)) $split_basis = 'gross';

    $split_pct_artist = isset($_POST['vms_split_percent_artist']) ? (float) $_POST['vms_split_percent_artist'] : '';

    $commission_mode = isset($_POST['vms_commission_mode']) ? sanitize_text_field($_POST['vms_commission_mode']) : 'none';
    if (!in_array($commission_mode, array('none', 'add_on_top', 'deduct_from_artist'), true)) $commission_mode = 'none';

    $commission_pct  = isset($_POST['vms_commission_percent']) ? (float) $_POST['vms_commission_percent'] : '';
    $commission_base = isset($_POST['vms_commission_base']) ? sanitize_text_field($_POST['vms_commission_base']) : 'flat_fee';
    if (!in_array($commission_base, array('flat_fee', 'gross', 'net'), true)) $commission_base = 'flat_fee';

    $min_guarantee = isset($_POST['vms_min_guarantee']) ? (float) $_POST['vms_min_guarantee'] : '';
    $cap_amount    = isset($_POST['vms_cap_amount']) ? (float) $_POST['vms_cap_amount'] : '';

    $notes = isset($_POST['vms_notes']) ? sanitize_textarea_field($_POST['vms_notes']) : '';

    update_post_meta($post_id, '_vms_venue_id', $venue_id);
    update_post_meta($post_id, '_vms_comp_type', $type);
    update_post_meta($post_id, '_vms_flat_fee', $flat_fee);

    update_post_meta($post_id, '_vms_split_basis', $split_basis);
    update_post_meta($post_id, '_vms_split_percent_artist', $split_pct_artist);

    update_post_meta($post_id, '_vms_commission_mode', $commission_mode);
    update_post_meta($post_id, '_vms_commission_percent', $commission_pct);
    update_post_meta($post_id, '_vms_commission_base', $commission_base);

    update_post_meta($post_id, '_vms_min_guarantee', $min_guarantee);
    update_post_meta($post_id, '_vms_cap_amount', $cap_amount);

    update_post_meta($post_id, '_vms_notes', $notes);

}, 10, 2);
