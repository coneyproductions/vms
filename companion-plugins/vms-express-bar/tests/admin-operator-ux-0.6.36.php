<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('VMSEB_URL', 'https://example.test/wp-content/plugins/vms-express-bar/');
define('VMSEB_VERSION', '0.6.38-test');

class WP_Post
{
    public int $ID;
    public string $post_type;

    public function __construct(int $id, string $post_type = 'product')
    {
        $this->ID = $id;
        $this->post_type = $post_type;
    }
}

class WC_Product
{
    protected int $id;
    protected string $type;

    public function __construct(int $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function get_id(): int { return $this->id; }
    public function is_type(string $type): bool { return $this->type === $type; }
}

class WC_Product_Variation extends WC_Product
{
    private int $parent_id;

    public function __construct(int $id, int $parent_id)
    {
        parent::__construct($id, 'variation');
        $this->parent_id = $parent_id;
    }

    public function get_parent_id(): int { return $this->parent_id; }
}

$products = array(
    101 => new WC_Product(101, 'simple'),
    202 => new WC_Product(202, 'variable'),
    203 => new WC_Product_Variation(203, 202),
);
$registry = array(
    'p_101' => array('enabled' => 1),
    'v_203' => array('enabled' => 1),
);
$event_meta = array(
    '_vms_express_bar_enabled' => '1',
    '_vmseb_auto_embed' => '0',
    '_vmseb_open_at' => '',
    '_vmseb_close_at' => '',
    '_vms_express_bar_headline' => '',
    '_vms_express_bar_pickup_instructions' => '',
);
$meta_updates = array();
$rendered_checkbox = array();

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return htmlspecialchars($text, ENT_QUOTES); }
function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_textarea(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url(string $url): string { return $url; }
function wp_kses_post(string $html): string { return $html; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field(string $action, string $name): void { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid" data-action="' . esc_attr($action) . '" />'; }
function checked($checked, $current = true, bool $echo = true): string
{
    $output = $checked == $current ? 'checked="checked"' : '';
    if ($echo) echo $output;
    return $output;
}
function current_user_can(string $capability, ...$args): bool { unset($capability, $args); return true; }
function sanitize_text_field($value): string { return trim((string) $value); }
function sanitize_textarea_field($value): string { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce(string $nonce, string $action): bool { return $nonce === 'valid' && $action === 'vmseb_save_900'; }
function wc_get_product(int $id) { global $products; return $products[$id] ?? false; }
function woocommerce_wp_checkbox(array $field): void
{
    global $rendered_checkbox;
    $rendered_checkbox = $field;
    echo '<input type="checkbox" id="' . esc_attr((string) $field['id']) . '" value="' . esc_attr((string) $field['checked_value']) . '" ' . checked($field['value'], $field['checked_value'], false) . ' />';
}
function vmseb_get_registry(bool $refresh = false): array { global $registry; unset($refresh); return $registry; }
function vmseb_get_event_meta(int $event_plan_id): array
{
    global $event_meta;
    unset($event_plan_id);
    return array(
        'enabled' => $event_meta['_vms_express_bar_enabled'] === '1',
        'auto_embed' => $event_meta['_vmseb_auto_embed'] !== '0',
        'opens_at' => $event_meta['_vmseb_open_at'],
        'closes_at' => $event_meta['_vmseb_close_at'],
    );
}
function get_post_meta(int $post_id, string $key, bool $single = false)
{
    global $event_meta;
    unset($post_id, $single);
    return $event_meta[$key] ?? '';
}
function update_post_meta(int $post_id, string $key, $value): bool
{
    global $event_meta, $meta_updates;
    unset($post_id);
    $event_meta[$key] = $value;
    $meta_updates[$key] = $value;
    return true;
}
function vmseb_invalidate_event_plan_public_cache(int $event_plan_id): array { unset($event_plan_id); return array(); }

require dirname(__DIR__) . '/includes/admin.php';

function vmseb_ux_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

global $post;
$post = new WP_Post(101);
ob_start();
vmseb_render_woo_product_membership_control();
$simple_html = (string) ob_get_clean();
vmseb_ux_assert(($rendered_checkbox['id'] ?? '') === '_vmseb_show_in_express_bar', 'simple product control did not render');
vmseb_ux_assert(($rendered_checkbox['value'] ?? '') === 'yes', 'simple product control did not read vmseb_catalog_registry state');
vmseb_ux_assert(strpos($simple_html, 'checked="checked"') !== false, 'simple product registry state was not reflected in the checkbox');

$post = new WP_Post(202);
ob_start();
vmseb_render_woo_product_membership_control();
$variable_html = (string) ob_get_clean();
vmseb_ux_assert(strpos($variable_html, 'notice notice-info inline') !== false, 'variable product guidance is not a native information block');
vmseb_ux_assert(strpos($variable_html, 'This is a variable product. Express Bar visibility is managed separately for each variation.') !== false, 'variable product guidance is incomplete');
vmseb_ux_assert(strpos($variable_html, 'Open the Variations tab') !== false, 'variable product guidance does not direct operators to Variations');
vmseb_ux_assert(strpos($variable_html, 'class="button button-secondary"') !== false && strpos($variable_html, 'Manage Bar Menu') !== false, 'variable product guidance lacks a Manage Bar Menu button');

ob_start();
vmseb_render_woo_variation_membership_control(5, array(), new WP_Post(203, 'product_variation'));
$variation_html = (string) ob_get_clean();
vmseb_ux_assert(strpos($variation_html, 'variable_vmseb_show_in_express_bar[5]') !== false, 'per-variation control did not render');
vmseb_ux_assert(strpos($variation_html, 'checked="checked"') !== false, 'per-variation control did not read registry state');

$event_post = new WP_Post(900, 'vms_event_plan');
ob_start();
vmseb_render_metabox($event_post);
$metabox_html = (string) ob_get_clean();
vmseb_ux_assert(strpos($metabox_html, 'Express Bar Menu:') !== false && strpos($metabox_html, 'Manage Bar Menu') !== false, 'Event Plan metabox lacks the simplified Bar Menu link');
vmseb_ux_assert(strpos($metabox_html, 'page=vms-bar-menu') !== false, 'Event Plan Bar Menu link has the wrong destination');
vmseb_ux_assert(strpos($metabox_html, 'Uses the global Express Bar Menu') === false && strpos($metabox_html, 'Uses the global') === false, 'old global-menu wording remains');
vmseb_ux_assert(strpos($metabox_html, 'Show an Express Bar link on the public event page when possible.') === false, 'legacy event-page-link checkbox remains visible');
vmseb_ux_assert(strpos($metabox_html, 'name="vmseb_auto_embed"') === false, 'legacy auto-embed input remains visible');
vmseb_ux_assert(strpos($metabox_html, 'ticket-purchase flow') !== false, 'current workflow help copy is missing');
vmseb_ux_assert(strpos($metabox_html, '[vms_express_bar_menu event_plan_id=&quot;900&quot;]') !== false, 'operator/debug shortcode was unexpectedly removed');

$_POST = array(
    'vmseb_nonce' => 'valid',
    'vms_express_bar_enabled' => '1',
    'vmseb_open_at' => '',
    'vmseb_close_at' => '',
    'vms_express_bar_headline' => '',
    'vms_express_bar_pickup_instructions' => '',
);
vmseb_save_metabox(900, $event_post);
vmseb_ux_assert(!array_key_exists('_vmseb_auto_embed', $meta_updates), 'absent legacy checkbox rewrote _vmseb_auto_embed');
vmseb_ux_assert($event_meta['_vmseb_auto_embed'] === '0', 'absent legacy checkbox changed its persisted value');

$meta_updates = array();
$_POST['vmseb_auto_embed'] = '1';
vmseb_save_metabox(900, $event_post);
vmseb_ux_assert(($meta_updates['_vmseb_auto_embed'] ?? '') === '1', 'legacy posted auto-embed value is no longer compatible');

$public_source = (string) file_get_contents(dirname(__DIR__) . '/includes/public.php');
vmseb_ux_assert(strpos($public_source, "add_filter('bvmgr_ticketing_post_cart_offer', 'vmseb_ticketing_post_cart_offer', 10, 3);") !== false, 'ticket-flow Express Bar offer hook changed');
vmseb_ux_assert(strpos($public_source, "add_filter('the_content'") === false, 'legacy event-page CTA unexpectedly became active');

$expected_public_hashes = array(
    'includes/public.php' => '32d3b52a26fa0ed09c573188523a986013d23fbd97599757a1cebce81499859a',
    'assets/js/public.js' => '32ab1dd61915254c71e10cad43f4e311950a89db353da0db3884dd962d202898',
    'assets/css/public.css' => 'c9840313d05808041c7b66c5731279768565983b9b18e9813ec2f1b89b067e0d',
);
foreach ($expected_public_hashes as $file => $hash) {
    vmseb_ux_assert(hash_file('sha256', dirname(__DIR__) . '/' . $file) === $hash, $file . ' changed from accepted frontend');
}

fwrite(STDOUT, "PASS: Express Bar admin/operator UX 0.6.38\n");
