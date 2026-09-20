<?php
declare(strict_types=1);

final class BvmTicketingTestResponse extends RuntimeException
{
    public function __construct(public bool $success, public array $payload, public int $http = 200)
    {
        parent::__construct($success ? 'success' : 'error');
    }
}

final class BvmTicketingTestCart
{
    public array $items = array();
    public int $sequence = 0;

    public function add_to_cart($productId, $quantity, $variationId = 0, $variation = array(), $data = array())
    {
        $key = 'cart-' . (++$this->sequence);
        $this->items[$key] = array('product_id' => (int) $productId, 'quantity' => (int) $quantity, 'data' => $data);
        $GLOBALS['bvm_ticketing_timeline'][] = 'cart:add:' . (int) $productId;
        return $key;
    }

    public function remove_cart_item($key): void
    {
        $GLOBALS['bvm_ticketing_timeline'][] = 'cart:remove:' . (string) $key;
        unset($this->items[(string) $key]);
    }

    public function calculate_totals(): void
    {
        $GLOBALS['bvm_ticketing_timeline'][] = 'cart:totals';
    }
}

function bvm_ticketing_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['bvm_ticketing_checks']++;
}

function bvm_ticketing_extract_function(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) {
        throw new RuntimeException('Missing function ' . $name);
    }
    $brace = strpos($source, '{', $start);
    $depth = 0;
    $length = strlen($source);
    for ($index = $brace; $index < $length; $index++) {
        if ($source[$index] === '{') $depth++;
        if ($source[$index] === '}' && --$depth === 0) {
            return substr($source, $start, $index - $start + 1);
        }
    }
    throw new RuntimeException('Unclosed function ' . $name);
}

function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?: ''; }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function wp_json_encode($value) { return json_encode($value); }
function wp_unslash($value) { return $value; }
function bvmgr_array_is_list_compat(array $value): bool { return array_is_list($value); }
function __($value, $domain = null): string { return (string) $value; }
function home_url($path = ''): string { return 'https://contained.example.test' . (string) $path; }
function wc_get_cart_url(): string { return 'https://contained.example.test/cart/'; }
function wp_verify_nonce($nonce, $action): bool { return $nonce === 'valid'; }
function bvmgr_nonce_action_for_value($nonce, $fallback): string { return (string) $fallback; }
function is_user_logged_in(): bool { return false; }
function get_current_user_id(): int { return 0; }
function wp_send_json_error(): void {}
function wc_load_cart(): void {}
function wc_clear_notices(): void { $GLOBALS['bvm_ticketing_notices'] = array(); }
function wc_add_notice($message, $type): void { $GLOBALS['bvm_ticketing_notices'][] = (string) $message; }
function bvmgr_ticketing_v2_clear_success_notices(): void {}
function bvmgr_ticketing_v2_atomic_error_notices(): array { return $GLOBALS['bvm_ticketing_notices']; }
function bvmgr_ticketing_v2_ajax_send_error(array $payload, int $http = 400): void { throw new BvmTicketingTestResponse(false, $payload, $http); }
function bvmgr_ticketing_v2_ajax_send_success(array $payload): void { throw new BvmTicketingTestResponse(true, $payload, 200); }
function bvmgr_ticketing_v2_read_json_request_payload(int $maxBytes): array { return array('ok' => true, 'present' => true, 'payload' => $GLOBALS['bvm_ticketing_request']); }
function bvmgr_ticketing_v2_read_form_request_payload(array $source): array { return $source; }
function bvmgr_ticketing_v2_validate_atomic_add_payload(array $data): bool { return true; }
function bvmgr_ticketing_v2_atomic_normalize_ticket_line($line): array { return is_array($line) ? $line : array(); }
function bvmgr_ticketing_v2_atomic_normalize_addon_line($line): array { return is_array($line) ? $line : array(); }
function bvmgr_ticketing_v2_validate_product_sale_context($productId, $planId, $eventId, $kind): array
{
    return array('ok' => true, 'plan_id' => (int) $planId, 'event_id' => (int) $eventId);
}
function bvmgr_ticketing_v2_product_is_entitlement($productId): bool { return (int) $productId >= 200 && (int) $productId < 300; }
function bvmgr_ticketing_v2_collect_ticket_ratio_violations(int $planId): array { return array(); }
function apply_filters($hook, $value, ...$args)
{
    if ($hook === 'bvmgr_ticketing_purchase_extension_handlers') return $GLOBALS['bvm_ticketing_handlers'];
    return $value;
}
function WC() { return $GLOBALS['bvm_ticketing_wc']; }

