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
    'function bvmgr_ticketing_v2_find_unresolved_create_intent(',
    'function bvmgr_ticketing_v2_can_release_create_lock_after_handled_exit(',
    'function bvmgr_ticketing_v2_force_provider_product_insert_draft(',
    "add_filter('wp_insert_post_data', 'bvmgr_ticketing_v2_force_provider_product_insert_draft', PHP_INT_MAX, 4);",
    'function bvmgr_ticketing_v2_capture_provider_create_link(',
    "add_action('added_post_meta', 'bvmgr_ticketing_v2_capture_provider_create_link', 10, 4);",
    "add_action('updated_post_meta', 'bvmgr_ticketing_v2_capture_provider_create_link', 10, 4);",
    'function bvmgr_ticketing_v2_find_interrupted_create_candidates(',
    'function bvmgr_ticketing_v2_delete_create_lock_if_token_matches(',
    'function bvmgr_ticketing_v2_acquire_create_lock(',
    'function bvmgr_ticketing_v2_release_create_lock(',
    'function bvmgr_ticketing_v2_acquire_commit_lock(',
    'function bvmgr_ticketing_v2_release_commit_lock(',
    'function bvmgr_ticketing_v2_shutdown_release_active_commit_lock(',
    'function bvmgr_ticketing_v2_force_ticket_product_staged(',
    'function bvmgr_ticketing_v2_product_has_durable_create_recovery_identity(',
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

$commitBeforeProviderCreate = substr($commit, 0, $createPos);
$ticketCreateStart = strrpos($commitBeforeProviderCreate, "if (\$act === 'create') {");
$ticketAdoptStart = ($ticketCreateStart !== false) ? strpos($commit, "if (\$act === 'adopt') {", $ticketCreateStart) : false;
vms_issue5_assert_true($ticketCreateStart !== false && $ticketAdoptStart !== false, 'Unable to isolate the ticket CREATE action block.');
$ticketCreateBlock = substr($commit, $ticketCreateStart, $ticketAdoptStart - $ticketCreateStart);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_acquire_create_lock(',
    $ticketCreateBlock,
    'Ticket CREATE must acquire the atomic per-ticket lock before provider mutation.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_can_release_create_lock_after_handled_exit(',
    $ticketCreateBlock,
    'Ticket CREATE lock release on handled exits must be proof-based rather than unconditional.'
);
vms_issue5_assert_contains(
    'handled_failure_lock_retained',
    $ticketCreateBlock,
    'Handled provider failure must retain the CREATE lock when mutation may have started without durable recovery identity.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_persist_action_checkpoint(',
    $ticketCreateBlock,
    'Successful CREATE mapping must checkpoint inside the CREATE action before the next action branch.'
);
$lockPos = strpos($ticketCreateBlock, 'bvmgr_ticketing_v2_acquire_create_lock(');
$recoveryInsideCreatePos = strpos($ticketCreateBlock, 'bvmgr_ticketing_v2_find_interrupted_create_candidates(');
$providerCreatePos = strpos($ticketCreateBlock, 'bvmgr_ticketing_v2_create_ticket(');
$checkpointInsideCreatePos = strpos($ticketCreateBlock, 'bvmgr_ticketing_v2_persist_action_checkpoint(');
$restoreInsideCreatePos = strpos($ticketCreateBlock, 'bvmgr_ticketing_v2_restore_enabled_ticket_product(');
$releaseInsideCreatePos = strrpos($ticketCreateBlock, 'bvmgr_ticketing_v2_release_create_lock(');
vms_issue5_assert_true(
    $lockPos !== false
        && $recoveryInsideCreatePos !== false
        && $providerCreatePos !== false
        && $checkpointInsideCreatePos !== false
        && $restoreInsideCreatePos !== false
        && $releaseInsideCreatePos !== false
        && $lockPos < $recoveryInsideCreatePos
        && $lockPos < $providerCreatePos
        && $checkpointInsideCreatePos < $restoreInsideCreatePos
        && $restoreInsideCreatePos < $releaseInsideCreatePos,
    'CREATE lock must cover the final recovery scan, provider CREATE, durable mapping checkpoint, and publish restore in that order.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_get_sync($plan_id)',
    $ticketCreateBlock,
    'CREATE must refresh durable mapping after acquiring the lock.'
);
vms_issue5_assert_contains(
    "'create_mapping_checkpoint_failed'",
    $ticketCreateBlock,
    'CREATE must verify the durable mapping before publishing/restoring the product.'
);

