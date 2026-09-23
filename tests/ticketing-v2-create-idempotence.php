<?php
declare(strict_types=1);

function vms_issue5_fail(string $message): void
{
    throw new RuntimeException($message);
}

function vms_issue5_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        vms_issue5_fail($message);
    }
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_issue5_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        vms_issue5_fail(
            $message
            . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function vms_issue5_assert_contains(string $needle, string $haystack, string $message): void
{
    vms_issue5_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_issue5_extract_function(string $source, string $name): string
{
    $needle = 'function ' . $name . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        vms_issue5_fail('Unable to locate function ' . $name . '.');
    }

    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        vms_issue5_fail('Unable to locate opening brace for ' . $name . '.');
    }

    $depth = 1;
    $length = strlen($source);
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = $brace + 1; $i < $length; $i++) {
        $char = $source[$i];
        $next = ($i + 1 < $length) ? $source[$i + 1] : '';
        $prev = ($i > 0) ? $source[$i - 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }
        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }
        if ($inSingle) {
            if ($char === "'" && $prev !== '\\') {
                $inSingle = false;
            }
            continue;
        }
        if ($inDouble) {
            if ($char === '"' && $prev !== '\\') {
                $inDouble = false;
            }
            continue;
        }

        if ($char === '/' && $next === '/') {
            $inLineComment = true;
            $i++;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }
        if ($char === "'") {
            $inSingle = true;
            continue;
        }
        if ($char === '"') {
            $inDouble = true;
            continue;
        }
        if ($char === '{') {
            $depth++;
            continue;
        }
        if ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, ($i - $start) + 1);
            }
        }
    }

    vms_issue5_fail('Unable to locate closing brace for ' . $name . '.');
}

$sourcePath = __DIR__ . '/../includes/integrations/ticketing-phase-b.php';
$source = file_get_contents($sourcePath);
if (!is_string($source) || $source === '') {
    vms_issue5_fail('Failed to read Ticketing v2 source.');
}

// Provider writes are draft/hidden only while the guarded v2 CREATE context is active.
vms_issue5_assert_contains(
    "'status' => \$staged_v2_create ? 'draft' : 'publish'",
    $source,
    'Guarded v2 CREATE must start as draft while legacy provider callers retain publish behavior.'
);
vms_issue5_assert_contains(
    "\$args['_visibility'] = 'hidden';",
    $source,
    'Guarded v2 CREATE must start hidden.'
);

// Durable intent + provider-link capture are required before retry idempotence can work.
foreach (array(
    'function bvmgr_ticketing_v2_begin_create_intent(',
    'function bvmgr_ticketing_v2_capture_provider_create_link(',
    "add_action('added_post_meta', 'bvmgr_ticketing_v2_capture_provider_create_link', 10, 4);",
    "add_action('updated_post_meta', 'bvmgr_ticketing_v2_capture_provider_create_link', 10, 4);",
    'function bvmgr_ticketing_v2_find_interrupted_create_candidates(',
    'function bvmgr_ticketing_v2_persist_action_checkpoint(',
    'function bvmgr_ticketing_v2_commit_shutdown_diagnostics(',
) as $required) {
    vms_issue5_assert_contains($required, $source, 'Missing Issue #5 crash-safety primitive.');
}

$commit = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_commit_sync');
$recoveryPos = strpos($commit, 'bvmgr_ticketing_v2_find_interrupted_create_candidates(');
$intentPos = strpos($commit, 'bvmgr_ticketing_v2_begin_create_intent(');
$createPos = strpos($commit, 'bvmgr_ticketing_v2_create_ticket(');
vms_issue5_assert_true($recoveryPos !== false, 'Commit must inspect interrupted CREATE candidates.');
vms_issue5_assert_true($intentPos !== false, 'Commit must persist CREATE intent.');
vms_issue5_assert_true($createPos !== false, 'Commit must still contain one guarded provider CREATE call.');
vms_issue5_assert_true($recoveryPos < $createPos, 'Retry recovery must happen before provider CREATE.');
vms_issue5_assert_true($intentPos < $createPos, 'Durable CREATE intent must be written before provider CREATE.');

$ticketCreateStart = strpos($commit, "if (\$act === 'create') {", $recoveryPos);
$ticketAdoptStart = ($ticketCreateStart !== false) ? strpos($commit, "if (\$act === 'adopt') {", $ticketCreateStart) : false;
vms_issue5_assert_true($ticketCreateStart !== false && $ticketAdoptStart !== false, 'Unable to isolate the ticket CREATE action block.');
$ticketCreateBlock = substr($commit, $ticketCreateStart, $ticketAdoptStart - $ticketCreateStart);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_persist_action_checkpoint(',
    $ticketCreateBlock,
    'Successful CREATE mapping must checkpoint inside the CREATE action before the next action branch.'
);

$preview = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_preview_sync');
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_find_interrupted_create_candidates(',
    $preview,
    'Preview must surface interrupted CREATE recovery before proposing another CREATE.'
);
vms_issue5_assert_contains(
    "in_array(\$interrupted_status, array('ambiguous', 'unsafe', 'sold'), true)",
    $preview,
    'Preview must block ambiguous, unverifiable, or sold interrupted CREATE candidates.'
);

// Exercise the pure recovery classifier without loading WordPress.
$classifier = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_classify_interrupted_create_candidates');
eval($classifier);

vms_issue5_assert_same(
    array('status' => 'none', 'product_id' => 0, 'candidate_ids' => array()),
    bvmgr_ticketing_v2_classify_interrupted_create_candidates(array()),
    'No candidate should permit one guarded CREATE.'
);

$singleUnsold = bvmgr_ticketing_v2_classify_interrupted_create_candidates(array(
    array('product_id' => 8037, 'sold_check_ok' => 1, 'sold_qty' => 0),
));
vms_issue5_assert_same('safe', $singleUnsold['status'], 'One proven-unsold candidate must be recoverable.');
vms_issue5_assert_same(8037, $singleUnsold['product_id'], 'Recovery must preserve the existing product ID.');

$singleSold = bvmgr_ticketing_v2_classify_interrupted_create_candidates(array(
    array('product_id' => 8037, 'sold_check_ok' => 1, 'sold_qty' => 2),
));
vms_issue5_assert_same('sold', $singleSold['status'], 'A sold candidate must never be silently adopted.');

$unverified = bvmgr_ticketing_v2_classify_interrupted_create_candidates(array(
    array('product_id' => 8037, 'sold_check_ok' => 0, 'sold_qty' => 0),
));
vms_issue5_assert_same('unsafe', $unverified['status'], 'A candidate whose sales state cannot be verified must block.');

$ambiguous = bvmgr_ticketing_v2_classify_interrupted_create_candidates(array(
    array('product_id' => 8038, 'sold_check_ok' => 1, 'sold_qty' => 0),
    array('product_id' => 8037, 'sold_check_ok' => 1, 'sold_qty' => 0),
));
vms_issue5_assert_same('ambiguous', $ambiguous['status'], 'Two candidates must block instead of guessing.');
vms_issue5_assert_same(array(8037, 8038), $ambiguous['candidate_ids'], 'Ambiguous diagnostics must report deterministic candidate IDs.');

echo "PASS: Ticketing v2 CREATE idempotence source contract and recovery classifier.\n";
