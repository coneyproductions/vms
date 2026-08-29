<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CLI', true);

final class WP_User
{
    public int $ID;

    public function __construct(int $user_id)
    {
        $this->ID = $user_id;
    }
}

final class WP_CLI
{
    /** @var array<string,string> */
    public static array $commands = array();

    /** @var array<int,string> */
    public static array $messages = array();

    public static function add_command(string $name, string $callable): void
    {
        self::$commands[$name] = $callable;
    }

    public static function error(string $message): void
    {
        throw new RuntimeException($message);
    }

    public static function log(string $message): void
    {
        self::$messages[] = $message;
    }

    public static function warning(string $message): void
    {
        self::$messages[] = $message;
    }

    public static function success(string $message): void
    {
        self::$messages[] = $message;
    }
}

$GLOBALS['bvmgr_cli_actor_current_user'] = new WP_User(0);
$GLOBALS['bvmgr_cli_actor_capabilities'] = array();
$GLOBALS['bvmgr_cli_actor_capability_checks'] = array();
$GLOBALS['bvmgr_cli_actor_preview_calls'] = array();
$GLOBALS['bvmgr_cli_actor_apply_calls'] = array();
$GLOBALS['bvmgr_cli_actor_name_preview_calls'] = array();
$GLOBALS['bvmgr_cli_actor_name_apply_calls'] = array();
$GLOBALS['bvmgr_cli_actor_assertions'] = 0;

function absint($value): int
{
    return abs((int) $value);
}

function sanitize_text_field($value): string
{
    return trim((string) $value);
}

function sanitize_key($value): string
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value) ?? '');
}

function wp_get_current_user(): WP_User
{
    return $GLOBALS['bvmgr_cli_actor_current_user'];
}

function user_can($user, string $capability, int $object_id): bool
{
    $user_id = $user instanceof WP_User ? $user->ID : (int) $user;
    $GLOBALS['bvmgr_cli_actor_capability_checks'][] = array($user_id, $capability, $object_id);
    return !empty($GLOBALS['bvmgr_cli_actor_capabilities'][$user_id][$capability][$object_id]);
}

function get_the_title(int $post_id): string
{
    return 'Synthetic Event Plan #' . $post_id;
}

function bvmgr_event_occurrence_preview(int $plan_id, string $old_start, string $new_start, string $reason): array
{
    $GLOBALS['bvmgr_cli_actor_preview_calls'][] = array($plan_id, $old_start, $new_start, $reason);
    return array(
        'allowed' => true,
        'plan_id' => $plan_id,
        'plan_title' => 'Synthetic Event Plan',
        'mode' => 'repair',
        'canonical' => array('start_local' => $new_start . ':00'),
        'old' => array('start_local' => $old_start . ':00'),
        'new' => array('start_local' => $new_start . ':00'),
        'reason' => $reason,
        'tec_event_id' => 99,
        'counts' => array('admission_units' => 2, 'reservation_units' => 1),
        'categories' => array(),
        'product_ids' => array(100, 101),
        'attendee_ids' => array(),
        'notification_rows' => array(),
        'warnings' => array(),
        'ambiguities' => array(),
    );
}

function bvmgr_event_occurrence_preview_fingerprint(array $preview): string
{
    return hash('sha256', json_encode($preview));
}

function bvmgr_event_occurrence_apply(int $plan_id, string $old_start, string $new_start, string $reason, int $actor_user_id, string $fingerprint): array
{
    $GLOBALS['bvmgr_cli_actor_apply_calls'][] = array($plan_id, $old_start, $new_start, $reason, $actor_user_id, $fingerprint);
    return array(
        'ok' => true,
        'message' => 'Synthetic occurrence applied.',
        'operation_id' => 'synthetic-operation',
        'integrity' => array('ok' => true),
    );
}

function bvmgr_event_occurrence_integrity(int $plan_id): array
{
    return array('ok' => true, 'canonical_date' => '2026-09-12');
}

function bvmgr_event_occurrence_name_reconciliation_preview(int $plan_id, string $operation_id): array
{
    $GLOBALS['bvmgr_cli_actor_name_preview_calls'][] = array($plan_id, $operation_id);
    return array(
        'allowed' => true,
        'plan_id' => $plan_id,
        'plan_title' => 'Synthetic Event Plan',
        'operation_id' => $operation_id,
        'current_occurrence' => array('start_local' => '2026-09-12 19:00:00'),
        'counts' => array('eligible_changes' => 1),
        'rows' => array(array(
            'order_id' => 123,
            'order_item_id' => 456,
            'current_name' => 'General Admission',
            'proposed_name' => '2026-09-12 19:00 - General Admission',
            'current_effective_occurrence' => '2026-09-12 19:00:00',
            'historical_original_name_snapshot' => '2026-09-19 19:00 - General Admission',
            'safe' => true,
            'safety_reason' => 'safe',
        )),
        'warnings' => array(),
        'ambiguities' => array(),
    );
}