$recoveryFinder = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_find_interrupted_create_candidates');
vms_issue5_assert_contains(
    "bvmgr_ticketing_v2_product_meta_key('ticketing_create_intent_id')",
    $recoveryFinder,
    'Interrupted CREATE recovery must require the dedicated temporary CREATE-intent marker or the durable intent product ID.'
);
vms_issue5_assert_contains(
    '$matches_create_intent_marker',
    $recoveryFinder,
    'Ordinary VMS ownership markers alone must not classify a product as an interrupted CREATE.'
);
$identityPos = strpos($recoveryFinder, '$has_recovery_identity');
$titleMismatchPos = strpos($recoveryFinder, "'recovery_product_title_mismatch'");
vms_issue5_assert_true(
    $identityPos !== false && $titleMismatchPos !== false && $identityPos < $titleMismatchPos,
    'Durable interrupted-CREATE identity must be established before title mismatch can reject or bypass the candidate.'
);
$finishIntent = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_finish_create_intent');
vms_issue5_assert_contains(
    "delete_post_meta(\$product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_create_intent_id'))",
    $finishIntent,
    'Successful CREATE completion must remove the temporary recovery marker.'
);

$preview = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_preview_sync');
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_find_unresolved_create_intent(',
    $preview,
    'Preview must find unresolved CREATE intent by stable plan/event/ticket-key identity, independent of mutable hashes.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_find_interrupted_create_candidates(',
    $preview,
    'Preview must surface interrupted CREATE recovery before proposing another CREATE.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_find_unresolved_create_intent(',
    $commit,
    'Commit must independently resolve unresolved CREATE intent by stable identity before provider CREATE.'
);
vms_issue5_assert_contains(
    "in_array(\$interrupted_status, array('ambiguous', 'unsafe', 'sold'), true)",
    $preview,
    'Preview must block ambiguous, unverifiable, or sold interrupted CREATE candidates.'
);
$previewSafeRecoveryPos = strpos($preview, "if (\$interrupted_status === 'safe') {");
$previewUnsafeRecoveryPos = strpos($preview, "elseif (in_array(\$interrupted_status, array('ambiguous', 'unsafe', 'sold'), true))", $previewSafeRecoveryPos);
vms_issue5_assert_true(
    $previewSafeRecoveryPos !== false && $previewUnsafeRecoveryPos !== false,
    'Unable to isolate Preview interrupted-CREATE recovery block.'
);
$previewSafeRecoveryBlock = substr($preview, $previewSafeRecoveryPos, $previewUnsafeRecoveryPos - $previewSafeRecoveryPos);
vms_issue5_assert_contains(
    "\$row['action'] = 'create';",
    $previewSafeRecoveryBlock,
    'Preview-discovered interrupted CREATE recovery must route through the guarded CREATE state machine, not ADOPT.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_create_intent_is_terminal($matching_intent)',
    $preview,
    'Preview must block unresolved prior CREATE intents whose product identity cannot be proven.'
);
vms_issue5_assert_contains(
    "'unresolved_create_intent_requires_reconciliation'",
    $ticketCreateBlock,
    'Commit must block an unresolved prior CREATE intent instead of issuing another provider CREATE.'
);

