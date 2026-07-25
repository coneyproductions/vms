<?php
declare(strict_types=1);

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function strip_quotes(string $value): string
{
    if ($value === '') {
        return $value;
    }

    $quote = $value[0];
    if (($quote !== "'" && $quote !== '"') || substr($value, -1) !== $quote) {
        return $value;
    }

    return stripcslashes(substr($value, 1, -1));
}

function previous_non_whitespace_token(array $tokens, int $index)
{
    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_string($token)) {
            if (trim($token) === '') {
                continue;
            }

            return $token;
        }

        if ($token[0] === T_WHITESPACE) {
            continue;
        }

        return $token;
    }

    return null;
}

function next_non_whitespace_index(array $tokens, int $index): ?int
{
    $count = count($tokens);
    for ($i = $index; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_string($token)) {
            if (trim($token) === '') {
                continue;
            }

            return $i;
        }

        if ($token[0] === T_WHITESPACE) {
            continue;
        }

        return $i;
    }

    return null;
}

function find_translation_occurrences(array $tokens, string $function_name, string $message): array
{
    $matches = array();
    $count   = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== $function_name) {
            continue;
        }

        $open_paren_index = next_non_whitespace_index($tokens, $i + 1);
        if ($open_paren_index === null || $tokens[$open_paren_index] !== '(') {
            continue;
        }

        $first_argument_index = next_non_whitespace_index($tokens, $open_paren_index + 1);
        if ($first_argument_index === null) {
            continue;
        }

        $first_argument = $tokens[$first_argument_index];
        if (!is_array($first_argument) || $first_argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        if (strip_quotes($first_argument[1]) !== $message) {
            continue;
        }

        $comment = previous_non_whitespace_token($tokens, $i - 1);
        $matches[] = array(
            'line'    => $token[2],
            'comment' => is_array($comment) && in_array($comment[0], array(T_COMMENT, T_DOC_COMMENT), true) ? trim($comment[1]) : null,
        );
    }

    return $matches;
}

function normalize_executable_source(string $source): string
{
    $normalized = '';
    foreach (token_get_all($source) as $token) {
        if (is_string($token)) {
            if (trim($token) === '') {
                continue;
            }

            $normalized .= $token;
            continue;
        }

        if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE), true)) {
            continue;
        }

        $normalized .= $token[1];
    }

    return $normalized;
}

function assert_contains_count(string $haystack, string $needle, int $expected_count, string $context): void
{
    $actual_count = substr_count($haystack, $needle);
    if ($actual_count !== $expected_count) {
        fail($context . ': expected ' . $expected_count . ' occurrence(s), found ' . $actual_count . '.');
    }
}