function bvmgr_event_occurrence_name_reconciliation_fingerprint(array $preview): string
{
    return hash('sha256', json_encode($preview));
}

function bvmgr_event_occurrence_name_reconciliation_apply(int $plan_id, string $operation_id, int $actor_user_id, string $fingerprint): array
{
    $GLOBALS['bvmgr_cli_actor_name_apply_calls'][] = array($plan_id, $operation_id, $actor_user_id, $fingerprint);
    return array(
        'ok' => true,
        'message' => 'Synthetic current names reconciled.',
        'changed_order_item_ids' => array(456),
        'integrity' => array('ok' => true),
    );
}

function bvmgr_cli_actor_assert(bool $condition, string $message): void
{
    $GLOBALS['bvmgr_cli_actor_assertions']++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function bvmgr_cli_actor_expect_error(callable $callback, string $message_fragment): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        bvmgr_cli_actor_assert(str_contains($exception->getMessage(), $message_fragment), 'Unexpected CLI error: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected CLI error containing: ' . $message_fragment);
}

function bvmgr_cli_actor_args(array $overrides = array()): array
{
    return array_merge(array(
        'old-start' => '2026-09-19 19:00',
        'new-start' => '2026-09-12 19:00',
        'reason' => 'date_correction',
        'dry-run' => true,
    ), $overrides);
}

require dirname(__DIR__) . '/includes/core/cli/event-reschedule.php';

$command = new BVMGR_CLI_Event_Reschedule_Command();
$GLOBALS['bvmgr_cli_actor_current_user'] = new WP_User(7);
$GLOBALS['bvmgr_cli_actor_capabilities'][7]['edit_post'][42] = true;
$command(array('42'), bvmgr_cli_actor_args());

bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_preview_calls']) === 1, 'Authorized global user context did not reach preview.');
bvmgr_cli_actor_assert($GLOBALS['bvmgr_cli_actor_capability_checks'][0] === array(7, 'edit_post', 42), 'Capability check did not use the current global user and requested Event Plan.');
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_apply_calls']) === 0, 'Dry-run invoked the apply service.');
bvmgr_cli_actor_assert(in_array('Actor user ID: 7', WP_CLI::$messages, true), 'Authorized global user ID was not reported.');
bvmgr_cli_actor_assert(in_array('Dry run complete. APPLY would be allowed with the same inputs and --apply --confirm=RESCHEDULE.', WP_CLI::$messages, true), 'Authorized dry-run did not complete.');

$preview_count = count($GLOBALS['bvmgr_cli_actor_preview_calls']);
$GLOBALS['bvmgr_cli_actor_current_user'] = new WP_User(0);
bvmgr_cli_actor_expect_error(
    static function () use ($command): void {
        $command(array('42'), bvmgr_cli_actor_args(array('user' => '7')));
    },
    'WP-CLI global --user='
);
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_preview_calls']) === $preview_count, 'No-user execution reached preview.');

$GLOBALS['bvmgr_cli_actor_current_user'] = new WP_User(8);
bvmgr_cli_actor_expect_error(
    static function () use ($command): void {
        $command(array('42'), bvmgr_cli_actor_args());
    },
    'cannot edit this Event Plan'
);
bvmgr_cli_actor_assert(end($GLOBALS['bvmgr_cli_actor_capability_checks']) === array(8, 'edit_post', 42), 'Unauthorized user was not checked against the requested Event Plan.');
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_preview_calls']) === $preview_count, 'Unauthorized user reached preview.');

$GLOBALS['bvmgr_cli_actor_current_user'] = new WP_User(7);
bvmgr_cli_actor_expect_error(
    static function () use ($command): void {
        $args = bvmgr_cli_actor_args(array('apply' => true));
        unset($args['dry-run']);
        $command(array('42'), $args);
    },
    '--apply requires --confirm=RESCHEDULE'
);
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_apply_calls']) === 0, 'Apply ran without the confirmation token.');

$apply_args = bvmgr_cli_actor_args(array('apply' => true, 'confirm' => 'RESCHEDULE'));
unset($apply_args['dry-run']);
$command(array('42'), $apply_args);
$apply_call = $GLOBALS['bvmgr_cli_actor_apply_calls'][0] ?? array();
bvmgr_cli_actor_assert(($apply_call[4] ?? 0) === 7, 'Current global user ID was not passed to the canonical apply service.');
bvmgr_cli_actor_assert(($apply_call[5] ?? '') !== '', 'Apply did not receive the preview fingerprint.');

