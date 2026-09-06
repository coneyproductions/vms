<?php
/** Local Unix-socket CLI. Loads neither wp-config.php nor WordPress/plugins. */
if (PHP_SAPI !== 'cli') exit(2);
$options = getopt('', array('socket:', 'database:', 'prefix:', 'user:'));
$socket = $options['socket'] ?? '';
$database = $options['database'] ?? '';
$prefix = $options['prefix'] ?? '';
if ($socket === '' || $socket[0] !== '/' || !file_exists($socket) || filetype($socket) !== 'socket' || !preg_match('/^[a-zA-Z0-9_]+$/D', $database) || !preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) {
    fwrite(STDERR, "Required: --socket=/absolute/local/mysql.sock --database=name --prefix=wp_ [--user=name]; password via MYSQL_PWD.\n");
    exit(2);
}
define('ABSPATH', dirname(__DIR__) . '/');
require ABSPATH . 'includes/db/staffing-lifecycle-preflight.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli('localhost', $options['user'] ?? 'root', getenv('MYSQL_PWD') ?: '', $database, 0, $socket);
    $db->set_charset('utf8mb4');
    $db->query('SET SESSION TRANSACTION READ ONLY');
    $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $receipt = bvmgr_staffing_lifecycle_inspect(static function (string $sql) use ($db): array {
        if (!preg_match('/^(SELECT|SHOW)\b/', $sql)) throw new RuntimeException('Non-read query rejected');
        return $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }, $prefix);
    $db->rollback();
    $db->close();
    echo json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    exit($receipt['ok'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, "Read-only preflight could not complete; no promotion or migration is permitted.\n");
    exit(2);
}