$source = (string) file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php');
foreach (array(
    'bvmgr_ticketing_v2_validate_purchase_extensions_payload',
    'bvmgr_ticketing_v2_atomic_rollback_added_items',
    'bvmgr_ticketing_v2_purchase_extension_handlers',
    'bvmgr_ticketing_v2_atomic_rollback_extensions',
    'bvmgr_ticketing_v2_ajax_atomic_add_to_cart',
) as $functionName) {
    eval(bvm_ticketing_extract_function($source, $functionName));
}

$GLOBALS['bvm_ticketing_checks'] = 0;
bvm_ticketing_assert(bvmgr_ticketing_v2_validate_purchase_extensions_payload(array()), 'empty extension envelope must be valid');
bvm_ticketing_assert(bvmgr_ticketing_v2_validate_purchase_extensions_payload(array('rentals' => array('quantity' => 1))), 'object-like extension envelope must be valid');
bvm_ticketing_assert(!bvmgr_ticketing_v2_validate_purchase_extensions_payload(array(array('quantity' => 1))), 'list extension envelope must be rejected');
bvm_ticketing_assert(!bvmgr_ticketing_v2_validate_purchase_extensions_payload(array('Bad Key' => array())), 'unsanitized extension ID must be rejected');
bvm_ticketing_assert(!bvmgr_ticketing_v2_validate_purchase_extensions_payload(array('rentals' => 'invalid')), 'non-array private payload must be rejected');
bvm_ticketing_assert(!bvmgr_ticketing_v2_validate_purchase_extensions_payload(array('rentals' => array('blob' => str_repeat('x', 33000)))), 'oversized private payload must be rejected');

$run = static function (string $scenario, array $request, array $handler = array(), array $notices = array()): BvmTicketingTestResponse {
    $_POST = $_REQUEST = array();
    $GLOBALS['bvm_ticketing_timeline'] = array();
    $GLOBALS['bvm_ticketing_notices'] = $notices;
    $GLOBALS['bvm_ticketing_request'] = array_merge(array(
        'nonce' => 'valid', 'event_plan_id' => 41, 'tec_event_id' => 42,
        'ticket_lines' => array(), 'addon_lines' => array(), 'extensions' => array(),
    ), $request);
    $GLOBALS['bvm_ticketing_wc'] = (object) array('cart' => new BvmTicketingTestCart());
    $GLOBALS['bvm_ticketing_handlers'] = $handler ? array('test_extension' => $handler) : array();
    try {
        bvmgr_ticketing_v2_ajax_atomic_add_to_cart();
    } catch (BvmTicketingTestResponse $response) {
        $GLOBALS['bvm_ticketing_last_cart'] = $GLOBALS['bvm_ticketing_wc']->cart;
        return $response;
    }
    throw new RuntimeException($scenario . ' did not emit a response');
};

$successHandler = static function (array &$calls): array {
    return array(
        'validate' => static function ($payload, $context) use (&$calls) {
            $calls[] = 'validate'; $GLOBALS['bvm_ticketing_timeline'][] = 'extension:validate';
            return array('ok' => true, 'prepared' => array('quantity' => absint($payload['quantity'] ?? 0)));
        },
        'add' => static function ($prepared, $context) use (&$calls) {
            $calls[] = 'add'; $GLOBALS['bvm_ticketing_timeline'][] = 'extension:add';
            $key = $context['cart']->add_to_cart(300, absint($prepared['quantity'] ?? 0));
            return array('ok' => true, 'quantity' => absint($prepared['quantity'] ?? 0), 'cart_keys' => array($key));
        },
        'commit' => static function ($prepared, $context) use (&$calls) {
            $calls[] = 'commit'; $GLOBALS['bvm_ticketing_timeline'][] = 'extension:commit';
            return array('ok' => true);
        },
        'rollback' => static function ($prepared, $context) use (&$calls) {
            $calls[] = 'rollback'; $GLOBALS['bvm_ticketing_timeline'][] = 'extension:rollback';
        },
    );
};

$ordinary = $run('ordinary ticket', array('ticket_lines' => array(array('product_id' => 100, 'qty' => 1))));
bvm_ticketing_assert($ordinary->success, 'ordinary no-extension ticket purchase must succeed');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 1, 'ordinary ticket purchase must retain exactly one cart line');
bvm_ticketing_assert(($ordinary->payload['added_extensions'] ?? -1) === 0, 'ordinary purchase must report zero extension quantity');