$adoptStart = strpos($commit, "if (\$act === 'adopt') {");
$updateStart = ($adoptStart !== false) ? strpos($commit, "if (\$act === 'update') {", $adoptStart) : false;
vms_issue5_assert_true($adoptStart !== false && $updateStart !== false, 'Unable to isolate the ticket ADOPT action block.');
$adoptBlock = substr($commit, $adoptStart, $updateStart - $adoptStart);
vms_issue5_assert_contains(
    "'interrupted_create_preview_requires_refresh'",
    $adoptBlock,
    'An old Preview that encoded interrupted recovery as ADOPT must be rejected and refreshed.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_apply_ticket_image_policy($pid, $plan_id, $ticket_cfg, true)',
    $adoptBlock,
    'Legacy ADOPT must avoid WC_Product::save() for image changes so Event Tickets stock sync is not re-entered.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_verify_ticket_inventory_after_apply($pid, $ticket_cfg)',
    $adoptBlock,
    'Legacy ADOPT must verify authoritative capacity-minus-sold inventory before mapping.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_restore_enabled_ticket_product($pid, true)',
    $adoptBlock,
    'Legacy ADOPT must avoid Woo product save during public-state restore.'
);
$adoptInventoryVerifyPos = strpos($adoptBlock, 'bvmgr_ticketing_v2_verify_ticket_inventory_after_apply($pid, $ticket_cfg)');
$adoptMapCheckpointPos = strpos($adoptBlock, 'bvmgr_ticketing_v2_persist_action_checkpoint(');
vms_issue5_assert_true(
    $adoptInventoryVerifyPos !== false
        && $adoptMapCheckpointPos !== false
        && $adoptInventoryVerifyPos < $adoptMapCheckpointPos,
    'Legacy ADOPT inventory must be verified before the product can be checkpointed as canonical.'
);

$createLock = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_acquire_create_lock');
vms_issue5_assert_true(
    strpos($createLock, 'recovered_stale_lock') === false
        && strpos($createLock, 'create_lock_stale_seconds') === false,
    'Per-ticket CREATE lock must never be automatically stolen by elapsed time.'
);
vms_issue5_assert_contains(
    "'create_already_in_progress_or_interrupted'",
    $createLock,
    'An existing CREATE lock must conservatively block automatic retry.'
);

$commitLockPos = strpos($commit, 'bvmgr_ticketing_v2_acquire_commit_lock(');
$tecMutationPos = strpos($commit, '$tec_event_id = absint($payload[\'tec_event_id\'] ?? 0);');
$commitUnlockPos = strrpos($commit, 'bvmgr_ticketing_v2_release_commit_lock(');
vms_issue5_assert_true(
    $commitLockPos !== false
        && $tecMutationPos !== false
        && $commitUnlockPos !== false
        && $commitLockPos < $tecMutationPos
        && $commitUnlockPos > $tecMutationPos,
    'Per-plan Commit lock must cover mutable Prepare/actions/finalize work and final map writes.'
);

$insertFence = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_force_provider_product_insert_draft');
vms_issue5_assert_contains(
    "\$GLOBALS['bvmgr_ticketing_v2_active_create_context']",
    $insertFence,
    'Insertion-time draft fence must be scoped to the guarded Ticketing v2 CREATE context.'
);
vms_issue5_assert_contains(
    "\$post_type !== 'product'",
    $insertFence,
    'Insertion-time draft fence must affect only Woo product post writes.'
);
vms_issue5_assert_contains(
    "\$data['post_status'] = 'draft';",
    $insertFence,
    'Guarded provider product writes must be forced to draft before WordPress persists them.'
);

$createTicket = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_create_ticket');
$activeContextPos = strpos($createTicket, 'bvmgr_ticketing_v2_set_active_create_context($create_context)');
$providerCallForFencePos = strpos($createTicket, 'bvmgr_ticketing_b_create_woo_ticket(');
$clearContextPos = strpos($createTicket, 'bvmgr_ticketing_v2_clear_active_create_context()');
vms_issue5_assert_true(
    $activeContextPos !== false
        && $providerCallForFencePos !== false
        && $clearContextPos !== false
        && $activeContextPos < $providerCallForFencePos
        && $providerCallForFencePos < $clearContextPos,
    'Guarded CREATE context must surround the provider call so wp_insert_post_data can prevent an initial publish transition.'
);

