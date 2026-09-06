<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Discounts_Loader
{
    /** @var VMS_Discounts_Loader|null */
    protected static $instance = null;

    /** @var VMS_Discounts_Rules */
    protected $rules;

    /** @var VMS_Discounts_Mapping */
    protected $mapping;

    /** @var VMS_Discounts_Cart */
    protected $cart;

    /** @var VMS_Discounts_Admin */
    protected $admin;

    /** @var VMS_Discounts_Settings */
    protected $settings;

    /** @var VMS_Discounts_Order */
    protected $order;

    /** @var VMS_Discounts_Square_Bridge|null */
    protected $square_bridge;

    /** @var VMS_Discounts_Tips */
    protected $tips;

    public static function instance(): VMS_Discounts_Loader
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', static function () {
                if (!current_user_can('manage_options')) {
                    return;
                }
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('VMS Commerce Discounts requires WooCommerce to be active.', 'vms-commerce-discounts')
                    . '</p></div>';
            });
            return;
        }

        try {
            $this->require_files();

            add_action('wp_ajax_vms_discounts_search_products', ['VMS_Discounts_UI', 'ajax_search_products']);

            $this->rules = new VMS_Discounts_Rules();
            $this->mapping = new VMS_Discounts_Mapping();
            $this->cart = new VMS_Discounts_Cart($this->rules, $this->mapping);
            $this->admin = new VMS_Discounts_Admin($this->rules);
            $this->settings = new VMS_Discounts_Settings($this->rules);
            $this->tips = new VMS_Discounts_Tips($this->mapping);
            $this->order = new VMS_Discounts_Order($this->cart, $this->rules, $this->mapping);
            if ($this->square_bridge_parent_available() && class_exists('VMS_Discounts_Square_Bridge', false)) {
                $this->square_bridge = new VMS_Discounts_Square_Bridge($this->order);
            } else {
                $this->square_bridge = null;
                add_action('admin_notices', [$this, 'render_square_bridge_unavailable_notice']);
                $this->maybe_log_square_bridge_unavailable();
            }
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[vms-commerce-discounts] Boot failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            }

            add_action('admin_notices', static function () {
                if (!current_user_can('manage_options')) {
                    return;
                }
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('VMS Commerce Discounts failed to initialize. Check PHP logs.', 'vms-commerce-discounts')
                    . '</p></div>';
            });
        }
    }

    public static function activate(): void
    {
        self::instance()->require_files();

        $rules = new VMS_Discounts_Rules();
        $rules->maybe_migrate_legacy();
    }

    protected function require_files(): void
    {
        require_once VMS_DISCOUNTS_PATH . 'includes/helpers.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-ui.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-rules.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-mapping.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-cart.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-admin.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-settings.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-tips.php';
        require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-order.php';
        if ($this->square_bridge_parent_available()) {
            require_once VMS_DISCOUNTS_PATH . 'includes/class-vms-discounts-square-bridge.php';
        }
    }

    protected function square_bridge_parent_available(): bool
    {
        return class_exists('\\WooCommerce\\Square\\Gateway\\API\\Requests\\Orders');
    }

    public function render_square_bridge_unavailable_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . esc_html__('WooCommerce Square integration is unavailable. Commerce Discounts will continue without Square-specific discount synchronization.', 'vms-commerce-discounts')
            . '</p></div>';
    }

    protected function maybe_log_square_bridge_unavailable(): void
    {
        if (!function_exists('vms_discounts_debug_log')) {
            return;
        }

        vms_discounts_debug_log(
            'Square bridge integration unavailable because WooCommerce Square request classes are not loaded. Continuing without native Square discount synchronization.',
            [
                'parent_class' => 'WooCommerce\\Square\\Gateway\\API\\Requests\\Orders',
            ]
        );
    }
}
