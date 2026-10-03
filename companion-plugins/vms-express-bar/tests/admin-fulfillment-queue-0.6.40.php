<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('VMSEB_URL', 'https://example.test/wp-content/plugins/vms-express-bar/');
define('VMSEB_VERSION', '0.6.40-test');

final class VMSEB_Test_Item
{
    private string $name;
    private int $quantity;
    private array $meta;

    public function __construct(string $name, int $quantity, int $event_id, string $token)
    {
        $this->name = $name;
        $this->quantity = $quantity;
        $this->meta = array(
            '_vms_express_bar'                  => '1',
            '_vms_express_bar_event_plan_id'    => (string) $event_id,
            '_vms_express_bar_event_plan_title' => $GLOBALS['vmseb_queue_test_events'][$event_id]['title'] ?? '',
            '_vms_express_bar_pickup_name'      => 'Test Pickup',
            '_vmseb_item_token'                 => $token,
            '_vmseb_item_label'                 => $name,
        );
    }

    public function get_meta(string $key, bool $single = true)
    {
        unset($single);
        return $this->meta[$key] ?? '';
    }

    public function get_name(): string { return $this->name; }
    public function get_quantity(): int { return $this->quantity; }
}

final class VMSEB_Test_Date
{
    private string $value;

    public function __construct(string $value) { $this->value = $value; }
    public function date_i18n(string $format): string { unset($format); return $this->value; }
}

class WC_Order
{
    private int $id;
    private string $woo_status;
    private bool $paid;
    private array $meta;
    private array $items;

    public function __construct(int $id, string $woo_status, bool $paid, string $queue_status, int $event_id, string $item, int $quantity = 1)
    {
        $this->id = $id;
        $this->woo_status = $woo_status;
        $this->paid = $paid;
        $this->meta = array(
            '_vms_express_bar_order'          => '1',
            '_vms_express_bar_queue_status'   => $queue_status,
            '_vms_express_bar_event_plan_ids' => (string) $event_id,
            '_vms_express_bar_id_verified'    => '0',
        );
        $this->items = array(new VMSEB_Test_Item($item, $quantity, $event_id, 'p_' . $id));
    }

    public function is_paid(): bool { return $this->paid; }
    public function get_status(): string { return $this->woo_status; }
    public function get_id(): int { return $this->id; }
    public function get_items(string $type = 'line_item'): array { unset($type); return $this->items; }
    public function get_meta(string $key, bool $single = true) { unset($single); return $this->meta[$key] ?? ''; }
    public function get_billing_first_name(): string { return 'Queue'; }
    public function get_billing_last_name(): string { return 'Tester'; }
    public function get_billing_email(): string { return 'queue@example.test'; }
    public function get_date_created(): VMSEB_Test_Date { return new VMSEB_Test_Date('2026-10-02 12:00 pm'); }
    public function get_formatted_order_total(): string { return '$10.00'; }
    public function get_edit_order_url(): string { return 'https://example.test/wp-admin/admin.php?page=wc-orders&action=edit&id=' . $this->id; }
}