$runtime_files = array(
    'includes/admin/vendor-details.php' => array(
        'inventory' => 13,
        'targets'   => array(
            array(
                'function' => '__',
                'message'  => 'starts after %s',
                'count'    => 1,
                'comment'  => '/* translators: %s: guest-count threshold before the attendance bonus starts. */',
            ),
            array(
                'function' => '__',
                'message'  => '+%1$s every %2$s',
                'count'    => 1,
                'comment'  => '/* translators: 1: formatted bonus amount added per attendance step, 2: ticket count in each attendance bonus step. */',
            ),
            array(
                'function' => '__',
                'message'  => '+%s per ticket',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted bonus amount added per ticket after the attendance threshold. */',
            ),
            array(
                'function' => '__',
                'message'  => 'cap %s',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted maximum attendance bonus amount. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Scope: %s',
                'count'    => 1,
                'comment'  => '/* translators: %s: selected vendor defaults template scope label. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Potential max payout: %s.',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted maximum payout amount including base pay and capped attendance bonus. */',
            ),
            array(
                'function' => '__',
                'message'  => 'No bonus cap is set, so payout can keep climbing above %s.',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted base payout amount before uncapped attendance bonuses. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Base pay %s.',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted base pay amount. */',
            ),
            array(
                'function' => '__',
                'message'  => 'No bonus is earned through %s attendance.',
                'count'    => 1,
                'comment'  => '/* translators: %s: attendance threshold before bonuses begin. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Add %1$s every %2$s tickets after that.',
                'count'    => 1,
                'comment'  => '/* translators: 1: formatted bonus amount added per attendance step, 2: ticket count in each attendance bonus step. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Add %s per ticket after that.',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted bonus amount added per ticket after the attendance threshold. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Total bonus caps at %1$s once attendance reaches %2$s.',
                'count'    => 1,
                'comment'  => '/* translators: 1: formatted maximum bonus amount, 2: attendance count where the bonus cap is reached. */',
            ),
            array(
                'function' => '__',
                'message'  => 'Total bonus caps at %s.',
                'count'    => 1,
                'comment'  => '/* translators: %s: formatted maximum bonus amount. */',
            ),
        ),
        'snippets'  => array(
            "'attendanceStartsAfter'=>__('starts after %s','backstage-venue-manager')," => 1,
            "'attendanceStepSegment'=>__('+%1\$s every %2\$s','backstage-venue-manager')," => 1,
            "'attendanceContinuousSegment'=>__('+%s per ticket','backstage-venue-manager')," => 1,
            "'attendanceCapSegment'=>__('cap %s','backstage-venue-manager')," => 1,
            "'scopeLine'=>__('Scope: %s','backstage-venue-manager')," => 1,
            "'potentialMaxPayout'=>__('Potential max payout: %s.','backstage-venue-manager')," => 1,
            "'noBonusCapSummary'=>__('No bonus cap is set, so payout can keep climbing above %s.','backstage-venue-manager')," => 1,
            "'formulaBasePay'=>__('Base pay %s.','backstage-venue-manager')," => 1,
            "'formulaNoBonusThrough'=>__('No bonus is earned through %s attendance.','backstage-venue-manager')," => 1,
            "'formulaStepBonus'=>__('Add %1\$s every %2\$s tickets after that.','backstage-venue-manager')," => 1,
            "'formulaContinuousBonus'=>__('Add %s per ticket after that.','backstage-venue-manager')," => 1,
            "'formulaTotalBonusCapAtCount'=>__('Total bonus caps at %1\$s once attendance reaches %2\$s.','backstage-venue-manager')," => 1,
            "'formulaTotalBonusCap'=>__('Total bonus caps at %s.','backstage-venue-manager')," => 1,
        ),
    ),
    'includes/cpt/event-plans.php' => array(
        'inventory' => 5,
        'targets'   => array(
            array(
                'function' => 'esc_html__',
                'message'  => 'No %s selected yet.',
                'count'    => 2,
                'comment'  => '/* translators: %s: lowercased vendor category display label. */',
            ),
            array(
                'function' => '__',
                'message'  => 'This event is %1$d away from the next staffing trigger%2$s.',
                'count'    => 1,
                'comment'  => '/* translators: %1$d: number of guests remaining before the next staffing trigger, %2$s: optional staffing role suffix for that trigger. */',
            ),
            array(
                'function' => '__',
                'message'  => ' for %s',
                'count'    => 1,
                'comment'  => '/* translators: %s: staffing role name for the next trigger. */',
            ),
            array(
                'function' => '__',
                'message'  => 'guests %1$s-%2$s',
                'count'    => 1,
                'comment'  => '/* translators: %1$s: lower guest-count boundary, %2$s: upper guest-count boundary. */',
            ),
        ),
        'snippets'  => array(
            "printf(esc_html__('No %s selected yet.','backstage-venue-manager'),esc_html(strtolower(\$category_label)));" => 2,
            "sprintf(__('This event is %1\$d away from the next staffing trigger%2\$s.','backstage-venue-manager'),\$next_threshold_gap,\$next_threshold_role!==''?sprintf(__(' for %s','backstage-venue-manager'),\$next_threshold_role):'')" => 1,
            "\$label_parts[]=sprintf(__('guests %1\$s-%2\$s','backstage-venue-manager'),(isset(\$template_row['min_headcount'])&&\$template_row['min_headcount']!==null&&\$template_row['min_headcount']!=='')?(int)\$template_row['min_headcount']:0,(isset(\$template_row['max_headcount'])&&\$template_row['max_headcount']!==null&&\$template_row['max_headcount']!=='')?(int)\$template_row['max_headcount']:" => 1,
        ),
        'required_methods' => array(
            'render_event_plan_secondary_vendors_save_response_vendor_category_notice_html',
            'render_event_plan_secondary_vendors_lazy_load_vendor_category_notice_html',
            'build_event_plan_staff_response_template_alerts',
            'build_event_plan_staff_response_template_option_rows',
        ),
    ),
    'includes/integrations/ticketing-verifications.php' => array(
        'inventory' => 1,
        'targets'   => array(
            array(
                'function' => '__',
                'message'  => 'Configured limit: %1$s. Effective limit on this server: %2$s.',
                'count'    => 1,
                'comment'  => '/* translators: 1: configured upload size limit, 2: effective server upload size limit. */',
            ),
        ),
        'snippets'  => array(
            "echoesc_html(sprintf(__('Configured limit: %1\$s. Effective limit on this server: %2\$s.','backstage-venue-manager'),vms_ticketing_verification_format_bytes(\$configured_upload_bytes),vms_ticketing_verification_format_bytes(\$effective_upload_bytes)));" => 1,
        ),
    ),
    'includes/portal/vendor-portal.php' => array(
        'inventory' => 1,
        'targets'   => array(
            array(
                'function' => '__',
                'message'  => 'That file is too large. Please keep it under %s.',
                'count'    => 1,
                'comment'  => '/* translators: %s: current upload size limit for the promo video. */',
            ),
        ),
        'snippets'  => array(
            "'too_large_message'=>sprintf(__('That file is too large. Please keep it under %s.','backstage-venue-manager'),function_exists('size_format')?size_format(vms_vendor_portal_headliner_promo_video_max_bytes(),0):(string)vms_vendor_portal_headliner_promo_video_max_bytes())," => 1,
        ),
    ),
);