$stageFirstPos = strpos($createTicket, 'bvmgr_ticketing_v2_force_ticket_product_staged($product_id)');
$applyUpdatePos = strpos($createTicket, 'bvmgr_ticketing_b_apply_update_to_product(');
$stageSecondPos = ($applyUpdatePos !== false)
    ? strpos($createTicket, 'bvmgr_ticketing_v2_force_ticket_product_staged($product_id)', $applyUpdatePos)
    : false;
vms_issue5_assert_true(
    $stageFirstPos !== false
        && $applyUpdatePos !== false
        && $stageSecondPos !== false
        && $stageFirstPos < $applyUpdatePos
        && $stageSecondPos > $applyUpdatePos,
    'Provider-created product must be explicitly staged before and after normal ticket updates.'
);
$stageHelper = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_force_ticket_product_staged');
vms_issue5_assert_contains(
    "in_array('exclude-from-catalog', \$visibility_terms, true)",
    $stageHelper,
    'Staging helper must verify exclude-from-catalog taxonomy state.'
);
vms_issue5_assert_contains(
    "in_array('exclude-from-search', \$visibility_terms, true)",
    $stageHelper,
    'Staging helper must verify exclude-from-search taxonomy state.'
);
vms_issue5_assert_contains(
    "\$wc_visibility === 'hidden'",
    $stageHelper,
    'Staging helper must verify modern Woo catalog visibility directly.'
);
vms_issue5_assert_contains(
    'empty($errors)',
    $stageHelper,
    'Staging helper must fail when visibility writes or reads report errors.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_force_ticket_product_staged($product_id, false)',
    $source,
    'Provider meta capture must use the non-Woo-save staging path to avoid recursive provider save behavior.'
);

$adoptInventoryVerifier = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_verify_ticket_inventory_after_apply');
vms_issue5_assert_contains(
    '$expected_stock = max(0, $capacity - $sold_qty);',
    $adoptInventoryVerifier,
    'ADOPT inventory verification must derive expected remaining stock from capacity minus authoritative sold quantity.'
);
vms_issue5_assert_contains(
    "'inventory_mismatch_after_apply'",
    $adoptInventoryVerifier,
    'ADOPT must fail closed if persisted stock/capacity does not match the authoritative calculation.'
);

$restoreHelper = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_restore_enabled_ticket_product');
vms_issue5_assert_contains(
    '$already_public = ($current_status === \'publish\' && $current_visibility !== \'hidden\');',
    $restoreHelper,
    'Restore helper must recognize already-public products and avoid an unnecessary Woo save.'
);
vms_issue5_assert_contains(
    '!$avoid_wc_save',
    $restoreHelper,
    'Restore helper must support the ADOPT no-Woo-save path.'
);

$imageHelper = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_apply_ticket_image_policy');
vms_issue5_assert_contains(
    '!$avoid_wc_save && $current_image_id !== $target_image_id',
    $imageHelper,
    'Image policy must support avoiding unnecessary Woo saves during legacy ADOPT.'
);

$shutdown = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_commit_shutdown_diagnostics');
vms_issue5_assert_contains(
    '!bvmgr_ticketing_v2_create_intent_is_terminal($current_intent)',
    $shutdown,
    'Fatal shutdown must never downgrade completed/recovered CREATE intent state.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_product_has_durable_create_recovery_identity(',
    $shutdown,
    'Fatal shutdown must release a CREATE lock only after recovery identity is durably provable.'
);
$durableIdentity = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_product_has_durable_create_recovery_identity');
vms_issue5_assert_contains(
    "bvmgr_ticketing_v2_product_meta_key('ticketing_create_intent_id')",
    $durableIdentity,
    'Durable recovery identity must require the temporary CREATE-intent marker when sync mapping is not yet present.'
);
vms_issue5_assert_contains(
    "absint(\$mapped_row['woo_product_id'] ?? 0) === \$product_id",
    $durableIdentity,
    'A durable sync mapping must independently prove recovery identity.'
);

