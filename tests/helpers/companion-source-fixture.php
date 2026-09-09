<?php
/** Explicit source-only fixture. Never loads a plugin or modifies the source tree. */
function bvm_test_companion_runtime_copy(string $source): string {
    if (!is_dir($source)) throw new RuntimeException('Required explicit companion source is unavailable: ' . $source);
    $target = sys_get_temp_dir() . '/bvm-companion-' . bin2hex(random_bytes(8));
    if (!mkdir($target, 0700)) throw new RuntimeException('Cannot create owned companion fixture');
    register_shutdown_function(static function () use ($target): void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname());
        }
        rmdir($target);
    });
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $file) {
        $relative = substr($file->getPathname(), strlen(rtrim($source, '/')) + 1);
        if (preg_match('~(?:^|/)(?:tests|docs|\.git)/(?:.*)$~', $relative)) continue;
        if ($file->isLink()) throw new RuntimeException('Companion fixture rejects symlinks: ' . $relative);
        if (!$file->isFile()) continue;
        $dest = $target . '/' . $relative;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
        if (!copy($file->getPathname(), $dest) || hash_file('sha256', $dest) !== hash_file('sha256', $file->getPathname())) throw new RuntimeException('Companion source copy mismatch: ' . $relative);
    }
    return $target;
}
