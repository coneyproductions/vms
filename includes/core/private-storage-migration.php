<?php
/** Explicit administrator migration. No migration is attached to reads or activation. */
defined('ABSPATH') || exit;

/** Return only attachment IDs referenced by BVM private-document relationships. */
function bvmgr_private_storage_attachment_ids(): array
{
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT pm.post_id, pm.meta_key, pm.meta_value FROM %i pm JOIN %i p ON p.ID=pm.post_id WHERE p.post_type IN (%s,%s) AND pm.meta_key IN (%s,%s,%s,%s)',
        $wpdb->postmeta, $wpdb->posts, 'vms_vendor', 'vms_staff', '_vms_w9_upload_id', '_vms_stage_plot_attachment_id', '_vms_input_list_attachment_id', '_vms_staff_qualifications'
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($rows)) throw new RuntimeException('private_inventory_database_error');
    $ids = array();
    $kinds = array('_vms_w9_upload_id' => '_vms_w9_upload_storage_kind', '_vms_stage_plot_attachment_id' => '_vms_stage_plot_storage_kind', '_vms_input_list_attachment_id' => '_vms_input_list_storage_kind');
    foreach ($rows as $row) {
        if ($row['meta_key'] === '_vms_staff_qualifications') {
            $qualifications = get_post_meta((int) $row['post_id'], '_vms_staff_qualifications', true);
            foreach (is_array($qualifications) ? $qualifications : array() as $qualification) {
                if (is_array($qualification) && ($qualification['storage_kind'] ?? '') !== 'private_file' && !empty($qualification['attachment_id'])) $ids[] = absint($qualification['attachment_id']);
            }
        } elseif (get_post_meta((int) $row['post_id'], $kinds[$row['meta_key']], true) !== 'private_file') {
            $ids[] = absint($row['meta_value']);
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

/** @return array<string,mixed>|WP_Error */
function bvmgr_private_storage_inventory()
{
    global $wpdb;
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config)) return $config;
    $roots = bvmgr_private_storage_legacy_roots();
    if (!$roots) return bvmgr_private_storage_error('private_legacy_root_unknown');
    $legacy_private = array_key_first($roots);
    $groups = array();
    $seen = array();
    $add = static function (string $source, string $key, string $expected = '') use (&$groups, &$seen): void {
        $valid = bvmgr_private_files_validate_storage_key($key);
        if ($valid === '' || $valid !== $key) throw new RuntimeException('private_legacy_key_invalid');
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $destination = bvmgr_private_storage_target($key);
        if (!@file_exists($source) && !@is_link($source) && $destination !== '' && bvmgr_private_storage_safe_file($destination)) {
            if ($expected !== '' && !hash_equals($expected, (string) @hash_file('sha256', $destination))) throw new RuntimeException('private_destination_hash_mismatch');
            $receipt = get_option('bvmgr_private_migration_' . hash('sha256', $key), array());
            if (!is_array($receipt) || ($receipt['state'] ?? '') !== 'copied') return;
        }
        $groups['object:' . hash('sha256', $key)] = array('files' => array(array('source' => $source, 'key' => $key, 'expected' => $expected)), 'attachment_id' => 0);
    };
    try {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id, stored_filename, sha256 FROM %i ORDER BY id', bvmgr_private_files_table()), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) throw new RuntimeException('private_inventory_database_error');
        foreach ($rows as $row) $add($legacy_private . '/' . (string) $row['stored_filename'], (string) $row['stored_filename'], (string) $row['sha256']);
        // Include abandoned generated BVM files, but not unrelated companion uploads.
        $sweep = array();
        foreach (array('tax-docs', 'staff-certifications', 'vendor-tech-docs', 'verifications', 'event-plan-imports', 'general') as $bucket) $sweep[$legacy_private . '/' . $bucket] = $bucket . '/';
        foreach ($roots as $root => $prefix) if ($prefix !== '') $sweep[$root] = $prefix;
        $visited = 0;
        foreach ($sweep as $directory => $prefix) {
            if (!@file_exists($directory) && !@is_link($directory)) continue;
            if (!@is_dir($directory) || !bvmgr_private_storage_no_links($directory)) throw new RuntimeException('private_legacy_symlink');
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (++$visited > 10000) throw new RuntimeException('private_inventory_limit');
                if ($file->isLink()) throw new RuntimeException('private_legacy_symlink');
                if (!$file->isFile() || in_array($file->getFilename(), array('.htaccess', 'web.config', 'index.php'), true)) continue;
                $relative = substr(wp_normalize_path($file->getPathname()), strlen(wp_normalize_path($directory)) + 1);
                $add($file->getPathname(), $prefix . $relative);
            }
        }
        $uploads = wp_upload_dir(null, false);
        $base = bvmgr_private_storage_canonical((string) ($uploads['basedir'] ?? ''));
        foreach (bvmgr_private_storage_attachment_ids() as $id) {
            if (get_post_type($id) !== 'attachment') throw new RuntimeException('private_attachment_missing');
            $saved = get_post_meta($id, '_bvmgr_private_storage_manifest', true);
            $files = array();
            if (is_array($saved) && $saved) {
                foreach ($saved as $item) {
                    if (!is_array($item) || empty($item['legacy']) || empty($item['key'])) throw new RuntimeException('private_attachment_manifest_invalid');
                    $relative = bvmgr_private_files_validate_storage_key((string) $item['legacy']);
                    if ($relative === '' || $relative !== $item['legacy']) throw new RuntimeException('private_attachment_manifest_invalid');
                    $files[] = array('source' => $base . '/' . $relative, 'key' => (string) $item['key'], 'expected' => (string) ($item['sha256'] ?? ''));
                }
            } else {
                $path = bvmgr_private_storage_canonical((string) get_attached_file($id, true));
                if ($path === '' || !bvmgr_private_storage_within($path, $base) || !@is_file($path) || !bvmgr_private_storage_no_links($path)) throw new RuntimeException('private_attachment_path_invalid');
                $names = array(basename($path));
                $metadata = wp_get_attachment_metadata($id);
                if (is_array($metadata)) {
                    if (!empty($metadata['original_image'])) $names[] = (string) $metadata['original_image'];
                    foreach (($metadata['sizes'] ?? array()) as $size) if (is_array($size) && !empty($size['file'])) $names[] = (string) $size['file'];
                }
                $backups = get_post_meta($id, '_wp_attachment_backup_sizes', true);
                foreach (is_array($backups) ? $backups : array() as $size) if (is_array($size) && !empty($size['file'])) $names[] = (string) $size['file'];
                foreach (array_unique($names) as $name) {
                    if (basename($name) !== $name || bvmgr_private_files_validate_storage_key($name) !== $name) throw new RuntimeException('private_attachment_name_invalid');
                    $source = dirname($path) . '/' . $name;
                    if (!@file_exists($source) && $source !== $path) continue;
                    $files[] = array('source' => $source, 'key' => 'legacy-attachments/' . $id . '/' . $name, 'expected' => '');
                }
            }
            $pending = get_post_meta($id, '_bvmgr_private_storage_key', true) === '';
            foreach ($files as $file) if (@file_exists($file['source']) || !bvmgr_private_storage_safe_file(bvmgr_private_storage_target($file['key']))) $pending = true;
            if ($pending) $groups['attachment:' . $id] = array('files' => $files, 'attachment_id' => $id);
        }
    } catch (Throwable $error) {
        return bvmgr_private_storage_error(preg_match('/^private_[a-z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'private_inventory_failed');
    }
    return $groups;
}

function bvmgr_private_storage_pending(): bool
{
    $inventory = bvmgr_private_storage_inventory();
    return is_wp_error($inventory) || count($inventory) > 0;
}

/** Durable per-object recovery receipt, written only in the explicit migration. */
function bvmgr_private_storage_receipt(string $key, array $value): bool
{
    $option = 'bvmgr_private_migration_' . hash('sha256', $key);
    update_option($option, $value, false);
    return get_option($option) === $value;
}

/** Remove only empty directories inside recognized legacy private roots after verified removal. */
function bvmgr_private_storage_cleanup_legacy(string $source): void
{
    foreach (bvmgr_private_storage_legacy_roots() as $root => $prefix) {
        if (!bvmgr_private_storage_within($source, $root) || !bvmgr_private_storage_no_links($root)) continue;
        for ($directory = dirname($source); bvmgr_private_storage_within($directory, $root); $directory = dirname($directory)) {
            // The shared vms-private root may also belong to optional companions.
            if ($directory === $root && $prefix === '') break;
            if (!bvmgr_private_storage_no_links($directory) || !@rmdir($directory)) break;
            if ($directory === $root) break;
        }
        break;
    }
}

/** Copy/verify/publish/receipt/remove. Reruns never overwrite differing destination bytes. */
function bvmgr_private_storage_migrate_object(array $file): bool
{
    if (empty($GLOBALS['bvmgr_private_storage_migrating']) || !current_user_can('manage_options')) return false;
    $key = (string) $file['key'];
    $source = (string) $file['source'];
    $destination = bvmgr_private_storage_target($key);
    if ($destination === '' || !bvmgr_private_storage_no_links($source)) return false;
    $receipt = get_option('bvmgr_private_migration_' . hash('sha256', $key), array());
    if (!@file_exists($source)) {
        if (!is_array($receipt) || empty($receipt['sha256']) || !bvmgr_private_storage_safe_file($destination)
            || !hash_equals((string) $receipt['sha256'], (string) @hash_file('sha256', $destination))) return false;
        $receipt['state'] = 'complete';
        return bvmgr_private_storage_receipt($key, $receipt);
    }
    if (!@is_file($source) || !@is_readable($source) || (int) (@stat($source)['nlink'] ?? 0) !== 1) return false;
    $hash = @hash_file('sha256', $source);
    if (!is_string($hash) || (!empty($file['expected']) && !hash_equals((string) $file['expected'], $hash))) return false;
    $dir = dirname($destination);
    if (!bvmgr_private_storage_no_links($dir) || (!@is_dir($dir) && !@wp_mkdir_p($dir))) return false;
    if (@file_exists($destination)) {
        if (!bvmgr_private_storage_safe_file($destination) || !hash_equals($hash, (string) @hash_file('sha256', $destination))) return false;
    } else {
        $temporary = $dir . '/migration-' . wp_generate_uuid4() . '.part';
        $input = @fopen($source, 'rb');
        $output = @fopen($temporary, 'xb');
        if (!$input || !$output) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            return false;
        }
        @chmod($temporary, 0600);
        $copied = @stream_copy_to_stream($input, $output);
        $flushed = @fflush($output);
        if (function_exists('fsync')) $flushed = @fsync($output) && $flushed;
        fclose($input);
        fclose($output);
        $verified = $flushed && $copied === @filesize($source) && hash_equals($hash, (string) @hash_file('sha256', $temporary));
        // @link() publishes without ever overwriting an existing destination.
        $published = $verified && @link($temporary, $destination);
        wp_delete_file($temporary);
        if (!$published || @file_exists($temporary)) return false;
    }
    $value = array('key' => $key, 'sha256' => $hash, 'bytes' => @filesize($destination), 'state' => 'copied');
    if (!bvmgr_private_storage_receipt($key, $value)) return false;
    clearstatcache(true, $source);
    if (!bvmgr_private_storage_no_links($source) || !hash_equals($hash, (string) @hash_file('sha256', $source)) || !bvmgr_private_storage_safe_file($destination)) return false;
    wp_delete_file($source);
    clearstatcache(true, $source);
    if (@file_exists($source) || @is_link($source)) return false;
    bvmgr_private_storage_cleanup_legacy($source);
    $value['state'] = 'complete';
    return bvmgr_private_storage_receipt($key, $value);
}

