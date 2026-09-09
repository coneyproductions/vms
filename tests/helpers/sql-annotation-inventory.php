<?php
/** Stable SQL annotation ownership: nearest named declaration + occurrence and codes.
 * PHP tokens avoid treating strings/comments as function declarations. Function identities
 * and counts remain explicit; unrelated line movement cannot change the inventory.
 */
function bvm_test_sql_annotation_inventory(array $paths, string $root): array
{
    $inventory = array();
    foreach ($paths as $path) {
        $source = file_get_contents($path);
        if ($source === false) throw new RuntimeException('Cannot read SQL source: ' . $path);
        $owner = '<file>'; $counts = array();
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) continue;
            if ($token[0] === T_FUNCTION) {
                for ($i = $index + 1; isset($tokens[$i]); $i++) {
                    $next = $tokens[$i];
                    if ((is_array($next) ? $next[1] : $next) === '&') continue;
                    if (is_array($next) && in_array($next[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) continue;
                    if (is_array($next) && $next[0] === T_STRING) $owner = $next[1];
                    break;
                }
            }
            if (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
            foreach (explode("\n", $token[1]) as $line) {
                if (!str_contains($line, 'phpcs:ignore') || !str_contains($line, 'WordPress.DB.DirectDatabaseQuery')) continue;
                if (!preg_match('/phpcs:ignore\s+([^ ]+)/', $line, $match)) throw new RuntimeException('Malformed SQL annotation');
                $counts[$owner] = ($counts[$owner] ?? 0) + 1;
                $inventory[] = str_replace($root . '/', '', $path) . ':' . $owner . '#' . $counts[$owner] . ':' . $match[1];
            }
        }
    }
    return $inventory;
}
