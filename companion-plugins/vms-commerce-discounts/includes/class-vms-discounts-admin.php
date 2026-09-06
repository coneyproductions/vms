<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Admin
{
    /** @var VMS_Discounts_Rules */
    protected $rules;

    public function __construct(VMS_Discounts_Rules $rules)
    {
        $this->rules = $rules;

        add_action('add_meta_boxes', [$this, 'register_event_rules_metabox']);
        add_action('save_post', [$this, 'save_event_rules_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_event_rules_metabox(): void
    {
        foreach ($this->event_post_types() as $post_type) {
            if (!post_type_exists($post_type)) {
                continue;
            }

            add_meta_box(
                'vms-discounts-event-rules',
                'VMS Commerce Discounts',
                [$this, 'render_event_rules_metabox'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * @param \WP_Post $post
     */
    public function render_event_rules_metabox($post): void
    {
        $rules = $this->rules->get_event_rules((int) $post->ID);

        wp_nonce_field('vms_discounts_save_event_rules', 'vms_discounts_event_rules_nonce');

        echo '<p>Configure per-event rules using the form fields below. Raw JSON is still available in the collapsed Advanced section.</p>';
        echo '<p><strong>Need the global settings page?</strong> Use <em>WooCommerce &gt; VMS Discounts</em>. These rules are VMS discounts, not WooCommerce coupons.</p>';

        VMS_Discounts_UI::render_rules_builder(
            'vms_discounts_event_rules_form',
            $rules,
            'vms_discounts_event_rules_json',
            'vms_discounts_event_use_advanced',
            'Advanced (JSON)'
        );
    }

    /**
     * @param int $post_id
     * @param \WP_Post $post
     */
    public function save_event_rules_metabox(int $post_id, $post): void
    {
        if (!isset($_POST['vms_discounts_event_rules_nonce'])) {
            return;
        }

        if (!wp_verify_nonce((string) $_POST['vms_discounts_event_rules_nonce'], 'vms_discounts_save_event_rules')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($post->post_type, $this->event_post_types(), true)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $form_rules_raw = isset($_POST['vms_discounts_event_rules_form'])
            ? wp_unslash($_POST['vms_discounts_event_rules_form'])
            : [];

        $form_rules = VMS_Discounts_UI::rules_from_form($form_rules_raw);

        $use_advanced = isset($_POST['vms_discounts_event_use_advanced']);
        if ($use_advanced) {
            $raw_json = isset($_POST['vms_discounts_event_rules_json'])
                ? (string) wp_unslash($_POST['vms_discounts_event_rules_json'])
                : '';

            $parsed_rules = vms_discounts_parse_json_rules(trim($raw_json));
            if ($raw_json !== '' && empty($parsed_rules)) {
                return;
            }

            $this->rules->save_event_rules($post_id, $parsed_rules);
            return;
        }

        $this->rules->save_event_rules($post_id, $form_rules);
    }

    /**
     * @param string $hook_suffix
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_post_type = is_object($screen) && isset($screen->post_type) ? (string) $screen->post_type : '';

        if (!in_array($screen_post_type, $this->event_post_types(), true)) {
            return;
        }

        wp_enqueue_style(
            'vms-discounts-admin',
            VMS_DISCOUNTS_URL . 'assets/admin/discounts-admin.css',
            [],
            VMS_DISCOUNTS_VERSION
        );

        wp_enqueue_script(
            'vms-discounts-admin',
            VMS_DISCOUNTS_URL . 'assets/admin/discounts-admin.js',
            [],
            VMS_DISCOUNTS_VERSION,
            true
        );

        wp_localize_script('vms-discounts-admin', 'VMS_DISCOUNTS_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'productSearchNonce' => wp_create_nonce('vms_discounts_search_products'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function event_post_types(): array
    {
        $defaults = [
            'tribe_events',
            'vms_event_plan',
            'vms-event-plan',
        ];

        $post_types = apply_filters('vms_discounts_event_post_types', $defaults);
        $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', vms_discounts_array($post_types)))));

        return empty($post_types) ? $defaults : $post_types;
    }
}
