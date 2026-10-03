<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('VMSEB_URL', 'https://example.test/wp-content/plugins/vms-express-bar/');
define('VMSEB_VERSION', '0.6.35-test');

final class WooCommerce {}

class WP_Post
{
    public int $ID;
    public string $post_type;
    public string $post_status;

    public function __construct(int $id, string $post_type = 'product', string $post_status = 'publish')
    {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_status = $post_status;
    }
}

class WP_Term
{
    public string $name;
    public string $slug;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->slug = strtolower(str_replace(' ', '-', $name));
    }
}

class WC_Product
{
    protected int $id;
    protected string $type;
    protected string $name;
    protected string $sku;
    protected array $variation_ids;

    public function __construct(int $id, string $type, string $name, string $sku = '', array $variation_ids = array())
    {
        $this->id = $id;
        $this->type = $type;
        $this->name = $name;
        $this->sku = $sku;
        $this->variation_ids = $variation_ids;
    }

    public function get_id(): int { return $this->id; }
    public function is_type(string $type): bool { return $this->type === $type; }
    public function get_name(): string { return $this->name; }
    public function get_image_id(): int { return 0; }
    public function get_price_html(): string { return '$5.00'; }
    public function managing_stock(): bool { return false; }
    public function get_stock_quantity() { return null; }
    public function is_purchasable(): bool { return true; }
    public function get_stock_status(): string { return 'instock'; }
    public function get_sku(): string { return $this->sku; }
    public function get_available_variations(): array
    {
        return array_map(static function(int $id): array {
            return array('variation_id' => $id);
        }, $this->variation_ids);
    }
}

class WC_Product_Variation extends WC_Product
{
    private int $parent_id;

    public function __construct(int $id, int $parent_id, string $name, string $sku = '')
    {
        parent::__construct($id, 'variation', $name, $sku);
        $this->parent_id = $parent_id;
    }

    public function get_parent_id(): int { return $this->parent_id; }
}

$products = array(
    101 => new WC_Product(101, 'simple', 'House Red', 'HR-101'),
    102 => new WC_Product(102, 'simple', 'Sparkling Water', 'SW-102'),
    202 => new WC_Product(202, 'variable', 'Draft Beer', '', array(203)),
    203 => new WC_Product_Variation(203, 202, 'Draft Beer - Pint', 'DB-PINT'),
);
$categories = array(101 => 'Wine', 102 => 'Non-Alcoholic', 202 => 'Beer');
$stored_options = array(
    'vmseb_catalog_registry' => array(
        'p_101' => array(
            'enabled' => 0,
            'category' => 'Old Wine Name',
            'sort' => 7,
            'bucket_eligible' => 1,
            'age_gate' => 1,
            'online_cap' => 4,
            'bar_only_threshold' => 2,
        ),
    ),
    'vmseb_bar_menu_defaults' => array(),
);
$cache_invalidations = 0;
$rendered_checkbox = array();
$registered_actions = array();

function absint($value): int { return abs((int) $value); }
function sanitize_title(string $value): string { return strtolower(str_replace(' ', '-', $value)); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?? ''; }
function sanitize_text_field($value): string { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function wp_json_encode($value): string { return json_encode($value) ?: ''; }
function post_type_exists(string $type): bool { return $type === 'product'; }
function get_posts(array $args): array { return ($args['post_type'] ?? '') === 'product' ? array(101, 102, 202) : array(); }
function wc_get_product(int $id) { global $products; return $products[$id] ?? false; }
function wc_get_price_to_display(WC_Product $product): float { unset($product); return 5.0; }
function wp_get_attachment_image_url(int $id, string $size): string { unset($id, $size); return ''; }
function get_post(int $id): ?WP_Post
{
    global $products;
    if (!isset($products[$id])) return null;
    return new WP_Post($id, $products[$id] instanceof WC_Product_Variation ? 'product_variation' : 'product');
}
function wp_get_post_terms(int $id, string $taxonomy, array $args): array
{
    global $categories;
    unset($taxonomy, $args);
    return isset($categories[$id]) ? array(new WP_Term($categories[$id])) : array();
}
function get_option(string $name, $default = false) { global $stored_options; return $stored_options[$name] ?? $default; }
function update_option(string $name, $value, bool $autoload = false): bool
{
    global $stored_options;
    unset($autoload);
    $stored_options[$name] = $value;
    return true;
}
function vmseb_invalidate_dedicated_public_cache(): void { global $cache_invalidations; $cache_invalidations++; }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $registered_actions;
    $registered_actions[] = array($hook, $callback, $priority, $accepted_args);
}
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function current_user_can(string $capability, ...$args): bool { unset($capability, $args); return true; }
function wp_verify_nonce(string $nonce, string $action): bool { return $nonce === 'valid' && $action === 'woocommerce_save_data'; }
function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url(string $url): string { return $url; }
function wp_kses_post(string $html): string { return $html; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function checked($checked, $current = true, bool $echo = true): string
{
    $result = $checked == $current ? 'checked="checked"' : '';
    if ($echo) echo $result;
    return $result;
}
function woocommerce_wp_checkbox(array $field): void { global $rendered_checkbox; $rendered_checkbox = $field; }

require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/admin.php';

function vmseb_backend_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$items = vmseb_get_catalog_items(false);
vmseb_backend_test_assert(isset($items['p_102']) && $items['p_102']['enabled'] === 0, 'new Woo candidate did not appear disabled by default');
vmseb_backend_test_assert(isset($items['v_203']) && $items['v_203']['enabled'] === 0, 'new Woo variation candidate did not appear disabled by default');
vmseb_backend_test_assert(!isset($stored_options['vmseb_catalog_registry']['p_102']), 'candidate enumeration seeded an unwanted disabled registry row');
vmseb_backend_test_assert($items['p_101']['category'] === 'Wine', 'current Woo category did not override the obsolete stored category');
$grouped = vmseb_group_items_by_category(array('p_101' => $items['p_101']));
vmseb_backend_test_assert(isset($grouped['Wine']) && !isset($grouped['Old Wine Name']), 'public grouping did not use the current Woo category');

$search = vmseb_build_admin_search_value($items['v_203']);
foreach (array('Draft Beer - Pint', 'Beer', 'DB-PINT', 'Draft Beer', 'variation', 'product 202', 'variation 203') as $needle) {
    vmseb_backend_test_assert(strpos($search, $needle) !== false, 'search value omitted ' . $needle);
}

$result = vmseb_save_registry_rows(array(
    'p_102' => array('token' => 'p_102', 'product_id' => 102, 'enabled' => 1),
), 'test_unconfigured');
vmseb_backend_test_assert(!empty($result['persisted']) && !empty($stored_options['vmseb_catalog_registry']['p_102']['enabled']), 'enabling an unconfigured product did not persist the registry row');
vmseb_backend_test_assert($cache_invalidations === 1, 'new membership did not invalidate the public cache');

$post = new WP_Post(101);
vmseb_render_woo_product_membership_control();
vmseb_backend_test_assert(($rendered_checkbox['id'] ?? '') === '_vmseb_show_in_express_bar', 'Woo simple-product control was not rendered');
vmseb_backend_test_assert(($rendered_checkbox['value'] ?? '') === 'no', 'Woo simple-product control did not read the registry state');

$preserved = $stored_options['vmseb_catalog_registry']['p_101'];
$_POST = array('woocommerce_meta_nonce' => 'valid', '_vmseb_show_in_express_bar' => 'yes');
vmseb_save_woo_product_membership_control(101, $post);
vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['p_101']['enabled'] === 1, 'Woo simple-product control did not update the shared registry');
foreach (array('category', 'sort', 'bucket_eligible', 'age_gate', 'online_cap', 'bar_only_threshold') as $field) {
    vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['p_101'][$field] === $preserved[$field], 'Woo simple-product save changed ' . $field);
}

