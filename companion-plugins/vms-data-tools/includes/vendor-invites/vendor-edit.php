<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_register_vendor_lang_metabox')) {
    function vms_dt_vio_register_vendor_lang_metabox(): void
    {
        add_meta_box(
            'vms-dt-vio-lang',
            __('Portal Invite Language', 'vms-data-tools'),
            'vms_dt_vio_render_vendor_lang_metabox',
            'vms_vendor',
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes_vms_vendor', 'vms_dt_vio_register_vendor_lang_metabox');

if (!function_exists('vms_dt_vio_render_vendor_lang_metabox')) {
    function vms_dt_vio_render_vendor_lang_metabox(WP_Post $post): void
    {
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }

        wp_nonce_field('vms_dt_vio_vendor_lang_save', 'vms_dt_vio_vendor_lang_nonce');

        $preferred = vms_dt_vio_get_vendor_preferred_lang((int) $post->ID);
        $status = vms_dt_vio_get_vendor_portal_status((int) $post->ID);
        $langs = vms_dt_vio_supported_languages();

        echo '<p>';
        echo '<label for="vms_dt_vio_vendor_preferred_lang"><strong>' . esc_html__('Preferred language for invites', 'vms-data-tools') . '</strong></label><br>';
        echo '<select id="vms_dt_vio_vendor_preferred_lang" name="vms_dt_vio_vendor_preferred_lang">';
        echo '<option value="">' . esc_html__('Use site default', 'vms-data-tools') . '</option>';
        foreach ($langs as $code => $label) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($preferred, $code, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        echo '<p>';
        echo '<strong>' . esc_html__('Portal status', 'vms-data-tools') . ':</strong> ';
        echo esc_html(ucfirst($status));
        echo '</p>';
    }
}

if (!function_exists('vms_dt_vio_save_vendor_lang_metabox')) {
    function vms_dt_vio_save_vendor_lang_metabox(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'vms_vendor') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = isset($_POST['vms_dt_vio_vendor_lang_nonce'])
            ? sanitize_text_field((string) wp_unslash($_POST['vms_dt_vio_vendor_lang_nonce']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_dt_vio_vendor_lang_save')) {
            return;
        }

        $lang_raw = isset($_POST['vms_dt_vio_vendor_preferred_lang'])
            ? (string) wp_unslash($_POST['vms_dt_vio_vendor_preferred_lang'])
            : '';
        $lang = vms_dt_vio_sanitize_lang($lang_raw);
        vms_dt_vio_set_vendor_preferred_lang($post_id, $lang);

        // Ensure portal status defaults to unclaimed for imported/legacy vendors without a marker.
        $keys = vms_dt_vio_vendor_meta_keys();
        $status_raw = (string) get_post_meta($post_id, $keys['portal_status'], true);
        if (trim($status_raw) === '') {
            vms_dt_vio_set_vendor_portal_status($post_id, 'unclaimed');
        }
    }
}
add_action('save_post_vms_vendor', 'vms_dt_vio_save_vendor_lang_metabox', 20, 2);
