<?php
declare(strict_types=1);

// In-memory SQL/hook substitutes. Native WordPress/MySQL acceptance is separate.
final class Issue102MetadataDatabase {
    public string $postmeta = 'fixture_postmeta';
    public ?array $rows = null;
    public array $queries = [];
    public ?int $fail_id = null;

    public function prepare(string $sql, ...$args): array {
        return ['sql' => $sql, 'args' => $args];
    }

    public function get_col(array $query): array {
        $this->queries[] = $query;
        [$plan, $key, $value] = $query['args'];
        expect_query($query['sql'], 'SELECT');
        return array_map('strval', array_keys(array_filter($this->current_rows(),
            static fn($stored) => maybe_serialize($stored) === $value)));
    }

    public function query(array $query) {
        $this->queries[] = $query;
        expect_query($query['sql'], 'DELETE');
        [$id, $plan, $key, $value] = $query['args'];
        if ($this->fail_id === (int) $id) return false;
        $rows = $this->current_rows();
        if (!array_key_exists($id, $rows) || maybe_serialize($rows[$id]) !== $value) return 0;
        unset($rows[$id]);
        if ($this->rows !== null) $this->rows = $rows;
        $this->sync_meta($rows);
        return 1;
    }

    public function current_rows(): array {
        if ($this->rows !== null) return $this->rows;
        return array_key_exists('config', $GLOBALS['meta']) ? [1 => $GLOBALS['meta']['config']] : [];
    }

    public function sync_meta(array $rows): void {
        unset($GLOBALS['meta']['config']);
        if ($rows) $GLOBALS['meta']['config'] = reset($rows);
    }
}

function expect_query(string $sql, string $verb): void {
    if (!str_starts_with($sql, $verb) || !str_contains($sql, 'BINARY meta_value = %s') ||
        !str_contains($sql, 'post_id = %d AND meta_key = %s') ||
        ($verb === 'DELETE' && !str_contains($sql, 'meta_id = %d'))) {
        throw new RuntimeException('Missing atomic metadata SQL predicates');
    }
}
function wp_is_post_revision($id) { return false; }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function apply_filters($hook, $check, ...$args) {
    $GLOBALS['metadata_hook_events'][] = [$hook, $args];
    if ($hook === 'delete_post_metadata') {
        $GLOBALS['deletes'][] = $args[1];
        if ($GLOBALS['delete_mode'] === 'reject') return false;
        if ($GLOBALS['delete_mode'] === 'pretend') return true;
        if ($GLOBALS['delete_mode'] === 'competing') $GLOBALS['meta']['config'] = $GLOBALS['competing'];
    }
    return $check;
}
function do_action($hook, ...$args): void {
    $GLOBALS['metadata_hook_events'][] = [$hook, $args];
    if ($hook === 'delete_post_meta' && isset($GLOBALS['atomic_delete_interleave'])) {
        ($GLOBALS['atomic_delete_interleave'])();
    }
}
$GLOBALS['wpdb'] = new Issue102MetadataDatabase();
$GLOBALS['metadata_hook_events'] = [];