/** Authorized controlled lifecycle; caller supplies admin-post nonce or trusted WP-CLI context. */
function bvmgr_private_storage_migrate(int $limit = 25): array
{
    if (!current_user_can('manage_options') || (is_multisite() && !is_super_admin())) return array('ok' => false, 'error' => 'forbidden');
    $config = bvmgr_private_storage_config();
    if (is_wp_error($config) || !bvmgr_private_storage_prepare('', true)) return array('ok' => false, 'error' => 'private_storage_unavailable');
    $lock_path = $config['site'] . '/migration.lock';
    if (!bvmgr_private_storage_no_links($lock_path)) return array('ok' => false, 'error' => 'private_storage_symlink');
    $lock = @fopen($lock_path, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        return array('ok' => false, 'error' => 'private_migration_busy');
    }
    $GLOBALS['bvmgr_private_storage_migrating'] = true;
    try {
        $inventory = bvmgr_private_storage_inventory();
        if (is_wp_error($inventory)) return array('ok' => false, 'error' => $inventory->get_error_code());
        if (!$inventory) return array('ok' => true, 'migrated' => 0, 'remaining' => 0);
        $done = 0;
        $failures = array();
        foreach (array_slice($inventory, 0, max(1, min(100, $limit)), true) as $identity => $group) {
            $id = (int) $group['attachment_id'];
            if ($id > 0 && !get_post_meta($id, '_bvmgr_private_storage_manifest', true)) {
                $uploads = wp_upload_dir(null, false);
                $base = bvmgr_private_storage_canonical((string) $uploads['basedir']);
                $manifest = array();
                foreach ($group['files'] as $file) $manifest[] = array('legacy' => substr($file['source'], strlen($base) + 1), 'key' => $file['key'], 'sha256' => @hash_file('sha256', $file['source']));
                update_post_meta($id, '_bvmgr_private_storage_manifest', $manifest);
                if (get_post_meta($id, '_bvmgr_private_storage_manifest', true) !== $manifest) { $failures[] = $identity; continue; }
            }
            $ok = true;
            foreach ($group['files'] as $file) if (!bvmgr_private_storage_migrate_object($file)) { $ok = false; break; }
            if ($ok && $id > 0) {
                $key = (string) $group['files'][0]['key'];
                update_post_meta($id, '_bvmgr_private_storage_key', $key);
                $ok = get_post_meta($id, '_bvmgr_private_storage_key', true) === $key;
            }
            if ($ok) $done++; else $failures[] = $identity;
        }
        $after = bvmgr_private_storage_inventory();
        $remaining = is_wp_error($after) ? count($inventory) : count($after);
        $result = array('ok' => !$failures && !is_wp_error($after) && $remaining === 0, 'migrated' => $done, 'remaining' => $remaining, 'failures' => $failures);
        update_option('bvmgr_private_storage_migration_result', $result, false);
        return $result;
    } finally {
        unset($GLOBALS['bvmgr_private_storage_migrating']);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function bvmgr_private_storage_admin_menu(): void
{
    add_management_page(__('BVM Private Documents', 'backstage-venue-manager'), __('BVM Private Documents', 'backstage-venue-manager'), 'manage_options', 'bvmgr-private-storage', 'bvmgr_private_storage_admin_page');
}
add_action('admin_menu', 'bvmgr_private_storage_admin_menu');

function bvmgr_private_storage_admin_page(): void
{
    if (!current_user_can('manage_options')) return;
    $config = bvmgr_private_storage_config();
    $inventory = is_wp_error($config) ? $config : bvmgr_private_storage_inventory();
    echo '<div class="wrap"><h1>' . esc_html__('BVM Private Documents', 'backstage-venue-manager') . '</h1>';
    echo '<p>' . esc_html__('Ask your host to provision a writable directory outside every public document root and alias, then set BVMGR_PRIVATE_STORAGE_ROOT and BVMGR_PRIVATE_STORAGE_WEB_ROOTS in wp-config.php. The plugin readme documents the configuration. Private uploads stay disabled until configuration and legacy migration are complete.', 'backstage-venue-manager') . '</p>';
    if (is_wp_error($inventory)) {
        echo '<p>' . esc_html($inventory->get_error_message()) . ' (' . esc_html($inventory->get_error_code()) . ')</p>';
    } elseif (!$inventory) {
        echo '<p>' . esc_html__('Private storage is configured. No pending BVM-owned legacy documents were found.', 'backstage-venue-manager') . '</p>';
    } else {
        echo '<p>' . esc_html__('Legacy documents require migration. Their old public copies may remain exposed until migration succeeds. Back up documents and database securely before continuing. Each batch verifies copied content before removing owned originals; rerun interrupted batches.', 'backstage-venue-manager') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bvmgr_private_storage_migrate">';
        wp_nonce_field('bvmgr_private_storage_migrate');
        submit_button(__('Migrate next batch of private documents', 'backstage-venue-manager'));
        echo '</form>';
    }
    $last = get_option('bvmgr_private_storage_migration_result', array());
    if (is_array($last) && !empty($last['failures'])) echo '<p>' . esc_html__('A migration batch could not finish. The source or verified destination has been retained for recovery. Resolve permissions or conflicting destination content before retrying; do not delete the sole remaining copy.', 'backstage-venue-manager') . '</p>';
    echo '</div>';
}

function bvmgr_private_storage_admin_notice(): void
{
    if (!current_user_can('manage_options')) return;
    $config = bvmgr_private_storage_config();
    if (!is_wp_error($config) && !bvmgr_private_storage_pending()) return;
    echo '<div class="notice notice-error"><p>' . esc_html__('Private document uploads are unavailable or legacy documents need protection. Review Tools → BVM Private Documents. Existing public copies are not secured until migration completes.', 'backstage-venue-manager') . ' <a href="' . esc_url(admin_url('tools.php?page=bvmgr-private-storage')) . '">' . esc_html__('Review private storage', 'backstage-venue-manager') . '</a></p></div>';
}
add_action('admin_notices', 'bvmgr_private_storage_admin_notice');

function bvmgr_private_storage_admin_migrate(): void
{
    if (strtoupper(bvmgr_request_server_value('REQUEST_METHOD')) !== 'POST' || !current_user_can('manage_options')) wp_die(esc_html__('Access denied.', 'backstage-venue-manager'), '', array('response' => 403));
    check_admin_referer('bvmgr_private_storage_migrate');
    bvmgr_private_storage_migrate();
    wp_safe_redirect(admin_url('tools.php?page=bvmgr-private-storage'));
    exit;
}
add_action('admin_post_bvmgr_private_storage_migrate', 'bvmgr_private_storage_admin_migrate');