$_POST = array(
    'woocommerce_meta_nonce' => 'valid',
    'variable_vmseb_show_in_express_bar' => array(5 => 'yes'),
);
vmseb_save_woo_variation_membership_control(203, 5);
vmseb_backend_test_assert(!empty($stored_options['vmseb_catalog_registry']['v_203']['enabled']), 'Woo variation control did not persist the canonical variation row');
vmseb_backend_test_assert(($stored_options['vmseb_catalog_registry']['v_203']['category'] ?? '') === 'Beer', 'variation registry row did not retain the Woo category fallback');

$stored_options['vmseb_catalog_registry']['v_203']['sort'] = 12;
$stored_options['vmseb_catalog_registry']['v_203']['online_cap'] = 9;
$stored_options['vmseb_catalog_registry']['v_203']['bar_only_threshold'] = 3;
vmseb_get_registry(true);
$_POST['variable_vmseb_show_in_express_bar'][5] = 'no';
vmseb_save_woo_variation_membership_control(203, 5);
vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['v_203']['enabled'] === 0, 'Woo variation control did not disable the shared registry row');
vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['v_203']['sort'] === 12, 'Woo variation save changed sort');
vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['v_203']['online_cap'] === 9, 'Woo variation save changed online cap');
vmseb_backend_test_assert($stored_options['vmseb_catalog_registry']['v_203']['bar_only_threshold'] === 3, 'Woo variation save changed threshold');

$admin_source = file_get_contents(dirname(__DIR__) . '/includes/admin.php');
vmseb_backend_test_assert(is_string($admin_source) && strpos($admin_source, '<th>Show in Express Bar</th>') !== false, 'Bar Menu primary column label is missing');
vmseb_backend_test_assert(strpos($admin_source, 'New — not configured') !== false, 'unconfigured-row badge is missing');
vmseb_backend_test_assert(strpos($admin_source, 'Catalog Cleanup') !== false && strpos($admin_source, 'Remove deleted products') !== false, 'catalog cleanup copy is not truthful');
vmseb_backend_test_assert(strpos($admin_source, 'Manage Bar Menu') !== false && strpos($admin_source, 'Express Bar Menu:') !== false, 'Event Plan Bar Menu management cue is missing');

$expected_public_hashes = array(
    'includes/public.php' => '32d3b52a26fa0ed09c573188523a986013d23fbd97599757a1cebce81499859a',
    'assets/js/public.js' => '32ab1dd61915254c71e10cad43f4e311950a89db353da0db3884dd962d202898',
    'assets/css/public.css' => 'c9840313d05808041c7b66c5731279768565983b9b18e9813ec2f1b89b067e0d',
);
foreach ($expected_public_hashes as $file => $expected_hash) {
    vmseb_backend_test_assert(hash_file('sha256', dirname(__DIR__) . '/' . $file) === $expected_hash, $file . ' changed from accepted 0.6.39');
}

vmseb_backend_test_assert($cache_invalidations >= 4, 'menu membership changes did not continue invalidating public cache');
fwrite(STDOUT, "PASS: Express Bar backend foundation 0.6.35\n");