$expected_inventory = array(
    'includes/admin/vendor-details.php'               => 13,
    'includes/cpt/event-plans.php'                   => 5,
    'includes/integrations/ticketing-verifications.php' => 1,
    'includes/portal/vendor-portal.php'              => 1,
);

$total_inventory = 0;

foreach ($runtime_files as $relative_path => $spec) {
    $absolute_path = dirname(__DIR__) . '/' . $relative_path;
    if (!is_file($absolute_path)) {
        fail('Missing runtime file: ' . $relative_path);
    }

    $source = file_get_contents($absolute_path);
    if ($source === false) {
        fail('Unable to read runtime file: ' . $relative_path);
    }

    $tokens = token_get_all($source);
    $normalized = normalize_executable_source($source);
    $file_inventory = 0;

    foreach ($spec['targets'] as $target) {
        $occurrences = find_translation_occurrences($tokens, $target['function'], $target['message']);
        if (count($occurrences) !== $target['count']) {
            fail(
                $relative_path . ': expected ' . $target['count'] . ' occurrence(s) of ' . $target['function'] . '("' . $target['message'] . '"), found ' . count($occurrences) . '.'
            );
        }

        foreach ($occurrences as $occurrence) {
            if ($occurrence['comment'] !== $target['comment']) {
                fail(
                    $relative_path . ': translator comment mismatch for "' . $target['message'] . '" on line ' . $occurrence['line'] . '.'
                );
            }

            if (strpos($occurrence['comment'], 'translators:') === false) {
                fail($relative_path . ': missing translators prefix for "' . $target['message'] . '" on line ' . $occurrence['line'] . '.');
            }
        }

        $file_inventory += $target['count'];
    }

    if ($file_inventory !== $spec['inventory']) {
        fail($relative_path . ': expected inventory ' . $spec['inventory'] . ', found ' . $file_inventory . '.');
    }

    foreach ($spec['snippets'] as $snippet => $count) {
        assert_contains_count($normalized, $snippet, $count, $relative_path . ' executable source mismatch');
    }

    if (isset($spec['required_methods'])) {
        foreach ($spec['required_methods'] as $method_name) {
            if (strpos($source, $method_name) === false) {
                fail($relative_path . ': missing expected renderer method ' . $method_name . '.');
            }
        }
    }

    if ($expected_inventory[$relative_path] !== $file_inventory) {
        fail($relative_path . ': expected distribution ' . $expected_inventory[$relative_path] . ', found ' . $file_inventory . '.');
    }

    $total_inventory += $file_inventory;
}

if ($total_inventory !== 20) {
    fail('Expected total translator-comment inventory 20, found ' . $total_inventory . '.');
}

echo "plugin-check translator comments remediation: PASS\n";