$calls = array();
$mixed = $run('mixed purchase', array(
    'ticket_lines' => array(array('product_id' => 100, 'qty' => 4)),
    'addon_lines' => array(array('product_id' => 200, 'qty' => 1)),
    'extensions' => array('test_extension' => array('quantity' => 2)),
), $successHandler($calls));
bvm_ticketing_assert($mixed->success, 'mixed purchase must succeed');
bvm_ticketing_assert($calls === array('validate', 'add', 'commit'), 'extension phases must run validate/add/commit exactly once in order');
bvm_ticketing_assert(($mixed->payload['added_total'] ?? 0) === 7, 'four-ticket + add-on + two-extension quantity must total seven');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 3, 'mixed purchase must retain ticket, add-on, and extension cart lines');
$timeline = $GLOBALS['bvm_ticketing_timeline'];
bvm_ticketing_assert(array_search('extension:validate', $timeline, true) < array_search('cart:add:100', $timeline, true), 'extension validation must precede cart mutation');
bvm_ticketing_assert(array_search('extension:add', $timeline, true) > array_search('cart:add:200', $timeline, true), 'extension add must follow ticket/add-on cart adds');
bvm_ticketing_assert(array_search('extension:commit', $timeline, true) > array_search('cart:totals', $timeline, true), 'extension commit must follow successful cart totals');

$validationCalls = array();
$validationHandler = $successHandler($validationCalls);
$validationHandler['validate'] = static function () use (&$validationCalls) {
    $validationCalls[] = 'validate';
    return array('ok' => false, 'code' => 'terms_required', 'message' => 'Accept terms.');
};
$validationFailure = $run('validation failure', array(
    'ticket_lines' => array(array('product_id' => 100, 'qty' => 1)),
    'extensions' => array('test_extension' => array('quantity' => 1)),
), $validationHandler);
bvm_ticketing_assert(!$validationFailure->success && $validationFailure->http === 400, 'extension validation failure must be deterministic');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 0, 'extension validation failure must occur before any cart mutation');
bvm_ticketing_assert($validationCalls === array('validate'), 'validation failure must not invoke add/commit/rollback');

$addCalls = array();
$addHandler = $successHandler($addCalls);
$addHandler['add'] = static function ($prepared, $context) use (&$addCalls) {
    $addCalls[] = 'add';
    $key = $context['cart']->add_to_cart(300, 1);
    return array('ok' => false, 'code' => 'stock_changed', 'message' => 'Unavailable.', 'cart_keys' => array($key));
};
$addFailure = $run('add failure', array(
    'ticket_lines' => array(array('product_id' => 100, 'qty' => 1)),
    'extensions' => array('test_extension' => array('quantity' => 1)),
), $addHandler);
bvm_ticketing_assert(!$addFailure->success, 'extension add failure must return an error');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 0, 'extension add failure must roll back ticket and partial extension cart lines');
bvm_ticketing_assert($addCalls === array('validate', 'add', 'rollback'), 'extension add failure must compensate without commit');

$commitCalls = array();
$commitHandler = $successHandler($commitCalls);
$commitHandler['commit'] = static function () use (&$commitCalls) {
    $commitCalls[] = 'commit';
    return array('ok' => false, 'message' => 'Commit rejected.');
};
$commitFailure = $run('commit failure', array(
    'ticket_lines' => array(array('product_id' => 100, 'qty' => 1)),
    'extensions' => array('test_extension' => array('quantity' => 1)),
), $commitHandler);
bvm_ticketing_assert(!$commitFailure->success, 'extension commit failure must return an error');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 0, 'extension commit failure must roll back all cart lines');
bvm_ticketing_assert($commitCalls === array('validate', 'add', 'commit', 'rollback'), 'commit failure must invoke compensating rollback');
bvm_ticketing_assert(($commitFailure->payload['errors'][0]['code'] ?? '') === 'commit_failed', 'commit failure must return deterministic code');

$rollbackCalls = array();
$rollbackHandler = $successHandler($rollbackCalls);
$rollbackHandler['add'] = static function () use (&$rollbackCalls) { $rollbackCalls[] = 'add'; return array('ok' => false, 'message' => 'No stock.'); };
$rollbackHandler['rollback'] = static function () use (&$rollbackCalls) { $rollbackCalls[] = 'rollback'; throw new RuntimeException('rollback fixture'); };
$rollbackFailure = $run('rollback exception', array(
    'ticket_lines' => array(array('product_id' => 100, 'qty' => 1)),
    'extensions' => array('test_extension' => array('quantity' => 1)),
), $rollbackHandler);
bvm_ticketing_assert(!$rollbackFailure->success, 'rollback exception must not obscure deterministic add failure response');
bvm_ticketing_assert(count($GLOBALS['bvm_ticketing_last_cart']->items) === 0, 'rollback exception must not prevent Core cart rollback');
bvm_ticketing_assert($rollbackCalls === array('validate', 'add', 'rollback'), 'rollback callback must still be attempted once');

echo 'PASS ' . $GLOBALS['bvm_ticketing_checks'] . " purchase-extension envelope, registry, ordering, success, failure, commit, and rollback assertions\n";
