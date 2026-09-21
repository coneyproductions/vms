<?php
/** Explicit source-only fixture. Never loads a plugin or modifies the source tree. */
function bvm_test_companion_runtime_copy(string $source): string {
    $slug = basename(rtrim($source, '/'));
    $artifacts = array(
        'vms-events-slider' => array('vms-events-slider-1.0.10.zip', '0e770dddb184b5a05365de58a6e7967a0a670a730b6ab078acd16d055c84d1ab'),
        'vms-fill-dates' => array('vms-fill-dates-0.1.8.zip', 'e1e4a1c653fd7b6f51033b4163661c9a8c7b98285debfce8cd18559b7b7ff88c'),
        'vms-data-tools' => array('vms-data-tools-0.5.54.zip', 'd3dc1d9ed7f74aca0c09c9f8f2602a72de4714e97f6e70b1c231f161f5cae6aa'),
        'vms-express-bar' => array('vms-express-bar-0.6.23.zip', '3abf472c2794d7bc6c79e624baee17fa83e7ec9b50f602a5aa62d8964e729224'),
        'vms-refer-a-friend' => array('vms-refer-a-friend-0.2.6.zip', '6aa2ae48daab610284df5175bd9ef94592ea7fa926fd26b6dde5c00eb3ef0820'),
    );
    $target = sys_get_temp_dir() . '/bvm-companion-' . bin2hex(random_bytes(8));
    if (!mkdir($target, 0700)) throw new RuntimeException('Cannot create owned companion fixture');
    register_shutdown_function(static function () use ($target): void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname());
        }
        rmdir($target);
    });
    if (!is_dir($source)) {
        if (!isset($artifacts[$slug])) throw new RuntimeException('Required explicit companion source is unavailable: ' . $source);
        [$filename, $expectedSha256] = $artifacts[$slug];
        $archive = dirname(__DIR__, 2) . '/docs/addon-compatibility/artifacts/' . $filename;
        if (!is_file($archive) || hash_file('sha256', $archive) !== $expectedSha256) throw new RuntimeException('Companion artifact provenance mismatch: ' . $slug);
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot open companion artifact: ' . $slug);
        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                if (!str_starts_with($name, $slug . '/') || str_contains($name, '../') || str_starts_with($name, '/')) throw new RuntimeException('Unsafe companion artifact entry: ' . $name);
                $opsys = $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $opsys, $attributes, ZipArchive::OPSYS_UNIX) && (($attributes >> 16) & 0xF000) === 0xA000) throw new RuntimeException('Companion artifact rejects symlink: ' . $name);
                $relative = substr($name, strlen($slug) + 1);
                if ($relative === '' || str_ends_with($relative, '/') || preg_match('~(?:^|/)(?:tests|docs|\.git)(?:/|$)~', $relative)) continue;
                $contents = $zip->getFromIndex($index);
                if (!is_string($contents)) throw new RuntimeException('Cannot read companion artifact entry: ' . $name);
                $dest = $target . '/' . $relative;
                if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0700, true) && !is_dir(dirname($dest))) throw new RuntimeException('Cannot create companion fixture directory.');
                if (file_put_contents($dest, $contents) !== strlen($contents)) throw new RuntimeException('Cannot extract companion fixture entry: ' . $relative);
            }
        } finally {
            $zip->close();
        }
        return $target;
    }
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