$GLOBALS['vmseb_queue_test_events'] = array(
    6994 => array('title' => 'George Strait tribute: King George', 'date' => '2026-09-19 19:00:00', 'enabled' => '1'),
    7248 => array('title' => 'ABBA tribute - Super Trouper', 'date' => '2026-10-03 19:00:00', 'enabled' => '1'),
    8000 => array('title' => 'Future Test Show', 'date' => '2026-10-17 19:00:00', 'enabled' => '1'),
);
$GLOBALS['vmseb_queue_test_orders'] = array(
    new WC_Order(8345, 'completed', true, 'pending', 7248, 'Beer', 2),
    new WC_Order(8346, 'processing', true, 'ready', 7248, 'Wine', 1),
    new WC_Order(8359, 'failed', false, 'pending', 7248, 'Declined Beer', 4),
    new WC_Order(8013, 'processing', true, 'pending', 6994, 'Old Beer', 3),
    new WC_Order(8089, 'processing', true, 'completed', 6994, 'Old Completed Beer', 5),
    new WC_Order(9000, 'processing', true, 'pending', 8000, 'Future Beer', 6),
    new WC_Order(9001, 'processing', true, 'completed', 7248, 'Picked Up Wine', 1),
);

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?? ''; }
function sanitize_text_field($value): string { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function _n(string $single, string $plural, int $number, string $domain = ''): string { unset($domain); return $number === 1 ? $single : $plural; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr__(string $text, string $domain = ''): string { unset($domain); return htmlspecialchars($text, ENT_QUOTES); }
function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function esc_url(string $url): string { return $url; }
function wp_kses_post(string $html): string { return $html; }
function current_user_can(string $capability, ...$args): bool { unset($capability, $args); return true; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
function wp_nonce_field(string $action, string $name): void { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid" data-action="' . esc_attr($action) . '" />'; }
function selected($selected, $current = true, bool $echo = true): string
{
    $result = $selected == $current ? 'selected="selected"' : '';
    if ($echo) echo $result;
    return $result;
}
function submit_button(string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true): void
{
    unset($type, $name, $wrap);
    echo '<button type="submit">' . esc_html($text) . '</button>';
}
function get_posts(array $args): array { unset($args); return array_keys($GLOBALS['vmseb_queue_test_events']); }
function get_post_meta(int $post_id, string $key, bool $single = true)
{
    unset($single);
    return $key === '_vms_express_bar_enabled' ? ($GLOBALS['vmseb_queue_test_events'][$post_id]['enabled'] ?? '') : '';
}
function get_the_title(int $post_id): string { return $GLOBALS['vmseb_queue_test_events'][$post_id]['title'] ?? ''; }
function get_post_type(int $post_id): string { return isset($GLOBALS['vmseb_queue_test_events'][$post_id]) ? 'vms_event_plan' : ''; }
function vmseb_wp_timezone(): DateTimeZone { return new DateTimeZone('America/Chicago'); }
function vmseb_get_event_plan_start_datetime(int $post_id): ?DateTimeImmutable
{
    $value = $GLOBALS['vmseb_queue_test_events'][$post_id]['date'] ?? '';
    return $value !== '' ? new DateTimeImmutable($value, vmseb_wp_timezone()) : null;
}
function vmseb_resolve_public_event_plan_id(): int { return 7248; }
function vmseb_normalize_queue_status(string $status): string
{
    return in_array($status, array('pending', 'ready', 'completed'), true) ? $status : 'pending';
}
function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
    $date = new DateTimeImmutable('@' . (string) ($timestamp ?? time()));
    return $date->setTimezone($timezone ?? vmseb_wp_timezone())->format($format);
}
function wc_get_orders(array $args): array { unset($args); return $GLOBALS['vmseb_queue_test_orders']; }
function wc_get_order_statuses(): array { return array('wc-pending' => 'Pending', 'wc-processing' => 'Processing', 'wc-completed' => 'Completed', 'wc-failed' => 'Failed', 'wc-refunded' => 'Refunded'); }
function wc_get_order_status_name(string $status): string { return ucwords(str_replace('-', ' ', $status)); }

require dirname(__DIR__) . '/includes/admin.php';

function vmseb_queue_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$orders = $GLOBALS['vmseb_queue_test_orders'];
$options = vmseb_get_queue_event_options($orders);
vmseb_queue_assert(vmseb_get_default_queue_event_id($orders, $options) === 7248, 'default queue event did not select Super Trouper');
$pivot = vmseb_get_event_plan_start_datetime(7248);
vmseb_queue_assert($pivot instanceof DateTimeImmutable, 'Super Trouper pivot did not resolve');

vmseb_queue_assert(vmseb_order_matches_queue_view($orders[0], 'current', 7248, $pivot), '#8345 paid Woo completed order is not actionable');
vmseb_queue_assert(vmseb_order_matches_queue_view($orders[1], 'current', 7248, $pivot), 'Ready current-show order is not actionable');
vmseb_queue_assert(!vmseb_order_matches_queue_view($orders[2], 'current', 7248, $pivot), '#8359 failed order remained actionable');
vmseb_queue_assert(!vmseb_order_matches_queue_view($orders[3], 'current', 7248, $pivot), 'past unfinished order contaminated Current Show');
vmseb_queue_assert(vmseb_order_matches_queue_view($orders[3], 'past', 7248, $pivot), 'past unfinished order is missing from Past / Unclaimed');
vmseb_queue_assert(!vmseb_order_matches_queue_view($orders[4], 'past', 7248, $pivot), 'completed past order was misclassified as unclaimed');
vmseb_queue_assert(vmseb_order_matches_queue_view($orders[4], 'completed', 0, $pivot), 'completed past order is not recoverable in all-event history');
vmseb_queue_assert(vmseb_order_matches_queue_view($orders[5], 'future', 7248, $pivot), 'future unfinished order is missing from Future Orders');
vmseb_queue_assert(!vmseb_order_matches_queue_view($orders[5], 'current', 7248, $pivot), 'future order contaminated Current Show');
vmseb_queue_assert(vmseb_order_matches_queue_view($orders[6], 'completed', 7248, $pivot), 'current-show completed order is missing from Completed');

$current_display = vmseb_get_queue_order_display_data($orders[0], 'current', 7248, $pivot);
vmseb_queue_assert(($current_display['movement']['p_8345']['qty'] ?? 0) === 2, 'current-show movement quantity is wrong');
vmseb_queue_assert(strpos(implode(' ', $current_display['item_lines']), 'Declined') === false, 'failed item entered the current order display');

$action_html = vmseb_action_form(8345, 'completed', 'Picked Up', 'current', 7248);
vmseb_queue_assert(strpos($action_html, 'name="return_view" value="current"') !== false, 'action form did not preserve Current Show');
vmseb_queue_assert(strpos($action_html, 'name="return_event_plan_id" value="7248"') !== false, 'action form did not preserve Event Plan 7248');

$_GET = array('page' => 'vms-express-bar');
ob_start();
vmseb_render_queue_page();
$html = (string) ob_get_clean();
vmseb_queue_assert(strpos($html, 'Current Show') !== false && strpos($html, 'Past / Unclaimed') !== false && strpos($html, 'Future Orders') !== false, 'four queue views were not rendered');
vmseb_queue_assert(strpos($html, 'ABBA tribute - Super Trouper') !== false && strpos($html, 'Sat, Oct 3, 2026') !== false && strpos($html, '7:00 PM') !== false && strpos($html, 'Event Plan #7248') !== false, 'selected show identity is incomplete');
vmseb_queue_assert(strpos($html, '#8345') !== false && strpos($html, '#8346') !== false, 'current paid Pending/Ready orders were not rendered');
vmseb_queue_assert(strpos($html, '#8359') === false, 'failed #8359 rendered as fulfillment work');
vmseb_queue_assert(strpos($html, '#8013') === false && strpos($html, '#9000') === false && strpos($html, '#9001') === false, 'past, future, or completed order contaminated Current Show');
vmseb_queue_assert(strpos($html, 'Declined Beer') === false && strpos($html, 'Future Beer') === false && strpos($html, 'Old Beer') === false, 'movement/items included hidden work');
vmseb_queue_assert(substr_count($html, '<strong>1</strong> unfinished order is hidden') === 1, 'non-paid unfinished-order notice is missing or wrong');

$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');
vmseb_queue_assert(strpos($css, '@media (max-width: 782px)') !== false && strpos($css, 'content: attr(data-label)') !== false, 'mobile queue card treatment is missing');

$expected_public_hashes = array(
    'includes/public.php' => '32d3b52a26fa0ed09c573188523a986013d23fbd97599757a1cebce81499859a',
    'assets/js/public.js' => '32ab1dd61915254c71e10cad43f4e311950a89db353da0db3884dd962d202898',
    'assets/css/public.css' => 'c9840313d05808041c7b66c5731279768565983b9b18e9813ec2f1b89b067e0d',
);
foreach ($expected_public_hashes as $file => $expected_hash) {
    vmseb_queue_assert(hash_file('sha256', dirname(__DIR__) . '/' . $file) === $expected_hash, $file . ' changed from accepted public 0.6.39');
}

fwrite(STDOUT, "PASS: Express Bar admin fulfillment queue 0.6.40\n");
