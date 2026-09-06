<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Settings
{
    /** @var VMS_Discounts_Rules */
    protected $rules;

    /** @var string */
    protected $page_hook = '';

    public function __construct(VMS_Discounts_Rules $rules)
    {
        $this->rules = $rules;

        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_page(): void
    {
        $this->page_hook = add_submenu_page(
            'woocommerce',
            'VMS Commerce Discounts',
            'VMS Discounts',
            'manage_options',
            'vms-commerce-discounts',
            [$this, 'render_page']
        );
    }

    /**
     * @param string $hook_suffix
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        if ($this->page_hook === '' || $hook_suffix !== $this->page_hook) {
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

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = '';
        $notice_type = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vms_discounts_settings_nonce'])) {
            check_admin_referer('vms_discounts_save_settings', 'vms_discounts_settings_nonce');

            $form_rules_raw = isset($_POST['vms_discounts_global_rules_form'])
                ? wp_unslash($_POST['vms_discounts_global_rules_form'])
                : [];

            $form_rules = VMS_Discounts_UI::rules_from_form($form_rules_raw);

            $use_advanced = isset($_POST['vms_discounts_global_use_advanced']);
            $raw_json = isset($_POST['vms_discounts_global_rules_json'])
                ? (string) wp_unslash($_POST['vms_discounts_global_rules_json'])
                : '';

            if ($use_advanced) {
                $parsed_rules = vms_discounts_parse_json_rules($raw_json);
                if ($raw_json !== '' && empty($parsed_rules)) {
                    $notice = 'Global rules JSON could not be parsed. Settings were not saved.';
                    $notice_type = 'error';
                } else {
                    $this->rules->save_global_rules($parsed_rules);
                }
            } else {
                $this->rules->save_global_rules($form_rules);
            }

            if ($notice_type !== 'error') {
                update_option(
                    VMS_Discounts_Rules::GLOBAL_ENABLED_OPTION_KEY,
                    isset($_POST['vms_discounts_global_enabled']) ? 'yes' : 'no',
                    false
                );

                update_option(
                    VMS_Discounts_Rules::DEBUG_OPTION_KEY,
                    isset($_POST['vms_discounts_debug']) ? 'yes' : 'no',
                    false
                );

                update_option(
                    VMS_Discounts_Rules::ORDER_NOTE_OPTION_KEY,
                    isset($_POST['vms_discounts_order_note']) ? 'yes' : 'no',
                    false
                );

                $square_mode = isset($_POST['vms_discounts_square_mode'])
                    ? sanitize_key((string) wp_unslash($_POST['vms_discounts_square_mode']))
                    : VMS_Discounts_Rules::SQUARE_MODE_NATIVE;

                if (!in_array($square_mode, [VMS_Discounts_Rules::SQUARE_MODE_NATIVE, VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY], true)) {
                    $square_mode = VMS_Discounts_Rules::SQUARE_MODE_NATIVE;
                }

                update_option(
                    VMS_Discounts_Rules::SQUARE_MODE_OPTION_KEY,
                    $square_mode,
                    false
                );

                $tips_scope = isset($_POST['vms_discounts_tips_scope'])
                    ? sanitize_key((string) wp_unslash($_POST['vms_discounts_tips_scope']))
                    : VMS_Discounts_Tips::SCOPE_REGULAR_PRODUCT_ORDERS;
                if (!array_key_exists($tips_scope, VMS_Discounts_Tips::scope_options())) {
                    $tips_scope = VMS_Discounts_Tips::SCOPE_REGULAR_PRODUCT_ORDERS;
                }

                $tips_label = isset($_POST['vms_discounts_tips_label'])
                    ? sanitize_text_field((string) wp_unslash($_POST['vms_discounts_tips_label']))
                    : 'Tip / Gratuity';
                if (trim($tips_label) === '') {
                    $tips_label = 'Tip / Gratuity';
                }

                $tips_heading = isset($_POST['vms_discounts_tips_heading'])
                    ? sanitize_text_field((string) wp_unslash($_POST['vms_discounts_tips_heading']))
                    : 'Add a tip for the crew?';
                if (trim($tips_heading) === '') {
                    $tips_heading = 'Add a tip for the crew?';
                }

                $tips_description = isset($_POST['vms_discounts_tips_description'])
                    ? sanitize_text_field((string) wp_unslash($_POST['vms_discounts_tips_description']))
                    : '';

                $tips_presets = isset($_POST['vms_discounts_tips_presets'])
                    ? (string) wp_unslash($_POST['vms_discounts_tips_presets'])
                    : '1,3,5';
                $tips_presets_clean = implode(',', VMS_Discounts_Tips::sanitize_presets($tips_presets));
                if ($tips_presets_clean === '') {
                    $tips_presets_clean = '1,3,5';
                }

                $tips_max_amount = isset($_POST['vms_discounts_tips_max_amount'])
                    ? (float) wc_clean(wp_unslash($_POST['vms_discounts_tips_max_amount']))
                    : 100.0;
                if ($tips_max_amount <= 0.0) {
                    $tips_max_amount = 100.0;
                }

                update_option(VMS_Discounts_Tips::ENABLED_OPTION_KEY, isset($_POST['vms_discounts_tips_enabled']) ? 'yes' : 'no', false);
                update_option(VMS_Discounts_Tips::SCOPE_OPTION_KEY, $tips_scope, false);
                update_option(VMS_Discounts_Tips::LABEL_OPTION_KEY, $tips_label, false);
                update_option(VMS_Discounts_Tips::HEADING_OPTION_KEY, $tips_heading, false);
                update_option(VMS_Discounts_Tips::DESCRIPTION_OPTION_KEY, $tips_description, false);
                update_option(VMS_Discounts_Tips::PRESETS_OPTION_KEY, $tips_presets_clean, false);
                update_option(VMS_Discounts_Tips::ALLOW_CUSTOM_OPTION_KEY, isset($_POST['vms_discounts_tips_allow_custom']) ? 'yes' : 'no', false);
                update_option(VMS_Discounts_Tips::TAXABLE_OPTION_KEY, isset($_POST['vms_discounts_tips_taxable']) ? 'yes' : 'no', false);
                update_option(VMS_Discounts_Tips::MAX_AMOUNT_OPTION_KEY, $tips_max_amount, false);

                $notice = 'VMS Commerce Discounts settings saved.';
                $notice_type = 'success';
            }
        }

        $global_rules = $this->rules->get_global_rules();
        $global_enabled = $this->rules->global_rules_enabled();
        $debug_enabled = $this->rules->debug_enabled();
        $order_note_enabled = $this->rules->should_add_order_note();
        $square_mode = vms_discounts_get_square_mode();
        $tips_enabled = VMS_Discounts_Tips::is_enabled();
        $tips_scope = VMS_Discounts_Tips::get_scope();
        $tips_label = VMS_Discounts_Tips::get_tip_label();
        $tips_heading = VMS_Discounts_Tips::get_heading();
        $tips_description = VMS_Discounts_Tips::get_description();
        $tips_presets = implode(',', VMS_Discounts_Tips::get_presets());
        $tips_allow_custom = VMS_Discounts_Tips::custom_amount_allowed();
        $tips_taxable = VMS_Discounts_Tips::tips_are_taxable();
        $tips_max_amount = VMS_Discounts_Tips::get_max_amount();

        ?>
        <div class="wrap vms-discounts-admin-wrap">
            <h1>VMS Commerce Discounts</h1>
            <p><strong>Canonical admin destination:</strong> WooCommerce &gt; VMS Discounts. These are VMS rule-based discounts, not WooCommerce coupon rules.</p>

            <?php if ($notice !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('vms_discounts_save_settings', 'vms_discounts_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Enable global rules</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vms_discounts_global_enabled" value="1" <?php checked($global_enabled); ?> />
                                    Allow global rules in cart evaluation
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Add order note summary</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vms_discounts_order_note" value="1" <?php checked($order_note_enabled); ?> />
                                    Add a summarized order note of applied discount rules
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Square discount mode</th>
                            <td>
                                <select name="vms_discounts_square_mode">
                                    <option value="<?php echo esc_attr(VMS_Discounts_Rules::SQUARE_MODE_NATIVE); ?>" <?php selected($square_mode, VMS_Discounts_Rules::SQUARE_MODE_NATIVE); ?>>
                                        Native Square discount (preferred)
                                    </option>
                                    <option value="<?php echo esc_attr(VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY); ?>" <?php selected($square_mode, VMS_Discounts_Rules::SQUARE_MODE_COMPATIBILITY); ?>>
                                        Compatibility reduced-price fallback (temporary)
                                    </option>
                                </select>
                                <p class="description">Native mode pre-creates a Square order with explicit VMS discount records. Compatibility mode keeps the reduced-price stopgap for checkout continuity.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug logging</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vms_discounts_debug" value="1" <?php checked($debug_enabled); ?> />
                                    Enable rule-evaluation debug logs (Woo logger source: <code>vms-commerce-discounts</code>)
                                </label>
                            </td>
                        </tr>
                        <tr class="vms-discounts-section-row">
                            <th scope="row">Tips / gratuity</th>
                            <td>
                                <div class="vms-discounts-settings-card">
                                    <h2>Checkout Tips</h2>
                                    <p>Optional customer tip prompt. Tips are added as a WooCommerce fee line and kept separate from discount rules, ticket counts, inventory, and event add-on qualification.</p>

                                    <div class="vms-discounts-settings-grid">
                                        <label class="vms-discounts-field-checkbox">
                                            <input type="checkbox" name="vms_discounts_tips_enabled" value="1" <?php checked($tips_enabled); ?> />
                                            <span>Enable tips at checkout</span>
                                            <p class="vms-discounts-help">Turn this on when you are ready for customers to see the tip prompt.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Where tips appear</span>
                                            <select name="vms_discounts_tips_scope">
                                                <?php foreach (VMS_Discounts_Tips::scope_options() as $scope_key => $scope_label) : ?>
                                                    <option value="<?php echo esc_attr($scope_key); ?>" <?php selected($tips_scope, $scope_key); ?>><?php echo esc_html($scope_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="vms-discounts-help">Use regular product orders for today if Express Bar products are normal WooCommerce products.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Fee label</span>
                                            <input type="text" name="vms_discounts_tips_label" value="<?php echo esc_attr($tips_label); ?>" />
                                            <p class="vms-discounts-help">Shown as the fee line in cart, checkout, receipts, and Woo orders.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Customer heading</span>
                                            <input type="text" name="vms_discounts_tips_heading" value="<?php echo esc_attr($tips_heading); ?>" />
                                            <p class="vms-discounts-help">Prompt text shown above the tip buttons.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Customer description</span>
                                            <input type="text" name="vms_discounts_tips_description" value="<?php echo esc_attr($tips_description); ?>" />
                                            <p class="vms-discounts-help">Keep this short so checkout stays fast.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Preset amounts</span>
                                            <input type="text" name="vms_discounts_tips_presets" value="<?php echo esc_attr($tips_presets); ?>" />
                                            <p class="vms-discounts-help">Comma-separated flat dollar amounts, such as <code>1,3,5</code>.</p>
                                        </label>

                                        <label class="vms-discounts-field">
                                            <span>Maximum custom tip</span>
                                            <input type="number" min="1" step="0.01" name="vms_discounts_tips_max_amount" value="<?php echo esc_attr((string) $tips_max_amount); ?>" />
                                            <p class="vms-discounts-help">Safety cap for accidental custom entries.</p>
                                        </label>

                                        <label class="vms-discounts-field-checkbox">
                                            <input type="checkbox" name="vms_discounts_tips_allow_custom" value="1" <?php checked($tips_allow_custom); ?> />
                                            <span>Allow custom tip amount</span>
                                            <p class="vms-discounts-help">Customers can type their own amount in addition to preset buttons.</p>
                                        </label>

                                        <label class="vms-discounts-field-checkbox">
                                            <input type="checkbox" name="vms_discounts_tips_taxable" value="1" <?php checked($tips_taxable); ?> />
                                            <span>Tax the tip fee</span>
                                            <p class="vms-discounts-help">Default is off. Confirm tax treatment with your accountant before enabling.</p>
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Global rules</th>
                            <td>
                                <?php
                                VMS_Discounts_UI::render_rules_builder(
                                    'vms_discounts_global_rules_form',
                                    $global_rules,
                                    'vms_discounts_global_rules_json',
                                    'vms_discounts_global_use_advanced',
                                    'Advanced (JSON)'
                                );
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Save Discount Settings'); ?>
            </form>
        </div>
        <?php
    }
}