bvmgr_cli_actor_expect_error(
    static function () use ($command): void {
        $command(array('42'), bvmgr_cli_actor_args(array('apply' => true)));
    },
    'exactly one of --dry-run or --apply'
);
bvmgr_cli_actor_expect_error(
    static function () use ($command): void {
        $args = bvmgr_cli_actor_args();
        unset($args['dry-run']);
        $command(array('42'), $args);
    },
    'exactly one of --dry-run or --apply'
);

$name_command = new BVMGR_CLI_Event_Item_Name_Reconcile_Command();
$operation_id = 'de1814a7-5ada-4e6e-b587-46c1e80eff89';
$name_command(array('42'), array('operation-id' => $operation_id, 'dry-run' => true));
bvmgr_cli_actor_assert(($GLOBALS['bvmgr_cli_actor_name_preview_calls'][0] ?? array()) === array(42, $operation_id), 'Name-reconciliation dry run did not preserve the Event Plan and operation scope.');
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_name_apply_calls']) === 0, 'Name-reconciliation dry run invoked apply.');
bvmgr_cli_actor_assert(in_array('Dry run complete. APPLY would be allowed with the same inputs and --apply --confirm=RECONCILE-NAMES.', WP_CLI::$messages, true), 'Name-reconciliation dry run did not report the confirmation contract.');
bvmgr_cli_actor_expect_error(
    static function () use ($name_command, $operation_id): void {
        $name_command(array('42'), array('operation-id' => $operation_id, 'apply' => true));
    },
    '--apply requires --confirm=RECONCILE-NAMES'
);
bvmgr_cli_actor_assert(count($GLOBALS['bvmgr_cli_actor_name_apply_calls']) === 0, 'Name reconciliation ran without the confirmation token.');
$name_command(array('42'), array(
    'operation-id' => $operation_id,
    'apply' => true,
    'confirm' => 'RECONCILE-NAMES',
));
$name_apply_call = $GLOBALS['bvmgr_cli_actor_name_apply_calls'][0] ?? array();
bvmgr_cli_actor_assert(($name_apply_call[0] ?? 0) === 42 && ($name_apply_call[1] ?? '') === $operation_id, 'Name-reconciliation apply lost its Event Plan or operation scope.');
bvmgr_cli_actor_assert(($name_apply_call[2] ?? 0) === 7, 'Name-reconciliation apply did not use the authenticated global actor.');
bvmgr_cli_actor_assert(($name_apply_call[3] ?? '') !== '', 'Name-reconciliation apply did not receive the approved preview fingerprint.');

bvmgr_cli_actor_assert((WP_CLI::$commands['bvmgr event reschedule'] ?? '') === BVMGR_CLI_Event_Reschedule_Command::class, 'Canonical bvmgr command is not registered to the actor-aware class.');
bvmgr_cli_actor_assert((WP_CLI::$commands['vms event reschedule'] ?? '') === BVMGR_CLI_Event_Reschedule_Command::class, 'Transitional vms alias does not share canonical actor semantics.');
bvmgr_cli_actor_assert((WP_CLI::$commands['bvmgr event reconcile-current-item-names'] ?? '') === BVMGR_CLI_Event_Item_Name_Reconcile_Command::class, 'Canonical name-reconciliation command is not registered.');
bvmgr_cli_actor_assert((WP_CLI::$commands['vms event reconcile-current-item-names'] ?? '') === BVMGR_CLI_Event_Item_Name_Reconcile_Command::class, 'Transitional name-reconciliation alias is not registered.');

$cli_source = file_get_contents(dirname(__DIR__) . '/includes/core/cli/event-reschedule.php');
$admin_source = file_get_contents(dirname(__DIR__) . '/includes/admin/event-reschedule.php');
bvmgr_cli_actor_assert(is_string($cli_source) && !str_contains($cli_source, '$assoc_args[\'user\']'), 'CLI still depends on a command-local user argument.');
bvmgr_cli_actor_assert(is_string($cli_source) && str_contains($cli_source, 'wp_get_current_user()'), 'CLI does not resolve WP-CLI current user context.');
bvmgr_cli_actor_assert(is_string($admin_source) && str_contains($admin_source, 'bvmgr_event_occurrence_apply('), 'Admin workflow no longer uses the canonical apply service.');
bvmgr_cli_actor_assert(is_string($cli_source) && str_contains($cli_source, 'bvmgr_event_occurrence_apply('), 'CLI no longer uses the canonical apply service.');
bvmgr_cli_actor_assert(is_string($cli_source) && str_contains($cli_source, 'bvmgr_event_occurrence_name_reconciliation_apply('), 'CLI does not use the canonical name-reconciliation apply service.');

fwrite(STDOUT, 'PASS: ' . $GLOBALS['bvmgr_cli_actor_assertions'] . " reschedule CLI actor assertions\n");