$shutdownReleaseRegistration = strpos($source, "register_shutdown_function('bvmgr_ticketing_v2_shutdown_release_active_commit_lock')");
$shutdownDiagnosticRegistration = strpos($source, "register_shutdown_function('bvmgr_ticketing_v2_commit_shutdown_diagnostics')");
vms_issue5_assert_true(
    $shutdownReleaseRegistration !== false
        && $shutdownDiagnosticRegistration !== false
        && $shutdownReleaseRegistration < $shutdownDiagnosticRegistration,
    'Per-plan Commit lock fatal release must be registered before diagnostics so a diagnostic failure cannot strand the plan lock.'
);

$handledRelease = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_can_release_create_lock_after_handled_exit');
vms_issue5_assert_contains(
    'if (!$provider_mutation_started)',
    $handledRelease,
    'Handled exit may release freely only before provider mutation starts.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_create_intent_is_terminal($intent)',
    $handledRelease,
    'Handled exit may release after provider mutation when the CREATE intent is terminal.'
);
vms_issue5_assert_contains(
    'bvmgr_ticketing_v2_product_has_durable_create_recovery_identity(',
    $handledRelease,
    'Handled exit may release after provider mutation only when durable recovery identity exists.'
);

$unresolvedFinder = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_find_unresolved_create_intent');
vms_issue5_assert_true(
    strpos($unresolvedFinder, 'ticket_hash') === false
        && strpos($unresolvedFinder, 'config_hash') === false
        && strpos($unresolvedFinder, 'preview_id') === false,
    'Stable unresolved CREATE lookup must not depend on mutable ticket/config hashes or Preview ID.'
);

// Exercise the pure/shared title normalizers and recovery classifier without loading WordPress.
$dashNormalizer = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_normalize_title_dash_presentation');
eval($dashNormalizer);

$legacyTitleNormalizer = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match');
eval($legacyTitleNormalizer);

$normalizer = vms_issue5_extract_function($source, 'bvmgr_ticketing_v2_normalize_create_match_title');
eval($normalizer);

$expectedTitleNormalized = bvmgr_ticketing_v2_normalize_create_match_title(
    '2031-10-25 19:00 - ISSUE5 BB4818 handled-durable-recovery TICKET'
);
$entityEnDashNormalized = bvmgr_ticketing_v2_normalize_create_match_title(
    '2031-10-25 19:00 &#8211; ISSUE5 BB4818 handled-durable-recovery TICKET'
);
$unicodeEmDashNormalized = bvmgr_ticketing_v2_normalize_create_match_title(
    "2031-10-25 19:00 — ISSUE5 BB4818 handled-durable-recovery TICKET"
);
vms_issue5_assert_same(
    $expectedTitleNormalized,
    $entityEnDashNormalized,
    'HTML-encoded en dash from WordPress/TEC must normalize to the same interrupted-CREATE title as ASCII hyphen-minus.'
);
vms_issue5_assert_same(
    $expectedTitleNormalized,
    $unicodeEmDashNormalized,
    'Unicode dash presentation differences must not block a proven interrupted-CREATE recovery.'
);

$legacyAscii = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match(
    '2031-10-25 19:00 - ISSUE5 TICKET'
);
$legacyEntityEnDash = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match(
    '2031-10-25 19:00 &#8211; ISSUE5 TICKET'
);
vms_issue5_assert_same(
    'ISSUE5 TICKET',
    $legacyAscii,
    'Legacy exact-title normalizer must strip the canonical date prefix.'
);
vms_issue5_assert_same(
    $legacyAscii,
    $legacyEntityEnDash,
    'A WordPress-facing en dash must not prevent adoption of the same legitimate legacy sold ticket.'
);
vms_issue5_assert_true(
    bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match(
        '2031-10-25 19:00 &#8211; ISSUE5 VIP TICKET'
    ) !== $legacyAscii,
    'Meaningfully different legacy ticket titles must remain distinct after dash normalization.'
);

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
